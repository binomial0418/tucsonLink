# PROGRESS.md

## 2026-03-27

### 窗戶 / Key 彈出選單改版（index.php）

#### 佈局調整
- 窗戶與 Key 彈出選單改為垂直排列（`flex-direction: column`）
- 選單內按鈕文字改放置於圖示右方（`flex-direction: row`），字體放大至 13px

#### 定位改進
- `#expansion-panel` 移除 `right: 0`，改為 `width: fit-content; min-width: 110px`，選單寬度隨內容自適應
- `toggleExpansion(type, btn)` 加入動態定位：根據點擊按鈕的 `getBoundingClientRect()` 計算水平中心，設定 `panel.style.left`，讓選單對齊母按鈕位置
- `transition: all` 改為只作用於 `max-height`、`opacity`、`transform`，排除 `left`，避免選單從左右飛入的視覺問題
- `bottom` 從 `calc(100% + 10px)` 調整至 `calc(100% - 16px)`，抵銷 controls-card 的 `padding-top: 20px`，讓選單底部視覺上貼近母按鈕

### 長按確認機制改版（index.php）

#### 以 CD 進度條取代環形動畫
- 移除原本按鈕上的 SVG ring 動畫邏輯
- 新增 `#press-cd-overlay` 固定於畫面正中央，按下任何控制按鈕時出現
- 進度條從左到右填滿（`width: 0% → 100%`，duration 由 `--button-press-duration` 控制），集滿後觸發指令
- 提前放開手指即取消，進度條立即消失
- 觸發時保留震動回饋（`[50, 30, 100]` ms pattern）

#### CD 條視覺設計
- 毛玻璃卡片外觀（`backdrop-filter: blur`、圓角、陰影），與整體 UI 風格一致
- 出現動畫：scale 0.92 → 1 搭配 opacity 淡入
- 上方顯示指令名稱（上鎖、解鎖、開窗、關窗、連結、斷開、啟動／熄火）
- 下方顯示「放開可取消」提示文字

#### iOS visibilitychange 誤觸修正
- 加入 `wasHidden` 旗標：`visibilitychange` 只有在頁面曾進入 `hidden` 後回到 `visible` 才觸發刷新
- 修正 iOS PWA 第一次互動時誤觸發 `refreshDataSilent()` 的問題

---

## 2026-03-28

### 指令觸發後自動關閉選單並更新資料（index.php）

- 長按 CD 條跑完成功觸發指令後，若展開選單（窗戶／Key）為開啟狀態，自動收起
- 觸發後延遲 1.5 秒呼叫 `refreshDataSilent()`，讓 MQTT 指令生效後畫面自動反映最新狀態

### WakeLock NotAllowedError 靜默處理（index.php）

- `requestWakeLock()` 的 catch 區塊改為：`NotAllowedError`（系統拒絕，如低電量模式）靜默忽略，其餘錯誤仍印出 `console.warn`，消除 Safari Web Inspector 無意義的警告訊息

### Service Worker 修正外部 API 攔截問題（sw.js）

- SW fetch handler 加入同源判斷：非同源請求（如 `api.open-meteo.com`）直接 `return`，不呼叫 `respondWith()`，修正跨來源 fetch 失敗導致 `FetchEvent.respondWith received an error` 的 console 錯誤
- Cache 版本號從 `hyundai-link-v1` 升至 `hyundai-link-v2`，強制瀏覽器更新舊版 SW

---

### iOS PWA 背景更新與螢幕常亮修正（index.php）

#### 背景更新機制重構
- iOS PWA 背景時 JS 完全凍結，`setInterval` 停止，這是系統限制無法繞過
- 移除 `wasHidden` 旗標邏輯，改為：回到前台（`visibilitychange → visible`）時直接計算距上次更新經過時間，超過半個週期才補一次更新，更可靠
- 加入 `lastRefreshTime` 時間戳記，每次 `refreshDataSilent()` 完成後更新
- `pageshow` 改為只在 `event.persisted === true`（bfcache 恢復）時觸發，修正原本初始載入也會多餘 fetch 的問題
- 回前台與 bfcache 恢復時均重置 `setInterval` 計時器，確保下一次間隔從現在起算

#### Screen Wake Lock（螢幕常亮）
- 新增 `requestWakeLock()` 函式，使用 `navigator.wakeLock.request('screen')`（iOS 16.4+）
- 加入 visibility 與 `wakeLock.released` 雙重守衛，避免在背景或重複 acquire
- 監聽 wake lock sentinel 的 `release` 事件：系統強制釋放時（低電量/切換 app）自動重新 acquire
- 失敗時改為 `console.warn` 輸出錯誤名稱，方便 Safari Web Inspector 偵錯
- 在初始化、回前台（`visibilitychange`）、bfcache 恢復（`pageshow persisted`）三個時機均觸發

### Remember-Me 免登入機制（config/auth.php）

#### 問題
- iOS PWA 在背景被系統回收後，PHP session（`PHPSESSID` cookie）會遺失，導致每次重開 app 都需要重新登入

#### 解法：長效 remember-me token
- 登入成功時產生隨機 token，SHA-256 雜湊後存到伺服器端檔案 `config/.remember_tokens`，原始 token 透過 `tucson_remember` cookie 寫到瀏覽器（有效期 1 年）
- `isUserLoggedIn()` 偵測到 session 遺失或過期時，自動呼叫 `tryRememberLogin()` 比對 cookie token 與伺服器端雜湊，匹配即恢復 session
- 每次自動恢復時 token 輪換（舊 token 失效、發新 token），降低 token 洩漏風險
- 手動登出時同時清除 cookie 和伺服器端 token，確保登出後必須重新輸入帳密

#### 異動檔案
- `config/auth.php` — 新增 `setRememberToken()`、`tryRememberLogin()`、`clearRememberToken()`，修改 `isUserLoggedIn()`、`handleLoginRequest()`、`logout()`
- `.gitignore` — 加入 `config/.remember_tokens`
