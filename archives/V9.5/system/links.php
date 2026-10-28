<?php
/**
 * FlyLog V9.0 友情链接
 *
 * 数据落在 V9.0 新建的 links 表。三个设计要点：
 *   - 表可能还没建出来（老库升级中途、或建表权限不足），所以每个函数都先 fly_db_has_table 兜底，
 *     没表就当作「没有链接」，站点照常跑，绝不因为一个附属功能把整站拖成 500；
 *   - URL 只放行 http / https，挡掉 javascript: / data: / vbscript: 这类伪协议，
 *     否则后台存一条进去，前台渲染成 <a href="javascript:..."> 就是存储型 XSS；
 *   - 排序号 sort_no 升序、同序号按 id 升序，结果稳定可预测（不会每刷新一次顺序就变）。
 */
if (!defined('FLY_BOOTSTRAPPED')) { exit; }

/** links 表可用？（老库升级中途可能还没建出来） */
function fly_link_ready()
{
    return function_exists('fly_db_has_table') && fly_db_has_table('links');
}

/**
 * 规范化链接地址：缺协议时补 http://，并剔除控制字符。
 * 注意只做「补协议」，不做合法性判定——判定交给 fly_link_url_ok()。
 */
function fly_link_normalize_url($url)
{
    $url = trim((string)$url);
    // 剔除控制字符与换行：这类字符可能破坏 HTML 属性或响应头
    $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . ltrim($url, '/');
    }
    return $url;
}

/**
 * 链接地址是否可存：只允许 http / https，且必须能解析出主机名。
 * 这一步是前台输出的安全底线，不能省。
 */
function fly_link_url_ok($url)
{
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    $host = @parse_url($url, PHP_URL_HOST);
    return is_string($host) && $host !== '';
}

/** 取链接列表
 * @param bool $onlyVisible true = 只要前台可见的（后台管理传 false 看全部）
 * @return array
 */
function fly_links($onlyVisible = true)
{
    if (!fly_link_ready()) {
        return array();
    }
    try {
        $q = fly_db()->table('links');
        if ($onlyVisible) {
            $q->where('visible', 1);
        }
        $rows = $q->order('sort_no', 'ASC')->order('id', 'ASC')->all();
    } catch (Exception $e) {
        if (function_exists('fly_log')) {
            fly_log('links list fail: ' . $e->getMessage());
        }
        return array();
    }
    return is_array($rows) ? $rows : array();
}

/** 按 ID 取一条 */
function fly_link_find($id)
{
    if (!fly_link_ready()) {
        return null;
    }
    try {
        return fly_db()->table('links')->where('id', (int)$id)->one();
    } catch (Exception $e) {
        if (function_exists('fly_log')) {
            fly_log('links find fail: ' . $e->getMessage());
        }
        return null;
    }
}

/**
 * 按字符数截断（超长入库会被 DB 截断甚至报错，这里先友好地切一刀）。
 * 名字带 fly_link_ 前缀，避免和核心函数抢命名空间。
 */
function fly_link_cut($str, $max)
{
    $str = (string)$str;
    $max = (int)$max;
    if (function_exists('mb_substr')) {
        return mb_substr($str, 0, $max, 'UTF-8');
    }
    return substr($str, 0, $max);
}

/** 白名单过滤 + 类型收敛：只放行真实存在的列，多余字段直接丢弃 */
function fly_link_fields($input)
{
    $out = array();
    $out['name'] = isset($input['name']) ? fly_link_cut(trim((string)$input['name']), 100) : '';
    $out['url'] = isset($input['url']) ? fly_link_cut(fly_link_normalize_url($input['url']), 500) : '';
    $out['description'] = isset($input['description']) ? fly_link_cut(trim((string)$input['description']), 255) : '';
    $out['logo'] = isset($input['logo']) ? fly_link_cut(trim((string)$input['logo']), 500) : '';
    $out['sort_no'] = isset($input['sort_no']) ? (int)$input['sort_no'] : 0;
    $out['visible'] = empty($input['visible']) ? 0 : 1;
    return $out;
}

/**
 * 新增链接
 * @return array array(true, $newId) 或 array(false, $errMsg)
 */
function fly_link_add($input)
{
    if (!fly_link_ready()) {
        return array(false, '友情链接表尚未就绪，请稍后重试。');
    }
    $data = fly_link_fields($input);
    if ($data['name'] === '') {
        return array(false, '请填写站点名称。');
    }
    if (!fly_link_url_ok($data['url'])) {
        return array(false, '链接地址无效，请填写完整的 http(s) 网址。');
    }
    $data['created_at'] = date('Y-m-d H:i:s');
    try {
        $id = fly_db()->insert('links', $data);
    } catch (Exception $e) {
        if (function_exists('fly_log')) {
            fly_log('links add fail: ' . $e->getMessage());
        }
        return array(false, '保存失败，请稍后重试。');
    }
    return array(true, (int)$id);
}

/**
 * 更新链接
 * @return array array(true, '') 或 array(false, $errMsg)
 */
function fly_link_update($id, $input)
{
    if (!fly_link_ready()) {
        return array(false, '友情链接表尚未就绪，请稍后重试。');
    }
    $id = (int)$id;
    if (!fly_link_find($id)) {
        return array(false, '该链接不存在。');
    }
    $data = fly_link_fields($input);
    if ($data['name'] === '') {
        return array(false, '请填写站点名称。');
    }
    if (!fly_link_url_ok($data['url'])) {
        return array(false, '链接地址无效，请填写完整的 http(s) 网址。');
    }
    try {
        fly_db()->update('links', $data, 'id = ?', array($id));
    } catch (Exception $e) {
        if (function_exists('fly_log')) {
            fly_log('links update fail: ' . $e->getMessage());
        }
        return array(false, '保存失败，请稍后重试。');
    }
    return array(true, '');
}

/** 删除链接 */
function fly_link_delete($id)
{
    if (!fly_link_ready()) {
        return false;
    }
    try {
        return fly_db()->delete('links', 'id = ?', array((int)$id)) > 0;
    } catch (Exception $e) {
        if (function_exists('fly_log')) {
            fly_log('links delete fail: ' . $e->getMessage());
        }
        return false;
    }
}
