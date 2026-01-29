<?php

namespace LaraSwagger\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;

class GenerateApiDocsJson extends Command
{
    protected $signature = 'api:docs-json
        {--path=}
        {--base-url=}
        {--capture-response=}
        {--timeout=}
        {--auth-mode=}
        {--token=}
        {--token-type=}
        {--login-url=}
        {--login-method=}
        {--login-email=}
        {--login-password=}
        {--token-key=}
        {--skip=}';

    protected $description = 'Generate Swagger-style API documentation JSON';

    public function handle()
    {
        $timeout = (int)($this->option('timeout') ?? config('laraswagger.generator.timeout', 15));
        $capture = filter_var(
            $this->option('capture-response') ?? config('laraswagger.generator.capture_response', true),
            FILTER_VALIDATE_BOOL
        );

        $skip = $this->option('skip')
            ? explode(',', $this->option('skip'))
            : config('laraswagger.generator.skip', []);

        $token = $this->resolveToken($timeout);
        $endpoints = [];

        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')) continue;
            if ($route->getActionName() === 'Closure') continue;
            if ($this->shouldSkipRoute($route->uri(), $skip)) continue;

            [$controller, $method] = $this->parseAction($route->getActionName());
            if (!$controller || !$method) continue;

            $methods = $route->methods();
            $authRequired = $this->isAuthRequired($route->middleware());

            $endpoint = [
                'group' => $this->detectGroup($route->uri(), $route->getName()),
                'method' => implode('|', $methods),
                'uri' => '/' . $route->uri(),
                'name' => $route->getName(),
                'parameters' => $this->extractValidationRules($controller, $method),
                'auth_required' => $authRequired,
                'response' => null,
            ];

            if ($capture && in_array('GET', $methods)) {
                $endpoint['response'] = $this->captureResponse(
                    $this->buildEndpointUrl($route->uri()),
                    $authRequired ? $token : null,
                    $timeout
                );
            }

            $endpoints[] = $endpoint;
        }

        $path = base_path($this->option('path') ?? config('laraswagger.generator.path'));
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);

        file_put_contents($path, json_encode([
            'project' => config('app.name'),
            'base_url' => $this->getBaseUrl(),
            'generated_at' => now()->toDateTimeString(),
            'endpoints' => $endpoints,
        ], JSON_PRETTY_PRINT));

        $this->info('✅ API documentation generated successfully');
    }

    /* ---------- Helpers ---------- */

    private function getBaseUrl(): string
    {
        return rtrim(
            $this->option('base-url') ?? config('laraswagger.generator.base_url', config('app.url')),
            '/'
        );
    }

    private function resolveToken(int $timeout): ?string
    {
        if ($this->option('token')) return $this->option('token');

        if (($this->option('auth-mode') ?? config('laraswagger.generator.auth.mode')) !== 'login') {
            return null;
        }

        $res = Http::timeout($timeout)->post(
            $this->getBaseUrl() . ($this->option('login-url') ?? config('laraswagger.generator.auth.login.url')),
            [
                'email' => $this->option('login-email') ?? config('laraswagger.generator.auth.login.email'),
                'password' => $this->option('login-password') ?? config('laraswagger.generator.auth.login.password'),
            ]
        );

        return data_get($res->json(), $this->option('token-key') ?? config('laraswagger.generator.auth.login.token_key'));
    }

    private function captureResponse(string $url, ?string $token, int $timeout): array
    {
        $req = Http::timeout($timeout)->acceptJson();
        if ($token) $req->withToken($token, $this->option('token-type') ?? 'Bearer');

        $res = $req->get($url);
        return ['status' => $res->status(), 'body' => $res->json() ?? $res->body()];
    }

    private function parseAction(string $action): array
    {
        return str_contains($action, '@') ? explode('@', $action) : [null, null];
    }

    private function shouldSkipRoute(string $uri, array $skip): bool
    {
        foreach ($skip as $s) if (str_contains($uri, $s)) return true;
        return false;
    }

    private function isAuthRequired(array $middleware): bool
    {
        return collect($middleware)->contains(fn ($m) => str_starts_with($m, 'auth'));
    }

    private function detectGroup(string $uri, ?string $name): string
    {
        return explode('/', trim($uri, '/'))[1] ?? ($name ? explode('.', $name)[0] : 'general');
    }

    private function extractValidationRules(string $c, string $m): array
    {
        try {
            $ref = new ReflectionMethod($c, $m);
            foreach ($ref->getParameters() as $p) {
                $t = $p->getType()?->getName();
                if ($t && is_subclass_of($t, FormRequest::class)) {
                    return app($t)->rules();
                }
            }
        } catch (\Throwable) {}
        return [];
    }

    private function buildEndpointUrl(string $uri): string
    {
        return $this->getBaseUrl() . '/' . preg_replace('/\{[^}]+\}/', '1', $uri);
    }
}
