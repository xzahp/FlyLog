<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 数据模型基类化（BasePost / BaseMember / BaseComment）
 * 把数据表操作封装成基类，上层只面向对象编程，不直接写 SQL。内部基于 FlyDB 链式查询。
 */

/** 文章模型 */
class BasePost
{
    /** 取一篇（仅已发布，除非 $withDraft） */
    public static function find($id, $withDraft = false)
    {
        $q = fly_db()->table('posts')->where('id', (int)$id);
        if (!$withDraft) {
            // #55(P1) 草稿与未来定时文章对匿名访客不可见（post.php 改用 find($id) 触发本过滤）
            $q->where('is_draft', 0);
            $q->whereRaw('(publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)');
        }
        return $q->one();
    }

    /** 列表（已发布；含置顶优先 + 定时发布过滤 + #57 大偏移量游标优化） */
    public static function query($opt = array())
    {
        $db = fly_db();
        $q = $db->table('posts');
        $q->whereRaw('is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)');
        $kw = null;
        if (!empty($opt['category'])) {
            $q->where('category', $opt['category']);
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
        if ($limit > 0 && $offset >= 500) {
            $keySql = 'SELECT is_pinned, id FROM posts WHERE is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)';
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
        fly_db()->delete('comments', 'post_id = ?', array((int)$id));
        return fly_db()->delete('posts', 'id = ?', array((int)$id));
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
