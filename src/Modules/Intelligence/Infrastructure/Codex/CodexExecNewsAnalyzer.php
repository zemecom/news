<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use JsonException;
use Modules\Intelligence\Domain\Contracts\NewsAnalysisCache;
use Modules\Intelligence\Domain\Contracts\NewsAnalyzer;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class CodexExecNewsAnalyzer implements NewsAnalyzer
{
    public function __construct(
        private CodexProcessRunnerContract $runner,
        private NewsAnalysisCache $cache,
    ) {}

    public function analyze(RawNewsData $raw, AiProviderProfile $account): NewsAnalysisResult
    {
        $categories = config('intelligence.categories', []);
        $contract = CodexNewsAnalysisContract::fromConfig(is_array($categories) ? $categories : []);
        $model = $account->defaultModel !== '' ? $account->defaultModel : (string) config('intelligence.chatgpt_codex.model', 'gpt-5.4-mini');
        $reasoningEffort = $this->resolveReasoningEffort($account);
        $metadata = $this->metadata($account, $model, $reasoningEffort, $contract);
        $cacheReasoningEffort = $reasoningEffort ?? 'model_default';

        $cached = $this->cache->get(
            fingerprint: $raw->fingerprint,
            provider: AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            model: $model,
            reasoningEffort: $cacheReasoningEffort,
            analysisVersion: CodexNewsAnalysisContract::ANALYSIS_VERSION,
        );

        if ($cached instanceof NewsAnalysisResult) {
            return $this->withMetadata($cached, $metadata);
        }

        $schemaPath = $this->writeTempFile('codex-news-schema-', json_encode($contract->schema(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $outputPath = tempnam(sys_get_temp_dir(), 'codex-news-output-');
        $scratchDir = $this->ensureScratchDir();

        $result = $this->runner->run(
            command: $this->buildCommand($model, $reasoningEffort, $scratchDir, $schemaPath, (string) $outputPath),
            cwd: $scratchDir,
            env: [
                'CODEX_HOME' => $this->resolveCodexHome($account),
            ],
            timeoutSeconds: (int) config('intelligence.chatgpt_codex.timeout_seconds', 90),
            input: $contract->prompt($raw),
        );

        try {
            if ($result['exit_code'] !== 0) {
                $this->throwForCommandFailure($result['output'], $result['error_output']);
            }

            $rawOutput = trim((string) file_get_contents((string) $outputPath));
            $payload = json_decode($rawOutput, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new CodexException('Invalid Codex analysis payload: root value must be an object.');
            }
            /** @var array<string, mixed> $payload */
        } catch (JsonException $e) {
            throw new CodexException('Failed to decode Codex exec output.', previous: $e);
        } finally {
            @unlink((string) $schemaPath);
            @unlink((string) $outputPath);
        }

        $payload = CodexNewsAnalysisPayload::fromArray($payload, $contract);
        $analysis = new NewsAnalysisResult(
            translatedContent: $payload->translatedContent,
            generatedTitle: $payload->generatedTitle,
            category: $payload->category,
            tags: $payload->tags,
            sentiment: $payload->sentiment,
            analysisMetadata: $metadata,
        );

        $this->cache->put(
            fingerprint: $raw->fingerprint,
            provider: AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            model: $model,
            reasoningEffort: $cacheReasoningEffort,
            analysisVersion: CodexNewsAnalysisContract::ANALYSIS_VERSION,
            result: $analysis,
        );

        return $analysis;
    }

    private function ensureScratchDir(): string
    {
        $scratchDir = (string) config('intelligence.chatgpt_codex.scratch_dir', '/tmp/codex-news-analysis');

        if (! is_dir($scratchDir)) {
            mkdir($scratchDir, 0775, true);
        }

        return $scratchDir;
    }

    private function resolveCodexHome(AiProviderProfile $account): string
    {
        $base = rtrim((string) config('intelligence.chatgpt_codex.home_base', '/home/www-data/.codex/providers'), '/');
        $path = $base.'/'.$account->codexHomeSubpath;

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(
        AiProviderProfile $account,
        string $model,
        ?string $reasoningEffort,
        CodexNewsAnalysisContract $contract,
    ): array {
        return [
            'provider' => AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            'model' => $model,
            'reasoning_effort' => $reasoningEffort ?? 'model_default',
            'profile_slug' => $account->slug,
            'analysis_version' => CodexNewsAnalysisContract::ANALYSIS_VERSION,
            'categories_prompted' => $contract->categories(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function withMetadata(NewsAnalysisResult $result, array $metadata): NewsAnalysisResult
    {
        return new NewsAnalysisResult(
            translatedContent: $result->translatedContent,
            generatedTitle: $result->generatedTitle,
            category: $result->category,
            tags: $result->tags,
            sentiment: $result->sentiment,
            analysisMetadata: $metadata,
        );
    }

    private function writeTempFile(string $prefix, string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new CodexException('Failed to allocate temporary file for Codex schema.');
        }

        file_put_contents($path, $contents);

        return $path;
    }

    private function throwForCommandFailure(string $output, string $errorOutput): never
    {
        $message = trim($errorOutput !== '' ? $errorOutput : $output);
        $lowerMessage = strtolower($message);

        if (str_contains($message, 'usageLimitExceeded') || str_contains($lowerMessage, 'usage limit')) {
            throw new CodexUsageLimitExceededException($message);
        }

        if (str_contains($lowerMessage, 'unauthorized') || str_contains($lowerMessage, 'login')) {
            throw new CodexUnauthorizedException($message);
        }

        throw new CodexException($message !== '' ? $message : 'Codex exec failed without output.');
    }

    private function resolveReasoningEffort(AiProviderProfile $account): ?string
    {
        $effort = $account->defaultReasoningEffort;

        if (! is_string($effort) || $effort === '') {
            $configured = config('intelligence.chatgpt_codex.reasoning_effort');

            $effort = is_string($configured) ? $configured : null;
        }

        return is_string($effort) && $effort !== '' ? $effort : null;
    }

    /**
     * @return list<string>
     */
    private function buildCommand(
        string $model,
        ?string $reasoningEffort,
        string $scratchDir,
        string $schemaPath,
        string $outputPath,
    ): array {
        $command = [
            (string) config('intelligence.chatgpt_codex.binary', 'codex'),
            '--ask-for-approval',
            'never',
            'exec',
        ];

        if ($reasoningEffort !== null) {
            $command[] = '-c';
            $command[] = sprintf('model_reasoning_effort="%s"', $reasoningEffort);
        }

        array_push(
            $command,
            '-m',
            $model,
            '--skip-git-repo-check',
            '-C',
            $scratchDir,
            '--sandbox',
            'read-only',
            '--output-schema',
            $schemaPath,
            '-o',
            $outputPath,
            '-',
        );

        return $command;
    }
}
