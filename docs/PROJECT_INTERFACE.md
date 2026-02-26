# Project Interface
_SOURCE: Application Core_
# Application Core
###  Path: `/app/Console/Commands/HealthCheckCommand.php`

```php
namespace App\Console\Commands;

use Illuminate\Console\Command as Command;
use Illuminate\Support\Facades\DB as DB;
use Illuminate\Support\Facades\Redis as Redis;
use Throwable as Throwable;

final class HealthCheckCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'app:health-check';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Check if the application is healthy (DB, Redis)';


	/**
	 * Execute the console command.
	 */
	public function handle(): int
	{
	}
}


```
###  Path: `/app/Console/Commands/MessagingSetupCommand.php`

```php
namespace App\Console\Commands;

use App\Services\MessagingTopologyService as MessagingTopologyService;
use Illuminate\Console\Command as Command;

final class MessagingSetupCommand extends Command
{
	protected $signature = 'news:messaging:setup';
	protected $description = 'Declare RabbitMQ exchange, queues and bindings for SmartNews.';


	public function handle(MessagingTopologyService $topology): int
	{
	}
}


```
###  Path: `/app/Console/Commands/NewsCrawlCommand.php`

```php
namespace App\Console\Commands;

use Illuminate\Console\Command as Command;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem as NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source as Source;
use Throwable as Throwable;

final class NewsCrawlCommand extends Command
{
	protected $signature = "news:crawl \n                            {--source-id= : Crawl only one source id}\n                            {--date-from= : Parse articles from this date (Y-m-d H:i:s)}\n                            {--date-to= : Parse articles until this date (Y-m-d H:i:s)}\n                            {--limit= : Maximum number of articles to parse per source}\n                            {--sync : Run synchronously without queue}";
	protected $description = 'Fetch active sources and enqueue raw news jobs (RabbitMQ-backed Laravel queue).';


	public function handle(): int
	{
	}
}


```
###  Path: `/app/Console/Commands/NewsMediaBackfillCommand.php`

```php
namespace App\Console\Commands;

use Illuminate\Console\Command as Command;
use Illuminate\Database\Eloquent\Builder as Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as Collection;
use InvalidArgumentException as InvalidArgumentException;
use Modules\Catalog\Application\Actions\PreloadNewsMediaAction as PreloadNewsMediaAction;
use Modules\Catalog\Application\Jobs\PreloadNewsMediaJob as PreloadNewsMediaJob;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem as NewsItem;
use Throwable as Throwable;

final class NewsMediaBackfillCommand extends Command
{
	protected $signature = "news:media:backfill\n                            {--from-id= : Include news_items with id >= this value}\n                            {--to-id= : Include news_items with id <= this value}\n                            {--limit= : Maximum number of eligible items to process}\n                            {--chunk=200 : Number of rows to scan per chunk}\n                            {--queue=media_tasks : Queue name for async mode}\n                            {--all : Reprocess items even if media assets already exist}\n                            {--sync : Process synchronously in current process}\n                            {--dry-run : Print counters without dispatching or downloading}";
	protected $description = 'Backfill media assets for existing news items (original + local copies).';


	public function handle(PreloadNewsMediaAction $preloadNewsMedia): int
	{
	}


	private function intOption(string $name, int $min, ?int $default = null): ?int
	{
	}


	private function hasBackfillableMedia(NewsItem $item): bool
	{
	}


	private function extractMediaUrl(mixed $mediaItem): ?string
	{
	}


	private function normalizeString(mixed $value): ?string
	{
	}
}


```
###  Path: `/app/Filament/Resources/Sources/Pages/CreateSource.php`

```php
namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource as SourceResource;
use Filament\Resources\Pages\CreateRecord as CreateRecord;

class CreateSource extends CreateRecord
{
	protected static string $resource = SourceResource::class;
}


```
###  Path: `/app/Filament/Resources/Sources/Pages/EditSource.php`

```php
namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource as SourceResource;
use Filament\Actions\DeleteAction as DeleteAction;
use Filament\Resources\Pages\EditRecord as EditRecord;
use Override as Override;

class EditSource extends EditRecord
{
	protected static string $resource = SourceResource::class;


	#[Override]
	protected function getHeaderActions(): array
	{
	}
}


```
###  Path: `/app/Filament/Resources/Sources/Pages/ListSources.php`

```php
namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource as SourceResource;
use Filament\Actions\CreateAction as CreateAction;
use Filament\Resources\Pages\ListRecords as ListRecords;
use Override as Override;

class ListSources extends ListRecords
{
	protected static string $resource = SourceResource::class;


	#[Override]
	protected function getHeaderActions(): array
	{
	}


	#[Override]
	public function getMaxContentWidth(): \Filament\Support\Enums\Width|string|null
	{
	}
}


```
###  Path: `/app/Filament/Resources/Sources/Schemas/SourceForm.php`

```php
namespace App\Filament\Resources\Sources\Schemas;

use Filament\Forms\Components\DateTimePicker as DateTimePicker;
use Filament\Forms\Components\TextInput as TextInput;
use Filament\Forms\Components\Toggle as Toggle;
use Filament\Schemas\Schema as Schema;

class SourceForm
{
	public static function configure(Schema $schema): Schema
	{
	}
}


```
###  Path: `/app/Filament/Resources/Sources/SourceResource.php`

```php
namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages\CreateSource as CreateSource;
use App\Filament\Resources\Sources\Pages\EditSource as EditSource;
use App\Filament\Resources\Sources\Pages\ListSources as ListSources;
use App\Filament\Resources\Sources\Schemas\SourceForm as SourceForm;
use App\Filament\Resources\Sources\Tables\SourcesTable as SourcesTable;
use BackedEnum as BackedEnum;
use Filament\Resources\Resource as Resource;
use Filament\Schemas\Schema as Schema;
use Filament\Support\Icons\Heroicon as Heroicon;
use Filament\Tables\Table as Table;
use Modules\Catalog\Infrastructure\Persistence\Models\Source as Source;
use Override as Override;

class SourceResource extends Resource
{
	protected static ?string $model = Source::class;
	protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


	#[Override]
	public static function form(Schema $schema): Schema
	{
	}


	#[Override]
	public static function table(Table $table): Table
	{
	}


	#[Override]
	public static function getRelations(): array
	{
	}


	#[Override]
	public static function getPages(): array
	{
	}
}


```
###  Path: `/app/Filament/Resources/Sources/Tables/SourcesTable.php`

```php
namespace App\Filament\Resources\Sources\Tables;

use Filament\Actions\BulkActionGroup as BulkActionGroup;
use Filament\Actions\DeleteBulkAction as DeleteBulkAction;
use Filament\Actions\EditAction as EditAction;
use Filament\Tables\Columns\IconColumn as IconColumn;
use Filament\Tables\Columns\TextColumn as TextColumn;
use Filament\Tables\Table as Table;

class SourcesTable
{
	public static function configure(Table $table): Table
	{
	}
}


```
###  Path: `/app/Http/Controllers/Api/Admin/SourceController.php`

```php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller as Controller;
use Illuminate\Http\JsonResponse as JsonResponse;
use Modules\Delivery\Application\Actions\ListSourcesAction as ListSourcesAction;

final class SourceController extends Controller
{
	public function __construct(
		private readonly ListSourcesAction $listSources,
	) {
	}


	public function index(): JsonResponse
	{
	}
}


```
###  Path: `/app/Http/Controllers/Api/NewsController.php`

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as Controller;
use App\Http\Requests\Api\NewsIndexRequest as NewsIndexRequest;
use Carbon\CarbonImmutable as CarbonImmutable;
use Illuminate\Http\JsonResponse as JsonResponse;
use Modules\Delivery\Application\Actions\ListNewsAction as ListNewsAction;
use Modules\Delivery\Application\Actions\ListPublicSourcesAction as ListPublicSourcesAction;
use Modules\Delivery\Application\Actions\ShowNewsAction as ShowNewsAction;
use Modules\Delivery\Domain\DTO\NewsFeedFilters as NewsFeedFilters;

final class NewsController extends Controller
{
	public function __construct(
		private readonly ListNewsAction $listNews,
		private readonly ShowNewsAction $showNews,
		private readonly ListPublicSourcesAction $listPublicSources,
	) {
	}


	public function index(NewsIndexRequest $request): JsonResponse
	{
	}


	public function show(string $id): JsonResponse
	{
	}


	public function sources(): JsonResponse
	{
	}
}


```
###  Path: `/app/Http/Controllers/Controller.php`

```php
namespace App\Http\Controllers;

abstract class Controller
{
}


```
###  Path: `/app/Http/Controllers/HealthController.php`

```php
namespace App\Http\Controllers;

use App\Services\HealthCheckService as HealthCheckService;

final class HealthController extends Controller
{
	public function __construct(
		private readonly HealthCheckService $health,
	) {
	}


	/**
	 * @return array{status:string}
	 */
	public function live(): array
	{
	}


	public function ready(): \Illuminate\Http\JsonResponse
	{
	}
}


```
###  Path: `/app/Http/Controllers/Web/FeedPageController.php`

```php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as Controller;
use Illuminate\Contracts\View\View as View;

final class FeedPageController extends Controller
{
	public function __invoke(): View
	{
	}
}


```
###  Path: `/app/Http/Middleware/AutoLoginAdmin.php`

```php
namespace App\Http\Middleware;

use App\Models\User as User;
use Closure as Closure;
use Illuminate\Http\Request as Request;
use Illuminate\Support\Facades\Auth as Auth;
use Symfony\Component\HttpFoundation\Response as Response;

class AutoLoginAdmin
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
	 */
	public function handle(Request $request, Closure $next): Response
	{
	}
}


```
###  Path: `/app/Http/Middleware/EnsureUserIsAdmin.php`

```php
namespace App\Http\Middleware;

use App\Models\User as User;
use Closure as Closure;
use Illuminate\Http\JsonResponse as JsonResponse;
use Illuminate\Http\Request as Request;
use Symfony\Component\HttpFoundation\Response as Response;

final class EnsureUserIsAdmin
{
	/**
	 * @param  Closure(Request): Response  $next
	 */
	public function handle(Request $request, Closure $next): Response
	{
	}
}


```
###  Path: `/app/Http/Requests/Api/NewsIndexRequest.php`

```php
namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator as Validator;
use Illuminate\Foundation\Http\FormRequest as FormRequest;

final class NewsIndexRequest extends FormRequest
{
	public function authorize(): bool
	{
	}


	/**
	 * @return array<string, mixed>
	 */
	public function rules(): array
	{
	}


	public function withValidator(Validator $validator): void
	{
	}
}


```
###  Path: `/app/Livewire/CrawlerLog.php`

```php
namespace App\Livewire;

use Carbon\Carbon as Carbon;
use Illuminate\Support\Facades\Artisan as Artisan;
use Livewire\Component as Component;
use Modules\Catalog\Infrastructure\Persistence\Models\Source as Source;
use Modules\Crawler\Application\Jobs\FetchSourceJob as FetchSourceJob;
use Throwable as Throwable;

class CrawlerLog extends Component
{
	public string $output = 'Starting...';
	public ?int $sourceId = null;
	public string $logFile = '';
	public ?string $dateFrom = null;
	public ?string $dateTo = null;
	public ?int $limit = null;
	public bool $isStarted = false;


	public function mount(?int $sourceId = null): void
	{
	}


	public function startParsing(): void
	{
	}


	public function updateLog(): void
	{
	}


	private function writeLog(string $line): void
	{
	}


	private function appendLog(string $line): void
	{
	}


	private function parseDate(?string $value): ?Carbon
	{
	}


	public function render(): \Illuminate\Contracts\View\View
	{
	}
}


```
###  Path: `/app/Models/User.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory as HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable as Notifiable;
use Override as Override;

/**
 * @property string $role
 * @property string $email
 */
class User extends Authenticatable implements \Filament\Models\Contracts\FilamentUser
{
	use HasFactory;
	/** @use HasFactory<\Database\Factories\UserFactory> */
	use Notifiable;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var list<string>
	 */
	protected $fillable = ['name', 'email', 'password', 'role'];

	/**
	 * The attributes that should be hidden for serialization.
	 *
	 * @var list<string>
	 */
	protected $hidden = ['password', 'remember_token'];


	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	#[Override]
	protected function casts(): array
	{
	}


	public function isAdmin(): bool
	{
	}


	public function canAccessPanel(\Filament\Panel $panel): bool
	{
	}
}


```
###  Path: `/app/Providers/AppServiceProvider.php`

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider as ServiceProvider;
use Override as Override;
use PhpAmqpLib\Connection\AMQPStreamConnection as AMQPStreamConnection;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	#[Override]
	public function register(): void
	{
	}


	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
	}
}


```
###  Path: `/app/Providers/Filament/AdminPanelProvider.php`

```php
namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate as Authenticate;
use Filament\Http\Middleware\AuthenticateSession as AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents as DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent as DispatchServingFilamentEvent;
use Filament\Pages\Dashboard as Dashboard;
use Filament\Panel as Panel;
use Filament\PanelProvider as PanelProvider;
use Filament\Support\Colors\Color as Color;
use Filament\Widgets\AccountWidget as AccountWidget;
use Filament\Widgets\FilamentInfoWidget as FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse as AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies as EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings as SubstituteBindings;
use Illuminate\Session\Middleware\StartSession as StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession as ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
	public function panel(Panel $panel): Panel
	{
	}
}


```
###  Path: `/app/Providers/ModulesServiceProvider.php`

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider as ServiceProvider;
use Modules\Catalog\CatalogServiceProvider as CatalogServiceProvider;
use Modules\Crawler\CrawlerServiceProvider as CrawlerServiceProvider;
use Modules\Delivery\DeliveryServiceProvider as DeliveryServiceProvider;
use Modules\Intelligence\IntelligenceServiceProvider as IntelligenceServiceProvider;
use Override as Override;

final class ModulesServiceProvider extends ServiceProvider
{
	#[Override]
	public function register(): void
	{
	}
}


```
###  Path: `/app/Providers/TelescopeServiceProvider.php`

```php
namespace App\Providers;

use App\Models\User as User;
use Illuminate\Support\Facades\Gate as Gate;
use Laravel\Telescope\IncomingEntry as IncomingEntry;
use Laravel\Telescope\Telescope as Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider as TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
	}


	/**
	 * Prevent sensitive request details from being logged by Telescope.
	 */
	protected function hideSensitiveRequestDetails(): void
	{
	}


	/**
	 * Register the Telescope gate.
	 *
	 * This gate determines who can access Telescope in non-local environments.
	 */
	protected function gate(): void
	{
	}
}


```
###  Path: `/app/Services/HealthCheckService.php`

```php
namespace App\Services;

use Illuminate\Support\Facades\DB as DB;
use Illuminate\Support\Facades\Redis as Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection as AMQPStreamConnection;
use Throwable as Throwable;

final readonly class HealthCheckService
{
	public function __construct(
		private AMQPStreamConnection $amqp,
	) {
	}


	/**
	 * @return array{db:string,redis:string,rabbitmq:string}
	 */
	public function check(): array
	{
	}
}


```
###  Path: `/app/Services/MessagingTopologyService.php`

```php
namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel as AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection as AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable as AMQPTable;

final readonly class MessagingTopologyService
{
	public function __construct(
		private AMQPStreamConnection $connection,
	) {
	}


	public function declareTopology(): void
	{
	}


	public function declareTopologyOn(AMQPChannel $channel): void
	{
	}


	private function declareQueue(AMQPChannel $channel, string $name, bool $quorum): void
	{
	}
}


```
_SOURCE: Modules_
# Modules
###  Path: `/src/Modules/Catalog/Application/Actions/PreloadNewsMediaAction.php`

```php
namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\Http as Http;
use Illuminate\Support\Facades\Log as Log;
use Illuminate\Support\Facades\Storage as Storage;
use Illuminate\Support\Str as Str;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository as NewsMediaAssetRepository;
use Modules\Catalog\Domain\Contracts\NewsRepository as NewsRepository;
use RuntimeException as RuntimeException;
use Throwable as Throwable;

final readonly class PreloadNewsMediaAction
{
	public function __construct(
		private NewsRepository $news,
		private NewsMediaAssetRepository $mediaAssets,
	) {
	}


	public function __invoke(int $newsItemId): void
	{
	}


	/**
	 * @param  array{id:int, source_url:string, local_disk:string}  $candidate
	 */
	private function downloadCandidate(array $candidate): void
	{
	}


	private function resolveFileExtension(string $url, ?string $mimeType): string
	{
	}


	private function normalizeMimeType(?string $contentTypeHeader): ?string
	{
	}


	private function extensionByMimeType(?string $mimeType): ?string
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Application/Jobs/PreloadNewsMediaJob.php`

```php
namespace Modules\Catalog\Application\Jobs;

use Illuminate\Bus\Queueable as Queueable;
use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable as Dispatchable;
use Illuminate\Queue\InteractsWithQueue as InteractsWithQueue;
use Illuminate\Queue\SerializesModels as SerializesModels;
use Modules\Catalog\Application\Actions\PreloadNewsMediaAction as PreloadNewsMediaAction;

final class PreloadNewsMediaJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	public int $tries = 3;

	/** @var array<int, int> */
	public array $backoff = [10, 60, 300];


	public function __construct(
		public readonly int $newsItemId,
	) {
	}


	public function handle(PreloadNewsMediaAction $preloadNewsMedia): void
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Application/Listeners/QueueMediaPreloadListener.php`

```php
namespace Modules\Catalog\Application\Listeners;

use Modules\Catalog\Application\Jobs\PreloadNewsMediaJob as PreloadNewsMediaJob;
use Modules\Shared\Domain\Events\NewsEnriched as NewsEnriched;

final readonly class QueueMediaPreloadListener
{
	public function handle(NewsEnriched $event): void
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Application/Listeners/UpdateSourceStatusListener.php`

```php
namespace Modules\Catalog\Application\Listeners;

use Modules\Catalog\Domain\Contracts\SourceRepository as SourceRepository;
use Modules\Shared\Domain\Events\SourceFetchFailed as SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded as SourceFetchSucceeded;

final readonly class UpdateSourceStatusListener
{
	public function __construct(
		private SourceRepository $sourceRepository,
	) {
	}


	/**
	 * Handle the event.
	 */
	public function handle(object $event): void
	{
	}
}


```
###  Path: `/src/Modules/Catalog/CatalogServiceProvider.php`

```php
namespace Modules\Catalog;

use Illuminate\Support\Facades\Event as Event;
use Illuminate\Support\ServiceProvider as ServiceProvider;
use Modules\Catalog\Application\Listeners\QueueMediaPreloadListener as QueueMediaPreloadListener;
use Modules\Catalog\Application\Listeners\UpdateSourceStatusListener as UpdateSourceStatusListener;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository as NewsMediaAssetRepository;
use Modules\Catalog\Domain\Contracts\NewsRepository as NewsRepository;
use Modules\Catalog\Domain\Contracts\SourceRepository as SourceRepository;
use Modules\Catalog\Infrastructure\Persistence\EloquentNewsMediaAssetRepository as EloquentNewsMediaAssetRepository;
use Modules\Catalog\Infrastructure\Persistence\EloquentNewsRepository as EloquentNewsRepository;
use Modules\Catalog\Infrastructure\Persistence\EloquentSourceRepository as EloquentSourceRepository;
use Modules\Shared\Domain\Contracts\NewsStore as NewsStore;
use Modules\Shared\Domain\Events\NewsEnriched as NewsEnriched;
use Modules\Shared\Domain\Events\SourceFetchFailed as SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded as SourceFetchSucceeded;
use Override as Override;

final class CatalogServiceProvider extends ServiceProvider
{
	#[Override]
	public function register(): void
	{
	}


	public function boot(): void
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Domain/Contracts/NewsMediaAssetRepository.php`

```php
namespace Modules\Catalog\Domain\Contracts;

interface NewsMediaAssetRepository
{
	/**
	 * Синхронизирует оригинальные медиа-ссылки статьи в хранилище ассетов.
	 *
	 * @param  array<int, mixed>  $media
	 */
	public function syncOriginalMedia(int $newsItemId, ?string $imageUrl, array $media): void;


	/**
	 * Возвращает ассеты, которые нужно скачать локально.
	 *
	 * @return list<array{id:int, source_url:string, local_disk:string}>
	 */
	public function getDownloadCandidates(int $newsItemId): array;


	public function markDownloaded(
		int $assetId,
		string $localDisk,
		string $localPath,
		?string $downloadedMimeType,
		int $fileSizeBytes,
		string $checksumSha256,
	): void;


	public function markFailed(int $assetId, string $error): void;


	/**
	 * @param  list<int>  $newsItemIds
	 * @return array<int, array{
	 *   image_url:?string,
	 *   image_url_original:?string,
	 *   image_url_local:?string,
	 *   media:list<array{url:string, type:?string}>,
	 *   media_original:list<array{url:string, type:?string}>,
	 *   media_local:list<array{url:string, type:?string}>
	 * }>
	 */
	public function resolveForNewsItems(array $newsItemIds): array;
}


```
###  Path: `/src/Modules/Catalog/Domain/Contracts/NewsRepository.php`

```php
namespace Modules\Catalog\Domain\Contracts;

use Modules\Shared\Domain\Contracts\NewsStore as NewsStore;

interface NewsRepository extends NewsStore
{
	public function findIdByFingerprint(string $fingerprint): int;


	/**
	 * Возвращает исходные медиа-ссылки из сырой записи новости.
	 *
	 * @return array{image_url: ?string, media: array<int, mixed>}|null
	 */
	public function getMediaUrls(int $id): ?array;
}


```
###  Path: `/src/Modules/Catalog/Domain/Contracts/SourceRepository.php`

```php
namespace Modules\Catalog\Domain\Contracts;

interface SourceRepository
{
	public function updateSuccess(int $sourceId): void;


	public function updateFailure(int $sourceId): void;
}


```
###  Path: `/src/Modules/Catalog/Infrastructure/Persistence/EloquentNewsMediaAssetRepository.php`

```php
namespace Modules\Catalog\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager as DatabaseManager;
use Illuminate\Support\Collection as Collection;
use Illuminate\Support\Facades\Storage as Storage;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository as NewsMediaAssetRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsMediaAsset as NewsMediaAsset;

final readonly class EloquentNewsMediaAssetRepository implements NewsMediaAssetRepository
{
	private const SLOT_COVER = 'cover';
	private const SLOT_GALLERY = 'gallery';
	private const STATUS_PENDING = 'pending';
	private const STATUS_DOWNLOADED = 'downloaded';
	private const STATUS_FAILED = 'failed';

	public function __construct(
		private DatabaseManager $db,
	) {
	}


	public function syncOriginalMedia(int $newsItemId, ?string $imageUrl, array $media): void
	{
	}


	public function getDownloadCandidates(int $newsItemId): array
	{
	}


	public function markDownloaded(
		int $assetId,
		string $localDisk,
		string $localPath,
		?string $downloadedMimeType,
		int $fileSizeBytes,
		string $checksumSha256,
	): void
	{
	}


	public function markFailed(int $assetId, string $error): void
	{
	}


	public function resolveForNewsItems(array $newsItemIds): array
	{
	}


	/**
	 * @param  array<int, mixed>  $media
	 * @return list<array{
	 *   slot:string,
	 *   position:int,
	 *   source_url:string,
	 *   source_mime_type:?string
	 * }>
	 */
	private function buildDesiredAssets(?string $imageUrl, array $media): array
	{
	}


	private function assetKey(string $slot, int $position): string
	{
	}


	/**
	 * @return array{url:string, type:?string}|null
	 */
	private function parseMediaItem(mixed $mediaItem): ?array
	{
	}


	private function resolveLocalUrl(NewsMediaAsset $asset): ?string
	{
	}


	/**
	 * @return array{
	 *   image_url:?string,
	 *   image_url_original:?string,
	 *   image_url_local:?string,
	 *   media:list<array{url:string, type:?string}>,
	 *   media_original:list<array{url:string, type:?string}>,
	 *   media_local:list<array{url:string, type:?string}>
	 * }
	 */
	private function defaultMediaState(): array
	{
	}


	private function normalizeString(mixed $value): ?string
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Infrastructure/Persistence/EloquentNewsRepository.php`

```php
namespace Modules\Catalog\Infrastructure\Persistence;

use Modules\Catalog\Domain\Contracts\NewsRepository as NewsRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem as NewsItem;
use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus as NewsStatus;

final class EloquentNewsRepository implements NewsRepository
{
	public function existsByFingerprint(string $fingerprint): bool
	{
	}


	public function findIdByFingerprint(string $fingerprint): int
	{
	}


	public function storeRaw(RawNewsData $raw): int
	{
	}


	public function storeEnriched(EnrichedNewsData $enriched): void
	{
	}


	public function getMediaUrls(int $id): ?array
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Infrastructure/Persistence/EloquentSourceRepository.php`

```php
namespace Modules\Catalog\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB as DB;
use Modules\Catalog\Domain\Contracts\SourceRepository as SourceRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\Source as Source;

final class EloquentSourceRepository implements SourceRepository
{
	public function updateSuccess(int $sourceId): void
	{
	}


	public function updateFailure(int $sourceId): void
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Infrastructure/Persistence/Models/NewsItem.php`

```php
namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model as Model;

/**
 * @property int $id
 * @property string|null $image_url
 * @property array<int|string, mixed>|null $media
 */
final class NewsItem extends Model
{
	protected $table = 'news_items';
	protected $primaryKey = 'id';

	protected $fillable = [
		'id',
		'source_id',
		'title_original',
		'content_original',
		'title_generated',
		'content_translated',
		'image_url',
		'media',
		'sentiment_score',
		'tags',
		'is_important',
		'status',
		'source_metadata',
		'raw_fingerprint',
		'moderation_reason',
		'published_at',
	];

	/** @var array<string, string> */
	protected $casts = [
		'tags' => 'array',
		'source_metadata' => 'array',
		'is_important' => 'boolean',
		'published_at' => 'datetime',
		'media' => 'array',
	];


	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany<NewsMediaAsset, $this>
	 */
	public function mediaAssets(): \Illuminate\Database\Eloquent\Relations\HasMany
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Infrastructure/Persistence/Models/NewsMediaAsset.php`

```php
namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model as Model;

/**
 * @property int $id
 * @property int $news_item_id
 * @property string $slot
 * @property int $position
 * @property string|null $source_url
 * @property string|null $source_mime_type
 * @property string $local_disk
 * @property string|null $local_path
 * @property string|null $downloaded_mime_type
 * @property int|null $file_size_bytes
 * @property string|null $checksum_sha256
 * @property string $download_status
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $downloaded_at
 */
final class NewsMediaAsset extends Model
{
	protected $table = 'news_media_assets';

	protected $fillable = [
		'news_item_id',
		'slot',
		'position',
		'source_url',
		'source_mime_type',
		'local_disk',
		'local_path',
		'downloaded_mime_type',
		'file_size_bytes',
		'checksum_sha256',
		'download_status',
		'last_error',
		'downloaded_at',
	];

	/** @var array<string, string> */
	protected $casts = ['position' => 'int', 'file_size_bytes' => 'int', 'downloaded_at' => 'datetime'];


	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<NewsItem, $this>
	 */
	public function newsItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
	{
	}
}


```
###  Path: `/src/Modules/Catalog/Infrastructure/Persistence/Models/Source.php`

```php
namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model as Model;

final class Source extends Model
{
	protected $table = 'sources';

	protected $fillable = [
		'name',
		'url',
		'type',
		'language_default',
		'cron_expression',
		'is_active',
		'retry_backoff_state',
		'last_success_at',
		'last_error_at',
		'error_streak',
	];

	/** @var array<string, string> */
	protected $casts = [
		'is_active' => 'boolean',
		'retry_backoff_state' => 'array',
		'last_success_at' => 'datetime',
		'last_error_at' => 'datetime',
	];


	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany<NewsItem, $this>
	 */
	public function newsItems(): \Illuminate\Database\Eloquent\Relations\HasMany
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Application/Actions/FeedFetcherAction.php`

```php
namespace Modules\Crawler\Application\Actions;

use Illuminate\Support\Facades\Log as Log;
use InvalidArgumentException as InvalidArgumentException;
use Modules\Crawler\Application\Services\RawNewsFactory as RawNewsFactory;
use Modules\Crawler\Domain\Contracts\RawPublisher as RawPublisher;
use Modules\Crawler\Domain\Contracts\RssClient as RssClient;
use Modules\Crawler\Domain\Contracts\TelegramClient as TelegramClient;
use Modules\Shared\Domain\Events\SourceFetchFailed as SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded as SourceFetchSucceeded;
use Throwable as Throwable;

final readonly class FeedFetcherAction
{
	public function __construct(
		private RssClient $rssClient,
		private TelegramClient $telegramClient,
		private RawPublisher $publisher,
		private RawNewsFactory $rawNewsFactory,
		private \Modules\Crawler\Domain\Contracts\Deduplicator $deduplicator,
		private \Illuminate\Contracts\Events\Dispatcher $events,
	) {
	}


	/**
	 * @param  array{id:int,url:string,type:string,language_default:string|null}  $source
	 * @return array{total: int, new: int, duplicates: int}
	 */
	public function __invoke(
		array $source,
		?\Carbon\Carbon $dateFrom = null,
		?\Carbon\Carbon $dateTo = null,
		?int $limit = null,
	): array
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Application/Jobs/FetchSourceJob.php`

```php
namespace Modules\Crawler\Application\Jobs;

use Illuminate\Bus\Queueable as Queueable;
use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable as Dispatchable;
use Illuminate\Queue\InteractsWithQueue as InteractsWithQueue;
use Illuminate\Queue\SerializesModels as SerializesModels;
use Modules\Crawler\Application\Actions\FeedFetcherAction as FeedFetcherAction;

final class FetchSourceJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * @param  array{id:int,url:string,type:string,language_default:string|null}  $source
	 */
	public function __construct(
		public readonly array $source,
		public readonly ?\Carbon\Carbon $dateFrom = null,
		public readonly ?\Carbon\Carbon $dateTo = null,
		public readonly ?int $limit = null,
	) {
	}


	public function handle(FeedFetcherAction $fetchFeed): void
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Application/Jobs/ProcessNewsJob.php`

```php
namespace Modules\Crawler\Application\Jobs;

use Illuminate\Bus\Queueable as Queueable;
use Illuminate\Contracts\Events\Dispatcher as Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable as Dispatchable;
use Illuminate\Queue\InteractsWithQueue as InteractsWithQueue;
use Illuminate\Queue\SerializesModels as SerializesModels;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;
use Modules\Shared\Domain\Events\RawNewsCreated as RawNewsCreated;

final class ProcessNewsJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	public int $tries = 5;

	/** @var array<int, int> */
	public array $backoff = [5, 15, 60, 120, 300];


	public function __construct(
		public readonly RawNewsData $raw,
	) {
	}


	public function handle(Dispatcher $events): void
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Application/Services/RawNewsFactory.php`

```php
namespace Modules\Crawler\Application\Services;

use Carbon\CarbonImmutable as CarbonImmutable;
use Modules\Shared\Application\Services\FingerprintGenerator as FingerprintGenerator;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final readonly class RawNewsFactory
{
	public function __construct(
		private FingerprintGenerator $fingerprintGenerator,
	) {
	}


	/**
	 * @param  array{id:int,url:string,language_default:?string}  $source
	 * @param  array<string, mixed>  $item
	 */
	public function fromRss(array $source, array $item): RawNewsData
	{
	}
}


```
###  Path: `/src/Modules/Crawler/CrawlerServiceProvider.php`

```php
namespace Modules\Crawler;

use Illuminate\Support\ServiceProvider as ServiceProvider;
use Modules\Crawler\Application\Actions\FeedFetcherAction as FeedFetcherAction;
use Modules\Crawler\Application\Services\RawNewsFactory as RawNewsFactory;
use Modules\Crawler\Domain\Contracts\RawPublisher as RawPublisherContract;
use Modules\Crawler\Domain\Contracts\RssClient as RssClientContract;
use Modules\Crawler\Domain\Contracts\TelegramClient as TelegramClientContract;
use Modules\Crawler\Infrastructure\Http\RssClient as RssClient;
use Modules\Crawler\Infrastructure\Http\RssConnector as RssConnector;
use Modules\Crawler\Infrastructure\Http\TelegramClient as TelegramClient;
use Modules\Crawler\Infrastructure\Messaging\RawPublisher as RawPublisher;
use Modules\Shared\Application\Services\FingerprintGenerator as FingerprintGenerator;
use Override as Override;

final class CrawlerServiceProvider extends ServiceProvider
{
	#[Override]
	public function register(): void
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Domain/Contracts/ApiParser.php`

```php
namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection as Collection;

interface ApiParser
{
	/**
	 * @param  array<string, mixed>  $data
	 * @return Collection<int, array<string, mixed>>
	 */
	public function parse(array $data): Collection;


	public function supports(string $source): bool;
}


```
###  Path: `/src/Modules/Crawler/Domain/Contracts/Deduplicator.php`

```php
namespace Modules\Crawler\Domain\Contracts;

interface Deduplicator
{
	public function exists(string $fingerprint): bool;
}


```
###  Path: `/src/Modules/Crawler/Domain/Contracts/RawPublisher.php`

```php
namespace Modules\Crawler\Domain\Contracts;

use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

interface RawPublisher
{
	public function publish(RawNewsData $raw): void;
}


```
###  Path: `/src/Modules/Crawler/Domain/Contracts/RssClient.php`

```php
namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection as Collection;

interface RssClient
{
	/**
	 * @return Collection<int, array<string, mixed>>
	 */
	public function fetch(
		string $url,
		?\Carbon\Carbon $dateFrom = null,
		?\Carbon\Carbon $dateTo = null,
		?int $limit = null,
	): Collection;
}


```
###  Path: `/src/Modules/Crawler/Domain/Contracts/RssParser.php`

```php
namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection as Collection;

interface RssParser
{
	/**
	 * @return Collection<int, array<string, mixed>>
	 */
	public function parse(string $xmlBody): Collection;


	public function supports(string $url): bool;
}


```
###  Path: `/src/Modules/Crawler/Domain/Contracts/TelegramClient.php`

```php
namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection as Collection;

interface TelegramClient
{
	/**
	 * @return Collection<int, array<string, mixed>>
	 */
	public function fetch(
		string $channel,
		?\Carbon\Carbon $dateFrom = null,
		?\Carbon\Carbon $dateTo = null,
		?int $limit = null,
	): Collection;
}


```
###  Path: `/src/Modules/Crawler/Domain/Contracts/TelegramParser.php`

```php
namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection as Collection;

interface TelegramParser
{
	/**
	 * @param  array{channel: string}  $context
	 * @return Collection<int, array<string, mixed>>
	 */
	public function parse(string $htmlBody, array $context): Collection;


	public function supports(string $channel): bool;
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Http/RssClient.php`

```php
namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection as Collection;
use InvalidArgumentException as InvalidArgumentException;
use Modules\Crawler\Domain\Contracts\RssClient as RssClientContract;
use Modules\Crawler\Infrastructure\Services\RssParserResolver as RssParserResolver;
use Saloon\Enums\Method as Method;

final readonly class RssClient implements RssClientContract
{
	public function __construct(
		private RssConnector $connector,
		private RssParserResolver $resolver,
	) {
	}


	/**
	 * @return Collection<int, array<string, mixed>>
	 */
	public function fetch(
		string $url,
		?\Carbon\Carbon $dateFrom = null,
		?\Carbon\Carbon $dateTo = null,
		?int $limit = null,
	): Collection
	{
	}


	private function assertAllowedHost(string $url): void
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Http/RssConnector.php`

```php
namespace Modules\Crawler\Infrastructure\Http;

use Saloon\Http\Connector as Connector;

final class RssConnector extends Connector
{
	public function resolveBaseUrl(): string
	{
	}


	public function defaultConfig(): array
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Http/TelegramClient.php`

```php
namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection as Collection;
use Illuminate\Support\Facades\Log as Log;
use InvalidArgumentException as InvalidArgumentException;
use Modules\Crawler\Domain\Contracts\TelegramClient as TelegramClientContract;
use Modules\Crawler\Infrastructure\Services\TelegramParserResolver as TelegramParserResolver;
use Saloon\Enums\Method as Method;
use Saloon\Http\Response as Response;

final readonly class TelegramClient implements TelegramClientContract
{
	public function __construct(
		private RssConnector $connector,
		private TelegramParserResolver $resolver,
	) {
	}


	public function fetch(
		string $channel,
		?\Carbon\Carbon $dateFrom = null,
		?\Carbon\Carbon $dateTo = null,
		?int $limit = null,
	): Collection
	{
	}


	private function request(string $url, bool $isAjax = false): Response
	{
	}


	private function resolveChannelName(string $channel): string
	{
	}


	private function normalizeChannelName(string $name): string
	{
	}


	private function assertAllowedHost(string $url): void
	{
	}


	private function extractPostId(string $externalId): ?int
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Messaging/RawPublisher.php`

```php
namespace Modules\Crawler\Infrastructure\Messaging;

use Modules\Crawler\Application\Jobs\ProcessNewsJob as ProcessNewsJob;
use Modules\Crawler\Domain\Contracts\RawPublisher as RawPublisherContract;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final readonly class RawPublisher implements RawPublisherContract
{
	public function __construct()
	{
	}


	public function publish(RawNewsData $raw): void
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/AlJazeeraRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Override as Override;

final class AlJazeeraRssParser extends DefaultRssParser
{
	#[Override]
	public function supports(string $url): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/DefaultRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Illuminate\Support\Collection as Collection;
use Modules\Crawler\Domain\Contracts\RssParser as RssParser;
use SimpleXMLElement as SimpleXMLElement;

class DefaultRssParser implements RssParser
{
	/**
	 * @return Collection<int, array<string, mixed>>
	 */
	public function parse(string $xmlBody): Collection
	{
	}


	public function supports(string $url): bool
	{
	}


	/**
	 * @return array<string, mixed>
	 */
	protected function mapItem(SimpleXMLElement $item): array
	{
	}


	/**
	 * @return array<string, mixed>
	 */
	protected function mapAtomEntry(SimpleXMLElement $entry): array
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/HabrRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Override as Override;
use SimpleXMLElement as SimpleXMLElement;

final class HabrRssParser extends DefaultRssParser
{
	#[Override]
	public function supports(string $url): bool
	{
	}


	#[Override]
	protected function mapItem(SimpleXMLElement $item): array
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/HackerNewsRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Override as Override;

final class HackerNewsRssParser extends DefaultRssParser
{
	#[Override]
	public function supports(string $url): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/MedicalXpressRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Override as Override;

final class MedicalXpressRssParser extends DefaultRssParser
{
	#[Override]
	public function supports(string $url): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/ScienceDailyRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Override as Override;

final class ScienceDailyRssParser extends DefaultRssParser
{
	#[Override]
	public function supports(string $url): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/TechCrunchRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Override as Override;

final class TechCrunchRssParser extends DefaultRssParser
{
	#[Override]
	public function supports(string $url): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/Telegram/DefaultTelegramParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers\Telegram;

use DOMDocument as DOMDocument;
use DOMNode as DOMNode;
use DOMXPath as DOMXPath;
use Illuminate\Support\Collection as Collection;
use Modules\Crawler\Domain\Contracts\TelegramParser as TelegramParser;

class DefaultTelegramParser implements TelegramParser
{
	/**
	 * @param  array{channel: string}  $context
	 * @return Collection<int, array<string, mixed>>
	 */
	public function parse(string $htmlBody, array $context): Collection
	{
	}


	public function supports(string $channel): bool
	{
	}


	/**
	 * @return array<string, mixed>
	 */
	protected function mapItem(DOMXPath $xpath, DOMNode $node, string $channel): array
	{
	}


	/**
	 * @return array<int, array{url:string,type:string|null}>
	 */
	protected function extractMedia(DOMXPath $xpath, DOMNode $node): array
	{
	}


	/**
	 * @return array<int, string>
	 */
	protected function extractLinks(DOMXPath $xpath, DOMNode $node): array
	{
	}


	protected function extractPostId(string $externalId): ?int
	{
	}


	protected function extractTitleFromNode(DOMXPath $xpath, DOMNode $node, string $content, string $externalId): string
	{
	}


	protected function extractMainMessageText(DOMXPath $xpath, DOMNode $node): string
	{
	}


	protected function extractTitle(string $content, string $externalId): string
	{
	}


	protected function normalizeWhitespace(string $value): string
	{
	}


	protected function normalizeLink(string $link): string
	{
	}


	protected function buildLinkFromExternalId(string $externalId): string
	{
	}


	protected function extractUrlFromStyle(string $style): string
	{
	}


	protected function evalString(DOMXPath $xpath, DOMNode $node, string $expression): string
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/Telegram/ToporLiveTelegramParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers\Telegram;

use Override as Override;

final class ToporLiveTelegramParser extends DefaultTelegramParser
{
	#[Override]
	public function supports(string $channel): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Parsers/TheVergeRssParser.php`

```php
namespace Modules\Crawler\Infrastructure\Parsers;

use Override as Override;

final class TheVergeRssParser extends DefaultRssParser
{
	#[Override]
	public function supports(string $url): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Services/DbDeduplicator.php`

```php
namespace Modules\Crawler\Infrastructure\Services;

use Illuminate\Support\Facades\DB as DB;
use Modules\Crawler\Domain\Contracts\Deduplicator as Deduplicator;

final class DbDeduplicator implements Deduplicator
{
	public function exists(string $fingerprint): bool
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Services/RssParserResolver.php`

```php
namespace Modules\Crawler\Infrastructure\Services;

use Modules\Crawler\Domain\Contracts\RssParser as RssParser;
use Modules\Crawler\Infrastructure\Parsers\DefaultRssParser as DefaultRssParser;

final readonly class RssParserResolver
{
	/**
	 * @param  iterable<RssParser>  $parsers
	 */
	public function __construct(
		private iterable $parsers,
		private DefaultRssParser $defaultParser,
	) {
	}


	public function resolve(string $url): RssParser
	{
	}
}


```
###  Path: `/src/Modules/Crawler/Infrastructure/Services/TelegramParserResolver.php`

```php
namespace Modules\Crawler\Infrastructure\Services;

use Modules\Crawler\Domain\Contracts\TelegramParser as TelegramParser;
use Modules\Crawler\Infrastructure\Parsers\Telegram\DefaultTelegramParser as DefaultTelegramParser;

final readonly class TelegramParserResolver
{
	/**
	 * @param  iterable<TelegramParser>  $parsers
	 */
	public function __construct(
		private iterable $parsers,
		private DefaultTelegramParser $defaultParser,
	) {
	}


	public function resolve(string $channel): TelegramParser
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Application/Actions/ListNewsAction.php`

```php
namespace Modules\Delivery\Application\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginator;
use Modules\Delivery\Domain\Contracts\NewsFeedReader as NewsFeedReader;
use Modules\Delivery\Domain\DTO\NewsFeedFilters as NewsFeedFilters;

final readonly class ListNewsAction
{
	public function __construct(
		private NewsFeedReader $reader,
	) {
	}


	/**
	 * @return CursorPaginator<int, array<string, mixed>>
	 */
	public function __invoke(NewsFeedFilters $filters, int $perPage = 20, ?string $cursor = null): CursorPaginator
	{
	}


	public function count(NewsFeedFilters $filters): int
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Application/Actions/ListPublicSourcesAction.php`

```php
namespace Modules\Delivery\Application\Actions;

use Modules\Delivery\Domain\Contracts\SourcePublicReader as SourcePublicReader;

final readonly class ListPublicSourcesAction
{
	public function __construct(
		private SourcePublicReader $reader,
	) {
	}


	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function __invoke(): array
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Application/Actions/ListSourcesAction.php`

```php
namespace Modules\Delivery\Application\Actions;

use Modules\Delivery\Domain\Contracts\SourceAdminReader as SourceAdminReader;

final readonly class ListSourcesAction
{
	public function __construct(
		private SourceAdminReader $reader,
	) {
	}


	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function __invoke(): array
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Application/Actions/ShowNewsAction.php`

```php
namespace Modules\Delivery\Application\Actions;

use Modules\Delivery\Domain\Contracts\NewsFeedReader as NewsFeedReader;

final readonly class ShowNewsAction
{
	public function __construct(
		private NewsFeedReader $reader,
	) {
	}


	/**
	 * @return array<string, mixed>|null
	 */
	public function __invoke(string $id): ?array
	{
	}
}


```
###  Path: `/src/Modules/Delivery/DeliveryServiceProvider.php`

```php
namespace Modules\Delivery;

use Illuminate\Support\ServiceProvider as ServiceProvider;
use Modules\Delivery\Domain\Contracts\NewsFeedReader as NewsFeedReader;
use Modules\Delivery\Domain\Contracts\NewsMediaResolver as NewsMediaResolver;
use Modules\Delivery\Domain\Contracts\SourceAdminReader as SourceAdminReader;
use Modules\Delivery\Domain\Contracts\SourcePublicReader as SourcePublicReader;
use Modules\Delivery\Infrastructure\Persistence\DbNewsMediaResolver as DbNewsMediaResolver;
use Modules\Delivery\Infrastructure\Persistence\EloquentNewsFeedReader as EloquentNewsFeedReader;
use Modules\Delivery\Infrastructure\Persistence\EloquentSourceAdminReader as EloquentSourceAdminReader;
use Modules\Delivery\Infrastructure\Persistence\EloquentSourcePublicReader as EloquentSourcePublicReader;
use Override as Override;

final class DeliveryServiceProvider extends ServiceProvider
{
	#[Override]
	public function register(): void
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Domain/Contracts/NewsFeedReader.php`

```php
namespace Modules\Delivery\Domain\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginator;
use Modules\Delivery\Domain\DTO\NewsFeedFilters as NewsFeedFilters;

interface NewsFeedReader
{
	/**
	 * @return CursorPaginator<int, array<string, mixed>>
	 */
	public function paginatePublished(NewsFeedFilters $filters, int $perPage, ?string $cursor): CursorPaginator;


	/**
	 * @return array<string, mixed>|null
	 */
	public function findPublishedById(string $id): ?array;


	public function count(NewsFeedFilters $filters): int;
}


```
###  Path: `/src/Modules/Delivery/Domain/Contracts/NewsMediaResolver.php`

```php
namespace Modules\Delivery\Domain\Contracts;

interface NewsMediaResolver
{
	/**
	 * @param  list<int>  $newsItemIds
	 * @return array<int, array{
	 *   image_url:?string,
	 *   image_url_original:?string,
	 *   image_url_local:?string,
	 *   media:list<array{url:string, type:?string}>,
	 *   media_original:list<array{url:string, type:?string}>,
	 *   media_local:list<array{url:string, type:?string}>
	 * }>
	 */
	public function resolveForNewsItems(array $newsItemIds): array;
}


```
###  Path: `/src/Modules/Delivery/Domain/Contracts/SourceAdminReader.php`

```php
namespace Modules\Delivery\Domain\Contracts;

interface SourceAdminReader
{
	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list(): array;
}


```
###  Path: `/src/Modules/Delivery/Domain/Contracts/SourcePublicReader.php`

```php
namespace Modules\Delivery\Domain\Contracts;

interface SourcePublicReader
{
	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function listActive(): array;
}


```
###  Path: `/src/Modules/Delivery/Domain/DTO/NewsFeedFilters.php`

```php
namespace Modules\Delivery\Domain\DTO;

use Carbon\CarbonImmutable as CarbonImmutable;

final readonly class NewsFeedFilters
{
	public function __construct(
		public ?string $category = null,
		public ?int $sentimentMin = null,
		public ?int $sentimentMax = null,
		public ?bool $important = null,
		public ?CarbonImmutable $dateFrom = null,
		public ?CarbonImmutable $dateTo = null,
		public ?string $query = null,
		public ?int $sourceId = null,
	) {
	}
}


```
###  Path: `/src/Modules/Delivery/Infrastructure/Persistence/DbNewsMediaResolver.php`

```php
namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager as DatabaseManager;
use Illuminate\Support\Facades\Storage as Storage;
use Modules\Delivery\Domain\Contracts\NewsMediaResolver as NewsMediaResolver;

final readonly class DbNewsMediaResolver implements NewsMediaResolver
{
	private const SLOT_COVER = 'cover';

	public function __construct(
		private DatabaseManager $db,
	) {
	}


	public function resolveForNewsItems(array $newsItemIds): array
	{
	}


	private function resolveLocalUrl(string $diskName, ?string $localPath): ?string
	{
	}


	/**
	 * @return array{
	 *   image_url:?string,
	 *   image_url_original:?string,
	 *   image_url_local:?string,
	 *   media:list<array{url:string, type:?string}>,
	 *   media_original:list<array{url:string, type:?string}>,
	 *   media_local:list<array{url:string, type:?string}>
	 * }
	 */
	private function defaultMediaState(): array
	{
	}


	private function normalizeString(mixed $value): ?string
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Infrastructure/Persistence/EloquentNewsFeedReader.php`

```php
namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginator;
use Illuminate\Database\DatabaseManager as DatabaseManager;
use Illuminate\Database\Query\Builder as Builder;
use Illuminate\Pagination\Cursor as Cursor;
use Modules\Delivery\Domain\Contracts\NewsFeedReader as NewsFeedReader;
use Modules\Delivery\Domain\Contracts\NewsMediaResolver as NewsMediaResolver;
use Modules\Delivery\Domain\DTO\NewsFeedFilters as NewsFeedFilters;
use Modules\Shared\Domain\Enum\NewsStatus as NewsStatus;

final readonly class EloquentNewsFeedReader implements NewsFeedReader
{
	public function __construct(
		private DatabaseManager $db,
		private NewsMediaResolver $mediaAssets,
	) {
	}


	/**
	 * @return CursorPaginator<int, array<string, mixed>>
	 */
	public function paginatePublished(NewsFeedFilters $filters, int $perPage, ?string $cursor): CursorPaginator
	{
	}


	public function findPublishedById(string $id): ?array
	{
	}


	public function count(NewsFeedFilters $filters): int
	{
	}


	private function baseQuery(NewsFeedFilters $filters): Builder
	{
	}


	private function decodeCursor(?string $cursor): ?Cursor
	{
	}


	/**
	 * @param array{
	 *   image_url:?string,
	 *   image_url_original:?string,
	 *   image_url_local:?string,
	 *   media:list<array{url:string, type:?string}>,
	 *   media_original:list<array{url:string, type:?string}>,
	 *   media_local:list<array{url:string, type:?string}>
	 * }|null $resolvedMedia
	 * @return array<string, mixed>
	 */
	private function mapRow(object $row, ?array $resolvedMedia): array
	{
	}


	/**
	 * @return array<int|string, mixed>
	 */
	private function decodeJsonArray(mixed $value): array
	{
	}


	/**
	 * @param  array<int|string, mixed>  $media
	 * @return list<array{url:string, type:?string}>
	 */
	private function normalizeMediaItems(array $media): array
	{
	}


	/**
	 * @param  array<int, object>  $rows
	 * @return list<int>
	 */
	private function extractNewsItemIds(array $rows): array
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Infrastructure/Persistence/EloquentSourceAdminReader.php`

```php
namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager as DatabaseManager;
use Modules\Delivery\Domain\Contracts\SourceAdminReader as SourceAdminReader;

final readonly class EloquentSourceAdminReader implements SourceAdminReader
{
	public function __construct(
		private DatabaseManager $db,
	) {
	}


	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list(): array
	{
	}


	/**
	 * @return array<string, mixed>
	 */
	private function mapSourceRow(object $source): array
	{
	}
}


```
###  Path: `/src/Modules/Delivery/Infrastructure/Persistence/EloquentSourcePublicReader.php`

```php
namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager as DatabaseManager;
use Modules\Delivery\Domain\Contracts\SourcePublicReader as SourcePublicReader;

final readonly class EloquentSourcePublicReader implements SourcePublicReader
{
	public function __construct(
		private DatabaseManager $db,
	) {
	}


	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function listActive(): array
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Listeners/ProcessRawNewsListener.php`

```php
namespace Modules\Intelligence\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueue;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline as NewsProcessingPipeline;
use Modules\Shared\Domain\Events\RawNewsCreated as RawNewsCreated;

final class ProcessRawNewsListener implements ShouldQueue
{
	/** Попытки выполнения */
	public int $tries = 5;

	/**
	 * Бекофф (ожидание между попытками)
	 *
	 * @var array<int, int>
	 */
	public array $backoff = [5, 15, 60, 120, 300];


	public function __construct(
		private readonly NewsProcessingPipeline $pipeline,
	) {
	}


	public function viaQueue(): string
	{
	}


	public function handle(RawNewsCreated $event): void
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/NewsProcessingPipeline.php`

```php
namespace Modules\Intelligence\Application\Pipeline;

use Modules\Intelligence\Application\Pipeline\Steps\PipelineStep as PipelineStep;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher as EnrichedPublisher;
use Modules\Shared\Domain\Contracts\NewsStore as NewsStore;
use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;
use Modules\Shared\Domain\Events\NewsEnriched as NewsEnriched;

final readonly class NewsProcessingPipeline
{
	/**
	 * @param PipelineStep[] $steps
	 */
	public function __construct(
		private array $steps,
		private EnrichedPublisher $publisher,
		private NewsStore $news,
	) {
	}


	public function handle(RawNewsData $raw): void
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/SkipMessageException.php`

```php
namespace Modules\Intelligence\Application\Pipeline;

use RuntimeException as RuntimeException;

/**
 * Thrown when a pipeline step determines that the message
 * should be silently skipped (e.g. duplicate detection).
 */
final class SkipMessageException extends RuntimeException
{
	public function __construct(string $reason = 'skipped')
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/AntiClickbaitStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final class AntiClickbaitStep implements PipelineStep
{
	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/ClassifyStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\Classifier as Classifier;
use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final readonly class ClassifyStep implements PipelineStep
{
	public function __construct(
		private Classifier $classifier,
	) {
	}


	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/DeduplicateStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Application\Pipeline\SkipMessageException as SkipMessageException;
use Modules\Shared\Domain\Contracts\NewsStore as NewsStore;
use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final readonly class DeduplicateStep implements PipelineStep
{
	public function __construct(
		private NewsStore $news,
	) {
	}


	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/FinalizeStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus as NewsStatus;

final class FinalizeStep implements PipelineStep
{
	public function process(RawNewsData|EnrichedNewsData $input): EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/ImportanceStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final class ImportanceStep implements PipelineStep
{
	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/LanguageDetectStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final class LanguageDetectStep implements PipelineStep
{
	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/ModerationStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus as NewsStatus;

final class ModerationStep implements PipelineStep
{
	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/PipelineStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

interface PipelineStep
{
	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData;
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/SentimentStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer as SentimentAnalyzer;
use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final readonly class SentimentStep implements PipelineStep
{
	public function __construct(
		private SentimentAnalyzer $sentiment,
	) {
	}


	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Application/Pipeline/Steps/TranslateStep.php`

```php
namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final readonly class TranslateStep implements PipelineStep
{
	public function __construct(
		private \Modules\Intelligence\Domain\Contracts\Translator $translator,
	) {
	}


	public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Domain/Contracts/Classifier.php`

```php
namespace Modules\Intelligence\Domain\Contracts;

interface Classifier
{
	/**
	 * @return array{category:string,tags:array<int, string>}
	 */
	public function classify(string $content): array;
}


```
###  Path: `/src/Modules/Intelligence/Domain/Contracts/EnrichedPublisher.php`

```php
namespace Modules\Intelligence\Domain\Contracts;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;

interface EnrichedPublisher
{
	public function publish(EnrichedNewsData $enriched): void;
}


```
###  Path: `/src/Modules/Intelligence/Domain/Contracts/SentimentAnalyzer.php`

```php
namespace Modules\Intelligence\Domain\Contracts;

interface SentimentAnalyzer
{
	public function score(string $content): int;
}


```
###  Path: `/src/Modules/Intelligence/Domain/Contracts/TitleGenerator.php`

```php
namespace Modules\Intelligence\Domain\Contracts;

interface TitleGenerator
{
	public function generate(string $content, string $originalTitle): string;
}


```
###  Path: `/src/Modules/Intelligence/Domain/Contracts/Translator.php`

```php
namespace Modules\Intelligence\Domain\Contracts;

interface Translator
{
	public function translate(string $text, string $targetLanguage, string $sourceLanguage): string;
}


```
###  Path: `/src/Modules/Intelligence/Infrastructure/LLM/HeuristicTranslator.php`

```php
namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\Translator as Translator;

final class HeuristicTranslator implements Translator
{
	public function translate(string $text, string $targetLanguage, string $sourceLanguage): string
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Infrastructure/LLM/KeywordClassifier.php`

```php
namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\Classifier as Classifier;

final class KeywordClassifier implements Classifier
{
	/** @var array<string, array{category:string,tags:array<int,string>}> */
	private array $map = [
		'laravel' => ['category' => 'IT', 'tags' => ['it', 'laravel', 'php']],
		'php' => ['category' => 'IT', 'tags' => ['it', 'php']],
		'ai' => ['category' => 'IT', 'tags' => ['it', 'ai']],
		'econom' => ['category' => 'Экономика', 'tags' => ['economy']],
		'market' => ['category' => 'Экономика', 'tags' => ['markets']],
		'polit' => ['category' => 'Политика', 'tags' => ['politics']],
		'crime' => ['category' => 'Криминал', 'tags' => ['crime']],
		'медицин' => ['category' => 'Медицина', 'tags' => ['medicine']],
		'кримин' => ['category' => 'Криминал', 'tags' => ['crime']],
	];


	public function classify(string $content): array
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Infrastructure/LLM/KeywordSentimentAnalyzer.php`

```php
namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer as SentimentAnalyzer;

final class KeywordSentimentAnalyzer implements SentimentAnalyzer
{
	/** @var array<int, string> */
	private array $positiveWords = [
		'good',
		'great',
		'excellent',
		'growth',
		'success',
		'улучш',
		'рост',
		'успех',
	];

	/** @var array<int, string> */
	private array $negativeWords = ['bad', 'crisis', 'drop', 'loss', 'decline', 'паден', 'кризис', 'убыт'];


	public function score(string $content): int
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Infrastructure/LLM/ObjectivelyTitleGenerator.php`

```php
namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\TitleGenerator as TitleGenerator;

final class ObjectivelyTitleGenerator implements TitleGenerator
{
	/** @var array<int, string> */
	private array $clickbaitTokens = ['шок', 'сенсац', 'не поверите', '!!!', 'срочно', 'breaking'];


	public function generate(string $content, string $originalTitle): string
	{
	}


	private function truncate(string $value, int $limit = 140): string
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/Infrastructure/Messaging/EnrichedPublisher.php`

```php
namespace Modules\Intelligence\Infrastructure\Messaging;

use Modules\Intelligence\Domain\Contracts\EnrichedPublisher as EnrichedPublisherContract;
use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\Enum\NewsStatus as NewsStatus;
use PhpAmqpLib\Connection\AMQPStreamConnection as AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage as AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable as AMQPTable;

final readonly class EnrichedPublisher implements EnrichedPublisherContract
{
	public function __construct(
		private AMQPStreamConnection $connection,
		private string $exchange = 'news_flow',
		private string $readyRoutingKey = 'enriched.ready',
		private string $importantRoutingKey = 'enriched.ready.important',
		private string $rejectedRoutingKey = 'enriched.rejected',
	) {
	}


	public function publish(EnrichedNewsData $enriched): void
	{
	}
}


```
###  Path: `/src/Modules/Intelligence/IntelligenceServiceProvider.php`

```php
namespace Modules\Intelligence;

use Illuminate\Support\ServiceProvider as ServiceProvider;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline as NewsProcessingPipeline;
use Modules\Intelligence\Application\Pipeline\Steps\AntiClickbaitStep as AntiClickbaitStep;
use Modules\Intelligence\Application\Pipeline\Steps\ClassifyStep as ClassifyStep;
use Modules\Intelligence\Application\Pipeline\Steps\DeduplicateStep as DeduplicateStep;
use Modules\Intelligence\Application\Pipeline\Steps\FinalizeStep as FinalizeStep;
use Modules\Intelligence\Application\Pipeline\Steps\ImportanceStep as ImportanceStep;
use Modules\Intelligence\Application\Pipeline\Steps\LanguageDetectStep as LanguageDetectStep;
use Modules\Intelligence\Application\Pipeline\Steps\ModerationStep as ModerationStep;
use Modules\Intelligence\Application\Pipeline\Steps\SentimentStep as SentimentStep;
use Modules\Intelligence\Application\Pipeline\Steps\TranslateStep as TranslateStep;
use Modules\Intelligence\Domain\Contracts\Classifier as Classifier;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher as EnrichedPublisherContract;
use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer as SentimentAnalyzer;
use Modules\Intelligence\Domain\Contracts\TitleGenerator as TitleGenerator;
use Modules\Intelligence\Domain\Contracts\Translator as Translator;
use Modules\Intelligence\Infrastructure\LLM\HeuristicTranslator as HeuristicTranslator;
use Modules\Intelligence\Infrastructure\LLM\KeywordClassifier as KeywordClassifier;
use Modules\Intelligence\Infrastructure\LLM\KeywordSentimentAnalyzer as KeywordSentimentAnalyzer;
use Modules\Intelligence\Infrastructure\LLM\ObjectivelyTitleGenerator as ObjectivelyTitleGenerator;
use Modules\Intelligence\Infrastructure\Messaging\EnrichedPublisher as EnrichedPublisher;
use Modules\Shared\Domain\Contracts\NewsStore as NewsStore;
use Override as Override;

final class IntelligenceServiceProvider extends ServiceProvider
{
	#[Override]
	public function register(): void
	{
	}


	public function boot(): void
	{
	}
}


```
###  Path: `/src/Modules/Shared/Application/Services/FingerprintGenerator.php`

```php
namespace Modules\Shared\Application\Services;

use Carbon\CarbonImmutable as CarbonImmutable;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final class FingerprintGenerator
{
	public function generate(
		int $sourceId,
		string $link,
		string $publishedAt,
		?string $title = null,
		?string $externalId = null,
	): string
	{
	}


	public function attachFingerprint(RawNewsData $raw): RawNewsData
	{
	}


	private function normalizeTitle(string $title): string
	{
	}


	private function normalizeLink(string $link): string
	{
	}
}


```
###  Path: `/src/Modules/Shared/Domain/Contracts/NewsStore.php`

```php
namespace Modules\Shared\Domain\Contracts;

use Modules\Shared\Domain\DTO\EnrichedNewsData as EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

interface NewsStore
{
	public function existsByFingerprint(string $fingerprint): bool;


	public function storeRaw(RawNewsData $raw): int;


	public function storeEnriched(EnrichedNewsData $enriched): void;
}


```
###  Path: `/src/Modules/Shared/Domain/DTO/EnrichedNewsData.php`

```php
namespace Modules\Shared\Domain\DTO;

use Modules\Shared\Domain\Enum\NewsStatus as NewsStatus;

final readonly class EnrichedNewsData
{
	/**
	 * @param  array<int, string>  $tags
	 */
	public function __construct(
		public int $rawId,
		public ?string $titleGenerated,
		public string $contentTranslated,
		public int $sentiment,
		public string $category,
		public array $tags,
		public bool $importance,
		public NewsStatus $status,
		public ?string $moderationReason,
		public string $fingerprint,
	) {
	}


	/**
	 * Копия с подменой выбранных полей.
	 *
	 * @param array{
	 *     rawId?: int,
	 *     titleGenerated?: ?string,
	 *     contentTranslated?: string,
	 *     sentiment?: int,
	 *     category?: string,
	 *     tags?: array<int, string>,
	 *     importance?: bool,
	 *     status?: NewsStatus,
	 *     moderationReason?: ?string,
	 *     fingerprint?: string
	 * } $overrides
	 */
	public function with(array $overrides): self
	{
	}
}


```
###  Path: `/src/Modules/Shared/Domain/DTO/RawNewsData.php`

```php
namespace Modules\Shared\Domain\DTO;

use Carbon\CarbonImmutable as CarbonImmutable;

/**
 * Стандартизированное сырьё из источника.
 */
final readonly class RawNewsData
{
	/**
	 * @param  array<string, mixed>  $metadata
	 */
	public function __construct(
		public int $sourceId,
		public ?string $externalId,
		public string $title,
		public string $link,
		public string $content,
		public CarbonImmutable $publishedAt,
		public string $language,
		public array $metadata,
		public ?string $imageUrl,
		/** @var array<int, array{url:string,type:?string}> */
		public array $media,
		public string $fingerprint,
		public ?int $rawId = null,
	) {
	}


	/**
	 * Копия с подменой выбранных полей.
	 *
	 * @param array{
	 *     sourceId?: int,
	 *     externalId?: ?string,
	 *     title?: string,
	 *     link?: string,
	 *     content?: string,
	 *     publishedAt?: CarbonImmutable,
	 *     language?: string,
	 *     metadata?: array<string,mixed>,
	 *     imageUrl?: ?string,
	 *     media?: array<int, array{url:string,type:?string}>,
	 *     fingerprint?: string,
	 *     rawId?: ?int
	 * } $overrides
	 */
	public function with(array $overrides): self
	{
	}
}


```
###  Path: `/src/Modules/Shared/Domain/Enum/NewsStatus.php`

```php
namespace Modules\Shared\Domain\Enum;

enum NewsStatus: string
{
	case PROCESSING = 'processing';
	case PUBLISHED = 'published';
	case REJECTED = 'rejected';
}


```
###  Path: `/src/Modules/Shared/Domain/Events/NewsEnriched.php`

```php
namespace Modules\Shared\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable as Dispatchable;

final readonly class NewsEnriched
{
	use Dispatchable;

	public function __construct(
		public int $rawId,
	) {
	}
}


```
###  Path: `/src/Modules/Shared/Domain/Events/RawNewsCreated.php`

```php
namespace Modules\Shared\Domain\Events;

use Modules\Shared\Domain\DTO\RawNewsData as RawNewsData;

final readonly class RawNewsCreated
{
	public function __construct(
		public RawNewsData $raw,
	) {
	}
}


```
###  Path: `/src/Modules/Shared/Domain/Events/SourceFetchFailed.php`

```php
namespace Modules\Shared\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable as Dispatchable;

final readonly class SourceFetchFailed
{
	use Dispatchable;

	public function __construct(
		public int $sourceId,
		public string $errorMessage,
	) {
	}
}


```
###  Path: `/src/Modules/Shared/Domain/Events/SourceFetchSucceeded.php`

```php
namespace Modules\Shared\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable as Dispatchable;

final readonly class SourceFetchSucceeded
{
	use Dispatchable;

	public function __construct(
		public int $sourceId,
		public int $itemCount,
	) {
	}
}


```
---
**File Statistics**
- **Size**: 76.35 KB
- **Lines**: 3432
File: `../docs/PROJECT_INTERFACE.md`
