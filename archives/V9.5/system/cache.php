<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V5.5 缓存层
 * JSON 编解码存到 cachedir/（V5.5 起弃用 serialize，杜绝对象注入面），带 TTL；
 * 静态化模式下可显著降低数据库压力。
 * #51 缓存驱动可配：config.php 里 cache.driver = 'file'（默认）| 'redis'
 *   —— redis 需主机装有 phpredis 扩展且已配置连接；不具备条件时自动回退 file。
 */

/** #51 取 Redis 实例（不具备条件返回 null，调用方回退文件缓存） */
function fly_cache_redis()
{
    static $conn = null;
    static $tried = false;
    if ($tried) {
        return $conn;
    }
    $tried = true;
    if (fly_config('cache.driver', 'file') !== 'redis' || !extension_loaded('redis')) {
        return null;
    }
    try {
        $r = new Redis();
        $host = (string)fly_config('cache.redis_host', '127.0.0.1');
        $port = (int)fly_config('cache.redis_port', 6379);
        $timeout = (float)fly_config('cache.redis_timeout', 1.5);
        if (!$r->connect($host, $port, $timeout)) {
            return null;
        }
        $pass = (string)fly_config('cache.redis_pass', '');
        if ($pass !== '') {
            // #40(P2) auth 返回值不再无视：失败即降级 file 驱动，避免 NOAUTH 整站 500
            if (!$r->auth($pass)) {
                return null;
            }
        }
        // #40(P2) ping 验证连接真的可用（connect 成功但需要认证/不可用时提前发现）
        if (!$r->ping()) {
            return null;
        }
        $prefix = (string)fly_config('cache.redis_prefix', 'flylog:');
        $r->setOption(Redis::OPT_PREFIX, $prefix);
        $conn = $r;
    } catch (Throwable $e) {
        return null;
    }
    return $conn;
}

/** 缓存文件路径（key 转安全文件名） */
function fly_cache_path($key)
{
    $safe = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $key);
    // #42(P3) 显式去掉 ..（md5 前缀已阻断穿越，防未来调整拼接顺序时踩坑）
    $safe = str_replace('..', '__', $safe);
    return rtrim(FLY_CACHE, '/\\') . '/' . md5($key) . '_' . substr($safe, -40) . '.cache';
}

/** 读缓存（未命中/过期返回 null）
 *  #39/#40(P2) 缓存内容改 JSON 编解码（替代 serialize）：
 *  即使缓存文件被污染也无法构造 PHP 对象注入；Redis 调用全部 try/catch 降级。 */
function fly_cache_get($key, $ttl = 300)
{
    $r = fly_cache_redis();
    if ($r) {
        try {
            $v = $r->get($key);
            if ($v === false) { return null; }
            $data = json_decode($v, true);
            return is_array($data) && array_key_exists('value', $data) ? $data['value'] : null;
        } catch (Throwable $e) {
            return null;   // Redis 异常静默降级为未命中
        }
    }
    $file = fly_cache_path($key);
    if (!is_file($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !array_key_exists('expire', $data) || !array_key_exists('value', $data)) {
        return null;
    }
    if ($data['expire'] > 0 && $data['expire'] < time()) {
        @unlink($file);
        return null;
    }
    return $data['value'];
}

/** 写缓存（$ttl 秒，0 表示长期）
 *  #45(P3) 临时文件 + rename 原子替换，避免并发读半截文件 */
function fly_cache_set($key, $value, $ttl = 300)
{
    $r = fly_cache_redis();
    if ($r) {
        try {
            // #41(P3) ttl=0 语义统一为长期（redis 用 10 年代替永久，行为与 file 一致）
            $r->setex($key, $ttl > 0 ? (int)$ttl : 315360000, json_encode(array('value' => $value), JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            // 写入失败不影响主流程
        }
        return $value;
    }
    if (!is_dir(FLY_CACHE)) {
        @mkdir(FLY_CACHE, 0755, true);
    }
    $payload = json_encode(array('expire' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value), JSON_UNESCAPED_UNICODE);
    $tmp = fly_cache_path($key) . '.' . getmypid() . '.tmp';
    @file_put_contents($tmp, $payload, LOCK_EX);
    @rename($tmp, fly_cache_path($key));   // 原子替换
    return $value;
}

/** 记日志到 logsdir/（按天分文件）
 *  #44(P3) $file 参数白名单净化，防 ../ 穿越写出 logsdir */
function fly_log($message, $file = 'app')
{
    $file = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$file);
    if ($file === '') { $file = 'app'; }
    if (!is_dir(FLY_LOGS)) {
        @mkdir(FLY_LOGS, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(rtrim(FLY_LOGS, '/\\') . '/' . $file . '-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/** 删除单个缓存 */
function fly_cache_delete($key)
{
    $r = fly_cache_redis();
    if ($r) {
        try { $r->del($key); } catch (Throwable $e) {}
        return;
    }
    $file = fly_cache_path($key);
    if (is_file($file)) {
        @unlink($file);
    }
}

/** 清空缓存目录
 *  #43(P3) Redis 分支改 scan 迭代删除（keys('*') 会阻塞主线程数秒到数十秒） */
function fly_cache_clear()
{
    $r = fly_cache_redis();
    if ($r) {
        $n = 0;
        try {
            $it = null;
            while (($keys = $r->scan($it, '*', 500)) !== false && is_array($keys)) {
                foreach ($keys as $k) {
                    try { if ($r->unlink($k)) { $n++; } } catch (Throwable $e) {}
                }
                if ($it === null || $it === 0 || $it === '0') { break; }
            }
        } catch (Throwable $e) {
            return 0;
        }
        return $n;
    }
    $n = 0;
    foreach (glob(rtrim(FLY_CACHE, '/\\') . '/*.cache') as $f) {
        if (@unlink($f)) {
            $n++;
        }
    }
    return $n;
}
