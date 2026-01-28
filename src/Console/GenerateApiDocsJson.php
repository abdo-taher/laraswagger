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
        {--path= : Output file path}
        {--base-url= : Base URL}
        {--capture-response= : 1/0}
        {--timeout= : Timeout seconds}
        {--auth-mode= : none|manual|login}
        {--token= : Manual token}
        {--token-type= : Token prefix}
        {--login-url= : Login URL}
        {--login-method= : POST|GET}
        {--login-email= : Login email}
        {--login-password= : Login password}
        {--token-key= : Token key}
        {--skip= : Skip routes}';

    protected $description = 'Generate api-docs.json using LaraSwagger';

    public function handle()
    {
        $capture = (bool)$this->optionOrConfig(
            'capture-response',
            'laraswagger.generator.capture_response',
            true
        );

        $timeout = (int)$this->optionOrConfig(
            'timeout',
            'laraswagger.generator.timeout',
            15
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
                'action' => $route->getActionName(),
                'parameters' => $this->mergeParameters(
                    $route->parameterNames(),
                    $this->extractQueryParametersFromController($controller, $method)
                ),
                'auth' => ['required' => $authRequired],
                'request' => [
                    'validation' => $this->extractValidationRules($controller, $method)
                ],
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

        $path = base_path(
            $this->optionOrConfig(
                'path',
                'laraswagger.generator.path',
                'public/api-docs.json'
            )
        );

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, json_encode([
            'project' => config('app.name'),
            'base_url' => $this->getBaseUrl(),
            'generated_at' => now()->toDateTimeString(),
            'endpoints' => $endpoints,
        ], JSON_PRETTY_PRINT));

        $this->info("✅ api-docs.json generated");
    }

    /* ---------- helpers ---------- */

    private function optionOrConfig(string $option, string $config, mixed $default = null): mixed
    {
        return $this->option($option) ?? config($config, $default);
    }

    private function getBaseUrl(): string
    {
        return rtrim(
            $this->optionOrConfig('base-url', 'laraswagger.generator.base_url', config('app.url')),
            '/'
        );
    }

    private function resolveToken(int $timeout): ?string
    {
        if ($this->option('token')) return $this->option('token');

        $mode = $this->optionOrConfig('auth-mode', 'laraswagger.generator.auth.mode', 'none');

        if ($mode === 'manual') {
            return config('laraswagger.generator.auth.token');
        }

        if ($mode === 'login') {
            return $this->getTokenFromLogin($timeout);
        }

        return null;
    }

    private function getTokenFromLogin(int $timeout): ?string
    {
        $url = $this->getBaseUrl() . config('laraswagger.generator.auth.login.url');
        $res = Http::timeout($timeout)->post($url, [
            'email' => config('laraswagger.generator.auth.login.email'),
            'password' => config('laraswagger.generator.auth.login.password'),
        ]);

        return data_get($res->json(), 'data.' . config('laraswagger.generator.auth.login.token_key'));
    }

    private function captureResponse(string $url, ?string $token, int $timeout): array
    {
        $req = Http::timeout($timeout)->acceptJson();

        if ($token) {
            $req->withToken($token, config('laraswagger.generator.auth.token_type'));
        }

        $res = $req->get($url);

        return [
            'status' => $res->status(),
            'body' => $res->json() ?? $res->body(),
        ];
    }

    /* ---- utility methods unchanged logic ---- */

    private function parseAction(string $action): array
    {
        return str_contains($action, '@')
            ? explode('@', $action)
            : (str_contains($action, '::') ? explode('::', $action) : [null, null]);
    }

    private function shouldSkipRoute(string $uri, array $skip): bool
    {
        foreach ($skip as $s) if (str_contains($uri, $s)) return true;
        return false;
    }

    private function isAuthRequired(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if ($m === 'auth' || str_starts_with($m, 'auth:')) return true;
        }
        return false;
    }

    private function detectGroup(string $uri, ?string $name): string
    {
        $parts = explode('/', trim($uri, '/'));
        return $parts[1] ?? ($name ? explode('.', $name)[0] : 'general');
    }

    private function mergeParameters(array $route, array $query): array
    {
        return array_merge(
            array_map(fn($p) => ['name'=>$p,'in'=>'path','required'=>true], $route),
            $query
        );
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

    private function extractQueryParametersFromController(string $c, string $m): array
    {
        return [];
    }

    private function buildEndpointUrl(string $uri): string
    {
        return $this->getBaseUrl() . '/' . preg_replace('/\{[^}]+\}/', '1', $uri);
    }
}
