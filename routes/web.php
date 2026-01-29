<?php

use Illuminate\Support\Facades\Route;
use LaraSwagger\Http\Controllers\DocsController;

Route::get('/api-docs', [DocsController::class, 'swagger']);
Route::get('/api-docs/openapi.json', [DocsController::class, 'json']);
