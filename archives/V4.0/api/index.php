<?php
/**
 * FlyLog V2.0 只读 JSON API（/api/index.php）
 * 路由：?route=posts[&id=][&limit=] | categories | post&id= | comments&post_id= | info
 * 仅只读开放已发布内容；禁 admin/install。
 */
require __DIR__ . '/../config.php';

$route = isset($_GET['route']) ? $_GET['route'] : 'info';
$base = fly_base_url();

function api_post($p, $base)
{
    return array(
        'id' => (int)$p['id'],
        'title' => $p['title'],
        'summary' => ($p['summary'] !== '' && $p['summary'] !== null) ? $p['summary'] : fly_excerpt($p['content'] ?? '', 120),
        'category' => $p['category'],
        'views' => (int)$p['views'],
        'pinned' => (bool)$p['is_pinned'],
        'created_at' => $p['created_at'],
        'url' => $base . 'post/' . (int)$p['id'] . '.html',
    );
}

switch ($route) {
    case 'posts':
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $posts = BasePost::all(array('limit' => $limit));
        $out = array();
        foreach ($posts as $p) { $out[] = api_post($p, $base); }
        fly_json(array('code' => 0, 'count' => count($out), 'data' => $out));
        break;
    case 'post':
        $id = (int)($_GET['id'] ?? 0);
        $p = BasePost::find($id);
        if (!$p) { fly_json(array('code' => 404, 'message' => '文章不存在'), 404); }
        fly_json(array('code' => 0, 'data' => api_post($p, $base)));
        break;
    case 'categories':
        $cats = fly_db()->all(
            "SELECT category, COUNT(*) AS n FROM posts
             WHERE category <> '' AND is_draft = 0
               AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
             GROUP BY category ORDER BY n DESC, category ASC"
        );
        fly_json(array('code' => 0, 'data' => $cats));
        break;
    case 'comments':
        $pid = (int)($_GET['post_id'] ?? 0);
        if ($pid <= 0) { fly_json(array('code' => 400, 'message' => '缺少 post_id'), 400); }
        $cs = BaseComment::forPost($pid, true);
        $out = array();
        foreach ($cs as $c) {
            $out[] = array('id' => (int)$c['id'], 'author' => $c['author'], 'content' => $c['content'], 'parent_id' => (int)$c['parent_id'], 'created_at' => $c['created_at']);
        }
        fly_json(array('code' => 0, 'count' => count($out), 'data' => $out));
        break;
    case 'info':
    default:
        fly_json(array(
            'code' => 0,
            'data' => array(
                'name' => fly_site_name(),
                'desc' => fly_site_desc(),
                'version' => FLY_VERSION,
                'driver' => defined('DB_DRIVER') ? DB_DRIVER : 'sqlite',
                'posts' => (int)fly_db()->value("SELECT COUNT(*) FROM posts WHERE is_draft = 0"),
            ),
        ));
}
