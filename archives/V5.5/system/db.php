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
        return $row[$col] ?? null;
    }
    /** #27(P2) 聚合计数不带 ORDER BY（MySQL5.7+/8.0 与 PgSQL 对 "ORDER BY 列不在 GROUP BY" 直接报错） */
    public function count()
    {
        $save = $this->cols;
        $saveOrder = $this->orderBy;
        $this->cols = 'COUNT(*) AS c';
        $this->orderBy = '';
        $sql = $this->sql();
        $this->cols = $save;
        $this->orderBy = $saveOrder;
        $v = $this->db->value($sql, $this->params);
        return (int)$v;
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
    public function lastId()
    {
        // #32(P3) PgSQL 的 SERIAL 需显式传序列名，否则返回 0
        if ($this->driver === 'pgsql') {
            return (int)$this->pdo->lastInsertId('posts_id_seq');
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
    if ($driver === 'sqlite' || $driver === 'pgsql') {
        foreach ($tables as $t => $sql) {
            $tables[$t] = preg_replace('/,\s*PRIMARY KEY \(id\)\s*\)/', ')', $sql);
        }
    }

    return $tables;
}
