<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return view('swagger');
});

Route::get('/docs/openapi.json', function () {
    return response()->json([
        'openapi' => '3.0.3',
        'info' => [
            'title' => config('app.name', 'Flash Sale Platform') . ' API',
            'version' => '1.0.0',
        ],
        'servers' => [
            [
                'url' => url('/api'),
            ],
        ],
        'paths' => [
            '/login' => [
                'post' => [
                    'summary' => 'Login',
                    'operationId' => 'login',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['email', 'password'],
                                    'properties' => [
                                        'email' => [
                                            'type' => 'string',
                                            'format' => 'email',
                                            'example' => 'demo@example.com',
                                        ],
                                        'password' => [
                                            'type' => 'string',
                                            'format' => 'password',
                                            'example' => 'password',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Authenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/LoginResponse',
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Invalid credentials or validation error',
                        ],
                    ],
                ],
            ],
            '/orders/dashboard' => [
                'get' => [
                    'summary' => 'Order dashboard',
                    'operationId' => 'orderDashboard',
                    'tags' => ['Orders'],
                    'security' => [
                        [
                            'bearerAuth' => [],
                        ],
                    ],
                    'parameters' => [
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'schema' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'default' => 1,
                            ],
                        ],
                        [
                            'name' => 'per_page',
                            'in' => 'query',
                            'schema' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 50,
                                'default' => 50,
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Dashboard orders',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/OrderDashboardResponse',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                        ],
                    ],
                ],
            ],
        ],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Sanctum',
                ],
            ],
            'schemas' => [
                'LoginResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'token_type' => [
                            'type' => 'string',
                            'example' => 'Bearer',
                        ],
                        'access_token' => [
                            'type' => 'string',
                        ],
                        'user' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer', 'example' => 1],
                                'name' => ['type' => 'string', 'example' => 'Demo User'],
                                'email' => ['type' => 'string', 'example' => 'demo@example.com'],
                            ],
                        ],
                    ],
                ],
                'OrderDashboardResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/OrderDashboardItem',
                            ],
                        ],
                        'meta' => [
                            '$ref' => '#/components/schemas/PaginationMeta',
                        ],
                    ],
                ],
                'OrderDashboardItem' => [
                    'type' => 'object',
                    'properties' => [
                        'event' => ['type' => 'string', 'nullable' => true],
                        'user' => ['type' => 'string', 'nullable' => true],
                        'product' => ['type' => 'string', 'nullable' => true],
                        'price' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                        'status' => ['type' => 'string', 'example' => 'paid'],
                    ],
                ],
                'PaginationMeta' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => ['type' => 'integer', 'example' => 1],
                        'per_page' => ['type' => 'integer', 'example' => 50],
                        'total' => ['type' => 'integer', 'example' => 100000],
                        'last_page' => ['type' => 'integer', 'example' => 2000],
                    ],
                ],
            ],
        ],
    ]);
});
