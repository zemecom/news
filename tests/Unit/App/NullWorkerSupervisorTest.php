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
            'crawler-worker' => [
                'service' => 'crawler-worker',
                'queue' => 'crawler_tasks',
            ],
        ]);
        config()->set('workers.operator_commands', [
            'start_all' => 'make worker-up',
            'restart_runtime' => 'docker compose restart crawler-worker',
        ]);

        $supervisor = new NullWorkerSupervisor;
        $status = $supervisor->status('crawler-worker');

        $this->assertFalse($supervisor->isConfigured());
        $this->assertSame('not-configured', $supervisor->backendLabel());
        $this->assertSame(['crawler-worker'], $supervisor->listRuntimes());
        $this->assertSame('not_configured', $status['state']);
        $this->assertSame('make worker-up', $status['operator_commands']['start_all']);
        $this->assertStringContainsString('not configured', (string) $status['error']);
    }

    public function test_it_refuses_runtime_actions_when_supervisor_is_disabled(): void
    {
        config()->set('workers.runtimes', [
            'crawler-worker' => [
                'service' => 'crawler-worker',
                'queue' => 'crawler_tasks',
            ],
        ]);

        $supervisor = new NullWorkerSupervisor;

        $result = $supervisor->restart('crawler-worker');

        $this->assertFalse($result['success']);
        $this->assertSame('crawler-worker', $result['runtime']);
        $this->assertStringContainsString('not configured', (string) $result['message']);
    }
}
