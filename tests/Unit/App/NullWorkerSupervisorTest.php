<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\NullWorkerSupervisor;
use Tests\TestCase;

final class NullWorkerSupervisorTest extends TestCase
{
    public function test_it_reports_not_configured_runtime_status_and_guidance(): void
    {
        config()->set('workers.runtimes', [
            'worker' => [
                'service' => 'worker',
                'queues' => ['crawler_tasks', 'intelligence_tasks', 'media_tasks'],
            ],
        ]);
        config()->set('workers.operator_commands', [
            'start_all' => 'docker compose --profile queue up -d worker',
            'restart_runtime_template' => 'docker compose restart worker',
            'scale_runtime_hint_template' => 'docker compose --profile queue up -d --scale worker=%d worker',
        ]);

        $supervisor = new NullWorkerSupervisor;
        $status = $supervisor->status('worker');

        $this->assertFalse($supervisor->isConfigured());
        $this->assertSame('not-configured', $supervisor->backendLabel());
        $this->assertSame(['worker'], $supervisor->listRuntimes());
        $this->assertSame('not_configured', $status['state']);
        $this->assertSame('docker compose --profile queue up -d worker', $status['operator_commands']['start_all']);
        $this->assertSame('docker compose restart worker', $status['operator_commands']['restart_runtime']);
        $this->assertSame('docker compose --profile queue up -d --scale worker=2 worker', $status['operator_commands']['scale_runtime_hint']);
        $this->assertStringContainsString('not configured', (string) $status['error']);
    }

    public function test_it_refuses_runtime_actions_when_supervisor_is_disabled(): void
    {
        config()->set('workers.runtimes', [
            'worker' => [
                'service' => 'worker',
                'queues' => ['crawler_tasks', 'intelligence_tasks', 'media_tasks'],
            ],
        ]);

        $supervisor = new NullWorkerSupervisor;

        $result = $supervisor->restart('worker');

        $this->assertFalse($result['success']);
        $this->assertSame('worker', $result['runtime']);
        $this->assertStringContainsString('not configured', (string) $result['message']);
    }
}
