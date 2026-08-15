<?php

use App\Providers\AppServiceProvider;
use App\Providers\ScrambleDocumentationServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    ScrambleDocumentationServiceProvider::class,
];
