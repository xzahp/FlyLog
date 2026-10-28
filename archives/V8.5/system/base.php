<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 数据模型基类化（BasePost / BaseMember / BaseComment）
 * 把数据表操作封装成基类，上层只面向对象编程，不直接写 SQL。内部基于 FlyDB 链式查询。
 */

/** 文章模型 */
class BasePost
{
    /** 取一篇（仅已发布、未进回收站，除非 $withDraft） */
    public static function find($id, $withDraft = false)
    {
        $q = fly_db()->table('posts')->where('id', (int)$id);
        if (!$withDraft) {
            // #55(P1) 草稿与未来定时文章对匿名访客不可见（post.php 改用 find($id) 触发本过滤）
            $q->where('is_draft', 0);
            $q->whereRaw('(publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)');
        }
        // V7.0 第二批：回收站里的文章前台不可见（deleted_at > 0 即已软删除）
        $q->whereRaw('deleted_at = 0');
        return $q->one();
    }

    /** 列表（已发布、未删；含置顶优先 + 定时发布过滤 + #57 大偏移量游标优化）
     *  $opt['trash'] = true 时改为只列「回收站」里的文章。 */
    public static function query($opt = array())
    {
        $db = fly_db();
        $q = $db->table('posts');
        // V7.0 第二批：回收站视图与正常视图互斥
        if (!empty($opt['trash'])) {
            $q->whereRaw('deleted_at > 0');
        } else {
            $q->whereRaw('deleted_at = 0');
        }
        $q->whereRaw('is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)');
        $kw = null;
        if (!empty($opt['category'])) {
            $q->where('category', $opt['category']);
        }
        // V7.0 分类树：命中父分类时把它所有后代分类的文章都算进来
        if (!empty($opt['cat_ids']) && is_array($opt['cat_ids'])) {
            $ph = implode(',', array_fill(0, count($opt['cat_ids']), '?'));
            $q->whereRaw('category_id IN (' . $ph . ')', array_map('intval', $opt['cat_ids']));
        }
        // V7.0 标签：子查询做关联过滤，四库通用
        if (!empty($opt['tag_id'])) {
            $q->whereRaw('id IN (SELECT post_id FROM post_tags WHERE tag_id = ?)', array((int)$opt['tag_id']));
        }
        if (!empty($opt['keyword'])) {
            $kw = '%' . $opt['keyword'] . '%';
            $q->whereRaw('(title LIKE ? OR summary LIKE ? OR content LIKE ?)', array($kw, $kw, $kw));
        }
        // 置顶优先，再按 ID 倒序
        $q->order('is_pinned', 'DESC')->order('id', 'DESC');

        $limit = !empty($opt['limit']) ? (int)$opt['limit'] : 0;
        $offset = !empty($opt['offset']) ? (int)$opt['offset'] : 0;

        // #57 大偏移量游标优化：OFFSET >= 500 时先做廉价键查询（只取两列）定位边界行，
        // 主查询改用范围条件走索引，避免 LIMIT 100000, 10 式全量回表。
        // 游标优化要求主查询与键查询条件完全一致；分类树/标签条件下键查询要多带
        // 一段构造，且这类归档页极少翻到 500+ 偏移，直接跳过更安全。
        $heavy = !empty($opt['cat_ids']) || !empty($opt['tag_id']);
        if ($limit > 0 && $offset >= 500 && !$heavy) {
            $keySql = 'SELECT is_pinned, id FROM posts WHERE deleted_at = 0 AND is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)';
            $keyParams = array();
            if (!empty($opt['category'])) {
                $keySql .= ' AND category = ?';
                $keyParams[] = $opt['category'];
            }
            if ($kw !== null) {
                $keySql .= ' AND (title LIKE ? OR summary LIKE ? OR content LIKE ?)';
                $keyParams[] = $kw; $keyParams[] = $kw; $keyParams[] = $kw;
            }
            $keySql .= ' ORDER BY is_pinned DESC, id DESC LIMIT 1 OFFSET ' . $offset;
            $key = $db->one($keySql, $keyParams);
            if ($key) {
                $q->whereRaw('(is_pinned < ? OR (is_pinned = ? AND id <= ?))', array(
                    (int)$key['is_pinned'], (int)$key['is_pinned'], (int)$key['id'],
                ));
                $offset = 0; // 已由范围条件定位，不再需要 OFFSET
            }
        }

        if ($limit > 0) {
            $q->limit($limit);
            if ($offset > 0) {
                $q->offset($offset);
            }
        }
        return $q;
    }
    public static function all($opt = array())
    {
        return self::query($opt)->all();
    }
    public static function count($opt = array())
    {
        return self::query($opt)->count();
    }

    /** 阅读量 +1（#61(P3) 仅对已发布文章计数，避免为草稿/未来定时文章虚增） */
    public static function incrementViews($id)
    {
        fly_db()->exec(
            'UPDATE posts SET views = views + 1 WHERE id = ? AND is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)',
            array((int)$id)
        );
    }

    public static function create($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return fly_db()->insert('posts', $data);
    }
    public static function update($id, $data)
    {
        return fly_db()->update('posts', $data, 'id = ?', array((int)$id));
    }
    public static function delete($id)
    {
        // V7.0 第二批：软删除（进回收站）。真要彻底删走 purge()。
        return fly_db()->update('posts', array('deleted_at' => time()), 'id = ?', array((int)$id));
    }

    /** 从回收站还原（清空 deleted_at） */
    public static function restore($id)
    {
        return fly_db()->update('posts', array('deleted_at' => 0), 'id = ?', array((int)$id));
    }

    /** 彻底删除（连带评论、标签关联、点赞记录） */
    public static function purge($id)
    {
        fly_db()->delete('comments', 'post_id = ?', array((int)$id));
        // V7.0：连带清标签关联，否则 post_tags 会留下一堆指向已删文章的孤儿行
        try {
            fly_db()->delete('post_tags', 'post_id = ?', array((int)$id));
        } catch (Exception $e) {
            fly_log('post_tag cleanup fail: ' . $e->getMessage());
        }
        // V7.0 第二批：连带清点赞记录（post_likes），避免指向已删文章的孤儿行导致计数错乱
        try {
            fly_db()->delete('post_likes', 'post_id = ?', array((int)$id));
        } catch (Exception $e) {
            fly_log('post_like cleanup fail: ' . $e->getMessage());
        }
        return fly_db()->delete('posts', 'id = ?', array((int)$id));
    }
}

/* ============================================================
 * V7.0 第二批：前台互动 + 热门文章（独立于 BasePost 的纯函数）
 * ========================================================== */

/** 点赞切换：同一 IP 对同文章只计一次（去重），再次点击取消赞。
 *  返回 array('liked'=>bool, 'likes'=>int)。失败安全降级。 */
function fly_post_like_toggle($postId, $ip)
{
    $db = fly_db();
    $postId = (int)$postId;
    $ip = substr((string)$ip, 0, 45);
    try {
        $exist = $db->one('SELECT id FROM post_likes WHERE post_id = ? AND ip = ?', array($postId, $ip));
        if ($exist) {
            $db->delete('post_likes', 'post_id = ? AND ip = ?', array($postId, $ip));
            // 防负数：仅当仍有赞时 -1（跨库通用的写法，避免依赖 GREATEST）
            $db->exec('UPDATE posts SET likes = likes - 1 WHERE id = ? AND likes > 0', array($postId));
            $liked = false;
        } else {
            $db->insert('post_likes', array('post_id' => $postId, 'ip' => $ip, 'created_at' => date('Y-m-d H:i:s')));
            $db->exec('UPDATE posts SET likes = likes + 1 WHERE id = ?', array($postId));
            $liked = true;
        }
        $likes = (int)$db->value('SELECT likes FROM posts WHERE id = ?', array($postId));
        return array('liked' => $liked, 'likes' => $likes);
    } catch (Throwable $e) {
        if (function_exists('fly_log')) { fly_log('like fail: ' . $e->getMessage(), 'php'); }
        $likes = (int)$db->value('SELECT likes FROM posts WHERE id = ?', array($postId));
        return array('liked' => false, 'likes' => $likes);
    }
}

/** 当前 IP 是否已赞过该文章 */
function fly_post_like_state($postId, $ip)
{
    try {
        return (bool)fly_db()->one('SELECT 1 FROM post_likes WHERE post_id = ? AND ip = ?', array((int)$postId, substr((string)$ip, 0, 45)));
    } catch (Throwable $e) {
        return false;
    }
}

/** 热门文章：按（阅读 + 点赞×5）综合排序，排除回收站 / 草稿 / 定时未到 */
function fly_post_popular($limit = 5)
{
    try {
        return fly_db()->all(
            "SELECT id, title, views, likes FROM posts
             WHERE deleted_at = 0 AND is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)
             ORDER BY (views + likes * 5) DESC, id DESC LIMIT ?",
            array((int)$limit)
        );
    } catch (Throwable $e) {
        return array();
    }
}

/** 会员模型（前台登录注册） */
class BaseMember
{
    public static function findByUsername($username)
    {
        return fly_db()->table('members')->where('username', $username)->one();
    }
    public static function find($id)
    {
        return fly_db()->table('members')->where('id', (int)$id)->one();
    }
    /** #59(P2) 注册：基本格式校验 + 唯一冲突友好失败（脏数据/超长/重复用户名不再直接 500） */
    public static function register($username, $password, $email = '')
    {
        if (!preg_match('/^[\w\x{4e00}-\x{9fa5}]{2,50}$/u', (string)$username)) {
            return false;
        }
        if (!is_string($password) || $password === '' || strlen($password) > 200) {
            return false;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $hash = fly_password_hash($password);
        try {
            return fly_db()->insert('members', array(
                'username' => $username,
                'password' => $hash,
                'email' => $email,
                'created_at' => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            return false; // 唯一约束冲突（重复用户名/邮箱）等
        }
    }
    /** #58(P2) 校验密码；用户不存在也执行一次哈希校验，拉齐耗时，防用户名枚举时序侧信道 */
    public static function verify($username, $password)
    {
        $m = self::findByUsername($username);
        if ($m) {
            $ok = fly_password_verify($password, $m['password']);
        } else {
            $ok = fly_password_verify($password, '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZd9qJ5T6E6');
        }
        return $ok ? $m : null;
    }
}

/** 留言模型 */
class BaseComment
{
    public static function forPost($postId, $onlyApproved = true)
    {
        $q = fly_db()->table('comments')->where('post_id', (int)$postId);
        if ($onlyApproved) {
            $q->where('status', 1);
        }
        return $q->order('id', 'ASC')->all();
    }
    public static function create($postId, $author, $content, $parentId = 0)
    {
        return fly_db()->insert('comments', array(
            'post_id' => (int)$postId,
            'parent_id' => (int)$parentId,
            'author' => $author,
            'content' => $content,
            'status' => 0, // #56(P2) 默认待审核（fail-closed）；前台 post.php 仍按 features.comment_audit 显式覆写
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }
    public static function setStatus($id, $status)
    {
        return fly_db()->update('comments', array('status' => (int)$status), 'id = ?', array((int)$id));
    }
    /** #57(P2) 删除前先把子回复提升为顶层，避免父留言删除后子回复成为孤儿 */
    public static function delete($id)
    {
        fly_db()->update('comments', array('parent_id' => 0), 'parent_id = ?', array((int)$id));
        return fly_db()->delete('comments', 'id = ?', array((int)$id));
    }
}
