<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\TitleGenerator;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final class AntiClickbaitStep implements PipelineStep
{
    public function __construct(private TitleGenerator $titleGenerator) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        // $title = $this->titleGenerator->generate($input->content, $input->title);
        // return $input->with(['title' => $title]);
        return $input;
    }
}
