<?php
/**
 * FlyLog V7.0 第二批 点赞接口（前台访客 AJAX 调用）
 * ------------------------------------------------------------------
 *  POST：id + _csrf  → 切换当前 IP 对该文章的点赞状态（去重）。
 *  返回 JSON：{code, liked, likes}。
 *  安全：CSRF 校验（与全站表单一致）+ 同 IP 同文章 1 秒 1 次轻量节流（防刷）。
 */
define('FLY_NO_COMPRESS', true);   // JSON 不参与 HTML 压缩
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fly_json(array('code' => 405, 'message' => 'Method Not Allowed'), 405);
}

fly_csrf_check();   // #20(P3) 无有效 token 直接 400 终止

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    fly_json(array('code' => 400, 'message' => '缺少文章 id'), 400);
}

// 文章必须存在且已对外发布（软删除 / 草稿 / 定时未到 都不接受点赞）
$post = BasePost::find($id);
if (!$post) {
    fly_json(array('code' => 404, 'message' => '文章不存在'), 404);
}

$ip = fly_client_ip();

// 轻量节流：同 IP 同文章 1 秒内重复请求直接返回当前状态（不重复落库）
$throttleKey = 'like:' . $ip . ':' . $id;
if ((int)fly_cache_get($throttleKey, 2) > 0) {
    $state = fly_post_like_state($id, $ip);
    $likes = (int)fly_db()->value('SELECT likes FROM posts WHERE id = ?', array($id));
    fly_json(array('code' => 0, 'liked' => $state, 'likes' => $likes, 'throttled' => true));
}
fly_cache_set($throttleKey, 1, 2);

$res = fly_post_like_toggle($id, $ip);
fly_json(array('code' => 0, 'liked' => $res['liked'], 'likes' => $res['likes']));
