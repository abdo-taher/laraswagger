# LaraSwagger

Generate Swagger/OpenAPI documentation for your Laravel API automatically, including routes, validation, controller parameters, and example responses.

---

## Features

- Auto-detect API routes (`api/*`)  
- Detect route parameters and query parameters from controllers  
- Extract validation rules from `FormRequest` classes  
- Auto-capture GET responses for example data  
- Supports authentication via token or login endpoint  
- Laravel auto-discovery via service provider  

---

## Installation

You can install the package via Composer **directly from Git**:

1. Add your package repository in your Laravel project’s `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/abdo-taher/laraswagger"
    }
]
Require the package:

composer require abdo-taher/laraswagger:dev-main
Publish the config file:

php artisan vendor:publish --provider="LaraSwagger\LaraSwaggerServiceProvider" --tag="config"
Usage
Generate api-docs.json with all API routes and validation rules:

php artisan api:docs-json
Options
Option	Description	Default
--path	Output file path	public/api-docs.json
--base-url	Base URL to call endpoints	config('app.url')
--capture-response	Capture GET responses automatically	1
--timeout	Request timeout in seconds	15
--auth-mode	`none	manual
--token	Manual Bearer token	-
--token-type	Token prefix	Bearer
--login-url	Login endpoint for login mode	/api/login
--login-method	POST or GET	POST
--login-email	Email for login	docs@local.test
--login-password	Password for login	12345678
--token-key	Token key in response	token
--skip	Comma-separated keywords to skip routes	-
Configuration
Config file config/laraswagger.php:

return [
    'output_path' => 'public/api-docs.json',
    'capture_response' => true,
    'timeout' => 15,
    'auth_mode' => 'none',
    'login' => [
        'url' => '/api/login',
        'method' => 'POST',
        'email' => 'docs@local.test',
        'password' => '12345678',
        'token_key' => 'token'
    ],
    'skip_routes' => [],
];
Example
php artisan api:docs-json --base-url=http://127.0.0.1:8000 --auth-mode=login
License
MIT License. See LICENSE.


---

### **config/laraswagger.php**

```php
<?php

return [
    'output_path' => 'public/api-docs.json',
    'capture_response' => true,
    'timeout' => 15,
    'auth_mode' => 'none',
    'login' => [
        'url' => '/api/login',
        'method' => 'POST',
        'email' => 'docs@local.test',
        'password' => '12345678',
        'token_key' => 'token'
    ],
    'skip_routes' => [],
];
