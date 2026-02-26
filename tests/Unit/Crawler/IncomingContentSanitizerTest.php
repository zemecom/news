<?php

declare(strict_types=1);

namespace Tests\Unit\Crawler;

use Modules\Crawler\Application\Services\IncomingContentSanitizer;
use Tests\TestCase;

final class IncomingContentSanitizerTest extends TestCase
{
    public function test_it_sanitizes_html_to_safe_plain_text(): void
    {
        config()->set('crawler.security.sanitization.max_text_length', 20000);

        $sanitizer = new IncomingContentSanitizer;
        $result = $sanitizer->sanitizeText(
            '<p>Hello <strong>world</strong></p><script>alert(1)</script><div>Second&nbsp;line</div>'
        );

        $this->assertSame("Hello world\nSecond line", $result);
        $this->assertStringNotContainsString('alert(1)', $result);
    }

    public function test_it_rejects_unsafe_url_schemes(): void
    {
        config()->set('crawler.security.allowed_url_schemes', ['http', 'https']);

        $sanitizer = new IncomingContentSanitizer;

        $this->assertNull($sanitizer->sanitizeUrl('javascript:alert(1)'));
        $this->assertSame('https://example.com/news', $sanitizer->sanitizeUrl('https://example.com/news'));
    }

    public function test_it_filters_and_normalizes_media_collection(): void
    {
        config()->set('crawler.security.allowed_url_schemes', ['http', 'https']);

        $sanitizer = new IncomingContentSanitizer;
        $media = $sanitizer->sanitizeMedia([
            ['url' => 'https://example.com/cover.jpg', 'type' => 'IMAGE/JPEG'],
            ['url' => 'javascript:alert(1)', 'type' => 'image/png'],
            ['url' => 'https://example.com/cover.jpg', 'type' => 'image/jpeg'],
            ['url' => 'https://example.com/video.mp4', 'type' => 'video/mp4'],
        ]);

        $this->assertSame([
            ['url' => 'https://example.com/cover.jpg', 'type' => 'image/jpeg'],
            ['url' => 'https://example.com/video.mp4', 'type' => 'video/mp4'],
        ], $media);
    }
}
