<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V7.0 第二批 审计日志
 * ------------------------------------------------------------------
 * 记录后台关键操作（文章 / 留言 / 分类 / 标签 / 单页 / 设置 / 登录 等的增删改），
 * 便于事后追溯。与 admin_logs（登录日志）分工：
 *   - admin_logs 只记登录成败（IP 脱敏）；
 *   - audit_logs 记「谁在什么时间对什么做了什么」。
 *
 * 设计要点
 *  - 全部写操作包在 try 里，审计失败绝不致命（绝不拖垮主流程）；
 *  - 记录当前登录管理员名（无则 system）；IP 取真实来访者 IP；
 *  - action 用「类型.动作」命名（post.create / comment.approve …），便于筛选。
 */

/**
 * 记录一条审计日志
 * @param string $action      动作，如 post.create / post.update / post.delete / comment.approve
 * @param string $targetType  目标类型 post/comment/category/tag/page/setting/admin ...
 * @param int    $targetId    目标 id（无则 0）
 * @param string $detail      补充描述
 */
function fly_audit($action, $targetType = '', $targetId = 0, $detail = '')
{
    try {
        $actor = isset($_SESSION['fly_admin_name']) ? (string)$_SESSION['fly_admin_name'] : 'system';
        fly_db()->insert('audit_logs', array(
            'actor'       => $actor,
            'action'      => (string)$action,
            'target_type' => (string)$targetType,
            'target_id'   => (int)$targetId,
            'detail'      => (string)$detail,
            'ip'          => fly_client_ip(),
            'created_at'  => date('Y-m-d H:i:s'),
        ));
    } catch (Throwable $e) {
        // 审计失败不致命：只进日志，绝不影响主流程
        if (function_exists('fly_log')) {
            fly_log('audit fail: ' . $e->getMessage(), 'php');
        }
    }
}

/** 审计日志列表（按时间倒序，支持 action 过滤 + 分页） */
function fly_audit_list($action = '', $limit = 100, $offset = 0)
{
    $db = fly_db();
    try {
        if ($action !== '') {
            return $db->all(
                'SELECT * FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT ? OFFSET ?',
                array($action, (int)$limit, (int)$offset)
            );
        }
        return $db->all(
            'SELECT * FROM audit_logs ORDER BY id DESC LIMIT ? OFFSET ?',
            array((int)$limit, (int)$offset)
        );
    } catch (Throwable $e) {
        return array();
    }
}

/** 审计日志总数（按 action 过滤） */
function fly_audit_count($action = '')
{
    $db = fly_db();
    try {
        if ($action !== '') {
            return (int)$db->value('SELECT COUNT(*) FROM audit_logs WHERE action = ?', array($action));
        }
        return (int)$db->value('SELECT COUNT(*) FROM audit_logs');
    } catch (Throwable $e) {
        return 0;
    }
}

/** 全部出现过的 action（用于筛选下拉） */
function fly_audit_actions()
{
    try {
        $rows = fly_db()->all('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC');
        return array_map(function ($r) { return $r['action']; }, $rows);
    } catch (Throwable $e) {
        return array();
    }
}

/** 把 action 转成中文可读名（审计页展示用） */
function fly_audit_action_label($action)
{
    $map = array(
        'post.create'      => '新建文章',
        'post.update'      => '编辑文章',
        'post.delete'      => '删除文章（进回收站）',
        'post.restore'     => '还原文章',
        'post.purge'       => '彻底删除文章',
        'comment.approve'  => '通过留言',
        'comment.unapprove'=> '撤下留言',
        'comment.delete'   => '删除留言',
        'comment.approve_all' => '一键通过全部留言',
        'category.create'  => '新建分类',
        'category.update'  => '编辑分类',
        'category.delete'  => '删除分类',
        'category.import'  => '导入历史分类',
        'tag.create'       => '新建标签',
        'tag.update'       => '编辑标签',
        'tag.delete'       => '删除标签',
        'tag.gc'           => '清理空标签',
        'page.create'      => '新建单页',
        'page.update'      => '编辑单页',
        'page.delete'      => '删除单页',
        'setting.update'   => '保存站点设置',
        'admin.login'      => '后台登录',
    );
    return isset($map[$action]) ? $map[$action] : $action;
}

/** 审计日志导出为 CSV 字符串（带 UTF-8 BOM，Excel 可直接打开）。
 *  @param string $action 动作筛选（'' = 全部）
 *  @return string CSV 文本
 */
function fly_audit_export_csv($action = '')
{
    $db = fly_db();
    try {
        if ($action !== '') {
            $rows = $db->all(
                'SELECT id, created_at, actor, action, target_type, target_id, detail, ip '
                . 'FROM audit_logs WHERE action = ? ORDER BY id DESC',
                array($action)
            );
        } else {
            $rows = $db->all(
                'SELECT id, created_at, actor, action, target_type, target_id, detail, ip '
                . 'FROM audit_logs ORDER BY id DESC'
            );
        }
    } catch (Throwable $e) {
        $rows = array();
    }

    $cols = array('ID', '时间', '操作人', '动作', '动作(可读)', '目标类型', '目标ID', '详情', 'IP');
    $esc = function ($v) {
        $v = (string)$v;
        if ($v === '') { return ''; }
        if (strpbrk($v, ",\"\n\r") !== false) {
            return '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    };

    $out = "\xEF\xBB\xBF"; // UTF-8 BOM
    $out .= implode(',', $cols) . "\r\n";
    foreach ($rows as $r) {
        $line = array(
            (int)$r['id'],
            $r['created_at'],
            $r['actor'] ?: 'system',
            $r['action'],
            fly_audit_action_label($r['action']),
            $r['target_type'] ?: '',
            (int)$r['target_id'] > 0 ? (int)$r['target_id'] : '',
            $r['detail'] ?: '',
            fly_mask_ip($r['ip']),
        );
        $out .= implode(',', array_map($esc, $line)) . "\r\n";
    }
    return $out;
}
