<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Landlord home page (route:cache friendly).
 */
class HomeController extends Controller
{
    /**
     * Render the landlord welcome page.
     *
     * @return View
     */
    public function __invoke(): View
    {
        return view('welcome');
    }
}
