<?php

return [

    'generator' => [

        'path' => 'api-docs',

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

    'projects' => [
        [
            'name' => 'API',
            'json' => public_path('laraswagger.json'),
        ],
    ],
];
