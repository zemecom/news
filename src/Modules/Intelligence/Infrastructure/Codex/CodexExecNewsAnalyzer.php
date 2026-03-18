<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use JsonException;
use Modules\Intelligence\Domain\Contracts\NewsAnalyzer;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class CodexExecNewsAnalyzer implements NewsAnalyzer
{
    public function __construct(
        private CodexProcessRunnerContract $runner,
    ) {}

    public function analyze(RawNewsData $raw, AiProviderProfile $account): NewsAnalysisResult
    {
        $schemaPath = $this->writeTempFile('codex-news-schema-', json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $outputPath = tempnam(sys_get_temp_dir(), 'codex-news-output-');
        $scratchDir = $this->ensureScratchDir();
        $model = $account->defaultModel !== '' ? $account->defaultModel : (string) config('intelligence.chatgpt_codex.model', 'gpt-5.4-mini');
        $reasoningEffort = $this->resolveReasoningEffort($account);

        $result = $this->runner->run(
            command: $this->buildCommand($model, $reasoningEffort, $scratchDir, $schemaPath, (string) $outputPath),
            cwd: $scratchDir,
            env: [
                'CODEX_HOME' => $this->resolveCodexHome($account),
            ],
            timeoutSeconds: (int) config('intelligence.chatgpt_codex.timeout_seconds', 90),
            input: $this->prompt($raw),
        );

        try {
            if ($result['exit_code'] !== 0) {
                $this->throwForCommandFailure($result['output'], $result['error_output']);
            }

            $rawOutput = trim((string) file_get_contents((string) $outputPath));
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawOutput, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CodexException('Failed to decode Codex exec output.', previous: $e);
        } finally {
            @unlink((string) $schemaPath);
            @unlink((string) $outputPath);
        }

        $metadata = [
            'provider' => AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            'model' => $model,
            'reasoning_effort' => $reasoningEffort ?? 'model_default',
            'profile_slug' => $account->slug,
            'analysis_version' => 1,
        ];

        return new NewsAnalysisResult(
            translatedContent: (string) ($payload['translated_content'] ?? $raw->content),
            generatedTitle: isset($payload['generated_title']) && $payload['generated_title'] !== '' ? (string) $payload['generated_title'] : null,
            category: (string) ($payload['category'] ?? ''),
            tags: array_values(array_filter($payload['tags'] ?? [], static fn (mixed $tag): bool => is_string($tag) && $tag !== '')),
            sentiment: (int) ($payload['sentiment'] ?? 0),
            analysisMetadata: array_merge($metadata, [
                'categories_prompted' => config('intelligence.categories', []),
            ]),
        );
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
    private function schema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'translated_content',
                'generated_title',
                'category',
                'tags',
                'sentiment',
            ],
            'properties' => [
                'translated_content' => [
                    'type' => 'string',
                ],
                'generated_title' => [
                    'type' => ['string', 'null'],
                    'maxLength' => 140,
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => config('intelligence.categories', []),
                ],
                'tags' => [
                    'type' => 'array',
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'string',
                        'maxLength' => 32,
                    ],
                ],
                'sentiment' => [
                    'type' => 'integer',
                    'minimum' => -10,
                    'maximum' => 10,
                ],
            ],
        ];
    }

    private function prompt(RawNewsData $raw): string
    {
        $categories = implode(', ', config('intelligence.categories', []));
        $language = $raw->language !== '' ? $raw->language : 'en';

        return <<<PROMPT
You are a news analysis engine.
Return only JSON matching the provided schema.

Tasks:
1. Translate the news content to Russian if it is not already Russian.
2. Produce an objective, non-clickbait title in Russian.
3. Pick exactly one category from this set: {$categories}.
4. Produce 0-8 short lowercase tags.
5. Produce a sentiment score from -10 to 10.

Rules:
- Preserve factual meaning.
- Avoid exaggeration.
- If the original title is already neutral, you may keep its meaning but rewrite it in clean Russian.
- Tags should be concise and lowercase.
- If the content is already in Russian, translated_content may stay semantically identical.

Source language: {$language}
Title: {$raw->title}
Content:
{$raw->content}
PROMPT;
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
