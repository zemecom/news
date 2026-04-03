<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    /**
     * @template TComponent of Component
     *
     * @param  class-string<TComponent>  $component
     * @param  array<string, mixed>  $params
     * @return Testable<TComponent>
     */
    protected function livewireAs(Authenticatable $user, string $component, array $params = []): Testable
    {
        $this->actingAs($user);

        return Livewire::test($component, $params);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
