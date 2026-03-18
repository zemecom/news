<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Resources\AiProviderAccounts\Schemas\AiProviderAccountForm;
use ReflectionClass;
use Tests\TestCase;

final class AiProviderAccountFormTest extends TestCase
{
    public function test_gpt_5_1_codex_mini_supports_only_medium_and_high_reasoning_levels(): void
    {
        $this->assertSame(
            ['medium', 'high'],
            $this->invokeSupportedReasoningEffortKeys('gpt-5.1-codex-mini'),
        );
    }

    public function test_gpt_5_1_codex_max_supports_low_through_xhigh_reasoning_levels(): void
    {
        $this->assertSame(
            ['low', 'medium', 'high', 'xhigh'],
            $this->invokeSupportedReasoningEffortKeys('gpt-5.1-codex-max'),
        );
    }

    public function test_gpt_5_4_supports_low_through_xhigh_reasoning_levels(): void
    {
        $this->assertSame(
            ['low', 'medium', 'high', 'xhigh'],
            $this->invokeSupportedReasoningEffortKeys('gpt-5.4'),
        );
    }

    /**
     * @return array<int, string>
     */
    private function invokeSupportedReasoningEffortKeys(string $model): array
    {
        $reflection = new ReflectionClass(AiProviderAccountForm::class);
        $method = $reflection->getMethod('supportedReasoningEffortKeysForModel');
        $method->setAccessible(true);

        /** @var array<int, string> $result */
        $result = $method->invoke(null, $model);

        return $result;
    }
}
