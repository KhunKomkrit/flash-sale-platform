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
            '/orders' => [
                'post' => [
                    'summary' => 'Place an order',
                    'operationId' => 'placeOrder',
                    'tags' => ['Orders'],
                    'security' => [
                        [
                            'bearerAuth' => [],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/PlaceOrderRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Order created',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/OrderResponse',
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                        ],
                        '409' => [
                            'description' => 'Out of stock, duplicate order, or inactive sale event',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/ErrorResponse',
                                    ],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Validation error',
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
                'PlaceOrderRequest' => [
                    'type' => 'object',
                    'required' => ['product_id', 'sale_event_id'],
                    'properties' => [
                        'product_id' => ['type' => 'integer', 'example' => 1],
                        'sale_event_id' => ['type' => 'integer', 'example' => 1],
                        'quantity' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'default' => 1,
                            'example' => 1,
                        ],
                    ],
                ],
                'OrderResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            '$ref' => '#/components/schemas/Order',
                        ],
                    ],
                ],
                'Order' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'example' => 1001],
                        'product_id' => ['type' => 'integer', 'example' => 1],
                        'sale_event_id' => ['type' => 'integer', 'example' => 1],
                        'quantity' => ['type' => 'integer', 'example' => 1],
                        'unit_price' => ['type' => 'number', 'format' => 'float', 'example' => 199.99],
                        'status' => ['type' => 'string', 'example' => 'pending'],
                    ],
                ],
                'ErrorResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string', 'example' => 'Product is out of stock.'],
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
