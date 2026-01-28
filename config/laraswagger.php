<?php

return [

    'generator' => [

        'path' => 'public/api-docs.json',

        'base_url' => null,

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
