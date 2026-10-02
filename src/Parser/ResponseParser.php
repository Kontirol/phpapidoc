<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Model\Response;

/**
 * Turns a "@response [status] {json}" tag into a Response.
 *
 *     @response {"code":200,"data":[]}          <- implies 200
 *     @response 201 {"code":200}
 *     @response 400 {"code":400,"message":"x"}
 */
final class ResponseParser
{
    /**
     * @param list<Diagnostic> $diagnostics
     */
    public function parse(TagValue $tag, ?string $file, array &$diagnostics): Response
    {
        $value = trim($tag->value);
        $statusCode = '200';
        $payload = $value;

        if ($value !== '' && preg_match('#^(default|\d{3})\s+(.*)$#s', $value, $matches) === 1) {
            $statusCode = $matches[1];
            $payload = trim($matches[2]);
        }

        $response = new Response($statusCode, self::describe($statusCode));

        if ($payload === '') {
            return $response;
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $diagnostics[] = Diagnostic::warning(
                'response.invalid_json',
                sprintf('Invalid JSON in @response for status %s: %s', $statusCode, json_last_error_msg()),
                $file,
                $tag->line
            );

            return $response;
        }

        $response->example = $decoded;
        $response->hasExample = true;

        return $response;
    }

    private static function describe(string $statusCode): string
    {
        switch ($statusCode) {
            case '200':
                return '成功';
            case '201':
                return '创建成功';
            case '204':
                return '无内容';
            case '400':
                return '参数错误';
            case '401':
                return '未登录或登录已过期';
            case '403':
                return '无权限';
            case '404':
                return '资源不存在';
            case '429':
                return '请求过于频繁';
            case '500':
                return '服务器内部错误';
            default:
                return $statusCode === 'default' ? '默认响应' : '响应';
        }
    }
}
