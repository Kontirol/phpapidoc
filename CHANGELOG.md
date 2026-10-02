# 更新日志

本项目遵循[语义化版本](https://semver.org/lang/zh-CN/)，格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)。

## [未发布]

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
- **URL 推导**：没有写 `@route` 时，按 ThinkPHP 的 pathinfo 约定从命名空间和类名推导（`app\api\controller\Translate::text()` → `/api/translate/text`），可用 `route.infer = false` 或 `--no-infer` 关闭
- **零注释起步**：`--all` 把没有 apidoc 标签的方法也纳入文档 —— URL 用推导、HTTP 方法从方法名猜、参数从方法签名反射，每一条都带 `endpoint.undocumented` notice
- **路由校验**：`route.source = thinkphp` 时读取 ThinkPHP 运行时的路由表，与 `@route` 交叉校验（不一致时以框架为准并给出 warning）
- **诊断系统**：15 个诊断码，分 `error` / `warning` / `notice` 三级；`--strict` 可把 warning 也当成失败
- 完整的文档：`README.md`、注解速查、[诊断码参考](docs/diagnostics.md)
- PHPStan level max 零错误，194 个测试 / 469 个断言

[未发布]: https://github.com/kontirol/apidoc/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/kontirol/apidoc/releases/tag/v0.1.0
