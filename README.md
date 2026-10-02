# apidoc

从 PHP 控制器的注释生成 **OpenAPI 3.0（Swagger）** 文档。

不需要手写 YAML，不需要继承任何基类，也不需要跑起来你的应用 —— 它只读源码。

```
$ vendor/bin/apidoc generate

apidoc 0.1.0
8 endpoint(s) documented, 1 ignored, in 10 ms.
Written:
  build/openapi.json  (27.4 KB)
  build/openapi.yaml  (11.2 KB)
```

生成的 `openapi.json` 可以直接导入 Swagger UI、Apifox、Postman、Knife4j。

---

## 安装

```bash
composer require --dev kontirol/apidoc
```

要求 PHP 7.4 以上（在 8.1 / 8.2 上验证过），唯一的运行时依赖是 `symfony/yaml`（只有你输出 YAML 时才会用到）。

---

## 快速开始

### 1. 在控制器方法上写注释

```php
<?php

namespace App\Api\Controller;

class UserController
{
    /**
     * 分页返回用户列表。
     *
     * @name 用户列表
     * @route /api/user/list
     * @method GET
     * @tag 用户
     * @auth bearer
     * @param int page=1 页码
     * @param int size=10 每页数量
     * @param string keyword 昵称关键字
     * @response 200 {"code":200,"message":"ok","data":{"total":42,"list":[{"id":1,"nickname":"张三"}]}}
     */
    public function index(): array
    {
        return [];
    }

    /**
     * @name 用户详情
     * @route /api/user/{id}
     * @method GET
     * @tag 用户
     * @auth bearer
     * @param int! id 用户 ID
     * @response 200 {"code":200,"message":"ok","data":{"id":1,"nickname":"张三"}}
     * @response 404 {"code":404,"message":"用户不存在"}
     */
    public function detail(int $id): array
    {
        return [];
    }
}
```

规则很简单：

- **只有带注释的方法才会被收集**，没有注释的方法直接跳过。
- `@route` 里的 `{id}` 会自动变成 OpenAPI 的 path 参数，并且强制 `required: true`。
- 方法签名里的标量参数（`int $page = 1`）会自动补进文档，标量以外的参数（`Request $request`）会跳过 —— 那是容器注入，不是接口参数。

### 2. 建一个 `apidoc.php`

```php
<?php

return [
    'controllers' => [
        ['path' => __DIR__ . '/app/Api/Controller', 'namespace' => 'App\\Api\\Controller'],
    ],
    'output' => [
        'json' => __DIR__ . '/public/openapi.json',
    ],
    'info' => [
        'title' => '我的项目接口文档',
        'version' => '1.0.0',
    ],
    'servers' => ['http://localhost:8000'],
];
```

也可以写成 JSON 或 YAML，文件名分别是 `apidoc.json` / `apidoc.yaml`。

### 3. 生成

```bash
vendor/bin/apidoc generate
```

在项目根目录下会自动找 `apidoc.php`、`apidoc.json`、`apidoc.yaml` 或 `apidoc.yml`。

---

## 注解速查

| 注解 | 说明 | 例子 |
|---|---|---|
| `@name` | 接口标题（OpenAPI `summary`） | `@name 用户列表` |
| `@desc` | 详细说明（支持多行，写到下一行即可） | `@desc 分页返回用户列表` |
| `@route` | 请求路径，`{name}` 表示路径变量 | `@route /api/user/{id}` |
| `@method` | HTTP 方法，默认 `GET` | `@method POST` |
| `@tag` / `@group` | 分组，可写多个 | `@tag 用户` |
| `@auth` | 鉴权方式：`bearer`、`apikey`、`none` | `@auth bearer` |
| `@param` | 请求参数 | `@param int page=1 页码` |
| `@body` | 请求体（JSON 示例或 `form`/`multipart`/`text`） | `@body {"name":"张三"}` |
| `@bodyParam` | 表单请求体的字段 | `@bodyParam file avatar 头像文件` |
| `@response` | 响应，可写多个状态码 | `@response 400 {"code":400}` |
| `@deprecated` | 标记为废弃 | `@deprecated` |
| `@ignore` | 完全排除这个接口 | `@ignore` |

### `@param` 的写法

```
@param <类型> <名字>[=<默认值>] <说明>
```

- 类型后面加 `!` 表示必填：`@param int! id 订单 ID`
- 名字后面跟 `=值` 表示默认值：`@param int page=1 页码`
- 类型映射：`int`/`integer` → `integer`，`float`/`number` → `number`，`bool` → `boolean`，`string[]` → `array`，`object`/`map`/`json` → `object`，其它一律 `string`
- 支持联合类型，取第一个非 `null` 的：`@param int|null id 订单 ID`

### `@response` 的写法

```
@response [状态码] {JSON 示例}
```

不写状态码就是 `200`。状态码的中文描述（`参数错误`、`未登录或登录已过期`……）是自动补的。

```php
@response 200 {"code":200,"data":{"id":1}}
@response 400 {"code":400,"message":"参数错误"}
```

JSON 里的结构会**自动反推成 JSON Schema**，包括嵌套对象和数组，所以 Swagger UI 里能看到完整的字段树。

### `@body` 的写法

```php
@body {"name":"张三","age":18}          // JSON 请求体，自动推断 schema
@body form                              // 表单
@body form name=张三 age=18             // 表单 + 示例字段
@body multipart                         // 文件上传，字段用 @bodyParam 声明
```

---

## URL 从哪来

`@route` 是最可靠的方式，写什么就是什么。但如果你不想为每个方法都手写一遍，apidoc 也能按 **ThinkPHP 的 pathinfo 约定**推：

```
app/api/controller/Translate.php::text()
    namespace app\api\controller   ->  /api
    类名 Translate                 ->  translate
    方法 text                      ->  text
                    ↓
            /api/translate/text
```

规则：

- 前缀优先取配置里 `controllers[].prefix`；没配就看命名空间 —— `app\{应用}\controller` → `/{应用}`，`app\controller` → 根
- 命名空间不符合这两个约定时**完全不推导**，不会给别的框架瞎编 URL
- 推导出来的值伴随一条 `route.inferred` notice，方便区分哪些是猜的
- 写了 `@route` 的永远以你写的为准，推导不会覆盖它

不想要这个行为就加 `--no-infer`，或者在配置里写 `'route' => ['infer' => false]`。

## 零注释起步

老项目想先看看「到底有多少接口」，可以先跑一次：

```bash
vendor/bin/apidoc generate --all
```

`--all` 会把**没有 apidoc 标签的方法也全部纳入**：

- URL 用上面的规则推导
- HTTP 方法从方法名猜（`saveCode` 这种驼峰写法也认）：`save_*` / `create_*` / `send_*` / `notify` → `POST`，`remove_*` / `delete_*` / `clear_*` / `cancel_*` → `DELETE`，`update_*` / `edit_*` → `PUT`，其余 `GET`
- `summary` 用方法名
- 参数从方法签名反射出来
- 每一条都带 `endpoint.undocumented` notice，明确标明这是猜的

拿到这张接口地图之后，再往注释里补 `@name` / `@param` / `@response`，文档质量自然就上来了。

## 命令行

```
apidoc generate [选项]     扫描并写出文档
apidoc validate [选项]     只检查，不写任何文件
apidoc version             打印版本
apidoc help                帮助
```

| 选项 | 说明 |
|---|---|
| `-c`, `--config <文件>` | 指定配置文件 |
| `--no-reflection` | 不用 PHP 反射补参数 |
| `--strict` | 把 warning 也当成错误 |
| `--no-fail-on-empty` | 一个接口都没扫到时也不报错 |
| `--all` | 连没有 apidoc 标签的方法也一并文档化（见「零注释起步」） |
| `--no-infer` | 不自动推导缺失的 `@route` |
| `-v`, `--verbose` | 打印扫描的目录和 notice 级诊断 |
| `-q`, `--quiet` | 只打印错误 |
| `-h`, `--help` | 帮助 |

**退出码**（方便挂 CI）：

| 码 | 含义 |
|---|---|
| `0` | 成功 |
| `1` | 跑不起来（配置错、文件不可写……） |
| `2` | 文档生成了，但有 error 级问题（比如两个接口占用同一个 `方法 + 路径`） |

`validate` 会额外检查重复路由、缺失路由、缺失标题，正好适合放进 pre-commit 或 CI：

```bash
vendor/bin/apidoc validate --strict
```

所有诊断码（共 15 个）的含义和修法见 [docs/diagnostics.md](docs/diagnostics.md)。

---

## 配置项

```php
<?php

return [
    // 要扫描的控制器目录，可以是字符串、字符串数组，或带命名空间前缀的数组
    'controllers' => [
        ['path' => 'app/Api/Controller', 'namespace' => 'App\\Api\\Controller'],
    ],

    // 输出文件，键是格式（json / yaml），值可以是相对路径
    'output' => [
        'json' => 'public/openapi.json',
        'yaml' => 'public/openapi.yaml',
    ],

    // OpenAPI 的 info 段
    'info' => [
        'title' => '接口文档',
        'version' => '1.0.0',
        'description' => '',
    ],

    'servers' => ['http://localhost:8000'],

    // 全局鉴权
    'security' => [
        'schemes' => [
            'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
        ],
        'default' => ['bearerAuth' => []],
    ],

    // 标签说明
    'tags' => ['用户' => '用户中心相关接口'],

    // 扫描细节
    'scan' => [
        'suffix' => 'Controller.php',
        'exclude' => ['vendor', 'node_modules', 'tests', 'runtime'],
    ],

    'strict' => false,                 // 等同 --strict
    'fail_on_empty' => true,           // 一个接口都没扫到就失败
    'reflection' => true,              // 用反射补参数
    'include_undocumented' => false,   // 等同 --all

    'route' => [
        // annotation（默认，只信 @route）/ thinkphp / auto
        'source' => 'annotation',
        // 没有写 @route 时，是否按 ThinkPHP 约定从命名空间推导
        'infer' => true,
    ],
];
```

相对路径一律相对于**配置文件所在目录**解析。

---

## 它是怎么工作的

```
ControllerScanner   遍历目录，按后缀 + exclude 规则找候选文件
      ↓
SourceScanner       用 token_get_all 解析出类、命名空间、public 方法
      ↓
DocBlockParser      把注释拆成标签（@name 后面的值、多行说明……）
      ↓
EndpointBuilder     把标签变成 ApiEndpoint（参数、请求体、响应、路径变量）
      ↓
ReflectionEnricher  可选：用 PHP 反射补齐方法签名里的参数
      ↓
RouteMatcher        可选：用框架路由表校验并补全 @route
      ↓
OpenApiBuilder      渲染成 OpenAPI 3.0 数组（顺手从 JSON 示例推 schema）
      ↓
DocumentWriter      写 JSON / YAML
```

整体的设计原则是 **能少猜就少猜**：

- 反射只做补全，不做反向校验 —— 因为 PHP 框架里大量参数是从 `Request::get()` 拿的，签名里根本没有，按签名校验会满屏误报。
- 路由表（如果配了）是**运行时真相**，如果和 `@route` 冲突，以路由表为准，但会给你一条 warning。
- 所有能修的问题都变成诊断信息（error / warning / notice），而不是直接崩掉。

---

## 与 ThinkPHP 配合

apidoc 不依赖任何框架，ThinkPHP 项目用起来和普通 PHP 项目没区别 —— 把控制器目录配好就行。只有两个 ThinkPHP 特有的点：

**1. 文件后缀。** ThinkPHP 8 默认 `controller_suffix = false`，控制器文件叫 `Auth.php` 而不是 `AuthController.php`，而 apidoc 默认只扫 `*Controller.php`：

```php
'scan' => ['suffix' => '.php'],
```

**2. URL 从哪来。** 看你的项目是哪种写法：

- **pathinfo 自动路由**（多应用模式的默认做法，不写 `route/*.php`）—— 这种项目 ThinkPHP 的路由表里**没有业务路由**（只有内置的 `/<MISS>`），所以 `@route` 得自己写。
- **注册式路由**（写了 `Route::get('user/detail', 'User/detail')`）—— 可以配 `route.source = thinkphp`，apidoc 会引导你的应用、读出真实路由表，与 `@route` 交叉校验，不一致时**以真实路由为准**并给出 warning；`@route` 没写但路由表里有，还会自动填上。

细节和排查工具见 [docs/thinkphp.md](docs/thinkphp.md)。

---

## 开发

```bash
composer install
composer test        # phpunit
composer analyse     # phpstan level max
composer check       # 两个都跑
```

想快速看效果，仓库里带了完整的例子：

```bash
php bin/apidoc generate -c examples/apidoc.php
# 产物在 build/openapi.json 和 build/openapi.yaml
```

---

## 许可证

MIT
