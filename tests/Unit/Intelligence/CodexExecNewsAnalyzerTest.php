<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Carbon\CarbonImmutable;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Infrastructure\Codex\CodexException;
use Modules\Intelligence\Infrastructure\Codex\CodexExecNewsAnalyzer;
use Modules\Intelligence\Infrastructure\Codex\CodexProcessRunnerContract;
use Modules\Shared\Domain\DTO\RawNewsData;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CodexExecNewsAnalyzerTest extends TestCase
{
    public function test_analyzer_builds_expected_codex_exec_command_and_maps_result(): void
    {
        config()->set('intelligence.chatgpt_codex.home_base', sys_get_temp_dir().'/codex-exec-home');
        config()->set('intelligence.chatgpt_codex.scratch_dir', sys_get_temp_dir().'/codex-scratch');

        $runner = $this->createMock(CodexProcessRunnerContract::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (array $command, ?string $cwd, array $env, ?int $timeoutSeconds, ?string $input): array {
                $this->assertSame('codex', $command[0]);
                $this->assertSame('--ask-for-approval', $command[1]);
                $this->assertSame('never', $command[2]);
                $this->assertSame('exec', $command[3]);
                $this->assertContains('-c', $command);
                $this->assertContains('model_reasoning_effort="high"', $command);
                $this->assertContains('gpt-5.4-mini', $command);
                $this->assertContains('--sandbox', $command);
                $this->assertContains('read-only', $command);
                $this->assertContains('--output-schema', $command);
                $this->assertContains('-o', $command);
                $this->assertSame(sys_get_temp_dir().'/codex-scratch', $cwd);
                $this->assertSame(90, $timeoutSeconds);
                $this->assertStringContainsString('Source language: en', (string) $input);
                $this->assertStringContainsString('Original title', (string) $input);
                $this->assertStringContainsString('Original content', (string) $input);
                $this->assertStringContainsString('chatgpt-default', $env['CODEX_HOME'] ?? '');

                $outputFlagIndex = array_search('-o', $command, true);
                $this->assertIsInt($outputFlagIndex);

                $outputPath = $command[$outputFlagIndex + 1];
                file_put_contents($outputPath, json_encode([
                    'translated_content' => 'Переведённый текст',
                    'generated_title' => 'Нейтральный заголовок',
                    'category' => 'IT',
                    'tags' => ['ai', 'laravel'],
                    'sentiment' => 4,
                ], JSON_THROW_ON_ERROR));

                return [
                    'exit_code' => 0,
                    'output' => '',
                    'error_output' => '',
                ];
            });

        $analyzer = new CodexExecNewsAnalyzer($runner);

        $result = $analyzer->analyze($this->rawNews(), $this->account());

        $this->assertSame('Переведённый текст', $result->translatedContent);
        $this->assertSame('Нейтральный заголовок', $result->generatedTitle);
        $this->assertSame('IT', $result->category);
        $this->assertSame(['ai', 'laravel'], $result->tags);
        $this->assertSame(4, $result->sentiment);
        $this->assertSame('chatgpt_codex', $result->analysisMetadata['provider']);
        $this->assertSame('gpt-5.4-mini', $result->analysisMetadata['model']);
        $this->assertSame('high', $result->analysisMetadata['reasoning_effort']);
        $this->assertSame(2, $result->analysisMetadata['analysis_version']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>|string, 1: string}>
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'missing required field' => [
                [
                    'translated_content' => 'Переведённый текст',
                    'generated_title' => 'Нейтральный заголовок',
                    'category' => 'IT',
                    'tags' => ['ai'],
                ],
                'sentiment',
            ],
            'invalid category' => [
                [
                    'translated_content' => 'Переведённый текст',
                    'generated_title' => 'Нейтральный заголовок',
                    'category' => 'Lifestyle',
                    'tags' => ['ai'],
                    'sentiment' => 4,
                ],
                'category',
            ],
            'invalid tags shape' => [
                [
                    'translated_content' => 'Переведённый текст',
                    'generated_title' => 'Нейтральный заголовок',
                    'category' => 'IT',
                    'tags' => ['ai', ''],
                    'sentiment' => 4,
                ],
                'tags',
            ],
            'invalid sentiment type' => [
                [
                    'translated_content' => 'Переведённый текст',
                    'generated_title' => 'Нейтральный заголовок',
                    'category' => 'IT',
                    'tags' => ['ai'],
                    'sentiment' => 'positive',
                ],
                'sentiment',
            ],
            'invalid sentiment range' => [
                [
                    'translated_content' => 'Переведённый текст',
                    'generated_title' => 'Нейтральный заголовок',
                    'category' => 'IT',
                    'tags' => ['ai'],
                    'sentiment' => 11,
                ],
                'sentiment',
            ],
            'too long generated title' => [
                [
                    'translated_content' => 'Переведённый текст',
                    'generated_title' => str_repeat('a', 141),
                    'category' => 'IT',
                    'tags' => ['ai'],
                    'sentiment' => 4,
                ],
                'generated_title',
            ],
            'invalid json' => [
                '{',
                'Failed to decode Codex exec output.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|string  $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_analyzer_rejects_invalid_codex_payload(array|string $payload, string $message): void
    {
        $this->expectException(CodexException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($message, '/').'/i');

        $this->analyzeWithPayload($payload);
    }

    private function rawNews(): RawNewsData
    {
        return new RawNewsData(
            sourceId: 1,
            externalId: 'ext-1',
            title: 'Original title',
            link: 'https://example.com/news/1',
            content: 'Original content',
            publishedAt: CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
            language: 'en',
            metadata: [],
            imageUrl: null,
            media: [],
            fingerprint: 'fp-1',
            rawId: null,
        );
    }

    private function account(): AiProviderProfile
    {
        return new AiProviderProfile(
            id: 1,
            slug: 'chatgpt-default',
            provider: AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            displayName: 'ChatGPT Codex',
            enabled: true,
            codexHomeSubpath: 'chatgpt-default',
            defaultModel: 'gpt-5.4-mini',
            defaultReasoningEffort: 'high',
            maxParallelJobs: 1,
            authStatus: AiProviderProfile::STATUS_AUTHENTICATED,
        );
    }

    /**
     * @param  array<string, mixed>|string  $payload
     */
    private function analyzeWithPayload(array|string $payload): void
    {
        config()->set('intelligence.chatgpt_codex.home_base', sys_get_temp_dir().'/codex-exec-home');
        config()->set('intelligence.chatgpt_codex.scratch_dir', sys_get_temp_dir().'/codex-scratch');

        $runner = $this->createMock(CodexProcessRunnerContract::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturnCallback(static function (array $command) use ($payload): array {
                $outputFlagIndex = array_search('-o', $command, true);
                self::assertIsInt($outputFlagIndex);

                $outputPath = $command[$outputFlagIndex + 1];
                file_put_contents(
                    $outputPath,
                    is_array($payload)
                        ? json_encode($payload, JSON_THROW_ON_ERROR)
                        : $payload,
                );

                return [
                    'exit_code' => 0,
                    'output' => '',
                    'error_output' => '',
                ];
            });

        $analyzer = new CodexExecNewsAnalyzer($runner);
        $analyzer->analyze($this->rawNews(), $this->account());
    }
}
