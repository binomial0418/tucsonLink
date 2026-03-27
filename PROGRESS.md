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
