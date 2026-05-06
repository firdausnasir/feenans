<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ...class_exists(TypeScriptTransformerConfigFactory::class)
        ? [TypeScriptTransformerServiceProvider::class]
        : [],
];
