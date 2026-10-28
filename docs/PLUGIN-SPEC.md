# FlyLog V10.0 插件开发规范（AI 可消费版）

> **这份文件怎么用**：把整份文件复制粘贴给任意 AI，然后说「按这份规范，给我写一个 <你的需求> 插件」。
> 它是从 FlyLog V10.0 真实源码里逐条核对出来的，不是示例伪代码。
> 引擎位置：`V10.0/system/plugin.php`（插件引擎）、`V10.0/system/hooks.php`（钩子内核）。
> 适用版本：FlyLog **V10.0** 及以上（纯原生 PHP，零 Composer、零第三方库）。

---

## §0 给 AI 的硬性指令（照做，别自由发挥）

1. **只能用 §4「可用 API 白名单」里列出的函数。** 禁止凭空发明 `fly_xxx()`——写出来语法检查能过，但运行时直接 Fatal Error 拖垮整站。
2. **只能用 §3「钩子总表」里真实存在的钩子名。** 名字错一个字母，插件会**静默失效**（不报错、不生效），这是本项目最难查的故障。
3. **输出 3 个文件**：`plugin.php`（必需）、`config.php`（可选，有配置项时才写）、`screenshot.png`（可选）。
4. **代码要能直接跑**，不要伪代码、不要 `// TODO`、不要省略号。
5. **所有用户可控内容输出到 HTML 前必须过 `fly_e()`**。
6. **禁止 `echo` 在 `fly_init` / `before_front_render` / `post_saved` 等早期钩子里**（会输出在 `<!DOCTYPE>` 之前，直接破坏页面）。
7. 交付前对照 **§10 自检清单** 逐条自查，并在回复里给出自查结果。

---

## §1 目录与文件约定

```
plugins/
└── <插件目录名>/              ← 只允许 [A-Za-z0-9_-]，小写短横线风格，如 reading-progress
    ├── plugin.php            ← 【必需】主文件，引擎 require_once 它
    ├── config.php            ← 【可选】插件设置页，后台「插件 → 设置」时载入
    └── screenshot.png        ← 【可选】卡片封面，建议 320×180 或 480×320
```

- 目录名 = 插件 slug，必须全局唯一，且要和代码里 `fly_plugin_opt()` 的第一个参数**完全一致**。
- `plugins/` 目录被 `.htaccess` 全站拒绝直接访问，**截图不能直接写 `/plugins/x/screenshot.png`**，后台已内置代理：`admin/plugin-image.php?p=<slug>`（引擎会自动拼，插件作者不用管）。
- 引擎加载时机：`bootstrap.php` → 数据库迁移完成 → `fly_load_active_plugins()` → 逐个 `require_once plugin.php`。**所以插件里可以直接用数据库**，表一定已经存在。

---

## §2 标准插件头（plugin.php 顶部注释）

引擎只解析 `plugin.php` **开头 8KB 内的第一个块注释**。格式是 `字段名: 值`，一行一个。

```php
<?php
/**
 * Plugin Name: 阅读进度条
 * Version: 1.0.0
 * Description: 一句话说明这个插件做什么（后台卡片上显示）
 * Author: 你的名字
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 */
```

| 字段 | 必填 | 说明 |
|---|---|---|
| `Plugin Name` | ✅ | 显示名，缺省时后台退化成目录名 |
| `Version` | ✅ | 形如 `1.0.0` |
| `Description` | 建议 | 一句话描述 |
| `Author` | 建议 | 作者 |
| `Category` | 建议 | 内容展示 / 互动社交 / SEO推广 / 性能图片 / 安全防护 / 数据统计 / 后台增强 / 外观自定义 |
| `Price` | 否 | **本项目统一写 `free`** |
| `Requires` | 否 | 最低核心版本，写 `10.0` |

头注释之后**第一行可执行代码必须是**：

```php
if (!defined('FLY_BOOTSTRAPPED')) { return; }
```

防止被直接 URL 访问时执行。

---

## §3 钩子总表（真实埋点，共 15 个）

`action` = 纯副作用（做点事，无返回值）；`filter` = 加工数据（**必须 `return`**）。
`$priority` 默认 10，数字越小越先执行。

| 钩子名 | 类型 | 触发时机 / 位置 | 回调收到的参数 | 能 echo？ |
|---|---|---|---|---|
| `fly_init` | action | 引导完成，所有插件已加载 | 无 | ❌ 绝对不行 |
| `init` | action | 同上（V2.0 遗留别名，等价） | 无 | ❌ |
| `fly_plugins_loaded` | action | 所有启用插件文件加载完毕 | 无 | ❌ |
| `plugin_loaded_<slug>` | action | 某个插件自己加载完 | `($slug)` | ❌ |
| `fly_head` | action | 前台 `<head>` 内部 | 无 | ✅ **注入 CSS/JS 就挂这个** |
| `fly_foot` | action | 前台页脚前 | 无 | ✅ **注入脚本就挂这个** |
| `fly_before_sidebar` | action | 侧边栏之前 | 无 | ✅ |
| `fly_after_sidebar` | action | 侧边栏之后 | 无 | ✅ |
| `before_front_render` | action | 首页/文章页渲染前 | `('index')` 或 `('post')` | ❌ |
| `the_content` | filter | 文章正文输出前 | `($html, $post)` → 返回 `$html` | —（返回值） |
| `pre_post_save` | filter | 后台保存文章前 | `($data, $id)` → 返回 `$data` | —（返回值） |
| `post_saved` | action | 后台保存文章后 | `($id, $data, $isNew)` | ❌ |
| `fly_comment_posted` | action | 评论提交成功后 | `($commentId, $postId)` | ❌ |
| `fly_admin_head` | action | 后台页面顶部 | `($title, $active)` | ✅ |
| `fly_admin_menu` | filter | 后台菜单数组生成时 | `($nav)` → 返回 `$nav` | —（返回值） |

> ⚠️ **没有 `fly_footer` 这个钩子**，只有 `fly_foot`。写错 = 静默失效。

### 挂载写法

```php
// action
fly_add_action('fly_foot', function () {
    echo '<p>Hello FlyLog</p>';
}, 10);          // 第三个参数 priority 可省略

// filter（必须 return！）
fly_add_filter('the_content', function ($html, $post) {
    return $html . '<hr><p>—— 全文完 ——</p>';
});
```

### `fly_admin_menu` 的 $nav 结构

```php
$nav['<菜单标识>'] = array('菜单标题', '文件名.php', '权限点', '');
```

权限点填 **`manage_settings`**（站长默认有）。示例：

```php
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['myplugin'] = array('我的插件', 'plugin-page.php', 'manage_settings', '');
    return $nav;                       // 忘了 return 菜单会全消失
});
```

---

## §4 可用 API 白名单（只用这些）

### 4.1 钩子
`fly_add_action($tag, $fn, $priority = 10)`、`fly_add_filter($tag, $fn, $priority = 10)`、`fly_do_action($tag, ...$args)`、`fly_apply_filters($tag, $value, ...$args)`

### 4.2 输出与安全
| 函数 | 作用 |
|---|---|
| `fly_e($str)` | HTML 转义输出，**所有动态内容必用** |
| `fly_excerpt($text, $len = 120)` | 摘要截取 |
| `fly_len($str)` | 中文友好的长度 |
| `fly_html_purify($html, $allowEmbed = false)` | 净化不可信 HTML |

### 4.3 站点与 URL
| 函数 | 作用 |
|---|---|
| `fly_base_url()` | 站点根 URL（自动适配域名） |
| `fly_site_name()` / `fly_site_desc()` / `fly_site_keywords()` | 站点信息 |
| `fly_asset($rel)` | 资源 URL（带版本号） |
| `fly_url($type, $params = array())` | 生成伪静态 URL |
| `fly_canonical_url()` | 当前页 canonical |
| `fly_redirect($url)` | 跳转 |
| `fly_json($data, $code = 200)` | 输出 JSON |
| `fly_mode()` | 当前 URL 模式 |

### 4.4 配置读写
| 函数 | 作用 |
|---|---|
| `fly_plugin_opt($slug, $key, $default = '')` | **读本插件配置**（推荐，自动加 `plugin_<slug>_` 前缀） |
| `fly_plugin_set_opt($slug, $key, $value)` | **写本插件配置** |
| `fly_opt($key, $default = null)` | 读全局配置 |
| `fly_set_option($key, $value)` | 写全局配置 |
| `fly_config($key, $default = null)` | 读 config.php 里的静态配置 |
| `fly_feature($key, $default = false)` | 读功能开关 |

### 4.5 数据库（`fly_db()` 返回的 PDO 封装）
```php
fly_db()->all('SELECT * FROM posts WHERE is_draft = 0 ORDER BY id DESC LIMIT 5');
fly_db()->one('SELECT * FROM posts WHERE id = ?', array($id));
fly_db()->value('SELECT COUNT(*) FROM comments');
fly_db()->exec('UPDATE posts SET views = views + 1 WHERE id = ?', array($id));
fly_db()->insert('表名', array('列' => '值'));
fly_db()->update('表名', array('列' => '值'), 'id = ?', array($id));
fly_db()->lastId();
```
链式查询构造器：
```php
fly_db()->table('posts')->select('id,title')->where('is_draft', 0)
        ->whereOp('views', '>', 100)->order('id', 'DESC')->limit(10)->all();
// 可用：select / where / whereRaw / whereOp / whereIn / order / limit / offset / all / one / value / count
```
表存在性判断（**建表前必判**）：`fly_db_has_table('表名')`、`fly_db_has_column('表名', '列名')`

### 4.6 内容组织
`fly_cat_all()`、`fly_cat_url($cat)`、`fly_cat_count($id)`、`fly_tag_all($limit = 0)`、`fly_tags_of($postId)`、`fly_page_by_slug($slug)`、`fly_page_all()`、`fly_slugify($s)`

### 4.7 缓存、日志、杂项
| 函数 | 作用 |
|---|---|
| `fly_cache_get($key, $ttl = 300)` / `fly_cache_set($key, $value, $ttl = 300)` | 文件缓存（**优先用，别每次查库**） |
| `fly_cache_delete($key)` | 删缓存 |
| `fly_log($message, $file = 'app')` | 写日志到 `logsdir/` |
| `fly_client_ip()` | 访客 IP |
| `fly_csrf_token()` / `fly_csrf_field()` | CSRF（后台表单要带） |
| `fly_audit($action, $targetType, $targetId, $detail)` | 写审计日志 |
| `fly_notify($title, $text)` | 站长通知 |
| `fly_reading_time($content)` / `fly_build_toc($content)` | 阅读时长 / TOC |

### 4.8 可用常量
`FLY_ROOT`、`FLY_SYSTEM`、`FLY_PLUGINS`、`FLY_THEMES`、`FLY_TPL`、`FLY_CACHE`、`FLY_DATA`、`FLY_LOGS`、`FLY_VERSION`、`FLY_BOOTSTRAPPED`

---

## §5 配置页 config.php 契约

后台点「设置」时，`admin/plugins.php?configure=<slug>` 会直接 include 这个文件（此时后台头尾、CSRF、管理员鉴权都已就绪）。

**约定的提交协议**：表单带 `<input type="hidden" name="plg_cfg" value="1">`，POST 回来自己处理保存。

```php
<?php
$__slug = 'my-plugin';                       // 必须和目录名一致
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['plg_cfg'] ?? '') === '1') {
    fly_plugin_set_opt($__slug, 'enable', empty($_POST['enable']) ? '0' : '1');
    fly_plugin_set_opt($__slug, 'text',  trim((string)($_POST['text'] ?? '')));
    echo '<p class="msg msg-ok" style="margin:10px 0">配置已保存</p>';
}
$__on  = fly_plugin_opt($__slug, 'enable', '1') === '1';
$__txt = fly_plugin_opt($__slug, 'text', '');
?>
<form method="post">
  <input type="hidden" name="plg_cfg" value="1">
  <label style="display:flex;align-items:center;gap:8px;margin:10px 0">
    <input type="checkbox" name="enable" value="1" style="width:auto" <?= $__on ? 'checked' : '' ?>>
    <b>启用本插件</b>
  </label>
  <label>自定义文字
    <input type="text" name="text" value="<?= fly_e($__txt) ?>" placeholder="留空则显示默认">
  </label>
  <p style="margin-top:12px"><button class="btn" type="submit">保存配置</button></p>
</form>
```

`plugin.php` 里读同一个开关：
```php
if (fly_plugin_opt('my-plugin', 'enable', '1') === '1') { /* 生效 */ }
```

---

## §6 数据库使用规则

### 能直接读的核心表

| 表 | 关键列 |
|---|---|
| `posts` | `id, title, summary, content, category, views, is_pinned, is_draft, publish_at, likes, deleted_at, created_at` |
| `comments` | `id, post_id, parent_id, author, content, status, created_at` |
| `categories` | `id, name, slug, parent_id, sort_order, description` |
| `tags` / `post_tags` | `tags(id,name,slug)`、`post_tags(post_id,tag_id)` |
| `pages` | `id, slug, title, content, status` |
| `options` | **`k` / `v`**（不是 name/value！） |
| `plugins` | `id, name, version, status, installed_at, updated_at` |
| `links` | `id, name, url, description, logo, sort_no, visible` |

> ⚠️ **`posts` 表没有 `status` 列**。查已发布文章用：`WHERE is_draft = 0 AND deleted_at = 0`。

### 插件需要自己的表时

**推荐做法：完全不建表**——数据存 `options`（`fly_plugin_set_opt`）或 `fly_cache_set()`。90% 的插件不需要新表。

确实需要表时，必须做存在性守卫，且只用四库通用写法（不要 AUTO_INCREMENT 字面量、不要反引号、不要 MySQL 专有类型）：

```php
if (!fly_db_has_table('myp_log')) {
    fly_db()->exec("CREATE TABLE IF NOT EXISTS myp_log (
        id INTEGER NOT NULL PRIMARY KEY,
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}
```

> 注意：主键自增写法在四种库里不一样（核心用 `fly_schema($driver)` 统一处理）。插件建表**不要依赖自增**，用雪花/时间戳/自己算最大 id 更稳。

---

## §7 硬约束（违反 = 整站崩 / 静默失效）

| # | 约束 | 后果 |
|---|---|---|
| 1 | 只调 §4 白名单里的函数 | 调不存在的函数 → Fatal Error，全站白屏 |
| 2 | 钩子名必须与 §3 完全一致 | 拼错 → **静默失效**，最难查 |
| 3 | 不在 `fly_init`/`before_front_render`/`post_saved` 里 echo | 输出跑到 `<!DOCTYPE>` 前，页面错乱 |
| 4 | 免费主机禁用了 `disk_free_space()` / `disk_total_space()` | 调用即致命 Error，`@` 压不住，必须 `function_exists()` 守卫 |
| 5 | 本机/线上无 GD 扩展 | 不要写图片裁剪/水印/压缩，交给浏览器 CSS 缩放 |
| 6 | 不用 Composer、不用第三方库、不用命名空间 | 破坏零依赖原则 |
| 7 | 四库兼容（MySQL/MariaDB/PgSQL/SQLite）：只用标准 SQL，不用 `AUTO_INCREMENT` 字面量、不用反引号、不用 MySQL 专有函数 | 换库就崩 |
| 8 | 插件目录名只允许 `[A-Za-z0-9_-]` | 引擎会拒绝加载 |
| 9 | 不要用 `sessions_start` 级别的全局状态污染；不要在插件里 `exit` / `die` | 会截断整页 |
| 10 | 单个 `plugin.php` 建议 ≤ 200 行 | 便于审查与性能 |
| 11 | 高频逻辑必须用 `fly_cache_get/set` 包一层 | 每个请求都查库会拖慢整站 |
| 12 | 所有 echo 的动态内容必须 `fly_e()` | XSS |

---

## §8 已知陷阱（本项目真实踩过）

1. **`fly_footer` 不存在**，正确是 `fly_foot`。
2. **`fly_in_root()` 不存在**（引擎内部才有别的名字），插件里要做路径包含判断就自己写 `realpath()` + `strpos()` 比对。
3. **`options` 表列名是 `k`/`v`**，写成 `name`/`value` 会被 try/catch 吞掉 → 静默失效。
4. **函数名不能和核心重名**：比如 `fly_theme_list()` 已被 V8.0 配色主题占用，新增引擎函数前先全站查重。插件内定义的函数名建议加前缀，如 `myp_xxx()`，且用 `if (!function_exists('myp_xxx'))` 包起来。
5. **`the_content` 只在文章正文生效**，列表页摘要不过这个 filter。
6. **插件加载发生在数据库迁移之后**——所以插件里可以直接查表，但不要指望在插件里建的表当次请求就存在（要 `fly_db_has_table` 守卫）。
7. **停用 = 完全失效，卸载 = 无残留**：卸载会清掉 `plugin_<slug>_` 前缀的 options，所以配置项务必用 `fly_plugin_opt()` 存。

---

## §9 三个完整可跑示例

### 示例 A：外观类（注入 CSS）

```php
<?php
/**
 * Plugin Name: 阅读进度条配色
 * Version: 1.0.0
 * Description: 定制顶部阅读进度条的高度与配色
 * Author: FlyLog
 * Category: 外观自定义
 * Price: free
 * Requires: 10.0
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

fly_add_action('fly_head', function () {
    if (fly_plugin_opt('my-progress', 'enable', '1') !== '1') { return; }
    echo '<style>' . <<<'FLYCSS'
.fly-progress{height:4px !important;background:linear-gradient(90deg,#1f6feb,#22d3ee) !important}
FLYCSS . '</style>';
});
```

### 示例 B：内容类（the_content 过滤 + 缓存）

```php
<?php
/**
 * Plugin Name: 文末版权块
 * Version: 1.0.0
 * Description: 在每篇文章正文末尾自动追加一行版权声明
 * Author: FlyLog
 * Category: 内容展示
 * Price: free
 * Requires: 10.0
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

if (!function_exists('myp_copyright_line')) {
    function myp_copyright_line()
    {
        $tpl = fly_plugin_opt('post-copyright', 'text', '本文为原创，转载请注明出处');
        return '<p class="myp-copyright" style="margin-top:24px;padding:10px 14px;'
             . 'border-left:3px solid #1f6feb;background:#f4f7fb;color:#475569">'
             . fly_e($tpl) . '</p>';
    }
}

fly_add_filter('the_content', function ($html, $post) {
    if (fly_plugin_opt('post-copyright', 'enable', '1') !== '1') { return $html; }
    if (empty($post['id'])) { return $html; }          // 只在文章页追加
    return $html . myp_copyright_line();               // filter 必须 return
});
```

### 示例 C：功能类（后台菜单 + 保存钩子 + 自建页）

`plugin.php`：

```php
<?php
/**
 * Plugin Name: 发布提醒
 * Version: 1.0.0
 * Description: 文章保存后写一条审计日志，并在后台加一个菜单入口
 * Author: FlyLog
 * Category: 后台增强
 * Price: free
 * Requires: 10.0
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }

// 1) 保存后做点事（这里 echo 会破坏页面，只能写日志/写库）
fly_add_action('post_saved', function ($id, $data, $isNew) {
    if (fly_plugin_opt('save-notify', 'enable', '1') !== '1') { return; }
    fly_log('文章#' . (int)$id . ' 已' . ($isNew ? '新建' : '更新'), 'plugin-save-notify');
    fly_audit('post_saved', 'post', (int)$id, ($isNew ? '新建' : '更新') . '：' . ($data['title'] ?? ''));
});

// 2) 给后台加菜单（filter 必须 return）
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['save_notify'] = array('发布提醒', 'save-notify.php', 'manage_settings', '');
    return $nav;
});
```

配套的后台页 `admin/save-notify.php`（菜单指向的文件，照抄 `admin/links.php` 的骨架）：

```php
<?php
require __DIR__ . '/_init.php';          // 后台引导 + 自动输出页面头部
fly_admin_require('manage_settings');    // 权限点要和 fly_admin_menu 里填的一致
?>
<div class="card">
  <h2>最近提醒</h2>
  <p class="hint">日志写在 logsdir/ 下，本页只读最后 20 条。</p>
  <pre class="code-block"><?php
    $f = FLY_LOGS . '/plugin-save-notify.log';
    echo fly_e(is_file($f) ? implode("\n", array_slice(file($f), -20)) : '暂无记录');
  ?></pre>
</div>
<?php fly_admin_foot(); ?>
```

> 后台自建页属于进阶用法，**优先用 §5 的 `config.php`**——它不需要往 `admin/` 目录塞文件。
> 三个必须遵守的点：① `require __DIR__ . '/_init.php'`（不是相对路径）；② 权限点用 `manage_settings`；③ 结尾调 `fly_admin_foot()`。

---

## §10 交付自检清单（AI 写完必须逐条过）

- [ ] 目录名合法 `[A-Za-z0-9_-]`，且与 `fly_plugin_opt()` 的 slug 一致
- [ ] `plugin.php` 头注释字段齐全，`Price: free`，`Requires: 10.0`
- [ ] 头注释后第一行是 `if (!defined('FLY_BOOTSTRAPPED')) { return; }`
- [ ] 用到的每个 `fly_*()` 都在 §4 白名单里（**逐个回查，别凭印象**）
- [ ] 用到的每个钩子名都在 §3 表里（**逐个回查**）
- [ ] filter 回调有 `return`；action 回调没有在早期钩子 echo
- [ ] 所有动态输出过 `fly_e()`
- [ ] 没用 `disk_free_space` / GD / Composer / MySQL 专有 SQL
- [ ] 查询 `posts` 时用 `is_draft = 0 AND deleted_at = 0`（没有 `status` 列）
- [ ] 高频逻辑有缓存；`plugin.php` 语法自检（`php -l`）
- [ ] 有配置项时附 `config.php`，提交协议用 `plg_cfg`

---

## 附录 A：该挂哪个钩子？（决策树）

```
要改前台外观（CSS）              → fly_add_action('fly_head')
要加前台脚本（JS/统计/浮动按钮） → fly_add_action('fly_foot')
要在正文里插东西（文末/首行缩进）→ fly_add_filter('the_content')
要在侧栏加小工具                 → fly_add_action('fly_after_sidebar')
要在文章保存瞬间改数据           → fly_add_filter('pre_post_save')   ← 记得 return $data
要在文章保存后做副作用（推送/通知）→ fly_add_action('post_saved')
评论刚提交                       → fly_add_action('fly_comment_posted')
要在后台加菜单/页面              → fly_add_filter('fly_admin_menu')
要在后台页面顶部插 CSS/JS        → fly_add_action('fly_admin_head')
站点启动就跑一次的定时任务/统计   → fly_add_action('fly_init')       ← 千万不能 echo
```

---

*本规范由 FlyLog V10.0 源码逐条核对生成。新增引擎函数或钩子时，请同步更新 §3 与 §4。*
