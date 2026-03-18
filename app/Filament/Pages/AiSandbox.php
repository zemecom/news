<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use LogicException;
use Modules\Intelligence\Application\Services\ActiveAiProviderResolver;
use Modules\Intelligence\Application\Services\RunAiSandboxAction;
use Modules\Intelligence\Domain\Exceptions\AiProviderException;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Override;
use Throwable;
use UnitEnum;

final class AiSandbox extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'AI Sandbox';

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'AI Sandbox';

    protected string $view = 'filament.pages.ai-sandbox';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    public ?string $errorMessage = null;

    public function mount(ActiveAiProviderResolver $resolver): void
    {
        $activeProvider = $resolver->resolveChatGptCodex();

        $this->getFormSchema()->fill([
            'provider_id' => $activeProvider?->id,
            'title' => 'OpenAI выпустила новую модель для анализа новостей',
            'language' => 'ru',
            'link' => 'https://example.com/news/sandbox',
            'content' => 'Компания OpenAI объявила о запуске новой компактной модели, которую можно использовать для быстрой аналитики и классификации материалов в редакционных системах.',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sandbox Request')
                    ->description('Песочница использует реальный AI-провайдер и текущие лимиты подписки.')
                    ->compact()
                    ->columns(2)
                    ->components([
                        Select::make('provider_id')
                            ->label('Provider')
                            ->options(fn (): array => $this->providerOptions())
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->required(),
                        Placeholder::make('provider_snapshot')
                            ->label('Current provider config')
                            ->content(fn (Get $get): string => $this->selectedProviderSummary($get->integer('provider_id', isNullable: true))),
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('language')
                            ->label('Language')
                            ->required()
                            ->maxLength(16),
                        TextInput::make('link')
                            ->label('Link')
                            ->url()
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('content')
                            ->label('Content')
                            ->rows(16)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function run(RunAiSandboxAction $sandbox): void
    {
        /** @var array{provider_id:int|string,title:string,language:string,link:string,content:string} $state */
        $state = $this->getFormSchema()->getState();

        $provider = $this->findProvider((int) $state['provider_id']);

        if (! $provider instanceof AiProviderAccount) {
            $this->result = null;
            $this->errorMessage = 'Выбранный AI provider не найден или отключён.';

            Notification::make()
                ->title('Sandbox failed')
                ->body($this->errorMessage)
                ->danger()
                ->send();

            return;
        }

        $startedAt = microtime(true);

        try {
            $analysis = $sandbox->run(
                account: $provider->toProfile(),
                title: $state['title'],
                content: $state['content'],
                language: $state['language'],
                link: $state['link'],
            );
        } catch (AiProviderException $e) {
            $this->result = null;
            $this->errorMessage = $e->getMessage();

            Notification::make()
                ->title('Sandbox failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);

            $this->result = null;
            $this->errorMessage = 'Unexpected sandbox error: '.$e->getMessage();

            Notification::make()
                ->title('Sandbox failed')
                ->body($this->errorMessage)
                ->danger()
                ->send();

            return;
        }

        $elapsedMilliseconds = (int) round((microtime(true) - $startedAt) * 1000);

        $this->result = [
            'provider' => [
                'display_name' => $provider->display_name,
                'email' => $provider->account_email,
                'plan_type' => $provider->plan_type,
                'model' => $provider->default_model,
                'reasoning_effort' => $provider->default_reasoning_effort ?? 'model_default',
                'status' => $provider->statusLabel(),
            ],
            'analysis' => [
                'translated_content' => $analysis->translatedContent,
                'generated_title' => $analysis->generatedTitle,
                'category' => $analysis->category,
                'tags' => $analysis->tags,
                'sentiment' => $analysis->sentiment,
                'analysis_metadata' => $analysis->analysisMetadata,
            ],
            'elapsed_ms' => $elapsedMilliseconds,
            'ran_at' => now()->toIso8601String(),
        ];
        $this->errorMessage = null;

        Notification::make()
            ->title('Sandbox completed')
            ->body(sprintf('AI ответ получен за %d мс.', $elapsedMilliseconds))
            ->success()
            ->send();
    }

    public function clearResult(): void
    {
        $this->result = null;
        $this->errorMessage = null;
    }

    public function selectedProviderRecord(): ?AiProviderAccount
    {
        return $this->findProvider(isset($this->data['provider_id']) ? (int) $this->data['provider_id'] : null);
    }

    #[Override]
    public function getMaxContentWidth(): \Filament\Support\Enums\Width
    {
        return \Filament\Support\Enums\Width::Full;
    }

    /**
     * @return array<int, string>
     */
    private function providerOptions(): array
    {
        /** @var array<int, AiProviderAccount> $providers */
        $providers = AiProviderAccount::query()
            ->where('provider', AiProviderAccount::PROVIDER_CHATGPT_CODEX)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get()
            ->all();

        $options = [];

        foreach ($providers as $provider) {
            $options[$provider->getKey()] = sprintf(
                '%s [%s, %s]',
                $provider->display_name,
                $provider->statusLabel(),
                $provider->default_model,
            );
        }

        return $options;
    }

    private function selectedProviderSummary(?int $providerId): string
    {
        $provider = $this->findProvider($providerId);

        if (! $provider instanceof AiProviderAccount) {
            return 'Выбери включённый AI provider для теста.';
        }

        return sprintf(
            'Plan: %s | Status: %s | Model: %s | Reasoning: %s | 5h: %s | Week: %s',
            $provider->plan_type ?? 'n/a',
            $provider->statusLabel(),
            $provider->default_model,
            $provider->default_reasoning_effort ?? 'model_default',
            $provider->rateLimitUsedPercent() !== null ? $provider->rateLimitUsedPercent().'%' : 'n/a',
            $provider->weeklyRateLimitUsedPercent() !== null ? $provider->weeklyRateLimitUsedPercent().'%' : 'n/a',
        );
    }

    private function findProvider(?int $providerId): ?AiProviderAccount
    {
        if ($providerId === null || $providerId <= 0) {
            return null;
        }

        /** @var AiProviderAccount|null $provider */
        $provider = AiProviderAccount::query()
            ->whereKey($providerId)
            ->where('provider', AiProviderAccount::PROVIDER_CHATGPT_CODEX)
            ->where('is_enabled', true)
            ->first();

        return $provider;
    }

    private function getFormSchema(): Schema
    {
        $schema = $this->getSchema('form');

        if (! $schema instanceof Schema) {
            throw new LogicException('AI Sandbox form schema is not configured.');
        }

        return $schema;
    }
}
