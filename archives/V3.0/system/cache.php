<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 文件缓存层
 * 串行化存到 cachedir/，带 TTL；静态化模式下可显著降低数据库压力。
 */

/** 缓存文件路径（key 转安全文件名） */
function fly_cache_path($key)
{
    $safe = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $key);
    return rtrim(FLY_CACHE, '/\\') . '/' . md5($key) . '_' . substr($safe, -40) . '.cache';
}

/** 读缓存（未命中/过期返回 null） */
function fly_cache_get($key, $ttl = 300)
{
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
    $file = fly_cache_path($key);
    if (is_file($file)) {
        @unlink($file);
    }
}

/** 清空缓存目录 */
function fly_cache_clear()
{
    $n = 0;
    foreach (glob(rtrim(FLY_CACHE, '/\\') . '/*.cache') as $f) {
        if (@unlink($f)) {
            $n++;
        }
    }
    return $n;
}
