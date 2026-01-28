<?php

use LaraSwagger\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

Route::get('/api-docs/openapi.json', [DocsController::class, 'openapi']);
Route::get('/api-docs', [DocsController::class, 'swagger']);
