<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Tests\TestCase;

final class FeedPageTest extends TestCase
{
    public function test_feed_page_is_accessible_and_contains_filters(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('SmartNews')
            ->assertSee('Фильтры')
            ->assertSee('id="filters-form"', false)
            ->assertSee('id="news-grid"', false);
    }
}
