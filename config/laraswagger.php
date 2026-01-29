<?php

return [

    'app_name' => env('APP_NAME', 'Laravel'),
    'generator' => [
        'base_url' => env('APP_URL','http://localhost' ),

        'capture_response' => true,

        'timeout' => 15,

        'skip' => [],

        'auth' => [
            'mode' => 'none', // none | manual | login

            'token' => null,
            'token_type' => 'Bearer',

            'login' => [
                'url' => '/api/login',
                'method' => 'POST',
                'email' => env('MAIN_USER_EMAIL','a@a.com'),
                'password' => env('MAIN_USER_PASSWORD','password'),
                'token_key' => 'token'
            ],
        ],
    ],
];
