<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Throwable;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                Action::make('reload_roadrunner')
                    ->label('Reload RoadRunner')
                    ->icon(Heroicon::OutlinedComputerDesktop)
                    ->sort(10)
                    ->requiresConfirmation()
                    ->modalDescription('Перезагрузит Octane/RoadRunner workers без рестарта контейнера.')
                    ->action(function (): void {
                        try {
                            $command = 'reload';
                            $exitCode = Artisan::call($command);

                            if ($exitCode !== 0) {
                                $command = 'octane:reload';
                                $exitCode = Artisan::call($command);
                            }

                            Notification::make()
                                ->title($exitCode === 0 ? 'RoadRunner reloaded' : 'RoadRunner reload returned a non-zero exit code')
                                ->body($exitCode === 0
                                    ? 'Octane/RoadRunner workers отправлены на graceful reload.'
                                    : (trim(Artisan::output()) ?: sprintf('Команда %s завершилась неуспешно.', $command)))
                                ->color($exitCode === 0 ? 'success' : 'warning')
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('RoadRunner reload failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\QueueOverviewWidget::class,
                \App\Filament\Widgets\AiProviderStatusWidget::class,
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\AutoLoginAdmin::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
