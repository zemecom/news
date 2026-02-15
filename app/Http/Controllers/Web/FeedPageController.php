<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class FeedPageController extends Controller
{
    public function __invoke(): View
    {
        return view('feed');
    }
}
