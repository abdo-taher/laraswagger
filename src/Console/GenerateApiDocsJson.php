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

    protected $description = 'Generate OpenAPI 3 JSON for Swagger UI';

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
        $rawEndpoints = [];

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

            $rawEndpoints[] = $endpoint;
        }

        // Transform raw endpoints into OpenAPI paths
        $paths = [];
        foreach ($rawEndpoints as $ep) {
            $uri = $ep['uri'];
            $method = strtolower(explode('|', $ep['method'])[0]);

            // Build requestBody for POST/PUT/PATCH
            $requestBody = null;
            if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
                $requestBody = $this->buildRequestBodyFromValidation($ep['parameters']);
            }

            $paths[$uri][$method] = [
                'tags' => [$ep['group']],
                'summary' => trim(($ep['name'] ?? '') . ' ' . $uri),
                'description' => $ep['name'] ?? '',
                'parameters' => [], // Optional: extend for query/path params
                'requestBody' => $requestBody,
                'security' => $ep['auth_required'] ? [['bearerAuth' => []]] : [],
                'responses' => [
                    (string)($ep['response']['status'] ?? 200) => [
                        'description' => 'Auto captured response',
                        'content' => [
                            'application/json' => [
                                'example' => $ep['response']['body'] ?? null
                            ]
                        ]
                    ]
                ]
            ];
        }

        $path = base_path($this->option('path') ?? 'public/api-docs.json');
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);

        file_put_contents($path, json_encode([
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name') . ' API Documentation',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => $this->getBaseUrl()]
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ]
                ]
            ],
            'paths' => $paths,
        ], JSON_PRETTY_PRINT));

        $this->info('✅ OpenAPI JSON generated successfully');
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
        // Merge by first URI segment, remove api prefix
        $segments = explode('/', trim($uri, '/'));
        if (isset($segments[0]) && $segments[0] === 'api') {
            return $segments[1] ?? 'general';
        }
        return $segments[0] ?? 'general';
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

    private function buildRequestBodyFromValidation(array $validation): ?array
    {
        if (empty($validation)) return null;

        $properties = [];
        $required = [];

        foreach ($validation as $field => $rules) {
            if (is_string($rules)) $rules = explode('|', $rules);

            $type = 'string';
            $rulesStr = implode('|', $rules);

            if (str_contains($rulesStr, 'numeric') || str_contains($rulesStr, 'integer')) $type = 'number';
            if (str_contains($rulesStr, 'boolean')) $type = 'boolean';

            $properties[$field] = ['type' => $type];
            if (str_contains($rulesStr, 'required')) $required[] = $field;
        }

        return [
            'required' => !empty($required),
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ]
                ]
            ]
        ];
    }

    private function buildEndpointUrl(string $uri): string
    {
        return $this->getBaseUrl() . '/' . preg_replace('/\{[^}]+\}/', '1', $uri);
    }
}
