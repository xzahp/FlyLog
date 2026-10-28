<?php
/**
 * FlyLog V7.0 第三批：安装器辅助（环境自检 + 部署协议存证）
 * 由根目录 install.php 在 require bootstrap 之后引入；仅定义函数，不输出、不 exit，便于测试。
 */

if (!function_exists('fly_install_env_check')) {
    function fly_install_env_check()
    {
        $items = array();

        // PHP 版本
        $phpOk = PHP_VERSION_ID >= 70400;
        $items[] = array(
            'label'   => 'PHP 版本',
            'ok'      => $phpOk,
            'req'     => true,
            'detail'  => $phpOk ? ('PHP ' . PHP_VERSION . '（满足 ≥ 7.4）') : ('当前 PHP ' . PHP_VERSION . '，需 ≥ 7.4'),
        );

        // 数据库驱动
        $drivers = array('sqlite' => 'pdo_sqlite', 'mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql');
        foreach ($drivers as $d => $ext) {
            $has = extension_loaded($ext);
            $items[] = array(
                'label'  => '数据库驱动 · ' . $d,
                'ok'     => $has,
                'req'    => ($d === 'sqlite'),
                'detail' => $has ? ('扩展 ' . $ext . ' 可用') : ($d === 'sqlite' ? ('缺失 ' . $ext . '（默认 SQLite 必需）') : ('缺失 ' . $ext . '（选该库时需补装）')),
            );
        }

        // 常用扩展
        $exts = array('json' => 'JSON', 'mbstring' => '多字节', 'hash' => '哈希', 'openssl' => 'OpenSSL', 'curl' => 'cURL', 'gd' => 'GD 图像处理');
        foreach ($exts as $ext => $name) {
            $has = extension_loaded($ext);
            // 必需：json/mbstring/hash（核心，几乎必装）；openssl/curl/gd 有回退，列为可选
            $req = in_array($ext, array('json', 'mbstring', 'hash'), true);
            $items[] = array(
                'label'  => $name . ' 扩展',
                'ok'     => $has,
                'req'    => $req,
                'detail' => $has ? '已启用' : ($req ? '缺失，必需' : '缺失（可选：openssl 影响安全随机数 / curl 影响在线升级检查与图片处理）'),
            );
        }

        // 目录可写性
        $root = defined('FLY_ROOT') ? FLY_ROOT : dirname(__DIR__);
        $cache = defined('FLY_CACHE') ? FLY_CACHE : ($root . '/cachedir');
        $logs = defined('FLY_LOGS') ? FLY_LOGS : ($root . '/logsdir');
        $data = defined('FLY_DATA') ? FLY_DATA : ($root . '/datadir');
        $dirs = array(
            '程序根目录（写入 config.php）' => $root,
            '缓存目录 cachedir'            => $cache,
            '日志目录 logsdir'             => $logs,
            '数据目录 datadir'             => $data,
            '上传目录 datadir/uploads'     => $data . '/uploads',
        );
        foreach ($dirs as $label => $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $w = is_dir($dir) && is_writable($dir);
            $items[] = array(
                'label'  => $label,
                'ok'     => $w,
                'req'    => true,
                'detail' => $w ? '可写' : '不可写，请检查目录权限（CHMOD 755/777）',
            );
        }

        return $items;
    }
}

if (!function_exists('fly_install_record_agreement')) {
    function fly_install_record_agreement($adminUser, $base = null)
    {
        if ($base === null) {
            $base = defined('FLY_DATA') ? FLY_DATA : (dirname(__DIR__) . '/datadir');
        }
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        $path = rtrim($base, '/\\') . '/agreement.json';
        $rec = array(
            'accepted_at'  => date('c'),
            'admin'        => (string)$adminUser,
            'fly_version'  => defined('FLY_VERSION') ? FLY_VERSION : '',
            'privacy'      => true,
            'terms'        => true,
            'copyright'    => true,
        );
        return @file_put_contents($path, json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}

if (!function_exists('fly_install_agreement_exists')) {
    function fly_install_agreement_exists($base = null)
    {
        if ($base === null) {
            $base = defined('FLY_DATA') ? FLY_DATA : (dirname(__DIR__) . '/datadir');
        }
        return is_file(rtrim($base, '/\\') . '/agreement.json');
    }
}
