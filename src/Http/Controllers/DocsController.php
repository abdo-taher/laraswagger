<?php

namespace LaraSwagger\Http\Controllers;

use Illuminate\Support\Facades\Http;

class DocsController extends \Illuminate\Routing\Controller
{
    public function openapi()
    {
        $paths = [];
        $tags = [];
        $tagGroups = [];

        $json = Http::get(config('api-docs-generator.path'))->json();
        if (!$json || !isset($json['endpoints'])) return;

        foreach ($json['endpoints'] as $ep) {
            $path = $ep['uri'];
            $method = strtolower(explode('|', $ep['method'])[0]);

            // Remove leading/trailing slashes & api prefix
            $segments = array_filter(explode('/', trim($path, '/')), fn($s) => $s !== 'api');

            if (!$segments) continue;

            // Leaf = last segment
            $leafTag = end($segments);

            // Add leaf tag to Swagger tags
            $tags[$leafTag] = ['name' => $leafTag];

            // Build dynamic tagGroups recursively
            $this->addToTagGroups($tagGroups, $segments, $leafTag);

            // Example request/response handling
            $requestBody = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])
                ? $this->buildRequestBodyFromValidation($ep['request']['validation'] ?? [])
                : null;

            $exampleBody = $ep['response']['body'] ?? null;
            $statusCode = $ep['response']['status'] ?? 200;

            $operationId = !empty($ep['name'])
                ? str_replace(['.', '-'], '_', $ep['name'])
                : $method . '_' . md5($path);

            $paths[$path][$method] = [
                'tags' => [$leafTag],
                'summary' => trim(($ep['name'] ?? '') . ' ' . $path),
                'description' => $ep['action'] ?? '',
                'operationId' => $operationId,
                'parameters' => $ep['parameters'] ?? [],
                'requestBody' => $requestBody,
                'security' => !empty($ep['auth']['required']) ? [['bearerAuth' => []]] : [],
                'servers' => [['url' => $json['base_url'] ?? '']],
                'responses' => [
                    (string)$statusCode => [
                        'description' => 'Auto captured response',
                        'content' => [
                            'application/json' => ['example' => $exampleBody]
                        ]
                    ]
                ]
            ];
        }

        // Flatten tags for Swagger
        $sortedTags = array_values($tags);
        usort($sortedTags, fn($a, $b) => $a['name'] === 'general' ? 1 : ($b['name'] === 'general' ? -1 : strcmp($a['name'], $b['name'])));

        return $this->swaggerResponse($sortedTags, $paths, $tagGroups);
    }

    private function buildRequestBodyFromValidation(array $validation): ?array
    {
        if (empty($validation)) return null;

        $properties = [];
        $required = [];

        foreach ($validation as $field => $rules) {
            if (is_string($rules)) {
                $rules = explode('|', $rules);
            }

            $rulesStr = implode('|', array_filter($rules, 'is_string'));

            $type = 'string';
            if (str_contains($rulesStr, 'numeric') || str_contains($rulesStr, 'integer')) $type = 'number';
            if (str_contains($rulesStr, 'boolean')) $type = 'boolean';

            $properties[$field] = ['type' => $type];

            if (str_contains($rulesStr, 'required')) {
                $required[] = $field;
            }
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                ],
            ],
        ];
    }

    private function addToTagGroups(array &$groups, array $segments, string $leaf)
    {
        $current = &$groups;
        foreach ($segments as $seg) {
            if (!isset($current[$seg])) $current[$seg] = [];
            $current = &$current[$seg];
        }
        // Store leaf tag
        $current = $leaf;
    }

    private function buildSwaggerGroups(array $groups): array
    {
        $result = [];
        foreach ($groups as $key => $value) {
            if (is_array($value)) {
                $result[] = [
                    'name' => ucfirst($key),
                    'tags' => $this->buildSwaggerGroups($value)
                ];
            } else {
                $result[] = ['name' => $value, 'tags' => []];
            }
        }
        return $result;
    }


    public function swagger()
    {
        return view('Laraswagger::swagger');
    }

    public function swaggerResponse($sortedTags, $paths, $tagGroups)
    {
        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name').' API Documentation',
                'version' => '1.0.0',
            ],
            'x-tagGroups' => $this->buildSwaggerGroups($tagGroups),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'tags' => $sortedTags,
            'paths' => $paths,
        ]);
    }



}
