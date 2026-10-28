<?php
if (!defined('FLY_BOOTSTRAPPED')) { http_response_code(404); exit('Not Found'); }
/**
 * FlyLog V2.0 数据库抽象层（PDO，四数据库）
 * 支持 MySQL / MariaDB / PgSQL / SQLite，安装时四选一。
 *  - MySQL/MariaDB 走 pdo_mysql；PgSQL 走 pdo_pgsql；SQLite 走 pdo_sqlite（上传即用）。
 *  - 统一用预处理语句，杜绝 SQL 注入；查询尽量用跨库通用语法。
 */

/** 支持的数据库驱动清单 */
function fly_db_drivers()
{
    return array(
        'mysql'  => 'MySQL / MariaDB',
        'pgsql'  => 'PostgreSQL',
        'sqlite' => 'SQLite（上传即用，无需建库）',
    );
}

/** #26(P2) 标识符白名单：列名/表名必须匹配安全标识符，否则抛异常。
 *  查询构建器是公开 API，防止插件/后续页面把用户输入当列名/方向传入形成注入。
 *  按驱动选择标识符引号（MySQL/SQLite 反引号，PgSQL 双引号）。 */
function fly_ident($name, $driver = 'mysql')
{
    $name = (string)$name;
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException('非法 SQL 标识符: ' . $name);
    }
    return ($driver === 'pgsql' ? '"' : '`') . $name . ($driver === 'pgsql' ? '"' : '`');
}

/** 链式查询构建器 */
class FlyQuery
{
    protected $db;
    protected $table;
    protected $cols = '*';
    protected $wheres = array();
    protected $params = array();
    protected $orderBy = '';
    protected $limitN = null;
    protected $offsetN = null;

    public function __construct(FlyDB $db, $table)
    {
        $this->db = $db;
        $this->table = fly_ident($table, $db->driver);
    }
    public function select($cols)
    {
        // select 仅允许安全列清单（* 或逗号分隔标识符）
        if ($cols !== '*' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\s*,\s*[A-Za-z_][A-Za-z0-9_]*)*$/', (string)$cols)) {
            throw new InvalidArgumentException('非法查询列: ' . $cols);
        }
        $this->cols = $cols;
        return $this;
    }
    public function where($col, $val)
    {
        $this->wheres[] = fly_ident($col, $this->db->driver) . ' = ?';   // #26(P2) 列名白名单
        $this->params[] = $val;
        return $this;
    }
    public function whereRaw($sql, $params = array())
    {
        $this->wheres[] = '(' . $sql . ')';
        foreach ($params as $p) {
            $this->params[] = $p;
        }
        return $this;
    }
    /**
     * V6.0 新增：带操作符的条件查询（旧版只有 where() 等值，范围查询被迫写 whereRaw 拼 SQL）
     * 操作符走白名单，列名仍走 fly_ident 白名单，值始终为绑定参数。
     */
    public function whereOp($col, $op, $val)
    {
        static $ops = array('=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT');
        $op = strtoupper(trim((string)$op));
        if (!in_array($op, $ops, true)) {
            $op = '=';
        }
        $this->wheres[] = fly_ident($col, $this->db->driver) . ' ' . $op . ' ?';
        $this->params[] = $val;
        return $this;
    }
    public function whereIn($col, $vals)
    {
        $vals = array_values($vals);
        if (!$vals) {
            $this->wheres[] = '1 = 0';
            return $this;
        }
        $ph = implode(',', array_fill(0, count($vals), '?'));
        $this->wheres[] = fly_ident($col, $this->db->driver) . ' IN (' . $ph . ')';   // #26(P2)
        foreach ($vals as $v) {
            $this->params[] = $v;
        }
        return $this;
    }
    /** #28(P2) 追加语义：多次 order 链式调用全部保留（旧实现只留最后一次，置顶排序被丢弃） */
    public function order($col, $dir = 'ASC')
    {
        $dir = (strtoupper((string)$dir) === 'DESC') ? 'DESC' : 'ASC';
        $this->orderBy = ($this->orderBy === '' ? '' : $this->orderBy . ', ') . fly_ident($col, $this->db->driver) . ' ' . $dir;   // #26(P2)
        return $this;
    }
    public function limit($n)
    {
        $this->limitN = max(0, (int)$n);
        return $this;
    }
    public function offset($n)
    {
        $this->offsetN = max(0, (int)$n);
        return $this;
    }
    public function sql()
    {
        $sql = 'SELECT ' . $this->cols . ' FROM ' . $this->table;
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        if ($this->orderBy !== '') {
            $sql .= ' ORDER BY ' . $this->orderBy;
        }
        if ($this->limitN !== null) {
            $sql .= ' LIMIT ' . $this->limitN;
            if ($this->offsetN) {
                $sql .= ' OFFSET ' . $this->offsetN;
            }
        }
        return $sql;
    }
    public function all()
    {
        return $this->db->all($this->sql(), $this->params);
    }
    public function one()
    {
        return $this->db->one($this->sql(), $this->params);
    }
    public function value($col = null)
    {
        $row = $this->db->one($this->sql(), $this->params);
        if (!$row) {
            return null;
        }
        if ($col === null) {
            return reset($row);
        }
        return isset($row[$col]) ? $row[$col] : null;
    }
    /**
     * #27(P2) 聚合计数不带 ORDER BY（MySQL5.7+/8.0 与 PgSQL 对 ORDER BY 列不在 GROUP BY 直接报错）
     * V6.0 #1(P1) 必须一并清空 LIMIT/OFFSET：否则生成 "COUNT(*) ... LIMIT 10 OFFSET 10"，
     * 第 2 页起返回空集 → value() 得 null → total=0 → 分页被永久夹回第 1 页。
     */
    public function count()
    {
        $save = $this->cols;
        $saveOrder = $this->orderBy;
        $saveLimit = $this->limitN;
        $saveOffset = $this->offsetN;
        $this->cols = 'COUNT(*) AS c';
        $this->orderBy = '';
        $this->limitN = null;
        $this->offsetN = null;
        $sql = $this->sql();
        $this->cols = $save;
        $this->orderBy = $saveOrder;
        $this->limitN = $saveLimit;
        $this->offsetN = $saveOffset;
        $v = $this->db->value($sql, $this->params);
        return $v === null ? 0 : (int)$v;
    }
}

/** PDO 封装 */
class FlyDB
{
    /** @var PDO */
    public $pdo;
    public $driver;

    public function __construct(PDO $pdo, $driver)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
    }

    public function table($name)
    {
        return new FlyQuery($this, $name);
    }

    public function run($sql, $params = array())
    {
        $t0 = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($params));
        // #75 慢查询日志：执行超过 1 秒记入 logsdir/slow-日期.log（按天切割+文件锁见 fly_log）
        $cost = microtime(true) - $t0;
        if ($cost > 1.0 && function_exists('fly_log')) {
            fly_log(sprintf('SLOW %.3fs | %s', $cost, $sql), 'slow');
        }
        return $stmt;
    }
    public function all($sql, $params = array())
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function one($sql, $params = array())
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
    public function value($sql, $params = array())
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }
    public function exec($sql, $params = array())
    {
        return $this->run($sql, $params)->rowCount();
    }
    /**
     * 取最后插入的自增 ID
     * #32(P3) PgSQL 的 SERIAL 需显式传序列名，否则返回 0
     * V6.0 #2(P1) 原实现硬编码 'posts_id_seq'，对 comments/members/admin_logs/attachments
     * 插入会返回错误 ID 或直接抛异常；改为按表名推导 `<table>_id_seq`。
     */
    public function lastId($table = 'posts')
    {
        if ($this->driver === 'pgsql') {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
            if ($table === '') {
                $table = 'posts';
            }
            return (int)$this->pdo->lastInsertId($table . '_id_seq');
        }
        return (int)$this->pdo->lastInsertId();
    }
    public function quote($v)
    {
        return $this->pdo->quote($v);
    }
    public function errorInfo()
    {
        return $this->pdo->errorInfo();
    }

    /** 插入一行，$data 为 列=>值；返回自增 ID（列名过白名单） */
    public function insert($table, $data)
    {
        $cols = array_keys($data);
        $safeCols = array();
        foreach ($cols as $c) {
            $safeCols[] = fly_ident($c, $this->driver);
        }
        $ph = implode(',', array_fill(0, count($safeCols), '?'));
        $sql = 'INSERT INTO ' . fly_ident($table, $this->driver) . ' (' . implode(',', $safeCols) . ') VALUES (' . $ph . ')';
        $this->run($sql, array_values($data));
        return $this->lastId();
    }
    /** 更新，$where 为原始条件串（含 ?），$params 为条件参数 + 数据参数顺序：先数据后条件 */
    public function update($table, $data, $where, $whereParams = array())
    {
        $sets = array();
        $params = array();
        foreach ($data as $col => $val) {
            $sets[] = fly_ident($col, $this->driver) . ' = ?';
            $params[] = $val;
        }
        foreach ($whereParams as $p) {
            $params[] = $p;
        }
        $sql = 'UPDATE ' . fly_ident($table, $this->driver) . ' SET ' . implode(',', $sets) . ' WHERE ' . $where;
        return $this->run($sql, $params)->rowCount();
    }
    public function delete($table, $where, $params = array())
    {
        return $this->run('DELETE FROM ' . fly_ident($table, $this->driver) . ' WHERE ' . $where, $params)->rowCount();
    }
}

/** 取得全局唯一数据库连接（懒加载） */
function fly_db()
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }
    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
    try {
        switch ($driver) {
            case 'mysql':
                $dsn = 'mysql:host=' . (DB_HOST ?: 'localhost')
                    . ';port=' . (defined('DB_PORT') && DB_PORT ? DB_PORT : 3306)
                    . ';charset=utf8mb4';
                // #33(P3) 库名进 DSN（去掉 USE 拼接）；库名过滤掉分号/反引号防 DSN 破裂
                if (DB_NAME) {
                    $dsn .= ';dbname=' . str_replace(array(';', '`', '"'), '', DB_NAME);
                }
                // #31(P3) 关闭模拟预处理：真服务端预编译，杜绝多字节截断类注入边界
                $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ));
                break;
            case 'pgsql':
                $dsn = 'pgsql:host=' . (DB_HOST ?: 'localhost')
                    . ';port=' . (defined('DB_PORT') && DB_PORT ? DB_PORT : 5432)
                    . ';dbname=' . DB_NAME;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ));
                break;
            case 'sqlite':
            default:
                $path = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : (FLY_DATA . '/flylog.sqlite');
                if (!is_dir(dirname($path))) {
                    @mkdir(dirname($path), 0755, true);
                }
                $pdo = new PDO('sqlite:' . $path, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
                $pdo->exec('PRAGMA foreign_keys = ON');
                break;
        }
    } catch (Exception $e) {
        // #30(P2) 连接失败不回显 PDO 消息（含 DSN/主机/账号细节），详情进 logsdir，debug 开才回显
        if (function_exists('fly_log')) {
            fly_log('DB CONNECT FAIL: ' . $e->getMessage(), 'php');
        }
        http_response_code(500);
        $detail = fly_config('site.debug', false)
            ? '<br><code>' . fly_e($e->getMessage()) . '</code>'
            : '';
        die('站点暂时无法访问（数据库连接异常），请稍后重试或联系站长。'
            . $detail
            . '<br>首次使用请先访问 <a href="install.php">install.php</a> 完成安装。');
    }
    $db = new FlyDB($pdo, $driver);
    return $db;
}

/**
 * 按驱动生成建表 DDL（install.php 调用）。
 * 返回 表名 => SQL 数组。兼容 MySQL/MariaDB、PgSQL、SQLite。
 */
function fly_schema($driver)
{
    if ($driver === 'pgsql') {
        $ai = 'SERIAL PRIMARY KEY';
        $engine = '';
        $ts = 'TIMESTAMP';
        $text = 'TEXT';
        // #29(P2) PgSQL 不支持无符号整型：统一 INTEGER/BIGINT
        $uint = 'INTEGER';
        $bigint = 'BIGINT';
    } elseif ($driver === 'sqlite') {
        $ai = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $engine = '';
        $ts = 'DATETIME';
        $text = 'TEXT';
        $uint = 'INT';
        $bigint = 'INT';
    } else { // mysql/mariadb
        $ai = 'INT UNSIGNED NOT NULL AUTO_INCREMENT';
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $ts = 'DATETIME';
        $text = 'MEDIUMTEXT';
        $uint = 'INT UNSIGNED';
        $bigint = 'BIGINT UNSIGNED';
    }

    $tables = array();

    // 文章表（V2.0：置顶 is_pinned / 草稿 is_draft / 定时发布 publish_at）
    $tables['posts'] =
        "CREATE TABLE IF NOT EXISTS posts (
            id {$ai},
            title VARCHAR(255) NOT NULL,
            summary VARCHAR(500) NOT NULL DEFAULT '',
            content {$text},
            category VARCHAR(100) NOT NULL DEFAULT '',
            views {$uint} NOT NULL DEFAULT 0,
            is_pinned SMALLINT NOT NULL DEFAULT 0,
            is_draft SMALLINT NOT NULL DEFAULT 0,
            publish_at {$ts} NULL DEFAULT NULL,
            likes {$uint} NOT NULL DEFAULT 0,
            deleted_at {$uint} NOT NULL DEFAULT 0,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // 留言表（V2.0：回复 parent_id）
    $tables['comments'] =
        "CREATE TABLE IF NOT EXISTS comments (
            id {$ai},
            post_id {$uint} NOT NULL,
            parent_id {$uint} NOT NULL DEFAULT 0,
            author VARCHAR(100) NOT NULL DEFAULT '',
            content TEXT,
            status SMALLINT NOT NULL DEFAULT 1,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // 管理员表（V2.0：角色 role）
    $tables['admins'] =
        "CREATE TABLE IF NOT EXISTS admins (
            id {$ai},
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_username (username)
        ){$engine}";

    // 前台会员表（V2.0 登录注册）
    $tables['members'] =
        "CREATE TABLE IF NOT EXISTS members (
            id {$ai},
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(190) NOT NULL DEFAULT '',
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_member (username)
        ){$engine}";

    // 登录防爆破表
    $tables['login_attempts'] =
        "CREATE TABLE IF NOT EXISTS login_attempts (
            ip VARCHAR(45) NOT NULL,
            fails {$uint} NOT NULL DEFAULT 0,
            lock_until {$ts} NULL DEFAULT NULL,
            updated_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ip)
        ){$engine}";

    // 登录日志表
    $tables['admin_logs'] =
        "CREATE TABLE IF NOT EXISTS admin_logs (
            id {$ai},
            username VARCHAR(100) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            success SMALLINT NOT NULL DEFAULT 0,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // 附件表
    $tables['attachments'] =
        "CREATE TABLE IF NOT EXISTS attachments (
            id {$ai},
            name VARCHAR(255) NOT NULL DEFAULT '',
            path VARCHAR(500) NOT NULL DEFAULT '',
            size {$bigint} NOT NULL DEFAULT 0,
            mime VARCHAR(100) NOT NULL DEFAULT '',
            post_id INTEGER NOT NULL DEFAULT 0,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // 键值配置表（TDK 等后台可视化配置）
    $tables['options'] =
        "CREATE TABLE IF NOT EXISTS options (
            k VARCHAR(100) NOT NULL,
            v TEXT,
            PRIMARY KEY (k)
        ){$engine}";

    // ---------- V7.0 内容组织层 ----------
    // 分类树（parent_id 实现无限级；slug 用于 URL，内联 UNIQUE 四库通用）
    $tables['categories'] =
        "CREATE TABLE IF NOT EXISTS categories (
            id {$ai},
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            parent_id INTEGER NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0,
            description VARCHAR(500) NOT NULL DEFAULT '',
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // 标签
    $tables['tags'] =
        "CREATE TABLE IF NOT EXISTS tags (
            id {$ai},
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // 文章 - 标签 关联（不用复合主键：四库对复合主键 + AUTO_INCREMENT 的处理差异太大）
    $tables['post_tags'] =
        "CREATE TABLE IF NOT EXISTS post_tags (
            id {$ai},
            post_id INTEGER NOT NULL DEFAULT 0,
            tag_id INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ){$engine}";

    // 单页（关于我 / 友链这类独立页面，不进文章列表与 feed）
    $tables['pages'] =
        "CREATE TABLE IF NOT EXISTS pages (
            id {$ai},
            slug VARCHAR(100) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            content {$text},
            status SMALLINT NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // ---------- V7.0 第二批 点赞 ----------
    $tables['post_likes'] =
        "CREATE TABLE IF NOT EXISTS post_likes (
            id {$ai},
            post_id {$uint} NOT NULL DEFAULT 0,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE (post_id, ip)
        ){$engine}";

    // ---------- V7.0 第二批 审计日志 ----------
    $tables['audit_logs'] =
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id {$ai},
            actor VARCHAR(100) NOT NULL DEFAULT '',
            action VARCHAR(60) NOT NULL DEFAULT '',
            target_type VARCHAR(30) NOT NULL DEFAULT '',
            target_id {$uint} NOT NULL DEFAULT 0,
            detail VARCHAR(500) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // ---------- V9.0 友情链接 ----------
    // 列名刻意避开 SQL 保留字：description 而非 desc、sort_no 而非 sort，
    // 这样四种数据库（MySQL/MariaDB/PgSQL/SQLite）都不需要额外改写。
    $tables['links'] =
        "CREATE TABLE IF NOT EXISTS links (
            id {$ai},
            name VARCHAR(100) NOT NULL,
            url VARCHAR(500) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            logo VARCHAR(500) NOT NULL DEFAULT '',
            sort_no {$uint} NOT NULL DEFAULT 0,
            visible SMALLINT NOT NULL DEFAULT 1,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ){$engine}";

    // SQLite 的 UNIQUE 内联在列上更稳；上面 mysql 分支用了 UNIQUE KEY 行，SQLite/PgSQL 需改写
    if ($driver === 'sqlite' || $driver === 'pgsql') {
        $tables['admins'] =
            "CREATE TABLE IF NOT EXISTS admins (
                id {$ai},
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'admin',
                created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ){$engine}";
        $tables['members'] =
            "CREATE TABLE IF NOT EXISTS members (
                id {$ai},
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(190) NOT NULL DEFAULT '',
                created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ){$engine}";
    }

    // SQLite/PgSQL 的 id 列已内联 PRIMARY KEY，需去掉表级重复声明，否则报 "more than one primary key"
    // 注意：表级 PK 后面可能还跟着 UNIQUE 等约束（如 post_likes），所以 PK 后面可能是逗号或右括号都要处理。
    if ($driver === 'sqlite' || $driver === 'pgsql') {
        foreach ($tables as $t => $sql) {
            $tables[$t] = preg_replace('/,\s*PRIMARY KEY \(id\)\s*(,|\))/', '$1', $sql);
        }
    }

    return $tables;
}

/**
 * V6.0 轻量迁移：给老库补列（老站点升级无需手工改库）
 * 只做"缺列则加"，不做删列/改类型，安全可重复执行。
 *
 * @return array 已执行的变更描述
 */
function fly_db_migrate()
{
    $db = fly_db();
    $driver = $db->driver;
    $done = array();

    // V7.0：老库升级时新表还不存在，这里按当前库方言补建（缺表才建，幂等）
    foreach (fly_schema($driver) as $tname => $sql) {
        if (fly_db_has_table($tname)) {
            continue;
        }
        try {
            $db->exec($sql);
            $done[] = 'table.' . $tname;
        } catch (Exception $e) {
            if (function_exists('fly_log')) {
                fly_log('migrate fail: create ' . $tname . ' :: ' . $e->getMessage());
            }
        }
    }

    // 需要补齐的列：表名 => array(列名 => 列定义)
    $plan = array(
        'attachments' => array('post_id' => 'INTEGER NOT NULL DEFAULT 0'),
    // V7.0 内容组织层：分类从「文章里的字符串」升级为独立分类树
    'posts' => array(
        'category_id' => 'INTEGER NOT NULL DEFAULT 0',
        // V7.0 第二批：点赞计数 + 软删除时间戳（0 = 未删除）
        'likes'      => 'INTEGER NOT NULL DEFAULT 0',
        'deleted_at' => 'INTEGER NOT NULL DEFAULT 0',
        // V9.0：SEO 字段可视化编辑（留空则回落到标题 / 自动摘要，老文章零影响）
        'seo_title'    => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
        'seo_keywords' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
        'seo_desc'     => 'VARCHAR(500) NOT NULL DEFAULT \'\'',
    ),
        // V6.5 登录界面：后台「记住我」长期会话令牌（库里只存 hash，不存明文）
        'admins' => array(
            'remember_token'  => 'VARCHAR(64)',
            'remember_expire' => 'VARCHAR(32)',
            'last_login_at'   => 'VARCHAR(32)',
        ),
    );

    foreach ($plan as $table => $cols) {
        foreach ($cols as $col => $def) {
            if (fly_db_has_column($table, $col)) {
                continue;
            }
            try {
                $db->exec('ALTER TABLE ' . fly_ident($table, $driver) . ' ADD COLUMN ' . fly_ident($col, $driver) . ' ' . $def);
                $done[] = $table . '.' . $col;
            } catch (Exception $e) {
                // 迁移失败不致命：相关功能降级，站点仍可用
                if (function_exists('fly_log')) {
                    fly_log('migrate fail: ' . $table . '.' . $col . ' :: ' . $e->getMessage());
                }
            }
        }
    }
    return $done;
}

/**
 * V7.0 数据迁移：老库「文章里的分类字符串」→ 新分类树
 *
 * 老版本把分类直接写在 posts.category 字段里（纯字符串，无层级、无描述）。
 * V7.0 引入 categories 表后需要做一次数据搬迁：把出现过的分类名建成分类记录，
 * 再把文章的 category_id 回填。设计上刻意满足：
 *   - 幂等：只处理 category_id 还是 0/NULL 的行，重复执行无副作用；
 *   - 省流：每次请求最多搬 50 条，搬迁完自然停（不会拖慢正常访问）；
 *   - slug 直接沿用原分类名，保证老链接 /?cat=分类名 一字不差继续可用；
 *   - 失败不致命：只记日志，站点照常跑。
 *
 * @return array 本轮变更描述
 */
function fly_db_migrate_v7()
{
    $db = fly_db();
    $done = array();
    try {
        if (!fly_db_has_column('posts', 'category_id')) {
            return $done;
        }
        $rows = $db->all(
            "SELECT id, category FROM posts
             WHERE category <> '' AND (category_id IS NULL OR category_id = 0)
             ORDER BY id ASC LIMIT 50"
        );
    } catch (Exception $e) {
        fly_log('v7 migrate scan fail: ' . $e->getMessage());
        return $done;
    }
    if (!$rows) {
        return $done;
    }
    foreach ($rows as $r) {
        $name = trim((string)$r['category']);
        if ($name === '') {
            continue;
        }
        $catId = fly_cat_ensure($name);
        if (!$catId) {
            continue;
        }
        try {
            $db->exec(
                'UPDATE posts SET category_id = ? WHERE id = ?',
                array((int)$catId, (int)$r['id'])
            );
            $done[] = 'post#' . $r['id'] . ' -> cat#' . $catId;
        } catch (Exception $e) {
            fly_log('v7 migrate post fail: ' . $e->getMessage());
        }
    }
    return $done;
}

/** 判断某表是否已存在（四种库各自的信息_schema 差异都在这里兜住） */
function fly_db_has_table($table)
{
    $db = fly_db();
    $driver = $db->driver;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    if ($table === '') {
        return false;
    }
    try {
        if ($driver === 'sqlite') {
            $v = $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1", array($table));
            return $v !== null && $v !== false;
        }
        if ($driver === 'pgsql') {
            $v = $db->value(
                'SELECT 1 FROM information_schema.tables WHERE table_name = ? LIMIT 1',
                array($table)
            );
            return $v !== null && $v !== false;
        }
        // MySQL / MariaDB：SHOW TABLES LIKE 不接受占位符，表名已做白名单过滤
        $rows = $db->all('SHOW TABLES LIKE ' . $db->pdo->quote($table));
        return !empty($rows);
    } catch (Exception $e) {
        return false;
    }
}

/** 判断某表是否已存在某列（四种库各自的信息_schema 差异都在这里兜住） */
function fly_db_has_column($table, $col)
{
    $db = fly_db();
    $driver = $db->driver;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
    $col = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$col);
    try {
        if ($driver === 'sqlite') {
            $rows = $db->all('PRAGMA table_info(' . $table . ')');
            foreach ($rows as $r) {
                if (isset($r['name']) && strtolower($r['name']) === strtolower($col)) {
                    return true;
                }
            }
            return false;
        }
        if ($driver === 'pgsql') {
            $v = $db->value(
                'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
                array($table, $col)
            );
            return $v !== null && $v !== false;
        }
        // MySQL / MariaDB
        $rows = $db->all('SHOW COLUMNS FROM ' . fly_ident($table, $driver) . ' LIKE ' . $db->pdo->quote($col));
        return !empty($rows);
    } catch (Exception $e) {
        return false;
    }
}
