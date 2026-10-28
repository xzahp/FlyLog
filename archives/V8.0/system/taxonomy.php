<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V7.0 内容组织层：分类树 / 标签 / 单页
 *
 * 设计要点
 *  - 分类是树（parent_id），查询子分类文章时要递归展开后代 id；
 *  - 老版本分类是 posts.category 里的字符串，这里全部做了兼容：
 *    fly_cat_resolve() 能同时吃 slug、分类名、数字 id 三种入参；
 *  - slug 允许中文（URL 里 rawurlencode 即可），因此老链接 /?cat=航拍 一字不改继续可用；
 *  - 所有写操作都走 fly_db() 的预处理，且对唯一冲突做友好降级（不 500）。
 */

/* ============================================================
 * 通用：slug
 * ========================================================== */

/** 生成 slug：中文保留，空白/标点转连字符，空串回落成带随机后缀的兜底值 */
function fly_slugify($s, $fallback = 'item')
{
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u', '-', $s);
    $s = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = $fallback . '-' . date('md') . '-' . mt_rand(100, 999);
    }
    return function_exists('mb_substr') ? mb_substr($s, 0, 90) : substr($s, 0, 90);
}

/** 在指定表内生成不重复的 slug（冲突自动加 -2 / -3 …） */
function fly_slug_unique($table, $slug, $excludeId = 0)
{
    $db = fly_db();
    $base = fly_slugify($slug, $table);
    $try = $base;
    $i = 2;
    while (true) {
        $row = $db->one(
            'SELECT id FROM ' . $table . ' WHERE slug = ?' . ($excludeId > 0 ? ' AND id <> ?' : ''),
            $excludeId > 0 ? array($try, (int)$excludeId) : array($try)
        );
        if (!$row) {
            return $try;
        }
        $try = $base . '-' . $i;
        if ($i++ > 50) {
            return $base . '-' . substr(md5($base . microtime(true)), 0, 6);
        }
    }
}

/* ============================================================
 * 分类树
 * ========================================================== */

/** 全部分类（按 sort_order、id 排序）
 *  刻意不做进程内缓存：后台增删改后同一进程内要立刻生效，
 *  分类数量级很小，每次查库的成本可忽略。 */
function fly_cat_all()
{
    try {
        $rows = fly_db()->all('SELECT * FROM categories ORDER BY sort_order ASC, id ASC');
    } catch (Exception $e) {
        fly_log('cat_all fail: ' . $e->getMessage());
        $rows = array();
    }
    return $rows;
}

/**
 * 文章 → 分类标签 HTML（列表项 / 详情页共用）
 * 优先用 V7.0 的 category_id（分类树，可点、有层级），
 * 老库还没回填时回落 posts.category 字符串，链接形态与旧版完全一致。
 */
function fly_cat_label($row)
{
    $cid = (int)($row['category_id'] ?? 0);
    if ($cid > 0) {
        foreach (fly_cat_all() as $c) {
            if ((int)$c['id'] === $cid) {
                return ' · <a href="' . fly_e(fly_cat_url($c)) . '">' . fly_e($c['name']) . '</a>';
            }
        }
    }
    $s = trim((string)($row['category'] ?? ''));
    if ($s !== '') {
        return ' · <a href="' . fly_e(fly_url('category', array('cat' => $s))) . '">' . fly_e($s) . '</a>';
    }
    return '';
}

/** 文章标签 HTML（详情页用；标签为空返回空串） */
function fly_tag_links($postId)
{
    $tags = fly_tags_of((int)$postId);
    if (!$tags) {
        return '';
    }
    $out = '';
    foreach ($tags as $t) {
        $out .= '<a class="tag-chip" href="' . fly_e(fly_url('tag', array('tag' => $t['slug']))) . '">#' . fly_e($t['name']) . '</a>';
    }
    return $out;
}

/**
 * 分类树（递归）。返回扁平数组，每项额外带 level（0 起）与 indent（显示用前缀）。
 * 顺序按父 → 子排列，可直接循环渲染 <select> 或侧栏。
 */
function fly_cat_tree($parentId = 0, $level = 0, &$out = null)
{
    if ($out === null) {
        $out = array();
    }
    foreach (fly_cat_all() as $c) {
        if ((int)$c['parent_id'] !== (int)$parentId) {
            continue;
        }
        $c['level'] = $level;
        $c['indent'] = $level > 0 ? str_repeat('　', $level) . '└ ' : '';
        $out[] = $c;
        fly_cat_tree((int)$c['id'], $level + 1, $out);
    }
    return $out;
}

/** 分类下拉选项 HTML（含层级缩进，$exclude 及其后代会被排除，防把父分类挂到自己下面） */
function fly_cat_options($selected = 0, $exclude = 0, $allowTop = true)
{
    $skip = array();
    if ($exclude > 0) {
        $skip = fly_cat_subtree_ids($exclude);
    }
    $html = $allowTop ? '<option value="0"' . ((int)$selected === 0 ? ' selected' : '') . '>— 未分类 —</option>' : '';
    foreach (fly_cat_tree() as $c) {
        if (in_array((int)$c['id'], $skip, true)) {
            continue;
        }
        $sel = ((int)$selected === (int)$c['id']) ? ' selected' : '';
        $html .= '<option value="' . (int)$c['id'] . '"' . $sel . '>'
            . fly_e($c['indent'] . $c['name']) . '</option>';
    }
    return $html;
}

/** 某分类自身 + 全部后代的 id（用于「分类页把子分类文章也算进来」） */
function fly_cat_subtree_ids($id)
{
    $id = (int)$id;
    if ($id <= 0) {
        return array();
    }
    $ids = array($id);
    $guard = 0;
    do {
        $grown = false;
        foreach (fly_cat_all() as $c) {
            $pid = (int)$c['parent_id'];
            if ($pid > 0 && in_array($pid, $ids, true) && !in_array((int)$c['id'], $ids, true)) {
                $ids[] = (int)$c['id'];
                $grown = true;
            }
        }
    } while ($grown && ++$guard < 20);
    return $ids;
}

/** 祖先链（含自身），用于面包屑：顶级在前 */
function fly_cat_path($id)
{
    $id = (int)$id;
    if ($id <= 0) {
        return array();
    }
    $map = array();
    foreach (fly_cat_all() as $c) {
        $map[(int)$c['id']] = $c;
    }
    if (!isset($map[$id])) {
        return array();
    }
    $chain = array();
    $cur = $id;
    $guard = 0;
    while (isset($map[$cur]) && $guard++ < 20) {
        array_unshift($chain, $map[$cur]);
        $cur = (int)$map[$cur]['parent_id'];
        if ($cur <= 0) {
            break;
        }
    }
    return $chain;
}

/** 按 id / slug / 分类名 找到分类（三种入参都吃，老字符串分类也能命中） */
function fly_cat_resolve($key)
{
    $key = trim((string)$key);
    if ($key === '') {
        return null;
    }
    $db = fly_db();
    try {
        if (ctype_digit($key) && (int)$key > 0) {
            $r = $db->one('SELECT * FROM categories WHERE id = ?', array((int)$key));
            if ($r) {
                return $r;
            }
        }
        $r = $db->one('SELECT * FROM categories WHERE slug = ?', array($key));
        if ($r) {
            return $r;
        }
        return $db->one('SELECT * FROM categories WHERE name = ?', array($key));
    } catch (Exception $e) {
        fly_log('cat_resolve fail: ' . $e->getMessage());
        return null;
    }
}

/** 有则取、无则建（V7.0 老分类字符串搬迁用） */
function fly_cat_ensure($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 0;
    }
    $exist = fly_cat_resolve($name);
    if ($exist) {
        return (int)$exist['id'];
    }
    try {
        return (int)fly_db()->insert('categories', array(
            'name'       => $name,
            'slug'       => fly_slug_unique('categories', $name),
            'parent_id'  => 0,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    } catch (Exception $e) {
        // 并发下可能撞唯一约束：再查一次兜住
        $again = fly_cat_resolve($name);
        return $again ? (int)$again['id'] : 0;
    }
}

/** 分类下的文章数（含子分类） */
function fly_cat_count($id, $withChildren = true)
{
    $db = fly_db();
    $ids = $withChildren ? fly_cat_subtree_ids($id) : array((int)$id);
    if (!$ids) {
        return 0;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    try {
        return (int)$db->value(
            "SELECT COUNT(*) FROM posts WHERE category_id IN ($ph)
             AND deleted_at = 0 AND is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)",
            $params
        );
    } catch (Exception $e) {
        return 0;
    }
}

/** 新建 / 更新分类（slug 留空则按名称生成并保证唯一） */
function fly_cat_save($data, $id = 0)
{
    $db = fly_db();
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        return false;
    }
    $slug = trim((string)($data['slug'] ?? ''));
    $slug = $slug !== '' ? fly_slugify($slug, 'cat') : fly_slug_unique('categories', $name, (int)$id);
    if ($id > 0) {
        // 改 slug 时也要保证不与别人撞
        $slug = fly_slug_unique('categories', $slug, (int)$id);
    }
    $row = array(
        'name'        => $name,
        'slug'        => $slug,
        'parent_id'   => (int)($data['parent_id'] ?? 0),
        'sort_order'  => (int)($data['sort_order'] ?? 0),
        'description' => trim((string)($data['description'] ?? '')),
    );
    try {
        if ($id > 0) {
            $db->update('categories', $row, 'id = ?', array((int)$id));
            return (int)$id;
        }
        $row['created_at'] = date('Y-m-d H:i:s');
        return (int)$db->insert('categories', $row);
    } catch (Exception $e) {
        fly_log('cat_save fail: ' . $e->getMessage());
        return false;
    }
}

/**
 * 删除分类：子分类提升到父级（不做连带删除，避免误删一片文章归属），
 * 文章 category_id 置 0（未分类），文章本身保留。
 */
function fly_cat_delete($id)
{
    $db = fly_db();
    $id = (int)$id;
    if ($id <= 0) {
        return false;
    }
    $cat = fly_cat_resolve($id);
    if (!$cat) {
        return false;
    }
    try {
        // 同时清掉「父分类被误设成自己」的脏数据
        $db->exec('UPDATE categories SET parent_id = ? WHERE parent_id = ?', array((int)$cat['parent_id'], $id));
        $db->exec('UPDATE posts SET category_id = 0 WHERE category_id = ?', array($id));
        $db->delete('categories', 'id = ?', array($id));
        return true;
    } catch (Exception $e) {
        fly_log('cat_delete fail: ' . $e->getMessage());
        return false;
    }
}

/** 分类链接 */
function fly_cat_url($cat)
{
    if (is_array($cat)) {
        $key = isset($cat['slug']) && $cat['slug'] !== '' ? $cat['slug'] : $cat['name'];
        $cid = (int)$cat['id'];
    } else {
        $key = (string)$cat;
        $cid = 0;
    }
    return fly_url('catid', array('cid' => $cid, 'cat' => $key));
}

/* ============================================================
 * 标签
 * ========================================================== */

/** 全部标签（带文章数） */
function fly_tag_all($limit = 0)
{
    $db = fly_db();
    $sql = "SELECT t.*, (SELECT COUNT(*) FROM post_tags pt WHERE pt.tag_id = t.id) AS n
            FROM tags t ORDER BY n DESC, t.id DESC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    try {
        return $db->all($sql);
    } catch (Exception $e) {
        fly_log('tag_all fail: ' . $e->getMessage());
        return array();
    }
}

/** 某篇文章的标签 */
function fly_tags_of($postId)
{
    try {
        return fly_db()->all(
            'SELECT t.* FROM post_tags pt JOIN tags t ON t.id = pt.tag_id
             WHERE pt.post_id = ? ORDER BY t.id ASC',
            array((int)$postId)
        );
    } catch (Exception $e) {
        return array();
    }
}

/** 有则取、无则建 */
function fly_tag_ensure($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 0;
    }
    $db = fly_db();
    try {
        $r = $db->one('SELECT id FROM tags WHERE slug = ? OR name = ?', array($name, $name));
        if ($r) {
            return (int)$r['id'];
        }
        return (int)$db->insert('tags', array(
            'name' => $name,
            'slug' => fly_slug_unique('tags', $name),
            'created_at' => date('Y-m-d H:i:s'),
        ));
    } catch (Exception $e) {
        $r = $db->one('SELECT id FROM tags WHERE slug = ? OR name = ?', array($name, $name));
        return $r ? (int)$r['id'] : 0;
    }
}

/**
 * 设置文章标签（全量覆盖）：传入名称数组，已存在的复用，不存在的自动创建。
 * 空标签、超长标签、超量标签（>20）会被丢弃，防刷。
 */
function fly_tag_set($postId, $names)
{
    $db = fly_db();
    $postId = (int)$postId;
    if ($postId <= 0) {
        return 0;
    }
    if (!is_array($names)) {
        // 兼容 "航拍,教程 入门" 这类一整串输入：逗号 / 空格 / 顿号 都能分
        $names = preg_split('/[,，、\s]+/u', (string)$names);
    }
    $ids = array();
    foreach ($names as $n) {
        $n = trim((string)$n);
        if ($n === '' || fly_len($n) > 50) {
            continue;
        }
        $tid = fly_tag_ensure($n);
        if ($tid > 0 && !in_array($tid, $ids, true)) {
            $ids[] = $tid;
        }
        if (count($ids) >= 20) {
            break;
        }
    }
    try {
        $db->delete('post_tags', 'post_id = ?', array($postId));
        foreach ($ids as $tid) {
            $db->insert('post_tags', array('post_id' => $postId, 'tag_id' => (int)$tid));
        }
    } catch (Exception $e) {
        fly_log('tag_set fail: ' . $e->getMessage());
    }
    return count($ids);
}

/** 按 slug / 名称找标签 */
function fly_tag_resolve($key)
{
    $key = trim((string)$key);
    if ($key === '') {
        return null;
    }
    try {
        $r = fly_db()->one('SELECT * FROM tags WHERE slug = ?', array($key));
        return $r ?: fly_db()->one('SELECT * FROM tags WHERE name = ?', array($key));
    } catch (Exception $e) {
        return null;
    }
}

/** 标签云：按使用次数分级（1-5 级字号） */
function fly_tag_cloud($limit = 30)
{
    $tags = fly_tag_all($limit);
    if (!$tags) {
        return array();
    }
    $max = 1;
    foreach ($tags as $t) {
        $max = max($max, (int)$t['n']);
    }
    foreach ($tags as $i => $t) {
        $tags[$i]['level'] = (int)ceil((int)$t['n'] / $max * 5);
        $tags[$i]['url'] = fly_url('tag', array('tag' => $t['slug']));
    }
    return $tags;
}

/** 删标签（连带清关联，文章不受影响） */
function fly_tag_delete($id)
{
    $db = fly_db();
    try {
        $db->delete('post_tags', 'tag_id = ?', array((int)$id));
        $db->delete('tags', 'id = ?', array((int)$id));
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/* ============================================================
 * 单页 Page
 * ========================================================== */

/** 取一个已发布单页 */
function fly_page_by_slug($slug)
{
    $slug = trim((string)$slug);
    if ($slug === '') {
        return null;
    }
    try {
        return fly_db()->one('SELECT * FROM pages WHERE slug = ? AND status = 1', array($slug));
    } catch (Exception $e) {
        return null;
    }
}

/** 单页列表（$onlyPublished=false 时后台用，含草稿） */
function fly_page_all($onlyPublished = true)
{
    try {
        $sql = 'SELECT id, slug, title, status, sort_order, updated_at FROM pages';
        if ($onlyPublished) {
            $sql .= ' WHERE status = 1';
        }
        return fly_db()->all($sql . ' ORDER BY sort_order ASC, id ASC');
    } catch (Exception $e) {
        return array();
    }
}

/** 新建 / 更新单页 */
function fly_page_save($data, $id = 0)
{
    $db = fly_db();
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '') {
        return false;
    }
    $slug = trim((string)($data['slug'] ?? ''));
    $slug = $slug !== '' ? fly_slugify($slug, 'page') : fly_slug_unique('pages', $title, (int)$id);
    $slug = fly_slug_unique('pages', $slug, (int)$id);
    $row = array(
        'title'      => $title,
        'slug'       => $slug,
        'content'    => (string)($data['content'] ?? ''),
        'status'     => !empty($data['status']) ? 1 : 0,
        'sort_order' => (int)($data['sort_order'] ?? 0),
        'updated_at' => date('Y-m-d H:i:s'),
    );
    try {
        if ($id > 0) {
            $db->update('pages', $row, 'id = ?', array((int)$id));
            return (int)$id;
        }
        $row['created_at'] = date('Y-m-d H:i:s');
        return (int)$db->insert('pages', $row);
    } catch (Exception $e) {
        fly_log('page_save fail: ' . $e->getMessage());
        return false;
    }
}

function fly_page_delete($id)
{
    try {
        fly_db()->delete('pages', 'id = ?', array((int)$id));
        return true;
    } catch (Exception $e) {
        return false;
    }
}
