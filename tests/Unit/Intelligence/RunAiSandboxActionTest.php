<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Modules\Intelligence\Application\Services\RunAiSandboxAction;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\Contracts\NewsAnalyzer;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;
use Modules\Intelligence\Infrastructure\Codex\CodexUnauthorizedException;
use Tests\TestCase;

final class RunAiSandboxActionTest extends TestCase
{
    public function test_it_builds_sandbox_raw_news_and_returns_analysis(): void
    {
        $profile = $this->makeProfile();
        $expected = new NewsAnalysisResult(
            translatedContent: 'Переведённый текст',
            generatedTitle: 'Нейтральный заголовок',
            category: 'IT',
            tags: ['ai', 'openai'],
            sentiment: 3,
            analysisMetadata: ['provider' => 'chatgpt_codex'],
        );

        $analyzer = $this->createMock(NewsAnalyzer::class);
        $analyzer
            ->expects($this->once())
            ->method('analyze')
            ->willReturnCallback(function ($raw, $account) use ($profile, $expected) {
                $this->assertSame($profile, $account);
                $this->assertSame(0, $raw->sourceId);
                $this->assertSame('Sandbox title', $raw->title);
                $this->assertSame('Sandbox content', $raw->content);
                $this->assertSame('en', $raw->language);
                $this->assertSame('https://example.com/item', $raw->link);
                $this->assertTrue($raw->metadata['sandbox']);

                return $expected;
            });

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager->expects($this->never())->method('sync');
        $statusManager->expects($this->never())->method('markUsageLimited');
        $statusManager->expects($this->never())->method('markNotAuthenticated');
        $statusManager->expects($this->never())->method('markError');

        $action = new RunAiSandboxAction($analyzer, $statusManager);

        $result = $action->run(
            account: $profile,
            title: 'Sandbox title',
            content: 'Sandbox content',
            language: 'en',
            link: 'https://example.com/item',
        );

        $this->assertSame($expected, $result);
    }

    public function test_it_marks_provider_not_authenticated_when_analyzer_reports_unauthorized(): void
    {
        $profile = $this->makeProfile();

        $analyzer = $this->createMock(NewsAnalyzer::class);
        $analyzer
            ->expects($this->once())
            ->method('analyze')
            ->willThrowException(new CodexUnauthorizedException('login required'));

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager->expects($this->never())->method('sync');
        $statusManager->expects($this->never())->method('markUsageLimited');
        $statusManager->expects($this->once())->method('markNotAuthenticated')->with($profile);
        $statusManager->expects($this->never())->method('markError');

        $action = new RunAiSandboxAction($analyzer, $statusManager);

        $this->expectException(CodexUnauthorizedException::class);
        $this->expectExceptionMessage('login required');

        $action->run(
            account: $profile,
            title: 'Sandbox title',
            content: 'Sandbox content',
            language: 'en',
            link: 'https://example.com/item',
        );
    }

    private function makeProfile(): AiProviderProfile
    {
        return new AiProviderProfile(
            id: 1,
            slug: 'chatgpt-default',
            provider: AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            displayName: 'ChatGPT Codex',
            enabled: true,
            codexHomeSubpath: 'chatgpt-default',
            defaultModel: 'gpt-5.4-mini',
            maxParallelJobs: 1,
            authStatus: AiProviderProfile::STATUS_AUTHENTICATED,
            accountEmail: 'admin@example.com',
            planType: 'plus',
        );
    }
}
