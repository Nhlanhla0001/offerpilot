<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront Experiment
    |--------------------------------------------------------------------------
    |
    | The floating-assistant experiment is server-side and opt-in. While inactive
    | the storefront is instrumented but every eligible session receives the safe
    | control arm, so no live experiment effect is claimed until this is enabled.
    |
    */

    'experiment' => [
        'active' => env('STOREFRONT_EXPERIMENT_ACTIVE', false),
    ],

];
