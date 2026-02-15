<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MessagingTopologyService;
use Illuminate\Console\Command;

final class MessagingSetupCommand extends Command
{
    protected $signature = 'news:messaging:setup';

    protected $description = 'Declare RabbitMQ exchange, queues and bindings for SmartNews.';

    public function handle(MessagingTopologyService $topology): int
    {
        $topology->declareTopology();
        $this->info('Messaging topology is ready.');

        return self::SUCCESS;
    }
}
