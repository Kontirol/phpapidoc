<?php

/**
 * Example configuration for apidoc.
 *
 * Generate the specification with:
 *
 *     php bin/apidoc generate -c examples/apidoc.php
 *     php bin/apidoc validate -c examples/apidoc.php
 *
 * The generated files land in build/.
 */

declare(strict_types=1);

return [
    'controllers' => [
        [
            'path' => __DIR__ . '/Api/Controller',
            'namespace' => 'App\\Api\\Controller',
        ],
    ],
    'output' => [
        'json' => __DIR__ . '/../build/openapi.json',
        'yaml' => __DIR__ . '/../build/openapi.yaml',
    ],
    'info' => [
        'title' => '示例接口文档',
        'version' => '1.0.0',
        'description' => '由 apidoc 从控制器注释自动生成，无需手写。',
    ],
    'servers' => [
        'http://localhost:8000',
    ],
    'security' => [
        'schemes' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => '登录接口返回的 token，放在 Authorization: Bearer <token> 里。',
            ],
        ],
        'default' => [
            'bearerAuth' => [],
        ],
    ],
    'tags' => [
        '用户' => '用户中心相关接口',
        '订单' => '订单相关接口',
    ],
    'route' => [
        // "annotation" means: trust @route in the docblocks, do not inspect a
        // framework route table. Use "thinkphp" to cross check with one.
        'source' => 'annotation',
    ],
];
