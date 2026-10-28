<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) { http_response_code(403); exit('Forbidden'); }
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
    define('FLY_VERSION', '2.0');
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
            @file_put_contents($idx, "<?php\n// 安全守卫：禁止列目录\nhttp_response_code(403);\nexit('Forbidden');\n");
        }
    }

    // ---------- 加载内核模块 ----------
    require_once FLY_SYSTEM . '/functions.php';
    require_once FLY_SYSTEM . '/hooks.php';
    require_once FLY_SYSTEM . '/cache.php';
    require_once FLY_SYSTEM . '/db.php';
    require_once FLY_SYSTEM . '/template.php';
    require_once FLY_SYSTEM . '/router.php';
    require_once FLY_SYSTEM . '/base.php';

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
        if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            fly_log('FATAL: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'], 'fatal');
        }
    });

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

    // ---------- 触发 init 钩子 + 加载插件 ----------
    fly_do_action('init');
    fly_load_plugins();
}
