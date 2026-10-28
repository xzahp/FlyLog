<?php
/**
 * FlyLog V6.5 后台登录（Z-Blog 风格完整登录界面）
 * ------------------------------------------------------------------
 * 安全能力（全部沿用并增强 V6.0）：
 *   · IP + 数据库级防爆破（连续 5 次失败锁 1 小时，登录页显示剩余时间）
 *   · CSRF 令牌校验
 *   · SQL 注入特征检测（命中直接按失败处理并计入防爆破）
 *   · 密码 Argon2id/Bcrypt，登录成功自动升级旧哈希
 *   · 登录日志（IP 脱敏）
 *   · V6.5 新增：SVG 图形验证码（不依赖 GD）、「记住我」长期会话（库内只存 hash）
 *   · V6.5 新增：X-Robots-Tag: noindex（后台登录页不进搜索引擎）
 */
require __DIR__ . '/../config.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');

/* ------------------------------------------------------------------ */
/* 「记住我」：长期会话令牌                                              */
/* ------------------------------------------------------------------ */

/** 生成随机 token（兼容无 random_bytes 的旧 PHP） */
function fly_admin_random_token()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
    return md5(uniqid(mt_rand(), true) . microtime(true) . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''));
}

/** 记住我 cookie 名 */
function fly_admin_remember_cookie()
{
    return 'flylog_admin_rm';
}

/** 下发记住我 cookie，并把 hash 写进 admins 表 */
function fly_admin_remember_issue($adminId, $days = 30)
{
    $token = fly_admin_random_token();
    $hash  = hash('sha256', $token);
    $expireTs = time() + ($days * 86400);
    $expireStr = date('Y-m-d H:i:s', $expireTs);

    try {
        fly_db()->update(
            'admins',
            array('remember_token' => $hash, 'remember_expire' => $expireStr),
            'id = ?',
            array((int)$adminId)
        );
    } catch (Exception $e) {
        return false; // 写库失败就不下发 cookie，避免产生永远校验不过的僵尸 cookie
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(fly_admin_remember_cookie(), $token, $expireTs, '/', '', $secure, true);
    return true;
}

/** 清掉记住我（登出 / 换账号时用） */
function fly_admin_remember_clear($adminId = 0)
{
    $name = fly_admin_remember_cookie();
    if ($adminId > 0) {
        try {
            fly_db()->update('admins', array('remember_token' => null, 'remember_expire' => null), 'id = ?', array((int)$adminId));
        } catch (Exception $e) { /* ignore */ }
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie($name, '', time() - 3600, '/', '', $secure, true);
    if (isset($_COOKIE[$name])) {
        unset($_COOKIE[$name]);
    }
}

/** 用 cookie 恢复会话；成功返回管理员行，失败返回 false */
function fly_admin_remember_resume()
{
    $name = fly_admin_remember_cookie();
    if (empty($_COOKIE[$name])) {
        return false;
    }
    $token = (string)$_COOKIE[$name];
    if ($token === '' || strlen($token) > 128) {
        fly_admin_remember_clear(0);
        return false;
    }
    try {
        $row = fly_db()->one(
            'SELECT id, username, role, remember_expire FROM admins WHERE remember_token = ?',
            array(hash('sha256', $token))
        );
    } catch (Exception $e) {
        return false; // 列还没迁移出来 / 库异常：静默降级为普通登录
    }
    if (!$row || empty($row['remember_expire'])) {
        fly_admin_remember_clear(0);
        return false;
    }
    if (strtotime($row['remember_expire']) <= time()) {
        fly_admin_remember_clear((int)$row['id']);
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['fly_admin_id']   = (int)$row['id'];
    $_SESSION['fly_admin_name'] = $row['username'];
    $_SESSION['fly_admin_role'] = $row['role'] ?: 'admin';
    // 一次性：恢复后立刻轮换令牌，避免同一 cookie 被无限复用
    fly_admin_remember_issue((int)$row['id']);
    return $row;
}

/* ------------------------------------------------------------------ */
/* 已登录 / 记住我自动登录                                              */
/* ------------------------------------------------------------------ */
if (!empty($_SESSION['fly_admin_id'])) {
    fly_redirect('index.php');
}
if (fly_admin_remember_resume()) {
    fly_redirect('index.php');
}

/* ------------------------------------------------------------------ */
/* 验证码开关（可在 config.php 的 features.admin_captcha 关闭）           */
/* ------------------------------------------------------------------ */
$captchaOn = fly_feature('admin_captcha', true);

/* ------------------------------------------------------------------ */
/* IP 级防爆破                                                          */
/* ------------------------------------------------------------------ */
$db  = fly_db();
$ip  = fly_client_ip();
$err = '';
$errKind = 'error';           // error | warn | info
$lockSeconds = 0;

$row = $db->table('login_attempts')->where('ip', $ip)->one();
$fails = $row ? (int)$row['fails'] : 0;
$lockUntil = $row && $row['lock_until'] ? strtotime($row['lock_until']) : 0;
$locked = ($lockUntil > time());
if ($lockUntil > 0 && $lockUntil <= time()) {
    if ($row) {
        $db->update('login_attempts', array('fails' => 0, 'lock_until' => null), 'ip = ?', array($ip));
    }
    $fails = 0;
    $locked = false;
}
if ($locked) {
    $lockSeconds = $lockUntil - time();
    $remainTxt = $lockSeconds >= 3600 ? ((int)ceil($lockSeconds / 3600) . ' 小时') : ((int)ceil($lockSeconds / 60) . ' 分钟');
    $err = '密码连续输错次数过多，当前 IP 已临时锁定，请约 ' . $remainTxt . '后再试。';
}

/** 记录登录日志（IP 脱敏） */
$fly_log_login = function ($username, $success) use ($db, $ip) {
    $db->insert('admin_logs', array(
        'username' => $username, 'ip' => fly_mask_ip($ip), 'success' => $success ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
    ));
};

/** 失败一次：累加计数，满 5 次锁 1 小时 */
$fly_fail_once = function () use ($db, $ip, $row, &$fails) {
    $newFails = $fails + 1;
    $lock = null;
    if ($newFails >= 5) {
        $lock = date('Y-m-d H:i:s', time() + 3600);
        $newFails = 0;
    }
    if ($row) {
        $db->update('login_attempts', array('fails' => $newFails, 'lock_until' => $lock), 'ip = ?', array($ip));
    } else {
        $db->insert('login_attempts', array('ip' => $ip, 'fails' => $newFails, 'lock_until' => $lock, 'updated_at' => date('Y-m-d H:i:s')));
    }
    $fails = $newFails;
};

/* ------------------------------------------------------------------ */
/* 处理提交                                                             */
/* ------------------------------------------------------------------ */
$usernameVal = '';
if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    fly_csrf_check();
    $usernameVal = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password    = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $captcha     = isset($_POST['captcha']) ? trim((string)$_POST['captcha']) : '';
    $remember    = !empty($_POST['remember']);

    if ($usernameVal === '' || $password === '') {
        $err = '请输入用户名和密码。';
    } elseif ($captchaOn) {
        $code = isset($_SESSION['fly_captcha_code']) ? (string)$_SESSION['fly_captcha_code'] : '';
        $ts   = isset($_SESSION['fly_captcha_ts']) ? (int)$_SESSION['fly_captcha_ts'] : 0;
        unset($_SESSION['fly_captcha_code'], $_SESSION['fly_captcha_ts']);
        if ($code === '' || $ts <= 0 || (time() - $ts) > 600) {
            $err = '验证码已过期，请点击图片刷新后重新输入。';
        } elseif (strtoupper($captcha) !== strtoupper($code)) {
            $err = '验证码不正确。';
            $fly_log_login($usernameVal, false);
            $fly_fail_once();
        }
    }

    if ($err === '') {
        $sqli  = fly_detect_sqli($usernameVal);
        $admin = $sqli ? null : $db->table('admins')->where('username', $usernameVal)->one();
        $passed = false;
        if ($admin) {
            $passed = fly_password_verify($password, $admin['password']);
            if (!$passed && $admin['password'] === $password) {
                $passed = true; // 兼容旧版明文
            }
            if ($passed && fly_password_needs_rehash($admin['password'])) {
                $db->update('admins', array('password' => fly_password_hash($password)), 'id = ?', array((int)$admin['id']));
            }
        }
        if ($passed) {
            session_regenerate_id(true);
            $_SESSION['fly_admin_id']   = (int)$admin['id'];
            $_SESSION['fly_admin_name'] = $admin['username'];
            $_SESSION['fly_admin_role'] = $admin['role'] ?: 'admin';

            if ($row) {
                $db->update('login_attempts', array('fails' => 0, 'lock_until' => null), 'ip = ?', array($ip));
            }
            try {
                $db->update('admins', array('last_login_at' => date('Y-m-d H:i:s')), 'id = ?', array((int)$admin['id']));
            } catch (Exception $e) { /* 列不存在时忽略 */ }

            if ($remember) {
                fly_admin_remember_issue((int)$admin['id']);
            }
            $fly_log_login($usernameVal, true);
            fly_redirect('index.php');
        }
        $err = '用户名或密码不正确。';
        $fly_log_login($usernameVal, false);
        $fly_fail_once();
        if ($fails > 0) {
            $err .= '（今日已连续输错 ' . $fails . ' 次，满 5 次将锁定本 IP 1 小时）';
        }
    }
}

// 登录页专用样式（不污染前台 app.css）
$loginCss = fly_base_url() . 'assets/login.css?v=' . rawurlencode(FLY_VERSION);
$siteName = fly_site_name();
$homeUrl  = fly_base_url();
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>后台登录 - <?= fly_e($siteName) ?> · FlyLog</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<link rel="stylesheet" href="<?= fly_e($loginCss) ?>">
</head>
<body class="login-body">

<div class="login-shell">

  <!-- 左侧品牌区（窄屏自动隐藏） -->
  <aside class="login-brand" aria-hidden="true">
    <div class="brand-mark">
      <svg class="brand-logo" viewBox="0 0 150 150" width="54" height="54" role="img" aria-label="FlyLog">
        <defs>
          <linearGradient id="lg_brand" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#16407E"/><stop offset="1" stop-color="#0A2A5E"/>
          </linearGradient>
        </defs>
        <rect x="2.5" y="2.5" width="145" height="145" rx="37.5" fill="url(#lg_brand)"/>
        <path fill="#22D3EE" transform="translate(35 98.75)" d="M12.56 6.45Q12.08 6.83 11.59 7.2Q10.53 7.97 10.39 9.27Q10.18 10.57 11.01 11.58Q11.77 12.65 13.08 12.78Q14.37 12.99 15.39 12.17Q15.92 11.76 16.43 11.36Q17.48 10.58 17.6 9.27Q17.79 7.97 16.95 6.97Q16.16 5.92 14.86 5.8Q13.56 5.61 12.56 6.45ZM-1.1 15.75L-1.66 16.1Q-2.79 16.76 -3.05 18.05Q-3.38 19.32 -2.65 20.41Q-1.99 21.54 -0.7 21.8Q0.57 22.13 1.66 21.4L2.21 21.05L2.77 20.7Q3.9 20.04 4.16 18.75Q4.49 17.48 3.76 16.39Q3.09 15.27 1.81 15.01Q0.54 14.68 -0.55 15.41L-1.1 15.75Z"/>
        <path fill="#FFF" transform="translate(77.5 30)" d="M0 0L47.5 60L0 47.5L0 0Z"/>
        <path fill="#22D3EE" transform="translate(77.5 77.5)" d="M0 0L47.5 12.5L0 42.5L0 0Z"/>
      </svg>
      <div class="brand-text">
        <strong>FlyLog</strong>
        <span>轻量现代博客系统</span>
      </div>
    </div>

    <h2 class="brand-title">管理你的站点</h2>
    <p class="brand-desc">零框架、零依赖的纯原生 PHP 博客内核，四种数据库开箱即用。</p>

    <ul class="brand-list">
      <li><span class="dot"></span>文章 · 页面 · 分类 · 留言，一处管理</li>
      <li><span class="dot"></span>SEO 全链路：canonical / OG / JSON-LD / 站点地图</li>
      <li><span class="dot"></span>多级角色、登录日志、防爆破与验证码保护</li>
    </ul>

    <div class="brand-foot">
      <span class="ver-badge">V<?= fly_e(FLY_VERSION) ?></span>
      <span>Powered by FlyLog PHP</span>
    </div>
  </aside>

  <!-- 右侧登录卡片 -->
  <main class="login-main">
    <div class="login-card">

      <div class="card-head">
        <svg class="card-logo" viewBox="0 0 150 150" width="40" height="40" aria-hidden="true">
          <rect x="2.5" y="2.5" width="145" height="145" rx="37.5" fill="#0A2A5E"/>
          <path fill="#22D3EE" transform="translate(35 98.75)" d="M12.56 6.45Q12.08 6.83 11.59 7.2Q10.53 7.97 10.39 9.27Q10.18 10.57 11.01 11.58Q11.77 12.65 13.08 12.78Q14.37 12.99 15.39 12.17Q15.92 11.76 16.43 11.36Q17.48 10.58 17.6 9.27Q17.79 7.97 16.95 6.97Q16.16 5.92 14.86 5.8Q13.56 5.61 12.56 6.45Z"/>
          <path fill="#FFF" transform="translate(77.5 30)" d="M0 0L47.5 60L0 47.5L0 0Z"/>
          <path fill="#22D3EE" transform="translate(77.5 77.5)" d="M0 0L47.5 12.5L0 42.5L0 0Z"/>
        </svg>
        <div>
          <h1 class="card-title">后台登录</h1>
          <p class="card-sub"><?= fly_e($siteName) ?></p>
        </div>
      </div>

      <?php if ($err !== ''): ?>
        <div class="alert alert-<?= fly_e($errKind) ?>" role="alert">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
          <span><?= fly_e($err) ?></span>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['gone']) && $err === ''): ?>
        <div class="alert alert-warn" role="alert">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
          <span>登录状态已失效（账号可能已被删除），请重新登录。</span>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['timeout']) && $err === ''): ?>
        <div class="alert alert-info" role="alert">
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
          <span>长时间未操作，已自动退出登录。</span>
        </div>
      <?php endif; ?>

      <form method="post" action="login.php" class="login-form" id="loginForm" autocomplete="on">
        <?= fly_csrf_field() ?>

        <div class="field">
          <label for="username">用户名</label>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 5.5V21h18v-1.5c0-3-4-5.5-9-5.5z"/></svg>
            <input id="username" name="username" type="text" value="<?= fly_e($usernameVal) ?>"
                   autocomplete="username" required autofocus maxlength="100" placeholder="请输入用户名">
          </div>
        </div>

        <div class="field">
          <label for="password">密码</label>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 1a5 5 0 00-5 5v3H6a2 2 0 00-2 2v9a2 2 0 002 2h12a2 2 0 002-2v-9a2 2 0 00-2-2h-1V6a5 5 0 00-5-5zm-3 8V6a3 3 0 016 0v3H9z"/></svg>
            <input id="password" name="password" type="password" autocomplete="current-password"
                   required maxlength="72" placeholder="请输入登录密码">
            <button type="button" class="eye" id="eyeBtn" aria-label="显示密码" title="显示/隐藏密码">
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 5c-5 0-9 4.5-9 7s4 7 9 7 9-4.5 9-7-4-7-9-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z"/></svg>
            </button>
          </div>
        </div>

        <?php if ($captchaOn): ?>
        <div class="field">
          <label for="captcha">验证码</label>
          <div class="captcha-row">
            <div class="input-wrap captcha-input">
              <svg class="input-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 1l3 5h-2v4h-2V6H9l3-5zm0 22l-3-5h2v-4h2v4h2l-3 5zM2 9l5-3v2h4v2H7v2L2 9zm20 6l-5 3v-2h-4v-2h4v-2l5 3z"/></svg>
              <input id="captcha" name="captcha" type="text" autocomplete="off" required
                     maxlength="8" placeholder="请输入右侧字符">
            </div>
            <img class="captcha-img" id="captchaImg" src="captcha.php?t=<?= time() ?>"
                 alt="图形验证码，点击可刷新" title="点击刷新验证码" width="120" height="42">
          </div>
        </div>
        <?php endif; ?>

        <div class="form-row">
          <label class="check"><input type="checkbox" name="remember" value="1"> <span>记住我（30 天）</span></label>
          <button type="button" class="link-btn" id="forgotBtn">忘记密码？</button>
        </div>

        <button type="submit" class="btn-login" id="submitBtn">登 录</button>
      </form>

      <div class="forgot-box" id="forgotBox" hidden>
        <strong>密码找回方式</strong>
        <p>FlyLog 不提供在线找回（无邮件服务时不发信），请用下列任一方式重置：</p>
        <ol>
          <li>命令行：<code>php cli.php admin:reset &lt;用户名&gt; &lt;新密码&gt;</code></li>
          <li>数据库：把 <code>admins</code> 表的 <code>password</code> 字段改为 <code>password_hash()</code> 生成的哈希</li>
        </ol>
        <p class="muted">重置后旧「记住我」令牌会自动失效。</p>
      </div>

      <div class="login-foot">
        <a class="back-home" href="<?= fly_e($homeUrl) ?>">← 返回站点首页</a>
        <span class="copy">Powered by FlyLog PHP V<?= fly_e(FLY_VERSION) ?></span>
      </div>

    </div>
  </main>
</div>

<script>
(function () {
  // 密码显示 / 隐藏
  var pwd = document.getElementById('password');
  var eye = document.getElementById('eyeBtn');
  if (pwd && eye) {
    eye.addEventListener('click', function () {
      var show = pwd.type === 'password';
      pwd.type = show ? 'text' : 'password';
      eye.setAttribute('aria-label', show ? '隐藏密码' : '显示密码');
      eye.classList.toggle('on', show);
    });
  }

  // 点击验证码刷新
  var img = document.getElementById('captchaImg');
  if (img) {
    img.addEventListener('click', function () {
      img.src = 'captcha.php?t=' + Date.now();
    });
    img.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { img.src = 'captcha.php?t=' + Date.now(); }
    });
  }

  // 忘记密码：就地展开说明，不跳转
  var fb = document.getElementById('forgotBtn');
  var box = document.getElementById('forgotBox');
  if (fb && box) {
    fb.addEventListener('click', function () {
      box.hidden = !box.hidden;
      fb.textContent = box.hidden ? '忘记密码？' : '收起说明';
    });
  }

  // 提交节流：防重复点击
  var form = document.getElementById('loginForm');
  var btn = document.getElementById('submitBtn');
  if (form && btn) {
    form.addEventListener('submit', function () {
      btn.disabled = true;
      btn.classList.add('loading');
      btn.textContent = '登录中…';
    });
  }
})();
</script>
</body>
</html>
