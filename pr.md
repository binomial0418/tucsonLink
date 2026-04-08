# Tucson Link PR 開發流程

本文件定義了專案在開發、審查到合併的自動化 SOP。

---

## P1：AI1 內測完成，啟動 PR 建立
**執行角色**：`AI1`

> 「程式碼測試完成了。請幫我嚴格執行以下 Git 與 GitHub CLI 流程：
> 
> 1. 將所有變更 `git add`，並撰寫符合 **Conventional Commits** 規範的 commit 訊息。
> 2. 將分支推送到遠端 (`git push origin HEAD`)。
> 3. 使用 `gh pr create --fill` 在終端機建立 Pull Request（請自動分析變更並填寫詳細的 Title 與 Body）。
> 
> **【關鍵任務】** PR 建立完成後，務必執行 `gh pr diff > pr_diff.txt` 將本次所有差異匯出成 `pr_diff.txt` 檔案，並儲存在專案根目錄，用於後續 Code Review。」

---

## P2：AI2 擔任 Senior Tech Lead 進行審查
**執行角色**：`AI2 (Senior Tech Lead)`

> 「你現在是一位嚴格的 **Senior Tech Lead**。請 Review `@pr_diff.txt` 中的 Pull Request 變更。
> 
> **審查重點：**
> * **效能與邏輯**：尋找潛在效能瓶頸（如 N+1 Query、不必要的迴圈）或併發問題。
> * **邊界條件**：檢查是否有未處理的 Null 值、極端輸入或錯誤捕捉 (Error Handling) 遺漏。
> * **架構與易讀性**：是否符合 DRY 原則與 Clean Code 規範。
> 
> **⚠️ 嚴格限制：** Think first, touch nothing. 請勿修改工作區內的任何程式碼。請利用 Antigravity 的 Artifact 功能，產出一份結構化的 Markdown Code Review 報告，將問題分為 **『Blocker (必須修復)』** 與 **『Nitpick (建議優化)』**。」

---

## P3：AI1 針對報告進行程式碼修復
**執行角色**：`AI1`

> 「這是 Senior Tech Lead 的 Review 報告，請幫我針對這個分支進行程式碼修復。
> 
> **修復完成後，請執行以下流程：**
> 1. 將變更 `git add` 並 `git commit`。
> 2. 將變更 `git push origin HEAD` 更新現有的 PR。
> 3. 再次執行 `gh pr diff > pr_diff.txt` 覆蓋舊的差異檔，進行第二次 Review。」

---

## P4：AI1 完成審查並合併 PR
**執行角色**：`AI1`

> 「Code Review 已經完美通過！請幫我執行以下流程來合併 PR 並清理環境：
> 
> 1. 執行 `gh pr merge --squash --delete-branch`（壓縮合併並自動刪除遠端分支）。
> 2. 切換回主分支 `git checkout main` 並執行 `git pull` 更新本地代碼。
> 3. 刪除本地端的 feature 分支。
> 4. 執行 `rm pr_diff.txt` 刪除差異檔，清理工作區。」
