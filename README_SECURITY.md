# Tucson Link 車輛遠端控制系統 - 安全升級指南

## 📋 更新內容

本次升級分離了資料庫認證邏輯，並加入完整的登入認證機制。

### 新增檔案

#### 1. `config/db.php` - 資料庫認證設定
- **功能**: 集中管理所有資料庫連接設定
- **位置**: `/config/db.php`
- **內容**:
  - 資料庫主機、埠口、名稱設定
  - 資料庫用戶名和密碼
  - `getDatabaseConnection()` 函數 - 獲取 PDO 連接
  - `testDatabaseConnection()` 函數 - 測試連接

**範例使用**:
```php
require_once 'config/db.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT * FROM table_name");
    $stmt->execute();
    $result = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}
```

#### 2. `config/auth.php` - 登入認證機制
- **功能**: 完整的會話管理和使用者認證
- **位置**: `/config/auth.php`
- **主要函數**:

| 函數名 | 說明 |
|-------|------|
| `validateLogin($username, $password)` | 驗證帳號密碼 |
| `setUserSession($username)` | 設置使用者會話 |
| `isUserLoggedIn()` | 檢查使用者是否已登入 |
| `getCurrentUser()` | 獲取當前登入使用者 |
| `logout()` | 執行登出操作 |
| `generateCSRFToken()` | 生成 CSRF 防護令牌 |
| `verifyCSRFToken($token)` | 驗證 CSRF 令牌 |
| `requireLogin($redirectTo)` | 頁面級登入驗證 |

**預設認證資訊**:
- **帳號**: `admin`
- **密碼**: `Tucson@2024`

⚠️ **安全提示**: 請立即修改預設密碼！

#### 3. `login.php` - 登入頁面
- **功能**: 使用者登入界面
- **特性**:
  - 現代化的 UI 設計
  - CSRF 防護
  - 錯誤提示
  - 振動反饋
  - iOS Web App 支持

#### 4. `logout.php` - 登出處理
- **功能**: 安全清除會話並重定向到登入頁面

### 修改檔案

#### `car-view.php` - 主要應用頁面
**變更內容**:
1. 移除硬編碼的資料庫認證資訊
2. 引入 `config/auth.php` 進行登入驗證
3. 引入 `config/db.php` 獲取資料庫連接
4. 添加登出按鈕
5. 添加登出確認對話框

**新增程式碼**:
```php
<?php
require_once 'config/auth.php';
require_once 'config/db.php';

// 驗證使用者是否已登入
requireLogin('car-view.php');

// 使用資料庫連接
try {
    $pdo = getDatabaseConnection();
    // ... 資料庫操作
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
}
?>
```

---

## 🚀 使用流程

### 首次訪問
1. 用戶訪問 `car-view.php`
2. 未登入時自動重定向到 `login.php`
3. 輸入帳號和密碼登入
4. 登入成功後重定向回應用頁面

### 日常使用
1. 打開應用進行車輛控制
2. 點擊右下角「登出」按鈕
3. 確認登出

### 會話管理
- **超時設定**: 1 小時無活動自動登出
- **CSRF 保護**: 每次登入生成新的安全令牌
- **安全重定向**: 登出後清除所有會話數據

---

## 🔒 安全建議

### 立即行動

1. **修改預設密碼**
   ```php
   // 編輯 config/auth.php
   define('ADMIN_PASSWORD', 'Your_Secure_Password_Here');
   ```

2. **使用密碼雜湊** (生產環境)
   ```php
   // 更新驗證邏輯使用 password_hash
   if (password_verify($password, $hashedPassword)) {
       // 密碼驗證成功
   }
   ```

3. **添加更多使用者**
   ```php
   // 在資料庫中建立 users 表
   function validateLoginFromDB($username, $password) {
       $pdo = getDatabaseConnection();
       $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = ?");
       $stmt->execute([$username]);
       $user = $stmt->fetch();
       return $user && password_verify($password, $user['password_hash']);
   }
   ```

4. **啟用 HTTPS**
   - 確保伺服器使用 SSL/TLS 加密
   - 所有登入數據都應在加密通道傳輸

5. **定期備份認證檔案**
   - 將 `config/` 目錄備份到安全位置

---

## 📁 檔案結構

```
tucsonLink/
├── config/
│   ├── db.php          # 資料庫認證 ✨ 新增
│   └── auth.php        # 登入認證系統 ✨ 新增
├── car-view.php        # 主應用頁面 (已修改)
├── login.php           # 登入頁面 ✨ 新增
├── logout.php          # 登出處理 ✨ 新增
├── icon.png
├── car.png
└── TucsonL-NX4-Book.pdf
```

---

## ⚙️ 配置修改

### 變更資料庫連接
編輯 `config/db.php`:
```php
define('DB_HOST', '192.168.1.100');  // 更改主機
define('DB_PORT', '3306');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

### 變更會話超時
編輯 `config/auth.php`:
```php
define('SESSION_TIMEOUT', 7200);  // 改為 2 小時
```

### 變更登入帳號
編輯 `config/auth.php`:
```php
define('ADMIN_USERNAME', 'your_username');
define('ADMIN_PASSWORD', 'your_password');
```

---

## 🧪 測試

### 測試登入功能
1. 清除瀏覽器 Cookie
2. 訪問 `car-view.php`
3. 應自動重定向到 `login.php`
4. 使用 `admin` / `Tucson@2024` 登入

### 測試登出功能
1. 登入後點擊右下角「登出」按鈕
2. 確認對話框後應重定向到登入頁面

### 測試會話超時
1. 登入應用
2. 等待 1 小時
3. 刷新頁面應重定向到登入頁面

---

## 📝 常見問題

**Q: 忘記密碼怎麼辦？**
A: 直接編輯 `config/auth.php` 修改 `ADMIN_PASSWORD`。未來可實現「忘記密碼」功能。

**Q: 如何支持多使用者？**
A: 建立資料庫表並修改 `validateLogin()` 函數從資料庫查詢用戶。

**Q: 可以跳過登入嗎？**
A: 移除 `car-view.php` 頂部的 `requireLogin()` 調用，但不建議在生產環境這樣做。

**Q: 如何檢查目前登入的使用者？**
A: 使用 `getCurrentUser()` 函數：
```php
$currentUser = getCurrentUser();
if ($currentUser) {
    echo "已登入：" . $currentUser;
}
```

---

## 🔄 升級步驟

1. ✅ 新建 `config/` 目錄
2. ✅ 上傳 `config/db.php` 和 `config/auth.php`
3. ✅ 上傳 `login.php` 和 `logout.php`
4. ✅ 備份並替換 `car-view.php`
5. ✅ 測試登入流程
6. ✅ 修改預設密碼

---

## 📞 技術支持

如遇問題，檢查：
1. PHP 版本是否 ≥ 7.0
2. `session.save_path` 目錄是否可寫
3. 資料庫連接是否正常
4. 瀏覽器是否允許 Cookie

---

**版本**: 1.0  
**更新日期**: 2024年12月8日  
**維護者**: Tucson Link Team
