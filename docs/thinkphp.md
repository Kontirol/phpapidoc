# ThinkPHP 集成

apidoc 不依赖任何框架，ThinkPHP 项目用起来和普通 PHP 项目一样 —— 把控制器目录配好就行。这一页只讲 ThinkPHP 特有的几个点。

---

## 1. 文件后缀

ThinkPHP 8 默认 `controller_suffix = false`，所以控制器文件叫 `Auth.php` 而不是 `AuthController.php`：

```
app/api/controller/
    Auth.php
    Speech.php
    Translate.php
```

而 apidoc 默认只扫 `*Controller.php`，配一下：

```php
'scan' => ['suffix' => '.php'],
```

（或者在你的 `config/route.php` 里把 `controller_suffix` 打开，让文件名带上后缀。）

注意 `controller_suffix` 不只影响**文件名**，还影响**URL**，见下一节。

---

## 2. URL 从哪来

ThinkPHP 项目分两种写法，apidoc 对它们的支持不一样。

### pathinfo 自动路由（多应用模式的默认做法）

不写任何 `route/*.php`，直接创建控制器就能访问：

```
app/api/controller/Translate.php::text()   →   /api/translate/text
```

**这种项目的路由表里没有业务路由。** ThinkPHP 只在 `think\Route` 里注册了一条内置的 `/<MISS>`（404 兜底），所以 `route.source = thinkphp` 帮不上忙。

好在这类项目的 URL 规则极其固定：

```
/{应用名}/{控制器段}/{操作段}
```

两个「段」的拼法取决于应用配置，apidoc 会自己去读：

| 配置项 | 出厂值 | 含义 |
|---|---|---|
| `route.controller_suffix` | `false` | 为 `false` 时，`OrderController` 的段是 **`ordercontroller`**（后缀是类名的一部分）；为 `true` 时是 **`order`**（框架自己补后缀去找类） |
| `app.url_convert` | `true` | URL 转小写。ThinkPHP **不做驼峰转下划线**，所以 `orderDetail()` 的段是 `orderdetail`，不是 `order_detail` |

也就是说：

```
app/api/controller/OrderController.php::myorders()
    namespace app\api\controller   →  /api
    类名 OrderController            →  ordercontroller     （controller_suffix = false）
    方法 myorders                   →  myorders
                    ↓
            /api/ordercontroller/myorders
```

所以 apidoc 会**按这两条规则自动推导**，`@route` 可以直接省掉：

```php
/**
 * @name 翻译
 * @method POST
 */
public function text(): array
```

推出来是 `/api/translate/text`，并附带一条 `route.inferred` notice，方便你知道这个值是猜的。

想精确控制就手写 `@route`（写了永远不会被推导覆盖）；完全不想要推导就加 `--no-infer`。

**项目不走 ThinkPHP 的约定时**（自己写了路由、URL 用 kebab-case 之类），手动指定：

```php
'url' => [
    'controller_suffix' => true,   // auto（默认，问框架）| true | false
    'case' => 'kebab',             // auto（默认）| lower | snake | kebab | keep
],
```

### 注册式路由（写了 `route/*.php` 的项目）

如果项目里用了显式路由：

```php
Route::get('user/detail', 'User/detail');
Route::post('order/create', 'Order/create')->middleware('auth');
```

那就可以让 apidoc 去读真实路由表：

```php
'route' => [
    'source' => 'thinkphp',
    'thinkphp' => [
        // 你的应用入口，相对配置文件所在目录
        'bootstrap' => 'vendor/autoload.php',
    ],
],
```

apidoc 会**在进程内引导你的应用**（`new think\App()` + `initialize()`），然后读 `think\Route::getRuleList()`。之后：

| 情况 | 结果 |
|---|---|
| `@route` 和真实路由不一致 | warning `route.mismatch`，**以真实路由为准** |
| `@method` 和路由的请求方法不一致 | warning `route.method_mismatch`，同样以框架为准 |
| 路由表里有，但没有任何注释声明 | notice `route.undocumented` |
| `@route` 没写，但路由表里有 | 自动填上 |

同一趟引导顺带会把上面那两项 URL 约定读出来，所以读路由表和推导 URL 用的是**同一次启动**，不会重复引导。

### 匹配用的是「控制器 + 方法」，不是 URL

因为框架给出的控制器名是**相对于当前应用**的：

```
Route::get('user/detail', 'User/detail')
                              ↑ 只有短名
```

而扫描出来的是完整类名 `app\api\controller\User`。所以 apidoc 会把每个接口**同时**按全名和短名建立索引：

```
app\api\controller\User::detail   ← 全名
user::detail                      ← 短名
```

两边都能对上。但**短名撞车时不会瞎猜** —— 如果 `app\api\controller\Order` 和 `app\admin\controller\Order` 同时存在，那条路由会被拒绝应用，报 `route.ambiguous`。宁可漏，不误报。

### 引导失败会怎样

分两种情况：

**读路由表失败**（`route.source = thinkphp`，或 `auto` 且进程里已经有 `think\App`）：

如果 `bootstrap` 指向的文件不存在、或者应用初始化抛异常（比如数据库连不上），apidoc 会直接报错退出（退出码 `1`），不会静默降级。消息里会带上具体路径：

```
error: Unable to bootstrap the framework to read its route table (D:\app\vendor\autoload.php): ...
```

**读 URL 约定失败**：不会报错，只是退回配置里的值 —— 未配置时按 ThinkPHP 出厂值（`controller_suffix = false`、`lower`）。因为约定读不到不影响文档生成，只影响推导出来的路径长什么样，没必要为此中断。

`route.source = auto`（默认值）则是「能用就用」：当前进程里能加载到 `think\App` 才去读路由表，否则安静地只用 `@route`。

---

## 3. 排查工具

路由校验结果不对时，用探针脚本看看 ThinkPHP 到底返回了什么：

```bash
php vendor/kontirol/apidoc/tools/inspect-thinkphp.php /path/to/your-project
```

输出分三段：

1. ThinkPHP 版本 + 路由管理器类名
2. `getRuleList()` 的**完整嵌套结构**（每个路由条目的 `method` / `rule` / `route` / `name` / `middleware` 实际值）
3. apidoc 解析出来的路由表

```
ThinkPHP version: 8.0.0
Route manager: think\Route
getRuleList() returned array

=== structure (depth <= 6) ===

root (array, 1)
  0 (array, 7)
    method = 'get'
    rule = 'user/detail'
    name = 'user.detail'
    route = 'User/detail'
    domain = '-'
    pattern (array, 0)
    option (array, 4)
      remove_slash = false
      middleware (array, 1)
        0 = 'auth'

=== as parsed by ThinkPhpRouteSource ===

METHOD PATH                    TARGET
GET    /user/detail            User::detail
```

只读，不会写你的项目任何文件。

**想确认推导出来的 URL 对不对**，不用探针 —— 加 `-v` 跑一次就够，每条推导都会打出结果：

```bash
vendor/bin/apidoc generate -v | grep route.inferred
```

```
OrderController::myorders() has no @route, /api/ordercontroller/myorders was derived from its namespace.
```

拿这个路径去访问一次，能通就说明约定读对了。

---

## 4. 多应用的前缀

多应用模式下 URL 的第一段就是应用名：

| 文件 | URL |
|---|---|
| `app/api/controller/Auth.php::login()` | `/api/auth/login` |
| `app/api/controller/OrderController.php::myorders()` | `/api/ordercontroller/myorders` |
| `app/backend/controller/User.php::list()` | `/backend/user/list` |
| `app/backend/controller/UserController.php::list()` | `/backend/usercontroller/list` |

写 `@route` 时带上这个前缀，或者在 `controllers[].prefix` 里把这个前缀配给整个目录。

**:warning: 推导的边界**：apidoc 只按上面的规则推导，它**不知道**你在 `route/*.php` 里改写过的 URL、域名绑定或者路由前缀。所以：

- 用了自定义路由的接口，请手写 `@route`
- 不确定时跑 `validate -v`，逐条对照 notice 里推导出来的地址和实际能访问的地址

---

## 5. 相关诊断码

| 码 | 级别 | 含义 |
|---|---|---|
| `route.inferred` | notice | `@route` 没写，这条路径是从命名空间推的（消息里带推导结果） |
| `route.undocumented` | notice | 路由表里有这条路由，但没有接口注释声明它 |
| `route.mismatch` | warning | `@route` 和路由表不一致（以框架为准） |
| `route.method_mismatch` | warning | `@method` 和路由表不一致（以框架为准） |
| `route.ambiguous` | warning | 路由只写了控制器短名，而项目里有多个同名控制器 |

全部诊断码见 [diagnostics.md](diagnostics.md)。
