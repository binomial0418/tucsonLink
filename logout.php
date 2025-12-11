<?php
/**
 * ==========================================
 * 登出處理頁面
 * ==========================================
 */

require_once 'config/auth.php';

// 執行登出操作
handleLogoutRequest();

// 重定向到登入頁面
header("Location: login.php");
exit;
