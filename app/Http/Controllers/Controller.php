<?php

namespace App\Http\Controllers;

use App\Traits\APIResponse;

/**
 * Base HTTP controller for the application.
 */
abstract class Controller
{
    use APIResponse;
}
