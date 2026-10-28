<?php
/**
 * FlyLog V2.0 GitHub 登录（OAuth，配置开关）
 * 未在后台配置 client_id/secret 或未启用时，显示友好提示；配置后走标准 OAuth 流程。
 */
require __DIR__ . '/config.php';

$enabled = fly_feature('github_login', false) && (string)fly_opt('github.client_id', '') !== '';
$cid = (string)fly_opt('github.client_id', '');
$secret = (string)fly_opt('github.client_secret', '');
$base = fly_base_url();
$redirectUri = $base . 'github.php';

$meta = array('title' => 'GitHub 登录 - ' . fly_site_name());
echo fly_render('parts/head', compact('meta'));
echo fly_render('parts/header', array('breadcrumb' => array(array('name' => '首页', 'url' => fly_url('home')), array('name' => 'GitHub 登录'))));

if (!$enabled) {
    echo '<div class="wrap"><div class="center-card"><h1>GitHub 登录未启用</h1>'
        . '<p class="sub">管理员尚未在后台「设置」中配置 GitHub OAuth 或未打开开关。</p>'
        . '<a class="btn" href="account.php?action=login">返回登录</a></div></div>';
    echo fly_render('parts/footer');
    exit;
}

// ---- 已配置：执行 OAuth ----
$code = isset($_GET['code']) ? (string)$_GET['code'] : '';
$state = isset($_GET['state']) ? (string)$_GET['state'] : '';

function gh_http_post($url, $fields, $headers = array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(array('Accept: application/json'), $headers));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}
function gh_http_get($url, $headers = array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(array('Accept: application/json', 'User-Agent: FlyLog'), $headers));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}

// 回调：用 code 换 token → 取用户 → 登录/注册
if ($code !== '') {
    if (!hash_equals((string)($_SESSION['fly_gh_state'] ?? ''), $state)) {
        echo '<div class="wrap"><div class="center-card"><div class="msg msg-err">state 校验失败，请重试。</div><a class="btn" href="account.php?action=login">返回</a></div></div>';
        echo fly_render('parts/footer');
        exit;
    }
    $tok = json_decode(gh_http_post('https://github.com/login/oauth/access_token', array(
        'client_id' => $cid, 'client_secret' => $secret, 'code' => $code, 'redirect_uri' => $redirectUri,
    )), true);
    if (empty($tok['access_token'])) {
        echo '<div class="wrap"><div class="center-card"><div class="msg msg-err">获取访问令牌失败，请检查 OAuth 配置。</div><a class="btn" href="account.php?action=login">返回</a></div></div>';
        echo fly_render('parts/footer');
        exit;
    }
    $user = json_decode(gh_http_get('https://api.github.com/user', array('Authorization: token ' . $tok['access_token'])), true);
    $login = isset($user['login']) ? (string)$user['login'] : ('gh_' . ($user['id'] ?? rand(1000, 9999)));
    $m = BaseMember::findByUsername($login);
    if (!$m) {
        $email = isset($user['email']) && $user['email'] ? (string)$user['email'] : '';
        $id = BaseMember::register($login, bin2hex(random_bytes(8)), $email); // 随机密码，GitHub 用户改用 OAuth 登录
        $m = BaseMember::find($id);
    }
    $_SESSION['fly_member_id'] = (int)$m['id'];
    fly_redirect('account.php');
}

// 发起授权
$state = bin2hex(random_bytes(8));
$_SESSION['fly_gh_state'] = $state;
$authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query(array(
    'client_id' => $cid, 'redirect_uri' => $redirectUri, 'state' => $state, 'scope' => 'read:user',
));
echo '<div class="wrap"><div class="center-card"><h1>GitHub 登录</h1><p class="sub">点击下面按钮，跳转到 GitHub 授权。</p>'
    . '<a class="btn" href="' . fly_e($authUrl) . '">用 GitHub 账号登录</a></div></div>';
echo fly_render('parts/footer');
