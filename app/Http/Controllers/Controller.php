<?php

namespace App\Http\Controllers;

use App\Traits\APIResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base HTTP controller for the application.
 */
abstract class Controller
{
    use APIResponse, AuthorizesRequests;
}
