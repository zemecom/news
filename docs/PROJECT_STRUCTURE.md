# Project Structure
_SOURCE: Directory Structure_
# Directory Structure
###  
```
└── app/
    ├── Console/
    │   ├── Commands/
    │   │   └── HealthCheckCommand.php
    │   │   └── MessagingSetupCommand.php
    │   │   └── NewsCrawlCommand.php
    │   │   └── NewsMediaBackfillCommand.php
    ├── Filament/
    │   ├── Resources/
    │   │   └── Sources/
    │   │       └── Pages/
    │   │           ├── CreateSource.php
    │   │           ├── EditSource.php
    │   │           ├── ListSources.php
    │   │       └── Schemas/
    │   │           ├── SourceForm.php
    │   │       └── SourceResource.php
    │   │       └── Tables/
    │   │           └── SourcesTable.php
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Api/
    │   │   │   ├── Admin/
    │   │   │   │   ├── SourceController.php
    │   │   │   ├── NewsController.php
    │   │   ├── Controller.php
    │   │   ├── HealthController.php
    │   │   ├── Web/
    │   │   │   └── FeedPageController.php
    │   ├── Middleware/
    │   │   ├── AutoLoginAdmin.php
    │   │   ├── EnsureUserIsAdmin.php
    │   ├── Requests/
    │   │   └── Api/
    │   │       └── NewsIndexRequest.php
    ├── Livewire/
    │   ├── CrawlerLog.php
    ├── Models/
    │   ├── User.php
    ├── Providers/
    │   ├── AppServiceProvider.php
    │   ├── Filament/
    │   │   ├── AdminPanelProvider.php
    │   ├── ModulesServiceProvider.php
    │   ├── TelescopeServiceProvider.php
    ├── Services/
    │   ├── HealthCheckService.php
    │   ├── MessagingTopologyService.php
    ├── Support/
    │   └── Octane/
    │       └── ResetDebugbarJsRenderer.php
└── src/
    └── Modules/
        └── Catalog/
            ├── Application/
            │   ├── Actions/
            │   │   ├── PreloadNewsMediaAction.php
            │   ├── Jobs/
            │   │   ├── PreloadNewsMediaJob.php
            │   ├── Listeners/
            │   │   └── QueueMediaPreloadListener.php
            │   │   └── UpdateSourceStatusListener.php
            ├── CatalogServiceProvider.php
            ├── Domain/
            │   ├── Contracts/
            │   │   └── NewsMediaAssetRepository.php
            │   │   └── NewsRepository.php
            │   │   └── SourceRepository.php
            ├── Infrastructure/
            │   └── Persistence/
            │       └── EloquentNewsMediaAssetRepository.php
            │       └── EloquentNewsRepository.php
            │       └── EloquentSourceRepository.php
            │       └── Models/
            │           └── NewsItem.php
            │           └── NewsMediaAsset.php
            │           └── Source.php
        └── Crawler/
            ├── Application/
            │   ├── Actions/
            │   │   ├── FeedFetcherAction.php
            │   ├── Jobs/
            │   │   ├── FetchSourceJob.php
            │   │   ├── ProcessNewsJob.php
            │   ├── Services/
            │   │   └── IncomingContentSanitizer.php
            │   │   └── RawNewsFactory.php
            ├── CrawlerServiceProvider.php
            ├── Domain/
            │   ├── Contracts/
            │   │   └── ApiParser.php
            │   │   └── Deduplicator.php
            │   │   └── RawPublisher.php
            │   │   └── RssClient.php
            │   │   └── RssParser.php
            │   │   └── TelegramClient.php
            │   │   └── TelegramParser.php
            ├── Infrastructure/
            │   └── Http/
            │       ├── RssClient.php
            │       ├── RssConnector.php
            │       ├── TelegramClient.php
            │   └── Messaging/
            │       ├── RawPublisher.php
            │   └── Parsers/
            │       ├── AlJazeeraRssParser.php
            │       ├── DefaultRssParser.php
            │       ├── HabrRssParser.php
            │       ├── HackerNewsRssParser.php
            │       ├── MedicalXpressRssParser.php
            │       ├── ScienceDailyRssParser.php
            │       ├── TechCrunchRssParser.php
            │       ├── Telegram/
            │       │   ├── DefaultTelegramParser.php
            │       │   ├── ToporLiveTelegramParser.php
            │       ├── TheVergeRssParser.php
            │   └── Security/
            │       ├── SourceUrlPolicy.php
            │   └── Services/
            │       └── DbDeduplicator.php
            │       └── RssParserResolver.php
            │       └── TelegramParserResolver.php
        └── Delivery/
            ├── Application/
            │   ├── Actions/
            │   │   └── ListNewsAction.php
            │   │   └── ListPublicSourcesAction.php
            │   │   └── ListSourcesAction.php
            │   │   └── ShowNewsAction.php
            ├── DeliveryServiceProvider.php
            ├── Domain/
            │   ├── Contracts/
            │   │   ├── NewsFeedReader.php
            │   │   ├── NewsMediaResolver.php
            │   │   ├── SourceAdminReader.php
            │   │   ├── SourcePublicReader.php
            │   ├── DTO/
            │   │   └── NewsFeedFilters.php
            ├── Infrastructure/
            │   └── Persistence/
            │       └── DbNewsMediaResolver.php
            │       └── EloquentNewsFeedReader.php
            │       └── EloquentSourceAdminReader.php
            │       └── EloquentSourcePublicReader.php
        └── Intelligence/
            ├── Application/
            │   ├── Listeners/
            │   │   ├── ProcessRawNewsListener.php
            │   ├── Pipeline/
            │   │   └── NewsProcessingPipeline.php
            │   │   └── SkipMessageException.php
            │   │   └── Steps/
            │   │       └── AntiClickbaitStep.php
            │   │       └── ClassifyStep.php
            │   │       └── DeduplicateStep.php
            │   │       └── FinalizeStep.php
            │   │       └── ImportanceStep.php
            │   │       └── LanguageDetectStep.php
            │   │       └── ModerationStep.php
            │   │       └── PipelineStep.php
            │   │       └── SentimentStep.php
            │   │       └── TranslateStep.php
            ├── Domain/
            │   ├── Contracts/
            │   │   └── Classifier.php
            │   │   └── EnrichedPublisher.php
            │   │   └── SentimentAnalyzer.php
            │   │   └── TitleGenerator.php
            │   │   └── Translator.php
            ├── Infrastructure/
            │   ├── LLM/
            │   │   ├── HeuristicTranslator.php
            │   │   ├── KeywordClassifier.php
            │   │   ├── KeywordSentimentAnalyzer.php
            │   │   ├── ObjectivelyTitleGenerator.php
            │   ├── Messaging/
            │   │   └── EnrichedPublisher.php
            ├── IntelligenceServiceProvider.php
        └── Shared/
            └── Application/
                ├── Services/
                │   └── FingerprintGenerator.php
                │   └── SourceRuntimeHealthPolicy.php
            └── Domain/
                └── Contracts/
                    ├── NewsStore.php
                └── DTO/
                    ├── EnrichedNewsData.php
                    ├── RawNewsData.php
                └── Enum/
                    ├── NewsStatus.php
                └── Events/
                    └── NewsEnriched.php
                    └── RawNewsCreated.php
                    └── SourceFetchFailed.php
                    └── SourceFetchSucceeded.php

```
---
**File Statistics**
- **Size**: 8.93 KB
- **Lines**: 210
File: `../docs/PROJECT_STRUCTURE.md`
