<?php
/**
 * FlyLog V7.0 第三批：程序内自动检查更新
 *   - fly_version_newer($latest, $current)：标准化版本号后比较（忽略前导 v/V）
 *   - fly_upgrade_fetch($url, $timeout)：带超时的安全 GET（curl 优先，退守 file_get_contents）
 *   - fly_upgrade_check($force)：读「在线升级检查」配置，命中则比对 FLY_VERSION，结果缓存 6h；
 *       返回 null（未启用/无结果/已是最新）或 ['latest','url','notes','current','error'=>false]
 * 由 bootstrap 在审计模块之后引入；仅定义函数，不输出、不 exit。
 */

if (!function_exists('fly_version_newer')) {
    function fly_version_newer($latest, $current)
    {
        $a = preg_replace('/^[^0-9]*/', '', (string)$latest);
        $b = preg_replace('/^[^0-9]*/', '', (string)$current);
        if ($a === '' || $b === '') {
            return false;
        }
        return version_compare($a, $b, '>');
    }
}

if (!function_exists('fly_upgrade_fetch')) {
    function fly_upgrade_fetch($url, $timeout = 5)
    {
        if (!is_string($url) || $url === '') {
            return null;
        }
        $url = (string)$url;
        // #169 仅放行 http/https，杜绝 file:// / ftp:// 等任意协议读取（SSRF 收窄）
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }
        $body = null;
        $ua = 'FlyLog-Upgrade-Checker/' . (defined('FLY_VERSION') ? FLY_VERSION : '0');
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int)$timeout,
                CURLOPT_CONNECTTIMEOUT => (int)$timeout,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ));
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }
        } else {
            $ctx = stream_context_create(array(
                'http'  => array('timeout' => (int)$timeout, 'user_agent' => $ua, 'ignore_errors' => true),
                'https' => array('timeout' => (int)$timeout, 'user_agent' => $ua),
            ));
            $body = @file_get_contents($url, false, $ctx);
        }
        if ($body === false || $body === null) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }
}

if (!function_exists('fly_upgrade_check')) {
    function fly_upgrade_check($force = false)
    {
        // 未启用在线升级检查 → 不发起任何网络请求
        if (!fly_feature('upgrade_check', false)) {
            return null;
        }
        $url = trim((string)fly_opt('upgrade.check_url', ''));
        if ($url === '') {
            return null;
        }

        $cacheKey = 'upgrade_check_v1';
        if (!$force) {
            $cached = fly_cache_get($cacheKey, 21600); // 6 小时
            if ($cached !== null) {
                // 仅当缓存标记“有可用更新”时才返回结果；否则（已是最新/拉取失败）返回 null，仪表盘不显示横幅
                return ($cached['available'] ?? false) ? $cached : null;
            }
        }

        $data = fly_upgrade_fetch($url);
        if (!$data || empty($data['version'])) {
            // 拉取失败：缓存一个短 TTL 的“未检出”占位，避免频繁打外部接口
            fly_cache_set($cacheKey, array('available' => false, 'error' => true), 1800);
            return null;
        }

        $latest = (string)$data['version'];
        if (fly_version_newer($latest, FLY_VERSION)) {
            $result = array(
                'available' => true,
                'latest'    => $latest,
                'url'       => isset($data['url']) ? (string)$data['url'] : '',
                'notes'     => isset($data['notes']) ? (string)$data['notes'] : '',
                'current'   => FLY_VERSION,
                'error'     => false,
            );
            fly_cache_set($cacheKey, $result, 21600);
            return $result;
        }

        // 已是最新：缓存状态（available=false），dashboard 据此不显示横幅
        fly_cache_set($cacheKey, array('available' => false, 'uptodate' => true, 'latest' => $latest, 'current' => FLY_VERSION), 21600);
        return null;
    }
}
