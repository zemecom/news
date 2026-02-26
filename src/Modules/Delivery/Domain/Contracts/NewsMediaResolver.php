<?php

declare(strict_types=1);

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
