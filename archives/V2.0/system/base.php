<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(403); exit('Forbidden'); }
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
            $q->where('is_draft', 0);
        }
        return $q->one();
    }

    /** 列表（已发布；含置顶优先 + 定时发布过滤） */
    public static function query($opt = array())
    {
        $db = fly_db();
        $q = $db->table('posts');
        $q->whereRaw('is_draft = 0 AND (publish_at IS NULL OR publish_at <= CURRENT_TIMESTAMP)');
        if (!empty($opt['category'])) {
            $q->where('category', $opt['category']);
        }
        if (!empty($opt['keyword'])) {
            $kw = '%' . $opt['keyword'] . '%';
            $q->whereRaw('(title LIKE ? OR summary LIKE ? OR content LIKE ?)', array($kw, $kw, $kw));
        }
        // 置顶优先，再按 ID 倒序
        $q->order('is_pinned', 'DESC')->order('id', 'DESC');
        if (!empty($opt['limit'])) {
            $q->limit((int)$opt['limit']);
            if (!empty($opt['offset'])) {
                $q->offset((int)$opt['offset']);
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

    /** 阅读量 +1 */
    public static function incrementViews($id)
    {
        fly_db()->exec('UPDATE posts SET views = views + 1 WHERE id = ?', array((int)$id));
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
    public static function register($username, $password, $email = '')
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return fly_db()->insert('members', array(
            'username' => $username,
            'password' => $hash,
            'email' => $email,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }
    /** 校验密码，成功返回会员行，失败 null */
    public static function verify($username, $password)
    {
        $m = self::findByUsername($username);
        if ($m && password_verify($password, $m['password'])) {
            return $m;
        }
        return null;
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
            'status' => 1, // 默认待审核由上层按配置决定，这里先记 1，前台插入时按配置覆写
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }
    public static function setStatus($id, $status)
    {
        return fly_db()->update('comments', array('status' => (int)$status), 'id = ?', array((int)$id));
    }
    public static function delete($id)
    {
        return fly_db()->delete('comments', 'id = ?', array((int)$id));
    }
}
