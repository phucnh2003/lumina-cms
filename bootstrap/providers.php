<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use Lumina\Cms\Providers\FortifyServiceProvider;
use Lumina\Setups\Providers\SetupsServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    FortifyServiceProvider::class,
    SetupsServiceProvider::class,
];
