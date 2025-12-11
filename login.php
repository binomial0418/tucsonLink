<?php
/**
 * ==========================================
 * Tucson Link 登入系統
 * ==========================================
 */

require_once 'config/auth.php';

$loginResult = null;
$showError = false;

// 處理登入表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginResult = handleLoginRequest();
    if ($loginResult['success']) {
        // 登入成功，重定向到首頁或指定頁面
        $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'car-view.php';
        header("Location: " . $redirect);
        exit;
    }
    $showError = true;
}

// 生成 CSRF 令牌
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- iOS Web App 設定 -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Tucson Link">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="application-name" content="Tucson Link">
    <link rel="apple-touch-icon" href="icon.png">
    
    <title>Tucson Link - 登入</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-main: #1d1d1f;
            --text-sub: #86868b;
            --text-light: #b0b0b5;
            --shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            --color-good: #34c759;
            --color-warning: #ff9f0a;
            --color-danger: #ff3b30;
            --accent-blue: #007aff;
            --safe-top: env(safe-area-inset-top, 20px);
            --safe-bottom: env(safe-area-inset-bottom, 20px);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
        }

        input, textarea {
            -webkit-user-select: text;
            user-select: text;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            overscroll-behavior: none;
        }

        .app-container {
            width: 100%;
            max-width: 420px;
            background-color: var(--card-bg);
            height: 100dvh;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .login-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 30px;
            text-align: center;
        }

        .logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--color-good) 100%);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            margin: 0 auto 25px;
            box-shadow: 0 12px 30px rgba(0, 122, 255, 0.25);
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .logo i {
            color: white;
        }

        .login-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 14px;
            color: var(--text-sub);
            margin-bottom: 30px;
        }

        .form-group {
            width: 100%;
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 14px;
            font-size: 16px;
            font-family: inherit;
            background: rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
            color: var(--text-main);
        }

        .form-group input:focus {
            outline: none;
            background: white;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .form-group input::placeholder {
            color: var(--text-light);
        }

        .error-message {
            display: none;
            padding: 14px 16px;
            background: rgba(255, 59, 48, 0.08);
            border-left: 4px solid var(--color-danger);
            border-radius: 10px;
            color: var(--color-danger);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            animation: shake 0.3s ease-in-out;
        }

        .error-message.show {
            display: block;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, #0051d5 100%);
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .login-btn:active {
            transform: scale(0.98);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .loading-spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-spinner.show {
            display: inline-block;
        }

        .footer-info {
            padding: 20px 30px;
            padding-bottom: calc(20px + var(--safe-bottom));
            text-align: center;
            font-size: 12px;
            color: var(--text-light);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .footer-info p {
            margin-bottom: 6px;
            opacity: 0.7;
        }

        input::-webkit-contacts-auto-fill-button {
            display: none !important;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <div class="login-content">
            <div class="logo">
                <i class="fas fa-car"></i>
            </div>
            
            <div class="login-header">
                <h1>Tucson Link</h1>
                <p>車輛遠端控制系統</p>
            </div>

            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i> <span id="errorText"></span>
            </div>

            <form id="loginForm" method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" style="width: 100%;">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="form-group">
                    <label for="username">帳號</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="輸入帳號"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">密碼</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="輸入密碼"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="login-btn" id="submitBtn">
                    <span class="loading-spinner" id="spinner"></span>
                    <span id="btnText">登入</span>
                </button>
            </form>
        </div>

        <div class="footer-info">
            <p><i class="fas fa-info-circle"></i> 預設帳號：duckegg</p>
            <p style="opacity: 0.5;">v1.0 © 2024 Hyundai Tucson Link</p>
        </div>
    </div>

    <script>
        // 隱藏瀏覽器介面 (iOS Safari)
        let lastScrollTop = 0;
        
        function hideAddressBar() {
            // 使用 scrollTo 強制隱藏位址列
            if (window.scrollY === 0) {
                window.scrollTo(0, 1);
            }
        }

        // 在頁面加載時隱藏位址列
        window.addEventListener('load', () => {
            setTimeout(hideAddressBar, 100);
            setTimeout(hideAddressBar, 500);
        });

        // 觸摸開始時隱藏位址列
        document.addEventListener('touchstart', () => {
            hideAddressBar();
        }, { passive: true });

        // 防止用戶下拉刷新露出位址列
        document.addEventListener('touchmove', (e) => {
            if (window.scrollY < 0) {
                e.preventDefault();
            }
        }, { passive: false });

        // 防止過度滾動
        let startY = 0;
        document.addEventListener('touchstart', (e) => {
            startY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', () => {
            hideAddressBar();
        }, { passive: true });

        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const spinner = document.getElementById('spinner');
        const btnText = document.getElementById('btnText');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');

        // 顯示錯誤訊息
        <?php if ($showError && isset($loginResult)) { ?>
            errorMessage.classList.add('show');
            errorText.textContent = '<?php echo htmlspecialchars($loginResult['message']); ?>';
            if (navigator.vibrate) navigator.vibrate(200);
        <?php } ?>

        // 表單提交事件
        form.addEventListener('submit', (e) => {
            submitBtn.disabled = true;
            spinner.classList.add('show');
            btnText.textContent = '登入中...';
            errorMessage.classList.remove('show');
        });

        // 密碼欄位按 Enter 提交
        document.getElementById('password').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                form.submit();
            }
        });

        // 監聽自動填充事件 (Face ID/Touch ID 或自動填充)
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');

        // 使用 change 事件監聽自動填充
        let autoFillDetected = false;
        
        passwordInput.addEventListener('change', () => {
            autoFillDetected = true;
            checkAndAutoSubmit();
        });

        // 監聽輸入完成事件 (WebAuthn autofill)
        passwordInput.addEventListener('input', debounce(() => {
            if (usernameInput.value.trim() && passwordInput.value.trim()) {
                checkAndAutoSubmit();
            }
        }, 500));

        // 監聽粘貼事件 (長按粘貼時自動提交)
        passwordInput.addEventListener('paste', () => {
            setTimeout(() => {
                if (usernameInput.value.trim() && passwordInput.value.trim()) {
                    checkAndAutoSubmit();
                }
            }, 100);
        });

        // 防抖函數
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // 檢查並自動提交
        function checkAndAutoSubmit() {
            const username = usernameInput.value.trim();
            const password = passwordInput.value.trim();
            
            // 只有在兩個欄位都有內容且至少 3 個字符時才自動提交
            if (username.length >= 3 && password.length >= 3) {
                setTimeout(() => {
                    autoSubmitForm();
                }, 300);
            }
        }

        // 自動提交表單
        function autoSubmitForm() {
            if (submitBtn.disabled) return; // 避免重複提交
            
            submitBtn.disabled = true;
            spinner.classList.add('show');
            btnText.textContent = '登入中...';
            errorMessage.classList.remove('show');
            
            form.submit();
        }

        // 隱藏加載動畫（頁面加載完成時）
        window.addEventListener('load', () => {
            if (!<?php echo $showError ? 'true' : 'false'; ?>) {
                spinner.classList.remove('show');
                submitBtn.disabled = false;
                btnText.textContent = '登入';
            }

            // 延遲隱藏位址列
            setTimeout(hideAddressBar, 1000);
        });

        // 視口變化時重新隱藏位址列
        window.addEventListener('orientationchange', () => {
            setTimeout(hideAddressBar, 100);
        });

        // 監聽視口高度變化 (鍵盤彈出時)
        window.addEventListener('resize', () => {
            setTimeout(hideAddressBar, 100);
        });
    </script>
</body>
</html>
