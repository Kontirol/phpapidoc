# 诊断码参考

apidoc 不会因为一个小问题就崩掉。它把遇到的所有问题收集成**诊断（Diagnostic）**，跑完后一次性报给你。

每条诊断有三个字段：

| 字段 | 含义 |
|---|---|
| `level` | `error` / `warning` / `notice` |
| `code` | 机器可读的编号，形如 `param.malformed` |
| `message` | 人能读的说明，带上文件路径和行号 |

---

## 级别与退出码

| 级别 | 默认行为 | `--strict` 下 |
|---|---|---|
| `error` | 退出码 `2`，文档照常写出来 | 同左 |
| `warning` | 只打印 | 退出码 `2` |
| `notice` | 只在 `-v` 时打印 | 只在 `-v` 时打印 |

命令行的退出码：

| 码 | 含义 |
|---|---|
| `0` | 一切正常 |
| `1` | 跑不起来（配置缺失、路径不可写、配置文件语法错……） |
| `2` | 文档生成了，但有 error（或 `--strict` 下的 warning） |

想忽略所有诊断，直接看退出码等于 0，就不要加 `--strict`；想让 CI 卡住任何小问题，就加。

---

## 全部诊断码

### 文件与扫描

| 码 | 级别 | 触发条件 | 怎么修 |
|---|---|---|---|
| `file.unreadable` | error | 控制器文件存在但读不出来（权限、被锁） | 检查文件权限；这个文件里的接口不会进文档 |

### 接口

| 码 | 级别 | 触发条件 | 怎么修 |
|---|---|---|---|
| `endpoint.duplicate_route` | error | 两个接口占用了同一个「方法 + 路径」 | 改掉重复的 `@route`；OpenAPI 不允许同一个 `GET /x` 出现两次 |
| `endpoint.no_route` | warning | 有注释但既没有 `@route`，也没有配路由来源 | 补 `@route`，或配置 `route.source` |
| `endpoint.no_summary` | notice | 既没有 `@name` 也没有 `@desc` | 补一个，否则 Swagger UI 里这行是空白的 |

### 参数

| 码 | 级别 | 触发条件 | 怎么修 |
|---|---|---|---|
| `param.malformed` | warning | `@param` 后面的内容不够两段 | 写成 `@param <类型> <名字> <说明>` |
| `param.missing_name` | warning | `@param` 的类型写了，但名字是空的 | 补上名字 |
| `param.undocumented` | notice | 方法签名里有这个参数，但注释里没写 | 不一定是问题：可能是本意。想补就加 `@param`，想彻底关掉反射就用 `--no-reflection` |
| `param.auto_path` | notice | `@route` 里有 `{id}`，但没有对应的 `@param` | 会自动生成一个 string 类型的路径参数；想控制类型和说明就补上 `@param int! id 用户 ID` |

### 请求体与响应

| 码 | 级别 | 触发条件 | 怎么修 |
|---|---|---|---|
| `body.invalid_json` | warning | `@body` 里的 JSON 语法错 | 修 JSON。此时请求体的 schema 会退化成空对象 |
| `response.invalid_json` | warning | `@response` 里的 JSON 语法错 | 同上，响应描述还会保留，但 example 和 schema 没了 |
| `response.duplicate` | notice | 同一个状态码写了两个 `@response` | 后者覆盖前者；删掉多余的那个 |

### 路由校验

| 码 | 级别 | 触发条件 | 怎么修 |
|---|---|---|---|
| `route.ambiguous` | warning | 路由只写了控制器短名（如 `Order/detail`），而项目里有多个同名控制器 | 在路由里写完整命名空间，或者忽略这条诊断（该路由不会被应用） |
| `route.mismatch` | warning | `@route` 和框架路由表不一致 | **以框架为准**，它会覆盖你的 `@route`。改掉注释即可 |
| `route.method_mismatch` | warning | `@method` 和框架路由表不一致 | 同上，框架为准 |
| `route.undocumented` | notice | 框架里有这条路由，但没有任何接口注释声明它 | 给对应的方法补注释，或者确认这个路由是不是废弃了 |

> `route.*` 只有在你配置了 `route.source`（框架路由表）之后才可能出现。默认的 `annotation` 模式下不会产生这些诊断。

匹配用的是「控制器 + 方法」而不是 URL，因为框架给出的控制器名是相对于当前应用的（`Order/detail`），而扫描出的是完整类名（`App\Api\Controller\Order`）。apidoc 会同时用完整名和短名去匹配；短名撞车时宁愿报 `route.ambiguous` 也不猜。

---

## 在代码里读诊断

如果你要把 apidoc 集成进自己的构建脚本，可以直接拿 `GenerationResult`：

```php
use Kontirol\ApiDoc\Application;
use Kontirol\ApiDoc\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/apidoc.php');
$result = (new Application($config))->run();

foreach ($result->diagnostics() as $diagnostic) {
    printf(
        "%s:%d [%s/%s] %s\n",
        $diagnostic->file,
        $diagnostic->line,
        $diagnostic->level,
        $diagnostic->code,
        $diagnostic->message
    );
}

echo $result->endpointCount(), " 个接口\n";
```
