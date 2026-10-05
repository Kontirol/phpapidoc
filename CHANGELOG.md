# 更新日志

本项目遵循[语义化版本](https://semver.org/lang/zh-CN/)，格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)。

## [未发布]

## [0.1.3] - 2026-10-03

### 修复

- **方法名是 PHP 半保留字时，整个方法会被静默丢掉**：`public function list()` 之前扫不到 —— 不报错、不警告、连 notice 都没有，接口就这么从文档里消失了。原因是 PHP 的词法器不把 `list` 当普通标识符（它是 `T_LIST`），而 `SourceScanner` 读方法名时只认 `T_STRING`，读不到就当作「这个 function 没有名字」直接跳过。现在方法名位置接受任何符合标识符形态的 token，类名位置仍然严格（PHP 不允许 `class list`）
- 同样受影响的还有 `print`、`default`、`include`、`require`、`unset`、`isset`、`empty`、`clone`、`case`、`for`、`while`、`return`、`new`、`use`、`match`、`array`、`callable` 等共 62 个名字，全部来自 PHP 手册「semi reserved words」那一节
- `public function &list()` 这种引用返回的写法也一并修好：PHP 8.1 把 `&` 拆成了 `T_AMPERSAND_*`，旧的字符串判断匹配不到，会当成非法 token 放弃

### 变更

- 测试：248 → 251

## [0.1.2] - 2026-10-03

第二个补丁版本，来自同一个真实项目（ThinkPHP 8 挂号系统，16 个接口）的复核。

### 修复

- **推导出的 URL 多剥了一层 `Controller`**：`app\api\controller\OrderController::myorders()` 之前被写成 `/api/order/myorders`，而 ThinkPHP 的 `route.controller_suffix` 出厂值是 `false`，真实地址是 `/api/ordercontroller/myorders`。之前无条件按 `true` 处理，文档里的路径根本调不通
- **推导出的 URL 被转成了下划线命名**：`orderDetail()` 之前写成 `/order_detail`，ThinkPHP 不做驼峰转下划线，真实地址是 `/orderdetail`。带驼峰的类名同样受影响，`VerifyCode` 被写成 `/verify_code/...`，真实地址是 `/verifycode/...`

两个问题叠加后，实测 16 个接口里 11 个路径是错的，且错的那些恰好都是「类名较长 + 方法名带驼峰」的接口。

### 新增

- **URL 约定自动探测**：`route.source = thinkphp` 时，apidoc 会向已启动的 ThinkPHP 应用询问 `route.controller_suffix` 与 `app.url_convert`，无需额外配置即可得到正确路径。约定通过新的 `Route\FrameworkConventionsInterface` 暴露，其它框架的 route source 也可以实现它
- **配置项 `url.controller_suffix`**：`auto`（默认，向框架询问；问不到时按 ThinkPHP 的默认 `false`）/ `true` / `false`
- **配置项 `url.case`**：`auto`（默认）/ `lower`（ThinkPHP 的默认）/ `snake` / `kebab` / `keep`
- `UrlInferrer` 新增 `stripsControllerSuffix()` 与 `segmentCase()` 访问器，便于调试与二次开发

### 变更

- `UrlInferrer` 构造函数新增第 3、4 个参数（`bool $controllerSuffix = false`、`string $segmentCase = CASE_LOWER`）。只传前两个参数时，行为与 ThinkPHP 出厂配置一致
- `ThinkPhpRouteSource` 只 bootstrap 应用一次（规则列表与 URL 约定共用同一次启动），并实现了 `FrameworkConventionsInterface`
- 测试：242 → 248

### 已知限制

- 若 `route.thinkphp.bootstrap` 指向的 `vendor/autoload.php` 里也装了 apidoc（例如在 A 项目里为 B 项目生成文档），composer 的 autoloader 会「后注册者优先」，apidoc 自身的类可能被那份旧副本顶替。这种场景请显式配置 `url.controller_suffix` 与 `url.case`，或改用目标项目自己安装的 apidoc

## [0.1.1] - 2026-10-02

第一个补丁版本，来自一次真实项目的试用（ThinkPHP 8 挂号系统，20 个接口）。

### 修复

- **驼峰方法名导致 HTTP 方法全部猜错**：`sendCode` / `cancelOrder` 之前会先转小写再分词，被拼成一个词（`sendcode`），关键词全部失配、一律猜成 `GET`。现在先按驼峰边界拆分再比对
- **有 `@param` 却没有 `@method` 的接口不再无条件当作 `GET`**：真实项目里大量方法只写了标准 PHPDoc（apidoc 的 `@param` 和 PHPDoc 的 `@param` 同名，因此这些方法会被识别成接口），但没有 apidoc 的 `@method`。现在改为按方法名猜测，并给出 `method.guessed` notice
- HTTP 方法关键词表扩充：`lock`、`unlock`、`close`、`notify`、`callback`、`payment`

### 新增

- 诊断码 `method.guessed`：接口没写 `@method`，当前值是从方法名猜的

## [0.1.0] - 2026-10-02

首个版本。

### 新增

- **注解 DSL**：`@name` `@desc` `@route` `@method` `@tag`（`@group` 别名）`@auth` `@param` `@body` `@bodyParam` `@response` `@deprecated` `@ignore`
- **参数**支持内联默认值（`@param int page=1 页码`）、必填标记（`@param int! id 订单 ID`）和 PHPDoc 类型映射
- **路由路径变量**：`@route /user/{id}` 自动生成 path 参数并强制 `required: true`
- **Schema 推断**：从 `@response` / `@body` 的 JSON 示例递归推断 JSON Schema，含嵌套对象与数组
- **反射补全**：方法签名里的标量参数自动补进文档，容器注入的 class 类型参数自动跳过
- **CLI**：`apidoc generate` / `apidoc validate` / `apidoc version` / `apidoc help`，退出码分 `0/1/2` 三档，可直接接 CI
- **配置文件**支持 PHP、JSON、YAML 三种格式，未指定时按 `apidoc.php` → `.json` → `.yaml` → `.yml` 顺序查找
- **输出**支持 JSON 与 YAML，可同时输出；生成的 `paths`、`responses` 均排序，保证每次生成的 diff 干净
- **URL 推导**：没有写 `@route` 时，按 ThinkPHP 的 pathinfo 约定从命名空间和类名推导，可用 `route.infer = false` 或 `--no-infer` 关闭
- **零注释起步**：`--all` 把没有 apidoc 标签的方法也纳入文档 —— URL 用推导、HTTP 方法从方法名猜、参数从方法签名反射，每一条都带 `endpoint.undocumented` notice
- **路由校验**：`route.source = thinkphp` 时读取 ThinkPHP 运行时的路由表，与 `@route` 交叉校验（不一致时以框架为准并给出 warning）
- **诊断系统**：15 个诊断码，分 `error` / `warning` / `notice` 三级；`--strict` 可把 warning 也当成失败
- 完整的文档：`README.md`、注解速查、[诊断码参考](docs/diagnostics.md)
- PHPStan level max 零错误，194 个测试 / 469 个断言

[未发布]: https://github.com/kontirol/apidoc/compare/v0.1.3...HEAD
[0.1.3]: https://github.com/kontirol/apidoc/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/kontirol/apidoc/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/kontirol/apidoc/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/kontirol/apidoc/releases/tag/v0.1.0
