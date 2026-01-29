<?php

namespace LaraSwagger\Http\Controllers;

use Illuminate\Support\Facades\Http;

class DocsController extends \Illuminate\Routing\Controller
{
    public function openapi()
    {

        $paths = [];
        $tags  = [];


        $json = Http::get(config('api-docs-generator.path'))->json();
        if (!$json || !isset($json['endpoints'])) return ;

        foreach ($json['endpoints'] as $ep) {

            $path = $ep['uri'];
            $method = strtolower(explode('|', $ep['method'])[0]);

            $tagName = $ep['group'] ?? 'general';
            $tags[$tagName] = ['name' => $tagName];

            $projectBaseUrl = $json['base_url'] ?? '';

            $exampleBody = $ep['response']['body'] ?? null;
            $statusCode  = $ep['response']['status'] ?? 200;

            $operationId = !empty($ep['name'])
                ? str_replace(['.', '-'], '_', $ep['name'])
                : $method . '_' . md5($path);

            // Build requestBody for POST/PUT/PATCH
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

                // GET params
                'parameters' => $ep['parameters'] ?? [],

                // POST body
                'requestBody' => $requestBody,

                // auth
                'security' => !empty($ep['auth']['required'])
                    ? [['bearerAuth' => []]]
                    : [],

                // server per project
                'servers' => [
                    ['url' => $projectBaseUrl]
                ],

                // response
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

        // sort tags + general last
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

            // normalize
            if (is_string($rules)) {
                $rules = explode('|', $rules);
            }

            $rulesStr = '';
            if (is_array($rules)) {
                foreach ($rules as $r) {
                    if (is_string($r)) $rulesStr .= $r . '|';
                }
            }

            $type = 'string';
            if (str_contains($rulesStr, 'numeric') || str_contains($rulesStr, 'integer')) $type = 'number';
            if (str_contains($rulesStr, 'boolean')) $type = 'boolean';

            $properties[$field] = [
                'type' => $type,
            ];

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

    public function swaggerResponse($sortedTags = null , $paths = null)
    {
        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('APP_NAME').' API Documentation',
                'version' => '1.0.0',
            ],
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

