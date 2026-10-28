<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 系统引导（bootstrap）
 * 由根目录 config.php 在定义完数据库/站点配置后引用。
 * 职责：定义目录常量 → 建立受保护目录 → 加载内核模块 → 加固会话
 *       → 错误处理入 logsdir → 触发 init 钩子 → 加载启用插件。
 */

if (!defined('FLY_BOOTSTRAPPED')) {
    define('FLY_BOOTSTRAPPED', true);

    // ---------- 目录常量（四目录独立划分） ----------
    $root = dirname(__DIR__);                 // V2.0 根
    define('FLY_ROOT', $root);
    define('FLY_VERSION', '9.5');
    define('FLY_SYSTEM', $root . '/system');       // 系统内核
    define('FLY_CACHE', $root . '/cachedir');      // 缓存 / 静态化
    define('FLY_LOGS', $root . '/logsdir');        // 日志
    define('FLY_DATA', $root . '/datadir');        // 数据（SQLite / 上传）
    define('FLY_TPL', $root . '/templates');       // 模板 / 主题
    define('FLY_PLUGINS', $root . '/plugins');     // 插件

    // ---------- 建立目录 + 防 WEB 直闯 ----------
    $dirs = array(FLY_SYSTEM, FLY_CACHE, FLY_LOGS, FLY_DATA, FLY_DATA . '/uploads', FLY_TPL, FLY_PLUGINS);
    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        // 双保险：.htaccess 拒绝 + index.php 空守卫（应对不支持 .htaccess 的环境）
        $guard = $d . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        $idx = $d . '/index.php';
        if (!is_file($idx)) {
            @file_put_contents($idx, "<?php\n// 安全守卫：禁止列目录\nhttp_response_code(404);\nexit('Not Found');\n");
        }
    }

    // ---------- 加载内核模块 ----------
    require_once FLY_SYSTEM . '/purify.php';   // V6.0 新增：HTML 白名单净化器（防存储型 XSS，需先于 functions 加载）
    require_once FLY_SYSTEM . '/functions.php';
    require_once FLY_SYSTEM . '/hooks.php';
    require_once FLY_SYSTEM . '/cache.php';
    require_once FLY_SYSTEM . '/db.php';
    require_once FLY_SYSTEM . '/template.php';
    require_once FLY_SYSTEM . '/router.php';
    require_once FLY_SYSTEM . '/base.php';
    require_once FLY_SYSTEM . '/taxonomy.php';  // V7.0 分类树 / 标签 / 单页
    require_once FLY_SYSTEM . '/audit.php';     // V7.0 第二批 审计日志
    require_once FLY_SYSTEM . '/upgrade.php';    // V7.0 第三批 程序内自动检查更新
    require_once FLY_SYSTEM . '/theme.php';      // V8.0 主题系统
    require_once FLY_SYSTEM . '/links.php';      // V9.0 友情链接
    require_once FLY_SYSTEM . '/security.php';

    // ---------- 请求计时起点（#75 性能探针：慢请求/慢查询记 logsdir/slow-*.log） ----------
    if (!defined('FLY_START')) {
        define('FLY_START', microtime(true));
    }

    // ---------- #64 内存上限（只升不降：当前已 >=128M 或无限则不动，避免覆盖主机配置） ----------
    // #4(P3) 正确解析 "1.5G"/"512M" 等小数+单位写法，避免误判为 0 反向压低主机配置
    $flyMl = trim((string)ini_get('memory_limit'));
    if ($flyMl !== '' && $flyMl !== '-1' && preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)/i', $flyMl, $mm)) {
        $flyNum = (float)$mm[1];
        $flyUnit = strtolower($mm[2]);
        $flyBytes = $flyNum;
        if ($flyUnit === 'm') { $flyBytes = $flyNum * 1048576; }
        elseif ($flyUnit === 'g') { $flyBytes = $flyNum * 1073741824; }
        elseif ($flyUnit === 'k') { $flyBytes = $flyNum * 1024; }
        if ($flyBytes > 0 && $flyBytes < 134217728) {
            @ini_set('memory_limit', '128M');
        }
    }

    // ---------- 错误处理（入 logsdir） ----------
    if (!is_dir(FLY_LOGS)) {
        @mkdir(FLY_LOGS, 0755, true);
    }
    @ini_set('log_errors', '1');
    @ini_set('error_log', FLY_LOGS . '/php-error-' . date('Y-m-d') . '.log');
    $debug = (bool)fly_config('site.debug', false);
    @ini_set('display_errors', $debug ? '1' : '0');
    register_shutdown_function(function () {
        $e = error_get_last();
        // #6(P3) Warning/Notice/Deprecated 也记录（此前只记 Fatal，线上排错无迹可寻）
        if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING, E_USER_NOTICE, E_RECOVERABLE_ERROR), true)) {
            $fatal = in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true);
            fly_log(($fatal ? 'FATAL: ' : ('PHP' . $e['type'] . ': ')) . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'], $fatal ? 'fatal' : 'php');
        }
        // #75 慢请求探针：整页耗时超过 1 秒记 slow 日志（含 URL 与内存峰值）
        $cost = microtime(true) - FLY_START;
        if ($cost > 1.0) {
            fly_log(sprintf(
                'PAGE %.3fs | %s %s | peak %.1fMB',
                $cost,
                (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
                (string)($_SERVER['REQUEST_URI'] ?? '-'),
                memory_get_peak_usage(true) / 1048576
            ), 'slow');
        }
    });

    // ---------- #2(P2) 安全：强制 HTTPS + 安全响应头（先于 session_start，避免 HTTP 请求先种 cookie 再跳转） ----------
    fly_force_https();
    fly_security_headers();

    // ---------- 加固会话 ----------
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,   // HTTPS 下置 true，HTTP 下 false（避免 cookie 不发）
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        session_start();
    }

    // ---------- 全局异常处理 ----------
    set_exception_handler('fly_exception_handler');

    // ---------- 触发 init 钩子 + 加载插件 ----------
    fly_do_action('init');
    fly_load_plugins();

    // ---------- V6.0 轻量迁移：老库自动补新列（缺列则加，幂等；失败只记日志不致命） ----------
    try {
        fly_db_migrate();
    } catch (Throwable $e) {
        fly_log('migrate error: ' . $e->getMessage(), 'php');
    }

    // ---------- V7.0 数据迁移：老分类字符串搬进分类树（幂等，每次最多搬 50 条） ----------
    try {
        fly_db_migrate_v7();
    } catch (Throwable $e) {
        fly_log('v7 migrate error: ' . $e->getMessage(), 'php');
    }

    // ---------- #168 HTML 输出压缩（去注释/压空白；debug/api/cli/静态构建场景跳过） ----------
    // #5(P3) JSON 输出场景（fly_json）经 FLY_JSON_OUTPUT 常量显式声明，不再靠 SCRIPT_NAME 猜路径
    if (!defined('FLY_NO_COMPRESS') && !defined('FLY_BUILDING') && !defined('FLY_JSON_OUTPUT')
        && php_sapi_name() !== 'cli'
        && strpos((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/api/') === false
        && !fly_config('site.debug', false)) {
        ob_start('fly_compress_html');
    }
}
