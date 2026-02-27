<?php

declare(strict_types=1);

namespace App\Support\Octane;

use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Laravel\Octane\Events\RequestReceived;
use ReflectionException;
use ReflectionProperty;

final class ResetDebugbarJsRenderer
{
    public function handle(RequestReceived $event): void
    {
        if (! class_exists(LaravelDebugbar::class) || ! $event->sandbox->resolved(LaravelDebugbar::class)) {
            return;
        }

        $debugbar = $event->sandbox->make(LaravelDebugbar::class);

        try {
            $property = new ReflectionProperty($debugbar, 'jsRenderer');
            $property->setValue($debugbar, null);
        } catch (ReflectionException) {
            // The internals may change between package versions; ignore safely.
        }
    }
}
