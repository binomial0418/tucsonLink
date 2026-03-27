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
