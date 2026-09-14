<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo User
    |--------------------------------------------------------------------------
    |
    | Credentials for the single demo account created by
    | `php artisan db:seed --class=DemoSeeder`. Only read there — never
    | during normal application boot. `name` has a safe, non-sensitive
    | fallback; `email`/`password` are intentionally left without a
    | fallback so DemoSeeder can detect and refuse to run with an
    | insecure/missing credential instead of defaulting to one.
    |
    */

    'user' => [
        'name' => env('DEMO_USER_NAME', 'Demo User'),
        'email' => env('DEMO_USER_EMAIL'),
        'password' => env('DEMO_USER_PASSWORD'),
    ],

];
