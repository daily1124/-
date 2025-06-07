# AI SEO Content Generator Pro

專業的WordPress AI驅動SEO內容自動生成系統

## 功能特色

### 🎯 智能關鍵字管理
- 自動從Google Trends抓取熱門關鍵字
- 支援一般關鍵字和娛樂城相關關鍵字分類
- 智能競爭度分析和優先級排序
- 每日自動更新關鍵字庫

### 📝 高品質內容生成
- 整合OpenAI GPT-4/GPT-3.5模型
- 支援8000字以上長文生成
- 自動優化文章結構和段落分布
- 智能圖片生成和插入

### 🔍 2025年精選摘要優化
- 優化段落式摘要結構
- 自動生成FAQ段落
- 支援列表和表格格式
- Schema.org結構化資料標記

### ⏰ 智能排程系統
- 獨立的台灣和娛樂城關鍵字排程
- 支援多種執行頻率設定
- 智能關鍵字選擇機制
- 台灣時區（UTC+8）支援

### 💰 成本控制與監控
- 即時API使用量追蹤
- 每日/每月預算設定
- 成本優化建議
- 詳細費用報表

### 📊 效能追蹤分析
- 文章瀏覽量統計
- 用戶參與度分析
- ROI效益評估
- 視覺化數據圖表

### 🛠️ 診斷與維護工具
- 系統健康檢查
- 功能測試工具
- 日誌管理系統
- 資料庫優化工具

## 系統需求

- WordPress 5.8 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- 記憶體限制：至少 128MB
- 必要的PHP擴展：curl, json, mbstring, openssl

## 安裝步驟

1. 下載外掛ZIP檔案
2. 在WordPress後台進入「外掛」→「安裝外掛」
3. 點擊「上傳外掛」並選擇ZIP檔案
4. 安裝完成後啟用外掛
5. 進入「AI SEO生成器」→「設定」配置API Key

## 基本設定

### OpenAI API Key設定
1. 前往 [OpenAI Platform](https://platform.openai.com/)
2. 註冊/登入帳號
3. 在API Keys頁面生成新的API Key
4. 將API Key貼到外掛設定中

### 預算設定
- 建議設定每日預算限制
- 啟用預算警告功能
- 定期檢查成本報告

## 使用指南

### 快速開始
1. **更新關鍵字**：進入「關鍵字管理」頁面，點擊「立即更新」
2. **生成文章**：在「內容生成」頁面輸入關鍵字，設定參數後開始生成
3. **設定排程**：在「排程設定」創建自動生成排程

### 進階功能

#### 批量生成
```bash
wp aisc generate "關鍵字1" --word-count=10000
wp aisc generate "關鍵字2" --model=gpt-4
```

#### 成本優化技巧
- 使用GPT-4 Turbo替代GPT-4（節省66%成本）
- 控制文章長度在8000-10000字
- 減少圖片生成數量
- 利用離峰時段排程

### SEO最佳實踐
1. 保持關鍵字密度在1-2%
2. 使用多層次標題結構（H2、H3）
3. 包含FAQ段落提升精選摘要機會
4. 添加內部連結和高權重外部連結

## WP-CLI 命令

### 關鍵字管理
```bash
# 更新所有關鍵字
wp aisc keywords update

# 列出關鍵字
wp aisc keywords list

# 分析特定關鍵字
wp aisc keywords analyze --keyword="WordPress SEO"
```

### 內容生成
```bash
# 生成文章
wp aisc generate "目標關鍵字" --word-count=8000 --model=gpt-4-turbo-preview

# 批量生成
wp aisc generate "關鍵字1" && wp aisc generate "關鍵字2"
```

### 排程管理
```bash
# 列出排程
wp aisc schedule list

# 創建排程
wp aisc schedule create --name="每日生成" --type=general --frequency=daily

# 執行排程
wp aisc schedule run --id=1
```

### 成本報告
```bash
# 查看成本
wp aisc cost

# 匯出報告
wp aisc cost --export=csv --period=month
```

### 系統診斷
```bash
# 完整診斷
wp aisc diagnose

# 測試API連接
wp aisc diagnose --test=api

# 自動修復
wp aisc diagnose --fix
```

## 故障排除

### 常見問題

**Q: API連接失敗**
- 檢查API Key是否正確
- 確認網路連接正常
- 查看錯誤日誌

**Q: 生成速度緩慢**
- 檢查伺服器資源
- 考慮使用更快的模型
- 優化文章長度設定

**Q: 成本過高**
- 使用GPT-3.5 Turbo
- 減少文章字數
- 設定預算限制

### 錯誤代碼

- `E001`: API Key無效
- `E002`: 預算超限
- `E003`: 網路連接錯誤
- `E004`: 資料庫錯誤

## 開發者資訊

### 檔案結構
```
ai-seo-content-generator/
├── admin/                  # 管理介面
├── assets/                 # CSS/JS資源
├── includes/              # 核心功能
│   ├── core/             # 核心類別
│   └── modules/          # 功能模組
├── languages/            # 語言檔案
├── logs/                 # 日誌檔案
└── uninstall.php        # 解除安裝腳本
```

### 掛鉤和過濾器

#### Actions
- `aisc_before_content_generation` - 內容生成前
- `aisc_after_content_generation` - 內容生成後
- `aisc_keyword_updated` - 關鍵字更新後

#### Filters
- `aisc_content_params` - 修改內容生成參數
- `aisc_generated_content` - 過濾生成的內容
- `aisc_seo_optimization` - 自訂SEO優化

### API端點

REST API基礎URL: `/wp-json/aisc/v1/`

- `POST /generate-content` - 生成內容
- `GET /keywords/search` - 搜尋關鍵字
- `GET /performance/{post_id}` - 取得效能數據
- `POST /batch` - 批量操作

## 更新日誌

### v1.0.0 (2024-01-01)
- 初始版本發布
- 完整的關鍵字管理系統
- AI內容生成功能
- SEO優化工具
- 排程管理系統
- 成本控制功能
- 效能追蹤分析
- 診斷工具

## 授權條款

本外掛採用 GPL v2 或更高版本授權。

## 支援與聯繫

- 官方網站：https://example.com/ai-seo-content-generator
- 技術支援：support@example.com
- 文檔中心：https://docs.example.com

## 致謝

感謝以下開源專案：
- WordPress
- OpenAI API
- Chart.js
- Select2

---

© 2024 AI SEO Content Generator Pro. All rights reserved.
