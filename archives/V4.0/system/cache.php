<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 文件缓存层
 * 串行化存到 cachedir/，带 TTL；静态化模式下可显著降低数据库压力。
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
            $r->auth($pass);
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
    return rtrim(FLY_CACHE, '/\\') . '/' . md5($key) . '_' . substr($safe, -40) . '.cache';
}

/** 读缓存（未命中/过期返回 null） */
function fly_cache_get($key, $ttl = 300)
{
    $r = fly_cache_redis();
    if ($r) {
        $v = $r->get($key);
        return $v === false ? null : @unserialize($v);
    }
    $file = fly_cache_path($key);
    if (!is_file($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = @unserialize($raw);
    if (!is_array($data) || !array_key_exists('expire', $data) || !array_key_exists('value', $data)) {
        return null;
    }
    if ($data['expire'] > 0 && $data['expire'] < time()) {
        @unlink($file);
        return null;
    }
    return $data['value'];
}

/** 写缓存（$ttl 秒，0 表示长期） */
function fly_cache_set($key, $value, $ttl = 300)
{
    $r = fly_cache_redis();
    if ($r) {
        $r->setex($key, $ttl > 0 ? (int)$ttl : 31536000, serialize($value));
        return $value;
    }
    if (!is_dir(FLY_CACHE)) {
        @mkdir(FLY_CACHE, 0755, true);
    }
    $payload = array('expire' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value);
    @file_put_contents(fly_cache_path($key), serialize($payload), LOCK_EX);
    return $value;
}

/** 记日志到 logsdir/（按天分文件） */
function fly_log($message, $file = 'app')
{
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
        $r->del($key);
        return;
    }
    $file = fly_cache_path($key);
    if (is_file($file)) {
        @unlink($file);
    }
}

/** 清空缓存目录 */
function fly_cache_clear()
{
    $r = fly_cache_redis();
    if ($r) {
        $n = 0;
        foreach ($r->keys('*') as $k) {
            if ($r->del($k)) { $n++; }
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
