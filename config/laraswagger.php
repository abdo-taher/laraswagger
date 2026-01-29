<?php

return [

    'app_name' => env('APP_NAME', 'Laravel'),
    'path' => public_path('api-docs.json'),
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
                'email' => 'docs@local.test',
                'password' => '12345678',
                'token_key' => 'token',
            ],
        ],
    ],
];
