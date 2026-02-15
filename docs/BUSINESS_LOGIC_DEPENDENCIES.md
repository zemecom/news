# Business Logic Dependencies

_Generated at: 2026-02-15 20:23:35Z (UTC)_

## Module Graph

```text
App
  -> Catalog
  -> Crawler
  -> Delivery
  -> Intelligence
  -> Shared
Catalog
  -> Shared
Crawler
  -> Shared
Delivery
  -> Shared
Intelligence
  -> Catalog
  -> Shared
Shared
```

## Class-Level Dependencies

```text
App\Console\Commands\MessagingSetupCommand
  file: app/Console/Commands/MessagingSetupCommand.php
  -> App\Services\MessagingTopologyService
App\Console\Commands\NewsCrawlCommand
  file: app/Console/Commands/NewsCrawlCommand.php
  -> Modules\Catalog\Infrastructure\Persistence\Models\Source
  -> Modules\Crawler\Application\Actions\FeedFetcherAction
App\Console\Commands\NewsProcessQueueCommand
  file: app/Console/Commands/NewsProcessQueueCommand.php
  -> App\Services\MessagingTopologyService
  -> Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline
  -> Modules\Shared\Domain\DTO\RawNewsData
App\Http\Controllers\Api\Admin\SourceController
  file: app/Http/Controllers/Api/Admin/SourceController.php
  -> App\Http\Controllers\Controller
  -> Modules\Delivery\Application\Actions\ListSourcesAction
App\Http\Controllers\Api\NewsController
  file: app/Http/Controllers/Api/NewsController.php
  -> App\Http\Controllers\Controller
  -> App\Http\Requests\Api\NewsIndexRequest
  -> Modules\Delivery\Application\Actions\ListNewsAction
  -> Modules\Delivery\Application\Actions\ShowNewsAction
  -> Modules\Delivery\Domain\DTO\NewsFeedFilters
App\Http\Controllers\Controller
  file: app/Http/Controllers/Controller.php
  deps: (none)
App\Http\Controllers\HealthController
  file: app/Http/Controllers/HealthController.php
  -> App\Services\HealthCheckService
App\Http\Controllers\Web\FeedPageController
  file: app/Http/Controllers/Web/FeedPageController.php
  -> App\Http\Controllers\Controller
App\Http\Middleware\EnsureUserIsAdmin
  file: app/Http/Middleware/EnsureUserIsAdmin.php
  -> App\Models\User
App\Http\Requests\Api\NewsIndexRequest
  file: app/Http/Requests/Api/NewsIndexRequest.php
  deps: (none)
App\Models\User
  file: app/Models/User.php
  deps: (none)
App\Providers\AppServiceProvider
  file: app/Providers/AppServiceProvider.php
  deps: (none)
App\Providers\ModulesServiceProvider
  file: app/Providers/ModulesServiceProvider.php
  -> Modules\Catalog\CatalogServiceProvider
  -> Modules\Crawler\CrawlerServiceProvider
  -> Modules\Delivery\DeliveryServiceProvider
  -> Modules\Intelligence\IntelligenceServiceProvider
App\Services\HealthCheckService
  file: app/Services/HealthCheckService.php
  deps: (none)
App\Services\MessagingTopologyService
  file: app/Services/MessagingTopologyService.php
  deps: (none)
Modules\Catalog\CatalogServiceProvider
  file: src/Modules/Catalog/CatalogServiceProvider.php
  -> Modules\Catalog\Domain\Contracts\NewsRepository
  -> Modules\Catalog\Infrastructure\Persistence\EloquentNewsRepository
Modules\Catalog\Domain\Contracts\NewsRepository
  file: src/Modules/Catalog/Domain/Contracts/NewsRepository.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Catalog\Infrastructure\Persistence\EloquentNewsRepository
  file: src/Modules/Catalog/Infrastructure/Persistence/EloquentNewsRepository.php
  -> Modules\Catalog\Domain\Contracts\NewsRepository
  -> Modules\Catalog\Infrastructure\Persistence\Models\NewsItem
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
  -> Modules\Shared\Domain\Enum\NewsStatus
Modules\Catalog\Infrastructure\Persistence\Models\NewsItem
  file: src/Modules/Catalog/Infrastructure/Persistence/Models/NewsItem.php
  deps: (none)
Modules\Catalog\Infrastructure\Persistence\Models\Source
  file: src/Modules/Catalog/Infrastructure/Persistence/Models/Source.php
  deps: (none)
Modules\Crawler\Application\Actions\FeedFetcherAction
  file: src/Modules/Crawler/Application/Actions/FeedFetcherAction.php
  -> Modules\Crawler\Application\Services\RawNewsFactory
  -> Modules\Crawler\Domain\Contracts\RawPublisher
  -> Modules\Crawler\Domain\Contracts\RssClient
  -> Modules\Crawler\Domain\Contracts\TelegramClient
Modules\Crawler\Application\Services\RawNewsFactory
  file: src/Modules/Crawler/Application/Services/RawNewsFactory.php
  -> Modules\Shared\Application\Services\FingerprintGenerator
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Crawler\CrawlerServiceProvider
  file: src/Modules/Crawler/CrawlerServiceProvider.php
  -> Modules\Crawler\Application\Actions\FeedFetcherAction
  -> Modules\Crawler\Application\Services\RawNewsFactory
  -> Modules\Crawler\Domain\Contracts\RawPublisher
  -> Modules\Crawler\Domain\Contracts\RssClient
  -> Modules\Crawler\Domain\Contracts\TelegramClient
  -> Modules\Crawler\Infrastructure\Http\RssClient
  -> Modules\Crawler\Infrastructure\Http\RssConnector
  -> Modules\Crawler\Infrastructure\Http\TelegramClient
  -> Modules\Crawler\Infrastructure\Messaging\RawPublisher
  -> Modules\Shared\Application\Services\FingerprintGenerator
Modules\Crawler\Domain\Contracts\RawPublisher
  file: src/Modules/Crawler/Domain/Contracts/RawPublisher.php
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Crawler\Domain\Contracts\RssClient
  file: src/Modules/Crawler/Domain/Contracts/RssClient.php
  deps: (none)
Modules\Crawler\Domain\Contracts\TelegramClient
  file: src/Modules/Crawler/Domain/Contracts/TelegramClient.php
  deps: (none)
Modules\Crawler\Infrastructure\Http\RssClient
  file: src/Modules/Crawler/Infrastructure/Http/RssClient.php
  -> Modules\Crawler\Domain\Contracts\RssClient
Modules\Crawler\Infrastructure\Http\RssConnector
  file: src/Modules/Crawler/Infrastructure/Http/RssConnector.php
  deps: (none)
Modules\Crawler\Infrastructure\Http\TelegramClient
  file: src/Modules/Crawler/Infrastructure/Http/TelegramClient.php
  -> Modules\Crawler\Domain\Contracts\TelegramClient
Modules\Crawler\Infrastructure\Messaging\RawPublisher
  file: src/Modules/Crawler/Infrastructure/Messaging/RawPublisher.php
  -> Modules\Crawler\Domain\Contracts\RawPublisher
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Delivery\Application\Actions\ListNewsAction
  file: src/Modules/Delivery/Application/Actions/ListNewsAction.php
  -> Modules\Delivery\Domain\Contracts\NewsFeedReader
  -> Modules\Delivery\Domain\DTO\NewsFeedFilters
Modules\Delivery\Application\Actions\ListSourcesAction
  file: src/Modules/Delivery/Application/Actions/ListSourcesAction.php
  -> Modules\Delivery\Domain\Contracts\SourceAdminReader
Modules\Delivery\Application\Actions\ShowNewsAction
  file: src/Modules/Delivery/Application/Actions/ShowNewsAction.php
  -> Modules\Delivery\Domain\Contracts\NewsFeedReader
Modules\Delivery\DeliveryServiceProvider
  file: src/Modules/Delivery/DeliveryServiceProvider.php
  -> Modules\Delivery\Domain\Contracts\NewsFeedReader
  -> Modules\Delivery\Domain\Contracts\SourceAdminReader
  -> Modules\Delivery\Infrastructure\Persistence\EloquentNewsFeedReader
  -> Modules\Delivery\Infrastructure\Persistence\EloquentSourceAdminReader
Modules\Delivery\Domain\Contracts\NewsFeedReader
  file: src/Modules/Delivery/Domain/Contracts/NewsFeedReader.php
  -> Modules\Delivery\Domain\DTO\NewsFeedFilters
Modules\Delivery\Domain\Contracts\SourceAdminReader
  file: src/Modules/Delivery/Domain/Contracts/SourceAdminReader.php
  deps: (none)
Modules\Delivery\Domain\DTO\NewsFeedFilters
  file: src/Modules/Delivery/Domain/DTO/NewsFeedFilters.php
  deps: (none)
Modules\Delivery\Infrastructure\Persistence\EloquentNewsFeedReader
  file: src/Modules/Delivery/Infrastructure/Persistence/EloquentNewsFeedReader.php
  -> Modules\Delivery\Domain\Contracts\NewsFeedReader
  -> Modules\Delivery\Domain\DTO\NewsFeedFilters
  -> Modules\Shared\Domain\Enum\NewsStatus
Modules\Delivery\Infrastructure\Persistence\EloquentSourceAdminReader
  file: src/Modules/Delivery/Infrastructure/Persistence/EloquentSourceAdminReader.php
  -> Modules\Delivery\Domain\Contracts\SourceAdminReader
Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline
  file: src/Modules/Intelligence/Application/Pipeline/NewsProcessingPipeline.php
  -> Modules\Catalog\Domain\Contracts\NewsRepository
  -> Modules\Intelligence\Application\Pipeline\Steps\PipelineStep
  -> Modules\Intelligence\Domain\Contracts\EnrichedPublisher
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Application\Pipeline\Steps\AntiClickbaitStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/AntiClickbaitStep.php
  -> Modules\Intelligence\Domain\Contracts\TitleGenerator
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Application\Pipeline\Steps\ClassifyStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/ClassifyStep.php
  -> Modules\Intelligence\Domain\Contracts\Classifier
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Application\Pipeline\Steps\DeduplicateStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/DeduplicateStep.php
  -> Modules\Catalog\Domain\Contracts\NewsRepository
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
  -> Modules\Shared\Domain\Enum\NewsStatus
Modules\Intelligence\Application\Pipeline\Steps\FinalizeStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/FinalizeStep.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
  -> Modules\Shared\Domain\Enum\NewsStatus
Modules\Intelligence\Application\Pipeline\Steps\ImportanceStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/ImportanceStep.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Application\Pipeline\Steps\LanguageDetectStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/LanguageDetectStep.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Application\Pipeline\Steps\ModerationStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/ModerationStep.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
  -> Modules\Shared\Domain\Enum\NewsStatus
Modules\Intelligence\Application\Pipeline\Steps\PipelineStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/PipelineStep.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Application\Pipeline\Steps\SentimentStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/SentimentStep.php
  -> Modules\Intelligence\Domain\Contracts\SentimentAnalyzer
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Application\Pipeline\Steps\TranslateStep
  file: src/Modules/Intelligence/Application/Pipeline/Steps/TranslateStep.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Intelligence\Domain\Contracts\Classifier
  file: src/Modules/Intelligence/Domain/Contracts/Classifier.php
  deps: (none)
Modules\Intelligence\Domain\Contracts\EnrichedPublisher
  file: src/Modules/Intelligence/Domain/Contracts/EnrichedPublisher.php
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
Modules\Intelligence\Domain\Contracts\SentimentAnalyzer
  file: src/Modules/Intelligence/Domain/Contracts/SentimentAnalyzer.php
  deps: (none)
Modules\Intelligence\Domain\Contracts\TitleGenerator
  file: src/Modules/Intelligence/Domain/Contracts/TitleGenerator.php
  deps: (none)
Modules\Intelligence\Domain\Contracts\Translator
  file: src/Modules/Intelligence/Domain/Contracts/Translator.php
  deps: (none)
Modules\Intelligence\Infrastructure\LLM\HeuristicTranslator
  file: src/Modules/Intelligence/Infrastructure/LLM/HeuristicTranslator.php
  -> Modules\Intelligence\Domain\Contracts\Translator
Modules\Intelligence\Infrastructure\LLM\KeywordClassifier
  file: src/Modules/Intelligence/Infrastructure/LLM/KeywordClassifier.php
  -> Modules\Intelligence\Domain\Contracts\Classifier
Modules\Intelligence\Infrastructure\LLM\KeywordSentimentAnalyzer
  file: src/Modules/Intelligence/Infrastructure/LLM/KeywordSentimentAnalyzer.php
  -> Modules\Intelligence\Domain\Contracts\SentimentAnalyzer
Modules\Intelligence\Infrastructure\LLM\ObjectivelyTitleGenerator
  file: src/Modules/Intelligence/Infrastructure/LLM/ObjectivelyTitleGenerator.php
  -> Modules\Intelligence\Domain\Contracts\TitleGenerator
Modules\Intelligence\Infrastructure\Messaging\EnrichedPublisher
  file: src/Modules/Intelligence/Infrastructure/Messaging/EnrichedPublisher.php
  -> Modules\Intelligence\Domain\Contracts\EnrichedPublisher
  -> Modules\Shared\Domain\DTO\EnrichedNewsData
  -> Modules\Shared\Domain\Enum\NewsStatus
Modules\Intelligence\IntelligenceServiceProvider
  file: src/Modules/Intelligence/IntelligenceServiceProvider.php
  -> Modules\Catalog\Domain\Contracts\NewsRepository
  -> Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline
  -> Modules\Intelligence\Application\Pipeline\Steps\AntiClickbaitStep
  -> Modules\Intelligence\Application\Pipeline\Steps\ClassifyStep
  -> Modules\Intelligence\Application\Pipeline\Steps\DeduplicateStep
  -> Modules\Intelligence\Application\Pipeline\Steps\FinalizeStep
  -> Modules\Intelligence\Application\Pipeline\Steps\ImportanceStep
  -> Modules\Intelligence\Application\Pipeline\Steps\LanguageDetectStep
  -> Modules\Intelligence\Application\Pipeline\Steps\ModerationStep
  -> Modules\Intelligence\Application\Pipeline\Steps\SentimentStep
  -> Modules\Intelligence\Application\Pipeline\Steps\TranslateStep
  -> Modules\Intelligence\Domain\Contracts\Classifier
  -> Modules\Intelligence\Domain\Contracts\EnrichedPublisher
  -> Modules\Intelligence\Domain\Contracts\SentimentAnalyzer
  -> Modules\Intelligence\Domain\Contracts\TitleGenerator
  -> Modules\Intelligence\Domain\Contracts\Translator
  -> Modules\Intelligence\Infrastructure\LLM\HeuristicTranslator
  -> Modules\Intelligence\Infrastructure\LLM\KeywordClassifier
  -> Modules\Intelligence\Infrastructure\LLM\KeywordSentimentAnalyzer
  -> Modules\Intelligence\Infrastructure\LLM\ObjectivelyTitleGenerator
  -> Modules\Intelligence\Infrastructure\Messaging\EnrichedPublisher
Modules\Shared\Application\Services\FingerprintGenerator
  file: src/Modules/Shared/Application/Services/FingerprintGenerator.php
  -> Modules\Shared\Domain\DTO\RawNewsData
Modules\Shared\Domain\DTO\EnrichedNewsData
  file: src/Modules/Shared/Domain/DTO/EnrichedNewsData.php
  -> Modules\Shared\Domain\Enum\NewsStatus
Modules\Shared\Domain\DTO\RawNewsData
  file: src/Modules/Shared/Domain/DTO/RawNewsData.php
  deps: (none)
Modules\Shared\Domain\Enum\NewsStatus
  file: src/Modules/Shared/Domain/Enum/NewsStatus.php
  deps: (none)
```

## Notes

- This file is generated by `scripts/generate_business_deps.php`.
- Regenerate with: `php scripts/generate_business_deps.php`.
- It focuses on internal business dependencies (`App\*`, `Modules\*`).
- Use with architecture tests in `tests/Architecture/LayerDependenciesTest.php`.
