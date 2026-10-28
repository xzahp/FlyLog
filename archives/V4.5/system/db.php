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
        $this->table = $table;
    }
    public function select($cols)
    {
        $this->cols = $cols;
        return $this;
    }
    public function where($col, $val)
    {
        $this->wheres[] = $col . ' = ?';
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
        $this->wheres[] = $col . ' IN (' . $ph . ')';
        foreach ($vals as $v) {
            $this->params[] = $v;
        }
        return $this;
    }
    public function order($col, $dir = 'ASC')
    {
        $dir = (strtoupper((string)$dir) === 'DESC') ? 'DESC' : 'ASC';
        $this->orderBy = $col . ' ' . $dir;
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
    public function count()
    {
        $save = $this->cols;
        $this->cols = 'COUNT(*) AS c';
        $sql = $this->sql();
        $this->cols = $save;
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

    /** 插入一行，$data 为 列=>值；返回自增 ID */
    public function insert($table, $data)
    {
        $cols = array_keys($data);
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . $ph . ')';
        $this->run($sql, array_values($data));
        return $this->lastId();
    }
    /** 更新，$where 为原始条件串（含 ?），$params 为条件参数 + 数据参数顺序：先数据后条件 */
    public function update($table, $data, $where, $whereParams = array())
    {
        $sets = array();
        $params = array();
        foreach ($data as $col => $val) {
            $sets[] = $col . ' = ?';
            $params[] = $val;
        }
        foreach ($whereParams as $p) {
            $params[] = $p;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE ' . $where;
        return $this->run($sql, $params)->rowCount();
    }
    public function delete($table, $where, $params = array())
    {
        return $this->run('DELETE FROM ' . $table . ' WHERE ' . $where, $params)->rowCount();
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
                $pdo = new PDO($dsn, DB_USER, DB_PASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
                if (DB_NAME) {
                    $pdo->exec('USE `' . DB_NAME . '`');
                }
                break;
            case 'pgsql':
                $dsn = 'pgsql:host=' . (DB_HOST ?: 'localhost')
                    . ';port=' . (defined('DB_PORT') && DB_PORT ? DB_PORT : 5432)
                    . ';dbname=' . DB_NAME;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
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
        http_response_code(500);
        die('数据库连接失败：' . fly_e($e->getMessage())
            . '<br>请检查 config.php 的数据库配置；SQLite 模式应无需任何配置、上传即用。'
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
    } elseif ($driver === 'sqlite') {
        $ai = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $engine = '';
        $ts = 'DATETIME';
        $text = 'TEXT';
    } else { // mysql/mariadb
        $ai = 'INT UNSIGNED NOT NULL AUTO_INCREMENT';
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $ts = 'DATETIME';
        $text = 'MEDIUMTEXT';
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
            views INT UNSIGNED NOT NULL DEFAULT 0,
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
            post_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NOT NULL DEFAULT 0,
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
            fails INT UNSIGNED NOT NULL DEFAULT 0,
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
            size INT UNSIGNED NOT NULL DEFAULT 0,
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
