<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V10.0 原生插件引擎（Plugin Engine）
 * ------------------------------------------------------------------
 * 纯原生 PHP 实现，不依赖任何 Composer 包。整套机制由四部分组成：
 *
 *   1) 目录约定：plugins/<插件名>/{plugin.php, config.php, screenshot.png}
 *   2) 头信息：plugin.php 顶部写标准插件头（Plugin Name / Version ...）
 *   3) 数据库状态：plugins 表记录 启用(active) / 停用(inactive)
 *   4) Hook 挂载：插件内用 fly_add_action()/fly_add_filter() 介入核心
 *
 * 核心埋点（V10.0 新增 + 沿用）—— 名字必须一字不差，写错插件会静默失效：
 *   fly_init               引导完成（插件已全部加载，适合统计/定时任务；此处禁止 echo，
 *                          因为它发生在 <!DOCTYPE> 之前，输出会顶乱页面）
 *   fly_plugins_loaded     所有启用插件文件加载完毕
 *   plugin_loaded_<slug>   某个插件加载完（slug 为插件目录名）
 *   fly_head              前台 <head> 内（注入 CSS / meta 挂这里）
 *   fly_foot              前台页脚前（注入 JS 挂这里）
 *   fly_before_sidebar / fly_after_sidebar   侧边栏前后
 *   before_front_render   前台页面渲染前（index.php / post.php 各一次）
 *   fly_admin_head        后台页面顶部
 *   fly_admin_menu        后台菜单数组（filter）
 *   pre_post_save         文章保存前（filter，可改 $data）
 *   post_saved            文章保存后（action：$id, $data, $isNew）
 *   the_content           正文输出前（filter）
 *   fly_comment_posted    评论发表后（action：$commentId, $postId）
 *
 * 插件最小示例（plugins/hello/plugin.php）：
 *   <?php
 *   /*
 *   Plugin Name: Hello FlyLog
 *   Version: 1.0.0
 *   Description: 在页脚说句话
 *   Author: FlyLog
 *   Category: 示例
 *   Price: free
 *   *\/
 *   fly_add_action('fly_foot', function () {
 *       echo '<p>Hello FlyLog V10.0！</p>';
 *   });
 */

// =====================================================================
// 一、目录与安全
// =====================================================================

/** 插件根目录 */
function fly_plugin_root()
{
    return defined('FLY_PLUGINS') ? FLY_PLUGINS : (FLY_ROOT . '/plugins');
}

/**
 * 插件名白名单校验：只允许 字母/数字/下划线/短横线
 * 目录穿越（../、/、\）一律拒绝 —— 插件上传是高危入口，这层必须卡死。
 */
function fly_plugin_safe_name($name)
{
    $name = (string)$name;
    if ($name === '' || strlen($name) > 64) {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
        return '';
    }
    return $name;
}

/** 插件目录绝对路径（名字非法返回空串） */
function fly_plugin_dir($name)
{
    $name = fly_plugin_safe_name($name);
    return $name === '' ? '' : fly_plugin_root() . '/' . $name;
}

/** 真实路径 containment：目标必须仍在插件根目录内 */
function fly_plugin_in_root($path)
{
    $real = realpath($path);
    $root = realpath(fly_plugin_root());
    if ($real === false || $root === false) {
        return false;
    }
    return strpos($real, $root . DIRECTORY_SEPARATOR) === 0 || $real === $root;
}

// =====================================================================
// 二、插件头信息解析
// =====================================================================

/**
 * 解析插件主文件顶部的 PHP 注释头。
 * 只认文件开头 8KB 内的块注释，字段格式： 字段名: 值
 * 标准字段：Plugin Name / Version / Description / Author / Category / Price / Requires
 */
function fly_plugin_header($file)
{
    $def = array(
        'name'        => '',
        'version'     => '1.0.0',
        'description' => '',
        'author'      => 'FlyLog',
        'category'    => '通用',
        'price'       => 'free',
        'requires'    => '10.0',
    );
    if (!is_file($file)) {
        return $def;
    }
    $src = (string)@file_get_contents($file, false, null, 0, 8192);
    if (!preg_match('#/\*.*?\*/#s', $src, $m)) {
        return $def;
    }
    $map = array(
        'plugin name' => 'name',
        'plugin uri'  => 'uri',
        'version'     => 'version',
        'description' => 'description',
        'author'      => 'author',
        'category'    => 'category',
        'price'       => 'price',
        'requires'    => 'requires',
    );
    foreach (preg_split('/\r\n|\n|\r/', $m[0]) as $line) {
        // 去掉注释装饰字符后匹配 "字段: 值"
        $line = trim($line, " \t\r\n*/\x0B");
        if (strpos($line, ':') === false) {
            continue;
        }
        list($k, $v) = explode(':', $line, 2);
        $k = strtolower(trim($k));
        $v = trim($v);
        if (isset($map[$k]) && $v !== '') {
            $def[$map[$k]] = $v;
        }
    }
    return $def;
}

/** 读取单个插件的完整信息（头信息 + 文件存在性 + 数据库状态） */
function fly_plugin_info($name)
{
    $name = fly_plugin_safe_name($name);
    if ($name === '') {
        return null;
    }
    $dir = fly_plugin_dir($name);
    if (!is_dir($dir)) {
        return null;
    }
    $main = $dir . '/plugin.php';
    $head = fly_plugin_header($main);
    if ($head['name'] === '') {
        $head['name'] = $name;   // 没写头就用目录名兜底，不至于在后台显示空白
    }
    $head['slug']       = $name;
    $head['dir']        = $dir;
    $head['main']       = $main;
    $head['has_main']   = is_file($main);
    $head['has_config'] = is_file($dir . '/config.php');
    // plugins/ 目录被 .htaccess 全拒（防 PHP 直闯），截图必须走后台代理读取
    $head['screenshot'] = is_file($dir . '/screenshot.png')
        ? fly_base_url() . '/admin/plugin-image.php?p=' . rawurlencode($name)
        : '';
    $head['status']     = fly_plugin_status($name);
    return $head;
}

/** 扫描 plugins/ 目录，返回全部插件信息（按名称排序） */
function fly_plugin_scan()
{
    $root = fly_plugin_root();
    $list = array();
    if (!is_dir($root)) {
        return $list;
    }
    foreach (glob($root . '/*', GLOB_ONLYDIR) as $d) {
        $info = fly_plugin_info(basename($d));
        if ($info) {
            $list[] = $info;
        }
    }
    usort($list, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    return $list;
}

// =====================================================================
// 三、状态存储（plugins 表）
// =====================================================================

/** 插件状态：active / inactive（表不存在时一律当作未安装·未启用） */
function fly_plugin_status($name)
{
    $name = fly_plugin_safe_name($name);
    if ($name === '' || !fly_db_has_table('plugins')) {
        return 'inactive';
    }
    try {
        $row = fly_db()->one('SELECT status FROM plugins WHERE name = ?', array($name));
    } catch (Exception $e) {
        return 'inactive';
    }
    return $row && $row['status'] === 'active' ? 'active' : 'inactive';
}

/** 是否启用 */
function fly_plugin_is_active($name)
{
    return fly_plugin_status($name) === 'active';
}

/** 写入状态（启用/停用共用），不存在则插入 */
function fly_plugin_set_status($name, $status)
{
    $name = fly_plugin_safe_name($name);
    if ($name === '' || !fly_db_has_table('plugins')) {
        return false;
    }
    $status = $status === 'active' ? 'active' : 'inactive';
    $info = fly_plugin_info($name);
    $version = $info ? $info['version'] : '1.0.0';
    try {
        $db = fly_db();
        $row = $db->one('SELECT id FROM plugins WHERE name = ?', array($name));
        if ($row) {
            $db->update('plugins', array('status' => $status, 'version' => $version), 'id = ?', array($row['id']));
        } else {
            $db->insert('plugins', array(
                'name'    => $name,
                'version' => $version,
                'status'  => $status,
            ));
        }
    } catch (Exception $e) {
        if (function_exists('fly_log')) {
            fly_log('plugin set_status fail: ' . $e->getMessage());
        }
        return false;
    }
    return true;
}

function fly_plugin_activate($name)
{
    $ok = fly_plugin_set_status($name, 'active');
    if ($ok && function_exists('fly_audit')) {
        fly_audit('plugin_activate', 'plugin', $name, '启用插件 ' . $name);
    }
    return $ok;
}

function fly_plugin_deactivate($name)
{
    $ok = fly_plugin_set_status($name, 'inactive');
    if ($ok && function_exists('fly_audit')) {
        fly_audit('plugin_deactivate', 'plugin', $name, '停用插件 ' . $name);
    }
    return $ok;
}

/** 卸载：清数据库记录；$removeFiles=true 时连目录一起删 */
function fly_plugin_uninstall($name, $removeFiles = false)
{
    $name = fly_plugin_safe_name($name);
    if ($name === '') {
        return false;
    }
    // 先停用，避免卸载瞬间插件还在队列里
    fly_plugin_set_status($name, 'inactive');
    if (fly_db_has_table('plugins')) {
        try {
            fly_db()->exec('DELETE FROM plugins WHERE name = ?', array($name));
        } catch (Exception $e) {
            if (function_exists('fly_log')) {
                fly_log('plugin uninstall fail: ' . $e->getMessage());
            }
        }
    }
    // 清掉该插件自己的配置项（key 前缀 plugin_<slug>_）
    // 注意：options 表的列是 k / v（不是 name / value），写错列名会被 try 吞掉变成静默失效
    try {
        fly_db()->exec("DELETE FROM options WHERE k LIKE ?", array('plugin_' . $name . '_%'));
    } catch (Throwable $e) {
        // 表结构异常时不影响主流程
    }
    // 同步清进程内缓存，否则同请求内「卸载后立刻读」还会拿到旧值
    if (function_exists('fly_opt_store')) {
        $opts = &fly_opt_store();
        if (is_array($opts)) {
            foreach (array_keys($opts) as $k) {
                if (strpos($k, 'plugin_' . $name . '_') === 0) {
                    unset($opts[$k]);
                }
            }
        }
    }
    if ($removeFiles) {
        $dir = fly_plugin_dir($name);
        if ($dir && fly_plugin_in_root($dir)) {
            fly_rmdir_recursive($dir);
        }
    }
    if (function_exists('fly_audit')) {
        fly_audit('plugin_uninstall', 'plugin', $name, '卸载插件 ' . $name . ($removeFiles ? '（含文件）' : ''));
    }
    return true;
}

/** 递归删除目录（仅用于插件卸载，内部自带 containment 保护） */
function fly_rmdir_recursive($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $p = $dir . '/' . $f;
        if (is_dir($p)) {
            fly_rmdir_recursive($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}

/** 当前启用的插件名列表 */
function fly_plugin_active_list()
{
    if (!fly_db_has_table('plugins')) {
        return array();
    }
    try {
        $rows = fly_db()->all("SELECT name FROM plugins WHERE status = 'active' ORDER BY id ASC");
    } catch (Exception $e) {
        return array();
    }
    $out = array();
    foreach ($rows as $r) {
        $out[] = $r['name'];
    }
    return $out;
}

// =====================================================================
// 四、插件配置读写（供插件 config.php 使用）
// =====================================================================

/** 读插件配置：fly_plugin_opt('my_plugin', 'text', '默认值') */
function fly_plugin_opt($slug, $key, $default = '')
{
    $v = fly_opt('plugin_' . $slug . '_' . $key, null);
    return $v === null ? $default : $v;
}

/** 写插件配置 */
function fly_plugin_set_opt($slug, $key, $value)
{
    fly_set_option('plugin_' . $slug . '_' . $key, $value);
}

// =====================================================================
// 五、加载启用插件
// =====================================================================

/**
 * 引导阶段调用：include 所有「已启用」插件的 plugin.php。
 * 每个插件加载完立即触发 plugin_loaded_<slug>，全部完成触发 fly_plugins_loaded。
 * 单个插件报错不影响整站 —— 捕获后写日志，继续加载下一个。
 */
function fly_load_active_plugins()
{
    $root = fly_plugin_root();
    if (!is_dir($root)) {
        fly_do_action('fly_plugins_loaded');
        return;
    }
    $active = fly_plugin_active_list();
    foreach ($active as $name) {
        $name = fly_plugin_safe_name($name);
        if ($name === '') {
            continue;
        }
        $main = fly_plugin_root() . '/' . $name . '/plugin.php';
        if (!is_file($main) || !fly_plugin_in_root($main)) {
            continue;
        }
        try {
            require_once $main;
            fly_do_action('plugin_loaded_' . $name, $name);
        } catch (Throwable $e) {
            if (function_exists('fly_log')) {
                fly_log('plugin load error [' . $name . ']: ' . $e->getMessage());
            }
        }
    }
    fly_do_action('fly_plugins_loaded');
}

// =====================================================================
// 六、ZIP 上传安装
// =====================================================================

/**
 * 安装插件包：优先用 ZipArchive，没有 zip 扩展时退化到纯 PHP 解压实现
 * （见 fly_unzip_extract），保证免费主机也能装插件。
 *
 * @param string $tmpFile 上传的临时文件
 * @param string $err     出错信息（引用传出）
 * @return string|false   成功返回插件名，失败 false
 */
function fly_plugin_install_zip($tmpFile, &$err)
{
    $err = '';
    if (!is_file($tmpFile)) {
        $err = '上传文件不存在';
        return false;
    }
    $root = fly_plugin_root();
    if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) {
        $err = '插件目录不可写（plugins/）';
        return false;
    }
    $tmpDir = rtrim(sys_get_temp_dir(), '/\\') . '/flylog_plugin_' . bin2hex(random_bytes(6));
    if (!@mkdir($tmpDir, 0755, true)) {
        $err = '临时目录创建失败';
        return false;
    }

    $ok = false;
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) === true) {
            $ok = $zip->extractTo($tmpDir);
            $zip->close();
        } else {
            $err = 'ZIP 打开失败（文件损坏或不是 zip）';
        }
    } else {
        $ok = fly_unzip_extract($tmpFile, $tmpDir);
        if (!$ok) {
            $err = 'ZIP 解压失败（文件损坏或使用了不支持的压缩算法）';
        }
    }
    if (!$ok) {
        fly_rmdir_recursive($tmpDir);
        return false;
    }

    // 包里可能带一层外层目录（xxx/插件名/...），自动识别真正的插件根目录
    $found = null;
    foreach (glob($tmpDir . '/*/plugin.php') as $m) {
        $found = dirname($m);
        break;
    }
    if ($found === null && is_file($tmpDir . '/plugin.php')) {
        $found = $tmpDir;
    }
    if ($found === null) {
        fly_rmdir_recursive($tmpDir);
        $err = '包内未找到 plugin.php，不是合法的 FlyLog 插件';
        return false;
    }

    $slug = basename($found);
    $slug = fly_plugin_safe_name($slug);
    if ($slug === '') {
        fly_rmdir_recursive($tmpDir);
        $err = '插件目录名不合法（只许字母数字下划线短横线）';
        return false;
    }
    $target = fly_plugin_root() . '/' . $slug;
    if (is_dir($target)) {
        fly_rmdir_recursive($target);   // 覆盖安装：同名先清
    }
    if (!@rename($found, $target)) {
        // 跨分区 rename 可能失败，退化成逐文件拷贝
        fly_copy_dir($found, $target);
    }
    fly_rmdir_recursive($tmpDir);

    $info = fly_plugin_info($slug);
    if (!$info) {
        $err = '插件写入失败';
        return false;
    }
    // 新装插件默认停用，等站长自己点启用（安全默认）
    fly_plugin_set_status($slug, 'inactive');
    if (function_exists('fly_audit')) {
        fly_audit('plugin_install', 'plugin', $slug, '安装插件 ' . $slug . ' v' . $info['version']);
    }
    return $slug;
}

/** 目录拷贝（rename 失败时的退路） */
function fly_copy_dir($src, $dst)
{
    if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
        return false;
    }
    foreach (scandir($src) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $s = $src . '/' . $f;
        $d = $dst . '/' . $f;
        if (is_dir($s)) {
            fly_copy_dir($s, $d);
        } else {
            @copy($s, $d);
        }
    }
    return true;
}

/**
 * 纯 PHP ZIP 解压（无 ext-zip 时的退路）
 * 支持 store(0) 与 deflate(8) 两种压缩方式，覆盖绝大多数插件包。
 * 自带三重防护：目录穿越拦截、文件数量上限、解压总量上限。
 */
function fly_unzip_extract($zipPath, $destDir)
{
    $data = @file_get_contents($zipPath);
    if ($data === false || strlen($data) < 22) {
        return false;
    }
    $len = strlen($data);
    // 定位 End Of Central Directory（从尾部往前找，容忍末尾有注释）
    $eocd = -1;
    for ($i = $len - 22; $i >= 0 && $i >= $len - 66000; $i--) {
        if (substr($data, $i, 4) === "PK\x05\x06") {
            $eocd = $i;
            break;
        }
    }
    if ($eocd < 0) {
        return false;
    }
    $cdCount  = unpack('v', substr($data, $eocd + 10, 2))[1];
    $cdOffset = unpack('V', substr($data, $eocd + 16, 4))[1];
    if ($cdOffset <= 0 || $cdOffset >= $len) {
        return false;
    }

    $maxFiles = 500;         // 插件包不该有几千个文件
    $maxBytes = 20 * 1024 * 1024;
    $written  = 0;
    $p = $cdOffset;

    for ($i = 0; $i < $cdCount && $i < $maxFiles; $i++) {
        if (substr($data, $p, 4) !== "PK\x01\x02") {
            break;
        }
        $method    = unpack('v', substr($data, $p + 10, 2))[1];
        $compSize  = unpack('V', substr($data, $p + 20, 4))[1];
        $uncompSize= unpack('V', substr($data, $p + 24, 4))[1];
        $nameLen   = unpack('v', substr($data, $p + 28, 2))[1];
        $extraLen  = unpack('v', substr($data, $p + 30, 2))[1];
        $commentLen= unpack('v', substr($data, $p + 32, 2))[1];
        $localOff  = unpack('V', substr($data, $p + 42, 4))[1];
        $name      = substr($data, $p + 46, $nameLen);
        $p += 46 + $nameLen + $extraLen + $commentLen;

        // 路径安全：拒绝 .. 与绝对路径
        $name = str_replace('\\', '/', $name);
        if (strpos($name, '..') !== false || strpos($name, ':') !== false) {
            continue;
        }
        $name = ltrim(preg_replace('#/{2,}#', '/', $name), '/');
        if ($name === '' || strpos($name, '/') === 0) {
            continue;
        }
        $target = rtrim($destDir, '/\\') . '/' . $name;

        if (substr($name, -1) === '/') {
            @mkdir($target, 0755, true);
            continue;
        }
        if ($localOff + 30 > $len) {
            continue;
        }
        $lNameLen  = unpack('v', substr($data, $localOff + 26, 2))[1];
        $lExtraLen = unpack('v', substr($data, $localOff + 28, 2))[1];
        $start = $localOff + 30 + $lNameLen + $lExtraLen;
        $raw = substr($data, $start, $compSize);

        if ($method === 8) {
            $content = @gzinflate($raw);
            if ($content === false) {
                $content = @gzuncompress($raw);   // 有些打包器写 zlib 头
            }
            if ($content === false) {
                continue;
            }
        } elseif ($method === 0) {
            $content = $raw;
        } else {
            continue;   // 其它算法（bz2/lzma）不支持
        }

        $written += strlen($content);
        if ($written > $maxBytes) {
            return false;
        }
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        @file_put_contents($target, $content);
    }
    return true;
}
