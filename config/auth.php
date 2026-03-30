<?php
/**
 * ==========================================
 * 登入認證系統
 * ==========================================
 * 負責會話管理、使用者驗證、密碼安全等
 */

session_start();

require_once __DIR__ . '/credentials.php';

define('SESSION_TIMEOUT', 3600000); // 1000小時
define('SESSION_KEY', 'tucson_user');
define('REMEMBER_TOKEN_FILE', __DIR__ . '/.remember_tokens');
define('REMEMBER_COOKIE_NAME', 'tucson_remember');
define('REMEMBER_COOKIE_LIFETIME', 86400 * 365); // 1 年

/**
 * 驗證使用者登入
 * @param string $username 使用者名稱
 * @param string $password 密碼
 * @return boolean
 */
function validateLogin($username, $password)
{
    if ($username !== ADMIN_USERNAME) {
        return false;
    }
    if (defined('ADMIN_PASSWORD_NEEDS_REHASH') && ADMIN_PASSWORD_NEEDS_REHASH) {
        return $password === ADMIN_PASSWORD_HASH;
    }
    return password_verify($password, ADMIN_PASSWORD_HASH);
}

/**
 * 設置使用者會話
 * @param string $username 使用者名稱
 */
function setUserSession($username)
{
    $_SESSION[SESSION_KEY] = [
        'username' => $username,
        'login_time' => time(),
        'last_activity' => time(),
    ];
}

/**
 * 檢查使用者是否已登入
 * @return boolean
 */
function isUserLoggedIn()
{
    if (!isset($_SESSION[SESSION_KEY])) {
        // Session 遺失時嘗試用 remember-me cookie 自動恢復
        return tryRememberLogin();
    }

    // 檢查會話超時
    $lastActivity = $_SESSION[SESSION_KEY]['last_activity'];
    if (time() - $lastActivity > SESSION_TIMEOUT) {
        unset($_SESSION[SESSION_KEY]);
        // Session 過期時也嘗試用 remember-me cookie 恢復
        return tryRememberLogin();
    }

    // 更新最後活動時間
    $_SESSION[SESSION_KEY]['last_activity'] = time();
    return true;
}

/**
 * 獲取當前登入的使用者名稱
 * @return string|null
 */
function getCurrentUser()
{
    if (isUserLoggedIn()) {
        return $_SESSION[SESSION_KEY]['username'];
    }
    return null;
}

/**
 * 執行登出操作
 */
function logout()
{
    clearRememberToken();
    unset($_SESSION[SESSION_KEY]);
    session_destroy();
}

/**
 * 驗證登入令牌 (CSRF 防護)
 * @return string
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 驗證 CSRF 令牌
 * @param string $token
 * @return boolean
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 讀取 token 檔案，回傳 array，並自動清除過期項目
 * 格式：{ "sha256hash": { "username": "...", "created": timestamp }, ... }
 */
function loadRememberTokens()
{
    if (!file_exists(REMEMBER_TOKEN_FILE)) {
        return [];
    }
    $tokens = json_decode(file_get_contents(REMEMBER_TOKEN_FILE), true);
    if (!is_array($tokens)) {
        return [];
    }
    // 清除過期 token
    $expiry = time() - REMEMBER_COOKIE_LIFETIME;
    foreach ($tokens as $hash => $entry) {
        if (!isset($entry['created']) || $entry['created'] < $expiry) {
            unset($tokens[$hash]);
        }
    }
    return $tokens;
}

/**
 * 產生並儲存 remember-me token，支援多裝置同時登入
 */
function setRememberToken($username)
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $tokens = loadRememberTokens();
    $tokens[$hash] = ['username' => $username, 'created' => time()];
    file_put_contents(REMEMBER_TOKEN_FILE, json_encode($tokens), LOCK_EX);

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(REMEMBER_COOKIE_NAME, $token, [
        'expires' => time() + REMEMBER_COOKIE_LIFETIME,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * 用 remember-me cookie 自動恢復 session（session 遺失時呼叫）
 * 輪換 token 時只替換當前裝置的那筆，不影響其他裝置
 */
function tryRememberLogin()
{
    if (!isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return false;
    }
    $tokens = loadRememberTokens();
    if (empty($tokens)) {
        return false;
    }
    $tokenHash = hash('sha256', $_COOKIE[REMEMBER_COOKIE_NAME]);
    // 找到符合的 token
    $matched = null;
    foreach ($tokens as $hash => $entry) {
        if (hash_equals($hash, $tokenHash)) {
            $matched = ['hash' => $hash, 'username' => $entry['username']];
            break;
        }
    }
    if (!$matched) {
        return false;
    }
    setUserSession($matched['username']);
    // 輪換：移除舊 hash，寫入新 token（只動這一筆）
    unset($tokens[$matched['hash']]);
    file_put_contents(REMEMBER_TOKEN_FILE, json_encode($tokens), LOCK_EX);
    setRememberToken($matched['username']);
    return true;
}

/**
 * 清除當前裝置的 remember-me token（登出時呼叫）
 */
function clearRememberToken()
{
    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $tokenHash = hash('sha256', $_COOKIE[REMEMBER_COOKIE_NAME]);
        $tokens = loadRememberTokens();
        unset($tokens[$tokenHash]);
        file_put_contents(REMEMBER_TOKEN_FILE, json_encode($tokens), LOCK_EX);
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * 處理登入請求
 * @return array ['success' => bool, 'message' => string]
 */
function handleLoginRequest()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => '無效的請求方式'];
    }

    // 驗證 CSRF 令牌
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        return ['success' => false, 'message' => '安全驗證失敗'];
    }

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => '帳號和密碼不能為空'];
    }

    if (validateLogin($username, $password)) {
        setUserSession($username);
        setRememberToken($username);
        return ['success' => true, 'message' => '登入成功'];
    }

    return ['success' => false, 'message' => '帳號或密碼錯誤'];
}

/**
 * 處理登出請求
 */
function handleLogoutRequest()
{
    logout();
}

/**
 * 重定向到登入頁面（如未登入）
 * @param string $redirectTo 登入後重定向到的頁面
 */
function requireLogin($redirectTo = 'index.php')
{
    if (!isUserLoggedIn()) {
        header("Location: login.php?redirect=" . urlencode($redirectTo));
        exit;
    }
}
