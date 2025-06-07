<?php
/**
 * 檔案：admin/class-admin.php
 * 功能：管理介面主類別
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Admin
 */

namespace AISC\Admin;

use AISC\Core\Database;
use AISC\Core\Logger;
use AISC\Core\Settings;
use AISC\Modules\KeywordManager;
use AISC\Modules\ContentGenerator;
use AISC\Modules\SEOOptimizer;
use AISC\Modules\Scheduler;
use AISC\Modules\CostController;
use AISC\Modules\Diagnostics;
use AISC\Modules\PerformanceTracker;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理介面類別
 * 
 * 負責所有後台管理介面的渲染和功能
 */
class Admin {
    
    /**
     * 模組實例
     */
    private Database $db;
    private Logger $logger;
    private Settings $settings;
    private ?KeywordManager $keyword_manager = null;
    private ?ContentGenerator $content_generator = null;
    private ?SEOOptimizer $seo_optimizer = null;
    private ?Scheduler $scheduler = null;
    private ?CostController $cost_controller = null;
    private ?Diagnostics $diagnostics = null;
    private ?PerformanceTracker $performance_tracker = null;
    
    /**
     * 當前頁面
     */
    private string $current_page = '';
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
        $this->settings = new Settings();
        
        // 初始化掛鉤
        $this->init_hooks();
    }
    
    /**
     * 1. 初始化
     */
    
    /**
     * 1.1 初始化掛鉤
     */
    private function init_hooks(): void {
        // 管理通知
        add_action('admin_notices', [$this, 'display_admin_notices']);
        
        // 自訂欄位欄
        add_filter('manage_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_posts_custom_column', [$this, 'display_custom_column'], 10, 2);
        add_filter('manage_edit-post_sortable_columns', [$this, 'make_columns_sortable']);
        
        // 批量操作
        add_filter('bulk_actions-edit-post', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk_actions'], 10, 3);
        
        // 文章篩選
        add_action('restrict_manage_posts', [$this, 'add_post_filters']);
        add_filter('parse_query', [$this, 'filter_posts_query']);
    }
    
    /**
     * 1.2 取得模組實例
     */
    private function get_module(string $module) {
        switch ($module) {
            case 'keyword_manager':
                if (!$this->keyword_manager) {
                    $this->keyword_manager = new KeywordManager();
                }
                return $this->keyword_manager;
                
            case 'content_generator':
                if (!$this->content_generator) {
                    $this->content_generator = new ContentGenerator();
                }
                return $this->content_generator;
                
            case 'seo_optimizer':
                if (!$this->seo_optimizer) {
                    $this->seo_optimizer = new SEOOptimizer();
                }
                return $this->seo_optimizer;
                
            case 'scheduler':
                if (!$this->scheduler) {
                    $this->scheduler = new Scheduler();
                }
                return $this->scheduler;
                
            case 'cost_controller':
                if (!$this->cost_controller) {
                    $this->cost_controller = new CostController();
                }
                return $this->cost_controller;
                
            case 'diagnostics':
                if (!$this->diagnostics) {
                    $this->diagnostics = new Diagnostics();
                }
                return $this->diagnostics;
                
            case 'performance_tracker':
                if (!$this->performance_tracker) {
                    $this->performance_tracker = new PerformanceTracker();
                }
                return $this->performance_tracker;
        }
        
        return null;
    }
    
    /**
     * 2. 頁面渲染方法
     */
    
    /**
     * 2.1 渲染儀表板
     */
    public function render_dashboard(): void {
        $this->current_page = 'dashboard';
        $stats = $this->get_dashboard_stats();
        
        ?>
        <div class="wrap aisc-dashboard">
            <h1><?php _e('AI SEO內容生成器儀表板', 'ai-seo-content-generator'); ?></h1>
            
            <!-- 統計卡片 -->
            <div class="aisc-stats-grid">
                <div class="aisc-stat-card">
                    <h3><?php _e('今日生成', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-value"><?php echo $stats['today_generated']; ?></div>
                    <div class="stat-label"><?php _e('篇文章', 'ai-seo-content-generator'); ?></div>
                </div>
                
                <div class="aisc-stat-card">
                    <h3><?php _e('今日成本', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-value">NT$ <?php echo number_format($stats['today_cost'], 2); ?></div>
                    <div class="stat-label"><?php _e('預算使用 ', 'ai-seo-content-generator'); echo $stats['budget_percentage']; ?>%</div>
                </div>
                
                <div class="aisc-stat-card">
                    <h3><?php _e('活躍關鍵字', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-value"><?php echo $stats['active_keywords']; ?></div>
                    <div class="stat-label"><?php _e('個關鍵字', 'ai-seo-content-generator'); ?></div>
                </div>
                
                <div class="aisc-stat-card">
                    <h3><?php _e('排程任務', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-value"><?php echo $stats['active_schedules']; ?></div>
                    <div class="stat-label"><?php _e('個活躍排程', 'ai-seo-content-generator'); ?></div>
                </div>
            </div>
            
            <!-- 快速操作 -->
            <div class="aisc-quick-actions">
                <h2><?php _e('快速操作', 'ai-seo-content-generator'); ?></h2>
                <div class="button-group">
                    <a href="<?php echo admin_url('admin.php?page=aisc-keywords'); ?>" class="button button-primary">
                        <?php _e('更新關鍵字', 'ai-seo-content-generator'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=aisc-content'); ?>" class="button">
                        <?php _e('生成新文章', 'ai-seo-content-generator'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=aisc-scheduler'); ?>" class="button">
                        <?php _e('管理排程', 'ai-seo-content-generator'); ?>
                    </a>
                </div>
            </div>
            
            <!-- 最近活動 -->
            <div class="aisc-recent-activities">
                <h2><?php _e('最近活動', 'ai-seo-content-generator'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('時間', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('類型', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('描述', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('狀態', 'ai-seo-content-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_activities'] as $activity): ?>
                        <tr>
                            <td><?php echo esc_html($activity['time']); ?></td>
                            <td><?php echo esc_html($activity['type']); ?></td>
                            <td><?php echo esc_html($activity['description']); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($activity['status']); ?>">
                                    <?php echo esc_html($activity['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * 2.2 渲染關鍵字管理頁面
     */
    public function render_keywords(): void {
        $this->current_page = 'keywords';
        $keyword_manager = $this->get_module('keyword_manager');
        
        // 處理表單提交
        if (isset($_POST['aisc_action'])) {
            $this->handle_keyword_action();
        }
        
        // 取得關鍵字列表
        $type_filter = $_GET['type'] ?? 'all';
        $keywords = $keyword_manager->get_keywords($type_filter);
        
        ?>
        <div class="wrap aisc-keywords">
            <h1>
                <?php _e('關鍵字管理', 'ai-seo-content-generator'); ?>
                <a href="#" class="page-title-action" id="aisc-update-keywords">
                    <?php _e('立即更新', 'ai-seo-content-generator'); ?>
                </a>
            </h1>
            
            <!-- 類型篩選 -->
            <ul class="subsubsub">
                <li><a href="?page=aisc-keywords" class="<?php echo $type_filter === 'all' ? 'current' : ''; ?>">
                    <?php _e('全部', 'ai-seo-content-generator'); ?> <span class="count">(<?php echo count($keywords); ?>)</span>
                </a> |</li>
                <li><a href="?page=aisc-keywords&type=general" class="<?php echo $type_filter === 'general' ? 'current' : ''; ?>">
                    <?php _e('一般關鍵字', 'ai-seo-content-generator'); ?>
                </a> |</li>
                <li><a href="?page=aisc-keywords&type=casino" class="<?php echo $type_filter === 'casino' ? 'current' : ''; ?>">
                    <?php _e('娛樂城關鍵字', 'ai-seo-content-generator'); ?>
                </a></li>
            </ul>
            
            <form method="post" action="">
                <?php wp_nonce_field('aisc_keyword_action'); ?>
                
                <!-- 批量操作 -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value=""><?php _e('批量操作', 'ai-seo-content-generator'); ?></option>
                            <option value="activate"><?php _e('啟用', 'ai-seo-content-generator'); ?></option>
                            <option value="deactivate"><?php _e('停用', 'ai-seo-content-generator'); ?></option>
                            <option value="delete"><?php _e('刪除', 'ai-seo-content-generator'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('套用', 'ai-seo-content-generator'); ?>">
                    </div>
                    
                    <!-- 搜尋框 -->
                    <div class="alignright">
                        <input type="search" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="<?php _e('搜尋關鍵字...', 'ai-seo-content-generator'); ?>">
                        <input type="submit" class="button" value="<?php _e('搜尋', 'ai-seo-content-generator'); ?>">
                    </div>
                </div>
                
                <!-- 關鍵字表格 -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th><?php _e('關鍵字', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('類型', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('搜尋量', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('競爭度', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('優先級', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('使用次數', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('狀態', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('操作', 'ai-seo-content-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keywords as $keyword): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="keyword_ids[]" value="<?php echo $keyword['id']; ?>">
                            </th>
                            <td><strong><?php echo esc_html($keyword['keyword']); ?></strong></td>
                            <td><?php echo $keyword['type'] === 'casino' ? '娛樂城' : '一般'; ?></td>
                            <td><?php echo number_format($keyword['search_volume']); ?></td>
                            <td>
                                <div class="competition-meter">
                                    <div class="competition-bar" style="width: <?php echo $keyword['competition_level']; ?>%"></div>
                                </div>
                                <?php echo round($keyword['competition_level']); ?>%
                            </td>
                            <td>
                                <span class="priority-score"><?php echo round($keyword['priority_score']); ?></span>
                            </td>
                            <td><?php echo $keyword['use_count']; ?></td>
                            <td>
                                <span class="status-<?php echo $keyword['status']; ?>">
                                    <?php echo $keyword['status'] === 'active' ? '啟用' : '停用'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=aisc-content&keyword=' . urlencode($keyword['keyword'])); ?>" class="button button-small">
                                    <?php _e('生成文章', 'ai-seo-content-generator'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }
    
    /**
     * 2.3 渲染內容生成頁面
     */
    public function render_content(): void {
        $this->current_page = 'content';
        $keyword = $_GET['keyword'] ?? '';
        
        ?>
        <div class="wrap aisc-content-generator">
            <h1><?php _e('內容生成器', 'ai-seo-content-generator'); ?></h1>
            
            <form method="post" id="aisc-content-form">
                <?php wp_nonce_field('aisc_generate_content'); ?>
                
                <div class="aisc-form-grid">
                    <!-- 左側：基本設定 -->
                    <div class="aisc-form-section">
                        <h2><?php _e('基本設定', 'ai-seo-content-generator'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="keyword"><?php _e('目標關鍵字', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <input type="text" name="keyword" id="keyword" value="<?php echo esc_attr($keyword); ?>" class="regular-text" required>
                                    <p class="description"><?php _e('輸入要優化的主要關鍵字', 'ai-seo-content-generator'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="word_count"><?php _e('文章字數', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <input type="number" name="word_count" id="word_count" value="8000" min="1500" max="15000" step="500" class="small-text">
                                    <p class="description"><?php _e('建議8000字以上以獲得更好的SEO效果', 'ai-seo-content-generator'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="model"><?php _e('AI模型', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <select name="model" id="model">
                                        <option value="gpt-4">GPT-4 (最高品質)</option>
                                        <option value="gpt-4-turbo-preview" selected>GPT-4 Turbo (推薦)</option>
                                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo (經濟)</option>
                                    </select>
                                    <p class="description"><?php _e('預估成本：', 'ai-seo-content-generator'); ?><span id="estimated-cost">計算中...</span></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="images"><?php _e('圖片數量', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <input type="number" name="images" id="images" value="5" min="0" max="10" class="small-text">
                                    <p class="description"><?php _e('文章中要插入的圖片數量', 'ai-seo-content-generator'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- 右側：SEO設定 -->
                    <div class="aisc-form-section">
                        <h2><?php _e('SEO優化設定', 'ai-seo-content-generator'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('內容優化', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="optimize_featured_snippet" value="1" checked>
                                        <?php _e('優化精選摘要', 'ai-seo-content-generator'); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="include_faq" value="1" checked>
                                        <?php _e('包含FAQ段落', 'ai-seo-content-generator'); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="add_schema" value="1" checked>
                                        <?php _e('添加結構化資料', 'ai-seo-content-generator'); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="auto_internal_links" value="1" checked>
                                        <?php _e('自動內部連結', 'ai-seo-content-generator'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="tone"><?php _e('寫作風格', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <select name="tone" id="tone">
                                        <option value="professional">專業權威</option>
                                        <option value="conversational">親切對話</option>
                                        <option value="educational">教育指導</option>
                                        <option value="persuasive">說服力強</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="categories"><?php _e('文章分類', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <select name="categories[]" id="categories" multiple style="width: 100%; height: 150px;">
                                        <?php
                                        $categories = get_categories(['hide_empty' => false]);
                                        foreach ($categories as $category) {
                                            echo '<option value="' . $category->term_id . '">' . esc_html($category->name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description"><?php _e('選擇多個分類請按住Ctrl鍵', 'ai-seo-content-generator'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="generate" class="button button-primary button-large" value="<?php _e('開始生成文章', 'ai-seo-content-generator'); ?>">
                </p>
            </form>
            
            <!-- 生成進度 -->
            <div id="generation-progress" style="display: none;">
                <h2><?php _e('生成進度', 'ai-seo-content-generator'); ?></h2>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-status"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 2.4 渲染排程設定頁面
     */
    public function render_scheduler(): void {
        $this->current_page = 'scheduler';
        $scheduler = $this->get_module('scheduler');
        $schedules = $scheduler->get_schedules();
        
        ?>
        <div class="wrap aisc-scheduler">
            <h1>
                <?php _e('排程設定', 'ai-seo-content-generator'); ?>
                <a href="#" class="page-title-action" id="aisc-add-schedule">
                    <?php _e('新增排程', 'ai-seo-content-generator'); ?>
                </a>
            </h1>
            
            <!-- 排程列表 -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('排程名稱', 'ai-seo-content-generator'); ?></th>
                        <th><?php _e('類型', 'ai-seo-content-generator'); ?></th>
                        <th><?php _e('頻率', 'ai-seo-content-generator'); ?></th>
                        <th><?php _e('下次執行', 'ai-seo-content-generator'); ?></th>
                        <th><?php _e('狀態', 'ai-seo-content-generator'); ?></th>
                        <th><?php _e('操作', 'ai-seo-content-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td><strong><?php echo esc_html($schedule['name']); ?></strong></td>
                        <td><?php echo $schedule['type'] === 'casino' ? '娛樂城' : '一般'; ?></td>
                        <td><?php echo esc_html($schedule['frequency_display']); ?></td>
                        <td><?php echo esc_html($schedule['next_run']); ?></td>
                        <td>
                            <span class="status-<?php echo $schedule['status']; ?>">
                                <?php echo $schedule['status'] === 'active' ? '啟用' : '停用'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="#" class="aisc-edit-schedule" data-id="<?php echo $schedule['id']; ?>"><?php _e('編輯', 'ai-seo-content-generator'); ?></a> |
                            <a href="#" class="aisc-toggle-schedule" data-id="<?php echo $schedule['id']; ?>">
                                <?php echo $schedule['status'] === 'active' ? '停用' : '啟用'; ?>
                            </a> |
                            <a href="#" class="aisc-delete-schedule" data-id="<?php echo $schedule['id']; ?>" style="color: #dc3232;"><?php _e('刪除', 'ai-seo-content-generator'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- 新增/編輯排程表單 -->
            <div id="schedule-form-modal" style="display: none;">
                <div class="aisc-modal-content">
                    <h2><?php _e('排程設定', 'ai-seo-content-generator'); ?></h2>
                    <form id="schedule-form">
                        <?php wp_nonce_field('aisc_schedule_action'); ?>
                        <input type="hidden" name="schedule_id" id="schedule_id" value="">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="schedule_name"><?php _e('排程名稱', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <input type="text" name="schedule_name" id="schedule_name" class="regular-text" required>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="schedule_type"><?php _e('關鍵字類型', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <select name="schedule_type" id="schedule_type">
                                        <option value="general">一般關鍵字</option>
                                        <option value="casino">娛樂城關鍵字</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="frequency"><?php _e('執行頻率', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <select name="frequency" id="frequency">
                                        <option value="once">單次</option>
                                        <option value="hourly">每小時</option>
                                        <option value="twicedaily">每天兩次</option>
                                        <option value="daily">每天</option>
                                        <option value="weekly">每週</option>
                                        <option value="monthly">每月</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="start_time"><?php _e('開始時間', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <input type="datetime-local" name="start_time" id="start_time" required>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="keyword_count"><?php _e('每次關鍵字數', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <input type="number" name="keyword_count" id="keyword_count" value="1" min="1" max="10" class="small-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="content_settings"><?php _e('內容設定', 'ai-seo-content-generator'); ?></label></th>
                                <td>
                                    <label>字數：<input type="number" name="word_count" value="8000" min="1500" class="small-text"></label><br>
                                    <label>圖片數：<input type="number" name="image_count" value="5" min="0" max="10" class="small-text"></label><br>
                                    <label>模型：
                                        <select name="ai_model">
                                            <option value="gpt-4-turbo-preview">GPT-4 Turbo</option>
                                            <option value="gpt-4">GPT-4</option>
                                            <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                        </select>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php _e('儲存排程', 'ai-seo-content-generator'); ?>">
                            <button type="button" class="button" onclick="closeScheduleModal()"><?php _e('取消', 'ai-seo-content-generator'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 2.5 渲染SEO優化頁面
     */
    public function render_seo(): void {
        $this->current_page = 'seo';
        
        ?>
        <div class="wrap aisc-seo">
            <h1><?php _e('SEO優化設定', 'ai-seo-content-generator'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('aisc_seo_settings');
                ?>
                
                <h2><?php _e('基礎SEO設定', 'ai-seo-content-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="keyword_density"><?php _e('關鍵字密度', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="number" name="aisc_keyword_density_min" value="<?php echo get_option('aisc_keyword_density_min', 1); ?>" min="0.5" max="3" step="0.1" class="small-text"> % -
                            <input type="number" name="aisc_keyword_density_max" value="<?php echo get_option('aisc_keyword_density_max', 2); ?>" min="0.5" max="3" step="0.1" class="small-text"> %
                            <p class="description"><?php _e('建議保持在1-2%之間以避免過度優化', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="meta_length"><?php _e('Meta描述長度', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="number" name="aisc_meta_length" value="<?php echo get_option('aisc_meta_length', 160); ?>" min="120" max="300" class="small-text"> 字符
                            <p class="description"><?php _e('Google通常顯示150-160個字符', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('自動優化功能', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aisc_auto_internal_links" value="1" <?php checked(get_option('aisc_auto_internal_links', 1)); ?>>
                                <?php _e('自動添加內部連結', 'ai-seo-content-generator'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="aisc_auto_external_links" value="1" <?php checked(get_option('aisc_auto_external_links', 1)); ?>>
                                <?php _e('自動添加高權重外部連結', 'ai-seo-content-generator'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="aisc_auto_related_posts" value="1" <?php checked(get_option('aisc_auto_related_posts', 1)); ?>>
                                <?php _e('自動顯示相關文章', 'ai-seo-content-generator'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="aisc_auto_schema" value="1" <?php checked(get_option('aisc_auto_schema', 1)); ?>>
                                <?php _e('自動添加結構化資料', 'ai-seo-content-generator'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('精選摘要優化', 'ai-seo-content-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('精選摘要策略', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aisc_featured_snippet_paragraph" value="1" <?php checked(get_option('aisc_featured_snippet_paragraph', 1)); ?>>
                                <?php _e('優化段落式摘要', 'ai-seo-content-generator'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="aisc_featured_snippet_list" value="1" <?php checked(get_option('aisc_featured_snippet_list', 1)); ?>>
                                <?php _e('包含列表格式', 'ai-seo-content-generator'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="aisc_featured_snippet_table" value="1" <?php checked(get_option('aisc_featured_snippet_table', 1)); ?>>
                                <?php _e('包含表格格式', 'ai-seo-content-generator'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="aisc_featured_snippet_faq" value="1" <?php checked(get_option('aisc_featured_snippet_faq', 1)); ?>>
                                <?php _e('生成FAQ段落', 'ai-seo-content-generator'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="faq_count"><?php _e('FAQ問題數量', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="number" name="aisc_faq_count" value="<?php echo get_option('aisc_faq_count', 10); ?>" min="5" max="20" class="small-text">
                            <p class="description"><?php _e('每篇文章生成的FAQ問題數量', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('內容品質控制', 'ai-seo-content-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="similarity_threshold"><?php _e('重複內容門檻', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="number" name="aisc_similarity_threshold" value="<?php echo get_option('aisc_similarity_threshold', 20); ?>" min="10" max="50" class="small-text"> %
                            <p class="description"><?php _e('新文章與現有文章的最大相似度', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('可讀性標準', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <label><?php _e('平均句子長度：', 'ai-seo-content-generator'); ?>
                                <input type="number" name="aisc_avg_sentence_length" value="<?php echo get_option('aisc_avg_sentence_length', 20); ?>" min="10" max="30" class="small-text"> 字
                            </label><br>
                            <label><?php _e('段落句子數：', 'ai-seo-content-generator'); ?>
                                <input type="number" name="aisc_paragraph_sentences" value="<?php echo get_option('aisc_paragraph_sentences', 4); ?>" min="2" max="8" class="small-text"> 句
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 2.6 渲染效能分析頁面
     */
    public function render_analytics(): void {
        $this->current_page = 'analytics';
        $performance_tracker = $this->get_module('performance_tracker');
        
        // 取得統計資料
        $period = $_GET['period'] ?? '7days';
        $analytics_data = $performance_tracker->get_analytics_data($period);
        
        ?>
        <div class="wrap aisc-analytics">
            <h1><?php _e('效能分析', 'ai-seo-content-generator'); ?></h1>
            
            <!-- 時間篩選 -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="analytics-period">
                        <option value="today" <?php selected($period, 'today'); ?>><?php _e('今天', 'ai-seo-content-generator'); ?></option>
                        <option value="7days" <?php selected($period, '7days'); ?>><?php _e('過去7天', 'ai-seo-content-generator'); ?></option>
                        <option value="30days" <?php selected($period, '30days'); ?>><?php _e('過去30天', 'ai-seo-content-generator'); ?></option>
                        <option value="90days" <?php selected($period, '90days'); ?>><?php _e('過去90天', 'ai-seo-content-generator'); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- 總覽統計 -->
            <div class="aisc-analytics-overview">
                <div class="stat-box">
                    <h3><?php _e('總瀏覽量', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-number"><?php echo number_format($analytics_data['total_pageviews']); ?></div>
                </div>
                <div class="stat-box">
                    <h3><?php _e('平均停留時間', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-number"><?php echo gmdate('i:s', $analytics_data['avg_time_on_page']); ?></div>
                </div>
                <div class="stat-box">
                    <h3><?php _e('跳出率', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-number"><?php echo round($analytics_data['bounce_rate'], 1); ?>%</div>
                </div>
                <div class="stat-box">
                    <h3><?php _e('ROI', 'ai-seo-content-generator'); ?></h3>
                    <div class="stat-number"><?php echo round($analytics_data['roi'], 1); ?>%</div>
                </div>
            </div>
            
            <!-- 圖表區域 -->
            <div class="aisc-charts">
                <div class="chart-container">
                    <h3><?php _e('流量趨勢', 'ai-seo-content-generator'); ?></h3>
                    <canvas id="traffic-chart"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3><?php _e('關鍵字表現', 'ai-seo-content-generator'); ?></h3>
                    <canvas id="keywords-chart"></canvas>
                </div>
            </div>
            
            <!-- 最佳表現文章 -->
            <div class="aisc-top-posts">
                <h3><?php _e('最佳表現文章', 'ai-seo-content-generator'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('文章標題', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('關鍵字', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('瀏覽量', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('停留時間', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('參與度', 'ai-seo-content-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics_data['top_posts'] as $post): ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_permalink($post['post_id']); ?>" target="_blank">
                                    <?php echo esc_html($post['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($post['keyword']); ?></td>
                            <td><?php echo number_format($post['pageviews']); ?></td>
                            <td><?php echo gmdate('i:s', $post['avg_time']); ?></td>
                            <td><?php echo round($post['engagement_score'], 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        // 初始化圖表
        jQuery(document).ready(function($) {
            // 流量趨勢圖
            const trafficCtx = document.getElementById('traffic-chart').getContext('2d');
            new Chart(trafficCtx, {
                type: 'line',
                data: <?php echo json_encode($analytics_data['traffic_chart_data']); ?>,
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // 關鍵字表現圖
            const keywordsCtx = document.getElementById('keywords-chart').getContext('2d');
            new Chart(keywordsCtx, {
                type: 'bar',
                data: <?php echo json_encode($analytics_data['keywords_chart_data']); ?>,
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * 2.7 渲染成本控制頁面
     */
    public function render_costs(): void {
        $this->current_page = 'costs';
        $cost_controller = $this->get_module('cost_controller');
        
        // 取得成本資料
        $cost_data = $cost_controller->get_cost_summary();
        $daily_budget = get_option('aisc_daily_budget', 1000);
        $monthly_budget = get_option('aisc_monthly_budget', 30000);
        
        ?>
        <div class="wrap aisc-costs">
            <h1><?php _e('成本控制', 'ai-seo-content-generator'); ?></h1>
            
            <!-- 預算設定 -->
            <div class="aisc-budget-settings">
                <h2><?php _e('預算設定', 'ai-seo-content-generator'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('aisc_cost_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="daily_budget"><?php _e('每日預算', 'ai-seo-content-generator'); ?></label></th>
                            <td>
                                NT$ <input type="number" name="aisc_daily_budget" id="daily_budget" value="<?php echo $daily_budget; ?>" min="0" step="100" class="regular-text">
                                <p class="description"><?php _e('設定0表示不限制', 'ai-seo-content-generator'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="monthly_budget"><?php _e('每月預算', 'ai-seo-content-generator'); ?></label></th>
                            <td>
                                NT$ <input type="number" name="aisc_monthly_budget" id="monthly_budget" value="<?php echo $monthly_budget; ?>" min="0" step="1000" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label><?php _e('預算警告', 'ai-seo-content-generator'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aisc_budget_warning" value="1" <?php checked(get_option('aisc_budget_warning', 1)); ?>>
                                    <?php _e('當使用量達到80%時發出警告', 'ai-seo-content-generator'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="aisc_budget_stop" value="1" <?php checked(get_option('aisc_budget_stop', 1)); ?>>
                                    <?php _e('超過預算時自動停止生成', 'ai-seo-content-generator'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <!-- 成本概覽 -->
            <div class="aisc-cost-overview">
                <h2><?php _e('成本概覽', 'ai-seo-content-generator'); ?></h2>
                
                <div class="cost-stats">
                    <div class="cost-stat">
                        <h3><?php _e('今日成本', 'ai-seo-content-generator'); ?></h3>
                        <div class="amount">NT$ <?php echo number_format($cost_data['today'], 2); ?></div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo min(100, ($cost_data['today'] / $daily_budget) * 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="cost-stat">
                        <h3><?php _e('本月成本', 'ai-seo-content-generator'); ?></h3>
                        <div class="amount">NT$ <?php echo number_format($cost_data['month'], 2); ?></div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo min(100, ($cost_data['month'] / $monthly_budget) * 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 成本明細 -->
            <div class="aisc-cost-details">
                <h2><?php _e('成本明細', 'ai-seo-content-generator'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('日期', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('文章標題', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('模型', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('Token使用', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('圖片', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('成本', 'ai-seo-content-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cost_data['details'] as $detail): ?>
                        <tr>
                            <td><?php echo esc_html($detail['date']); ?></td>
                            <td>
                                <a href="<?php echo get_permalink($detail['post_id']); ?>" target="_blank">
                                    <?php echo esc_html($detail['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($detail['model']); ?></td>
                            <td><?php echo number_format($detail['tokens']); ?></td>
                            <td><?php echo $detail['images']; ?></td>
                            <td>NT$ <?php echo number_format($detail['cost'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 成本優化建議 -->
            <div class="aisc-cost-optimization">
                <h2><?php _e('成本優化建議', 'ai-seo-content-generator'); ?></h2>
                
                <div class="optimization-tips">
                    <?php foreach ($cost_controller->get_optimization_tips() as $tip): ?>
                    <div class="tip">
                        <h4><?php echo esc_html($tip['title']); ?></h4>
                        <p><?php echo esc_html($tip['description']); ?></p>
                        <p class="potential-saving"><?php _e('預估節省：', 'ai-seo-content-generator'); ?> <?php echo $tip['saving']; ?>%</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 2.8 渲染診斷工具頁面
     */
    public function render_diagnostics(): void {
        $this->current_page = 'diagnostics';
        $diagnostics = $this->get_module('diagnostics');
        
        ?>
        <div class="wrap aisc-diagnostics">
            <h1><?php _e('診斷工具', 'ai-seo-content-generator'); ?></h1>
            
            <!-- 系統狀態 -->
            <div class="aisc-system-status">
                <h2><?php _e('系統狀態', 'ai-seo-content-generator'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('檢查項目', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('狀態', 'ai-seo-content-generator'); ?></th>
                            <th><?php _e('詳情', 'ai-seo-content-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnostics->run_system_checks() as $check): ?>
                        <tr>
                            <td><?php echo esc_html($check['name']); ?></td>
                            <td>
                                <span class="status-<?php echo $check['status']; ?>">
                                    <?php
                                    switch ($check['status']) {
                                        case 'pass':
                                            echo '✓ ' . __('正常', 'ai-seo-content-generator');
                                            break;
                                        case 'warning':
                                            echo '⚠ ' . __('警告', 'ai-seo-content-generator');
                                            break;
                                        case 'error':
                                            echo '✗ ' . __('錯誤', 'ai-seo-content-generator');
                                            break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($check['message']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 功能測試 -->
            <div class="aisc-function-tests">
                <h2><?php _e('功能測試', 'ai-seo-content-generator'); ?></h2>
                
                <div class="test-buttons">
                    <button class="button" data-test="keyword"><?php _e('測試關鍵字抓取', 'ai-seo-content-generator'); ?></button>
                    <button class="button" data-test="content"><?php _e('測試內容生成', 'ai-seo-content-generator'); ?></button>
                    <button class="button" data-test="seo"><?php _e('測試SEO優化', 'ai-seo-content-generator'); ?></button>
                    <button class="button" data-test="schedule"><?php _e('測試排程系統', 'ai-seo-content-generator'); ?></button>
                    <button class="button" data-test="api"><?php _e('測試API連接', 'ai-seo-content-generator'); ?></button>
                </div>
                
                <div id="test-results" class="test-results" style="display: none;">
                    <h3><?php _e('測試結果', 'ai-seo-content-generator'); ?></h3>
                    <div class="test-output"></div>
                </div>
            </div>
            
            <!-- 日誌查看器 -->
            <div class="aisc-log-viewer">
                <h2><?php _e('系統日誌', 'ai-seo-content-generator'); ?></h2>
                
                <div class="log-controls">
                    <select id="log-level">
                        <option value="all"><?php _e('所有級別', 'ai-seo-content-generator'); ?></option>
                        <option value="debug">Debug</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="critical">Critical</option>
                    </select>
                    
                    <input type="date" id="log-date" value="<?php echo date('Y-m-d'); ?>">
                    
                    <button class="button" id="refresh-logs"><?php _e('重新載入', 'ai-seo-content-generator'); ?></button>
                    <button class="button" id="clear-logs"><?php _e('清除日誌', 'ai-seo-content-generator'); ?></button>
                </div>
                
                <div class="log-content">
                    <pre id="log-display"><?php echo esc_html($diagnostics->get_recent_logs()); ?></pre>
                </div>
            </div>
            
            <!-- 資料庫工具 -->
            <div class="aisc-db-tools">
                <h2><?php _e('資料庫工具', 'ai-seo-content-generator'); ?></h2>
                
                <div class="db-actions">
                    <button class="button" id="optimize-tables"><?php _e('優化資料表', 'ai-seo-content-generator'); ?></button>
                    <button class="button" id="repair-tables"><?php _e('修復資料表', 'ai-seo-content-generator'); ?></button>
                    <button class="button button-primary" id="backup-data"><?php _e('備份資料', 'ai-seo-content-generator'); ?></button>
                    <button class="button" id="reset-plugin" style="color: #dc3232;"><?php _e('重置外掛', 'ai-seo-content-generator'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 2.9 渲染設定頁面
     */
    public function render_settings(): void {
        $this->current_page = 'settings';
        
        ?>
        <div class="wrap aisc-settings">
            <h1><?php _e('外掛設定', 'ai-seo-content-generator'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('aisc_general_settings'); ?>
                
                <!-- API設定 -->
                <h2><?php _e('API設定', 'ai-seo-content-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="openai_api_key"><?php _e('OpenAI API Key', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="password" name="aisc_openai_api_key" id="openai_api_key" value="<?php echo esc_attr(get_option('aisc_openai_api_key')); ?>" class="regular-text">
                            <p class="description"><?php _e('請輸入您的OpenAI API密鑰', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="google_api_key"><?php _e('Google API Key', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="password" name="aisc_google_api_key" id="google_api_key" value="<?php echo esc_attr(get_option('aisc_google_api_key')); ?>" class="regular-text">
                            <p class="description"><?php _e('用於搜尋量分析（選填）', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="ga_measurement_id"><?php _e('GA Measurement ID', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="text" name="aisc_ga_measurement_id" id="ga_measurement_id" value="<?php echo esc_attr(get_option('aisc_ga_measurement_id')); ?>" class="regular-text">
                            <p class="description"><?php _e('Google Analytics追蹤ID（如：G-XXXXXXXXXX）', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- 時區設定 -->
                <h2><?php _e('時區設定', 'ai-seo-content-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="timezone"><?php _e('時區', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <select name="aisc_timezone" id="timezone">
                                <option value="Asia/Taipei" <?php selected(get_option('aisc_timezone', 'Asia/Taipei'), 'Asia/Taipei'); ?>>台灣時間 (UTC+8)</option>
                            </select>
                            <p class="description"><?php _e('所有排程將使用此時區', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- 內容設定 -->
                <h2><?php _e('內容設定', 'ai-seo-content-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="default_word_count"><?php _e('預設文章字數', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="number" name="aisc_default_word_count" id="default_word_count" value="<?php echo get_option('aisc_default_word_count', 8000); ?>" min="1500" step="500" class="small-text">
                            <p class="description"><?php _e('新文章的預設字數', 'ai-seo-content-generator'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="default_images"><?php _e('預設圖片數量', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="number" name="aisc_default_images" id="default_images" value="<?php echo get_option('aisc_default_images', 5); ?>" min="0" max="20" class="small-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="default_model"><?php _e('預設AI模型', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <select name="aisc_default_model" id="default_model">
                                <option value="gpt-4" <?php selected(get_option('aisc_default_model'), 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-4-turbo-preview" <?php selected(get_option('aisc_default_model'), 'gpt-4-turbo-preview'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected(get_option('aisc_default_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <!-- 進階設定 -->
                <h2><?php _e('進階設定', 'ai-seo-content-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="log_level"><?php _e('日誌級別', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <select name="aisc_log_level" id="log_level">
                                <option value="error" <?php selected(get_option('aisc_log_level'), 'error'); ?>>Error</option>
                                <option value="warning" <?php selected(get_option('aisc_log_level'), 'warning'); ?>>Warning</option>
                                <option value="info" <?php selected(get_option('aisc_log_level'), 'info'); ?>>Info</option>
                                <option value="debug" <?php selected(get_option('aisc_log_level'), 'debug'); ?>>Debug</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="log_retention"><?php _e('日誌保留天數', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <input type="number" name="aisc_log_retention" id="log_retention" value="<?php echo get_option('aisc_log_retention', 30); ?>" min="7" max="365" class="small-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label><?php _e('開發者選項', 'ai-seo-content-generator'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aisc_debug_mode" value="1" <?php checked(get_option('aisc_debug_mode')); ?>>
                                <?php _e('啟用除錯模式', 'ai-seo-content-generator'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="aisc_test_mode" value="1" <?php checked(get_option('aisc_test_mode')); ?>>
                                <?php _e('測試模式（不實際調用API）', 'ai-seo-content-generator'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 3. 管理通知和其他功能
     */
    
    /**
     * 3.1 顯示管理通知
     */
    public function display_admin_notices(): void {
        // API Key檢查
        if (!get_option('aisc_openai_api_key')) {
            ?>
            <div class="notice notice-warning">
                <p><?php _e('AI SEO內容生成器：請先設定OpenAI API Key才能開始使用。', 'ai-seo-content-generator'); ?>
                <a href="<?php echo admin_url('admin.php?page=aisc-settings'); ?>"><?php _e('前往設定', 'ai-seo-content-generator'); ?></a></p>
            </div>
            <?php
        }
        
        // 預算警告
        $cost_controller = $this->get_module('cost_controller');
        if ($cost_controller->is_budget_warning()) {
            ?>
            <div class="notice notice-warning">
                <p><?php _e('AI SEO內容生成器：您的使用量已接近預算限制。', 'ai-seo-content-generator'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * 3.2 新增自訂欄位欄
     */
    public function add_custom_columns($columns): array {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['aisc_generated'] = __('AI生成', 'ai-seo-content-generator');
                $new_columns['aisc_keyword'] = __('關鍵字', 'ai-seo-content-generator');
                $new_columns['aisc_performance'] = __('效能', 'ai-seo-content-generator');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * 3.3 顯示自訂欄位內容
     */
    public function display_custom_column(string $column, int $post_id): void {
        switch ($column) {
            case 'aisc_generated':
                if (get_post_meta($post_id, '_aisc_generated', true)) {
                    echo '<span class="dashicons dashicons-yes" style="color: #4caf50;"></span>';
                }
                break;
                
            case 'aisc_keyword':
                $keyword = get_post_meta($post_id, '_aisc_keyword', true);
                if ($keyword) {
                    echo esc_html($keyword);
                }
                break;
                
            case 'aisc_performance':
                if (get_post_meta($post_id, '_aisc_generated', true)) {
                    $performance_tracker = $this->get_module('performance_tracker');
                    $performance = $performance_tracker->get_post_performance($post_id, 'all');
                    
                    echo '<span title="' . esc_attr(sprintf(
                        __('瀏覽：%d | 參與度：%.1f%%', 'ai-seo-content-generator'),
                        $performance['total_pageviews'],
                        $performance['engagement_score']
                    )) . '">';
                    echo number_format($performance['total_pageviews']);
                    echo ' <span style="color: #666;">(' . round($performance['engagement_score']) . '%)</span>';
                    echo '</span>';
                }
                break;
        }
    }
    
    /**
     * 3.4 設定可排序欄位
     */
    public function make_columns_sortable($columns): array {
        $columns['aisc_keyword'] = 'aisc_keyword';
        $columns['aisc_performance'] = 'aisc_performance';
        
        return $columns;
    }
    
    /**
     * 3.5 新增批量操作
     */
    public function add_bulk_actions($bulk_actions): array {
        $bulk_actions['aisc_optimize_seo'] = __('優化SEO', 'ai-seo-content-generator');
        $bulk_actions['aisc_regenerate'] = __('重新生成', 'ai-seo-content-generator');
        
        return $bulk_actions;
    }
    
    /**
     * 3.6 處理批量操作
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids): string {
        if ($doaction === 'aisc_optimize_seo') {
            foreach ($post_ids as $post_id) {
                // 執行SEO優化
                $this->optimize_post_seo($post_id);
            }
            
            $redirect_to = add_query_arg('aisc_optimized', count($post_ids), $redirect_to);
        }
        
        return $redirect_to;
    }
    
    /**
     * 3.7 新增文章篩選器
     */
    public function add_post_filters(): void {
        global $typenow;
        
        if ($typenow === 'post') {
            ?>
            <select name="aisc_filter" id="aisc_filter">
                <option value=""><?php _e('所有文章', 'ai-seo-content-generator'); ?></option>
                <option value="generated" <?php selected($_GET['aisc_filter'] ?? '', 'generated'); ?>>
                    <?php _e('AI生成', 'ai-seo-content-generator'); ?>
                </option>
                <option value="manual" <?php selected($_GET['aisc_filter'] ?? '', 'manual'); ?>>
                    <?php _e('手動撰寫', 'ai-seo-content-generator'); ?>
                </option>
            </select>
            <?php
        }
    }
    
    /**
     * 3.8 篩選文章查詢
     */
    public function filter_posts_query($query): void {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'post' && isset($_GET['aisc_filter'])) {
            if ($_GET['aisc_filter'] === 'generated') {
                $query->set('meta_key', '_aisc_generated');
                $query->set('meta_value', '1');
            } elseif ($_GET['aisc_filter'] === 'manual') {
                $query->set('meta_query', [
                    [
                        'key' => '_aisc_generated',
                        'compare' => 'NOT EXISTS'
                    ]
                ]);
            }
        }
    }
    
    /**
     * 4. 資料處理方法
     */
    
    /**
     * 4.1 取得儀表板統計
     */
    private function get_dashboard_stats(): array {
        $stats = [
            'today_generated' => 0,
            'today_cost' => 0,
            'budget_percentage' => 0,
            'active_keywords' => 0,
            'active_schedules' => 0,
            'recent_activities' => []
        ];
        
        // 今日生成
        $stats['today_generated'] = $this->db->count('content_history', [
            'created_at' => ['LIKE', current_time('Y-m-d') . '%']
        ]);
        
        // 今日成本
        $cost_controller = $this->get_module('cost_controller');
        $stats['today_cost'] = $cost_controller->get_today_cost();
        $stats['budget_percentage'] = $cost_controller->get_budget_usage_percentage();
        
        // 活躍關鍵字
        $stats['active_keywords'] = $this->db->count('keywords', [
            'status' => 'active'
        ]);
        
        // 活躍排程
        $stats['active_schedules'] = $this->db->count('schedules', [
            'status' => 'active'
        ]);
        
        // 最近活動
        $stats['recent_activities'] = $this->get_recent_activities(10);
        
        return $stats;
    }
    
    /**
     * 4.2 取得最近活動
     */
    private function get_recent_activities(int $limit = 10): array {
        $activities = [];
        
        // 從日誌中取得最近活動
        $logs = $this->db->get_results(
            "SELECT * FROM {$this->db->get_table_name('logs')} 
            WHERE level IN ('info', 'warning', 'error') 
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        );
        
        foreach ($logs as $log) {
            $activities[] = [
                'time' => human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ago',
                'type' => $this->get_activity_type($log->category),
                'description' => $log->message,
                'status' => $this->get_activity_status($log->level)
            ];
        }
        
        return $activities;
    }
    
    /**
     * 4.3 處理關鍵字操作
     */
    private function handle_keyword_action(): void {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'aisc_keyword_action')) {
            wp_die(__('安全驗證失敗', 'ai-seo-content-generator'));
        }
        
        $keyword_manager = $this->get_module('keyword_manager');
        
        if (isset($_POST['bulk_action']) && !empty($_POST['keyword_ids'])) {
            $action = $_POST['bulk_action'];
            $keyword_ids = array_map('intval', $_POST['keyword_ids']);
            
            switch ($action) {
                case 'activate':
                    $keyword_manager->update_status($keyword_ids, 'active');
                    break;
                case 'deactivate':
                    $keyword_manager->update_status($keyword_ids, 'inactive');
                    break;
                case 'delete':
                    $keyword_manager->delete_keywords($keyword_ids);
                    break;
            }
        }
    }
    
    /**
     * 4.4 優化文章SEO
     */
    private function optimize_post_seo(int $post_id): void {
        $seo_optimizer = $this->get_module('seo_optimizer');
        $post = get_post($post_id);
        
        if ($post) {
            $keyword = get_post_meta($post_id, '_aisc_keyword', true);
            $optimized = $seo_optimizer->optimize_content($post->post_content, $keyword);
            
            // 更新文章
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $optimized['content'],
                'post_excerpt' => $optimized['excerpt']
            ]);
            
            // 更新Meta
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $optimized['meta_description']);
        }
    }
    
    /**
     * 4.5 取得活動類型
     */
    private function get_activity_type(string $category): string {
        $types = [
            'keyword' => __('關鍵字', 'ai-seo-content-generator'),
            'content' => __('內容生成', 'ai-seo-content-generator'),
            'schedule' => __('排程', 'ai-seo-content-generator'),
            'seo' => __('SEO優化', 'ai-seo-content-generator'),
            'system' => __('系統', 'ai-seo-content-generator')
        ];
        
        return $types[$category] ?? $category;
    }
    
    /**
     * 4.6 取得活動狀態
     */
    private function get_activity_status(string $level): string {
        $statuses = [
            'info' => __('成功', 'ai-seo-content-generator'),
            'warning' => __('警告', 'ai-seo-content-generator'),
            'error' => __('失敗', 'ai-seo-content-generator')
        ];
        
        return $statuses[$level] ?? $level;
    }
}
