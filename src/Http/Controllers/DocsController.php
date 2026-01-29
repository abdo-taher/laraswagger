<?php

namespace LaraSwagger\Http\Controllers;

use Illuminate\Support\Facades\Http;

class DocsController extends \Illuminate\Routing\Controller
{
    public function openapi()
    {
        $paths = [];
        $tags  = [];
        $tagGroups = [];

        $json = Http::get(config('api-docs-generator.path'))->json();
        if (!$json || !isset($json['endpoints'])) return;

        foreach ($json['endpoints'] as $ep) {

            $path = $ep['uri'];
            $method = strtolower(explode('|', $ep['method'])[0]);

            /* ===============================
               ✅ HIERARCHICAL GROUPING LOGIC
               api/dashboard/profile/update
               => dashboard / profile
            =============================== */

            $segments = explode('/', trim($path, '/'));

// remove api
            if ($segments[0] === 'api') array_shift($segments);

            $level1 = $segments[0] ?? 'general';   // dashboard | app | website
            $level2 = $segments[1] ?? null;        // admin | client
            $level3 = $segments[2] ?? 'general';   // profile

// Swagger tag = leaf ONLY
            $tagName = $level3;
            $tags[$tagName] = ['name' => $tagName];

// build hierarchy
            if ($level2) {
                $tagGroups[$level1][$level2][] = $tagName;
            } else {
                $tagGroups[$level1][] = $tagName;
            }



            /* =============================== */

            $projectBaseUrl = $json['base_url'] ?? '';

            $exampleBody = $ep['response']['body'] ?? null;
            $statusCode  = $ep['response']['status'] ?? 200;

            $operationId = !empty($ep['name'])
                ? str_replace(['.', '-'], '_', $ep['name'])
                : $method . '_' . md5($path);

            $requestBody = null;
            if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
                $validation = $ep['request']['validation'] ?? [];
                $requestBody = $this->buildRequestBodyFromValidation($validation);
            }

            $paths[$path][$method] = [
                'tags' => [$tagName],
                'summary' => trim(($ep['name'] ?? '') . ' ' . $path),
                'description' => $ep['action'] ?? '',
                'operationId' => $operationId,
                'parameters' => $ep['parameters'] ?? [],
                'requestBody' => $requestBody,
                'security' => !empty($ep['auth']['required'])
                    ? [['bearerAuth' => []]]
                    : [],
                'servers' => [
                    ['url' => $projectBaseUrl]
                ],
                'responses' => [
                    (string)$statusCode => [
                        'description' => 'Auto captured response',
                        'content' => [
                            'application/json' => [
                                'example' => $exampleBody
                            ]
                        ]
                    ]
                ]
            ];
        }

        $sortedTags = array_values($tags);
        usort($sortedTags, function ($a, $b) {
            if ($a['name'] === 'general') return 1;
            if ($b['name'] === 'general') return -1;
            return strcmp($a['name'], $b['name']);
        });

        return $this->swaggerResponse($sortedTags, $paths);
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

    public function swagger()
    {
        return view('Laraswagger::swagger');
    }

    public function swaggerResponse($sortedTags, $paths, $tagGroups)
    {
        $groups = [];

        foreach ($tagGroups as $lvl1 => $children) {

            $subGroups = [];

            foreach ($children as $lvl2 => $tags) {
                $subGroups[] = [
                    'name' => ucfirst($lvl2),
                    'tags' => array_values(array_unique($tags)),
                ];
            }

            $groups[] = [
                'name' => ucfirst($lvl1),
                'tags' => $subGroups,
            ];
        }

        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name').' API Documentation',
                'version' => '1.0.0',
            ],
            'x-tagGroups' => $groups, // ✅ 3 LEVELS
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'tags' => array_values($sortedTags),
            'paths' => $paths,
        ]);
    }


}
