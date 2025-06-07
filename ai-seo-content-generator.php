<?php
/**
 * Plugin Name: AI SEO Content Generator Pro
 * Plugin URI: https://example.com/ai-seo-content-generator
 * Description: 專業的AI驅動SEO內容自動生成系統，整合關鍵字抓取、內容生成、SEO優化和排程管理功能
 * Version: 1.0.0
 * Author: Professional Developer
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: ai-seo-content-generator
 * Domain Path: /languages
 * Requires at least: 6.8.1
 * Requires PHP: 8.4.7
 * 
 * @package AI_SEO_Content_Generator
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 1. 外掛常數定義
define('AISC_VERSION', '1.0.0');
define('AISC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AISC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AISC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AISC_DB_VERSION', '1.0.0');
define('AISC_TABLE_PREFIX', $wpdb->prefix . 'aisc_');

// 2. 自動載入器
spl_autoload_register(function ($class) {
    $prefix = 'AISC\\';
    $base_dir = AISC_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// 3. 主外掛類別
class AI_SEO_Content_Generator {
    
    /**
     * 單例模式實例
     */
    private static ?self $instance = null;
    
    /**
     * 核心模組
     */
    private array $modules = [];
    
    /**
     * 獲取單例實例
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建構函數
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_modules();
    }
    
    /**
     * 3.1 載入依賴檔案
     */
    private function load_dependencies(): void {
        // 載入核心檔案
        require_once AISC_PLUGIN_DIR . 'includes/core/class-database.php';
        require_once AISC_PLUGIN_DIR . 'includes/core/class-logger.php';
        require_once AISC_PLUGIN_DIR . 'includes/core/class-settings.php';
        
        // 載入模組檔案
        require_once AISC_PLUGIN_DIR . 'includes/modules/class-keyword-manager.php';
        require_once AISC_PLUGIN_DIR . 'includes/modules/class-content-generator.php';
        require_once AISC_PLUGIN_DIR . 'includes/modules/class-seo-optimizer.php';
        require_once AISC_PLUGIN_DIR . 'includes/modules/class-scheduler.php';
        require_once AISC_PLUGIN_DIR . 'includes/modules/class-cost-controller.php';
        require_once AISC_PLUGIN_DIR . 'includes/modules/class-diagnostics.php';
        require_once AISC_PLUGIN_DIR . 'includes/modules/class-performance-tracker.php';
        
        // 載入管理介面
        if (is_admin()) {
            require_once AISC_PLUGIN_DIR . 'admin/class-admin.php';
            require_once AISC_PLUGIN_DIR . 'admin/class-ajax-handler.php';
        }
    }
    
    /**
     * 3.2 初始化掛鉤
     */
    private function init_hooks(): void {
        // 啟用和停用掛鉤
        register_activation_hook(AISC_PLUGIN_BASENAME, [$this, 'activate']);
        register_deactivation_hook(AISC_PLUGIN_BASENAME, [$this, 'deactivate']);
        
        // 核心動作掛鉤
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        
        // 管理介面掛鉤
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }
        
        // AJAX掛鉤
        add_action('wp_ajax_aisc_update_keywords', [$this, 'ajax_update_keywords']);
        add_action('wp_ajax_aisc_generate_content', [$this, 'ajax_generate_content']);
        add_action('wp_ajax_aisc_run_diagnostics', [$this, 'ajax_run_diagnostics']);
        
        // Cron掛鉤
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('aisc_keyword_update', [$this, 'cron_update_keywords']);
        add_action('aisc_content_generation', [$this, 'cron_generate_content']);
    }
    
    /**
     * 3.3 初始化模組
     */
    private function init_modules(): void {
        $this->modules['database'] = new AISC\Core\Database();
        $this->modules['logger'] = new AISC\Core\Logger();
        $this->modules['settings'] = new AISC\Core\Settings();
        $this->modules['keyword_manager'] = new AISC\Modules\KeywordManager();
        $this->modules['content_generator'] = new AISC\Modules\ContentGenerator();
        $this->modules['seo_optimizer'] = new AISC\Modules\SEOOptimizer();
        $this->modules['scheduler'] = new AISC\Modules\Scheduler();
        $this->modules['cost_controller'] = new AISC\Modules\CostController();
        $this->modules['diagnostics'] = new AISC\Modules\Diagnostics();
        $this->modules['performance_tracker'] = new AISC\Modules\PerformanceTracker();
        
        if (is_admin()) {
            $this->modules['admin'] = new AISC\Admin\Admin();
            $this->modules['ajax_handler'] = new AISC\Admin\AjaxHandler();
        }
    }
    
    /**
     * 4. 外掛啟用處理
     */
    public function activate(): void {
        // 建立資料表
        $this->modules['database']->create_tables();
        
        // 設定預設選項
        $this->set_default_options();
        
        // 設定排程任務
        $this->schedule_cron_events();
        
        // 建立必要目錄
        $this->create_directories();
        
        // 清除快取
        flush_rewrite_rules();
        
        // 記錄啟用日誌
        $this->modules['logger']->info('外掛已啟用', ['version' => AISC_VERSION]);
    }
    
    /**
     * 4.1 設定預設選項
     */
    private function set_default_options(): void {
        $defaults = [
            'aisc_openai_api_key' => '',
            'aisc_gpt_model' => 'gpt-4-turbo-preview',
            'aisc_content_length' => 8000,
            'aisc_keyword_density' => 1.5,
            'aisc_images_per_article' => 3,
            'aisc_auto_internal_links' => true,
            'aisc_generate_faq' => true,
            'aisc_faq_count' => 10,
            'aisc_daily_budget' => 1000, // 台幣
            'aisc_timezone' => 'Asia/Taipei',
            'aisc_categories' => [
                '所有文章', '娛樂城教學', '虛擬貨幣', '體育', '科技', 
                '健康', '新聞', '明星', '汽車', '理財', 
                '生活', '社會', '美食', '追劇'
            ]
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }
    
    /**
     * 4.2 設定排程任務
     */
    private function schedule_cron_events(): void {
        // 設定台灣時區
        date_default_timezone_set('Asia/Taipei');
        
        // 關鍵字更新排程（每天中午12點）
        if (!wp_next_scheduled('aisc_keyword_update')) {
            $timestamp = strtotime('today 12:00:00');
            if ($timestamp < time()) {
                $timestamp = strtotime('tomorrow 12:00:00');
            }
            wp_schedule_event($timestamp, 'daily', 'aisc_keyword_update');
        }
    }
    
    /**
     * 4.3 建立必要目錄
     */
    private function create_directories(): void {
        $directories = [
            AISC_PLUGIN_DIR . 'logs',
            AISC_PLUGIN_DIR . 'cache',
            AISC_PLUGIN_DIR . 'temp',
            AISC_PLUGIN_DIR . 'exports'
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // 建立 .htaccess 保護檔案
                $htaccess = $dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, 'deny from all');
                }
            }
        }
    }
    
    /**
     * 5. 外掛停用處理
     */
    public function deactivate(): void {
        // 移除排程任務
        wp_clear_scheduled_hook('aisc_keyword_update');
        wp_clear_scheduled_hook('aisc_content_generation');
        
        // 清除暫存資料
        $this->clear_transients();
        
        // 記錄停用日誌
        $this->modules['logger']->info('外掛已停用');
        
        // 清除快取
        flush_rewrite_rules();
    }
    
    /**
     * 5.1 清除暫存資料
     */
    private function clear_transients(): void {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_aisc_%' 
            OR option_name LIKE '_transient_timeout_aisc_%'"
        );
    }
    
    /**
     * 6. 初始化外掛
     */
    public function init(): void {
        // 載入語言檔案
        load_plugin_textdomain(
            'ai-seo-content-generator',
            false,
            dirname(AISC_PLUGIN_BASENAME) . '/languages'
        );
        
        // 註冊自訂文章類型（如果需要）
        $this->register_post_types();
        
        // 註冊自訂分類（如果需要）
        $this->register_taxonomies();
    }
    
    /**
     * 7. 載入前端資源
     */
    public function enqueue_public_assets(): void {
        // 只在需要的頁面載入
        if (is_singular() && get_post_meta(get_the_ID(), '_aisc_generated', true)) {
            wp_enqueue_style(
                'aisc-public',
                AISC_PLUGIN_URL . 'assets/css/public.css',
                [],
                AISC_VERSION
            );
            
            wp_enqueue_script(
                'aisc-public',
                AISC_PLUGIN_URL . 'assets/js/public.js',
                ['jquery'],
                AISC_VERSION,
                true
            );
            
            // 傳遞本地化資料
            wp_localize_script('aisc-public', 'aisc_public', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aisc_public_nonce')
            ]);
        }
    }
    
    /**
     * 8. 載入管理介面資源
     */
    public function enqueue_admin_assets($hook): void {
        // 只在外掛頁面載入
        if (strpos($hook, 'aisc') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'aisc-admin',
            AISC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AISC_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'aisc-admin',
            AISC_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api', 'wp-i18n', 'wp-components', 'wp-element'],
            AISC_VERSION,
            true
        );
        
        // Chart.js for analytics
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );
        
        // 本地化資料
        wp_localize_script('aisc-admin', 'aisc_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => home_url('/wp-json/aisc/v1/'),
            'nonce' => wp_create_nonce('aisc_admin_nonce'),
            'strings' => [
                'confirm_delete' => __('確定要刪除嗎？', 'ai-seo-content-generator'),
                'processing' => __('處理中...', 'ai-seo-content-generator'),
                'success' => __('操作成功', 'ai-seo-content-generator'),
                'error' => __('操作失敗', 'ai-seo-content-generator')
            ]
        ]);
    }
    
    /**
     * 9. 新增管理選單
     */
    public function add_admin_menu(): void {
        $capability = 'manage_options';
        
        // 主選單
        add_menu_page(
            __('AI SEO內容生成器', 'ai-seo-content-generator'),
            __('AI SEO生成器', 'ai-seo-content-generator'),
            $capability,
            'aisc-dashboard',
            [$this->modules['admin'], 'render_dashboard'],
            'dashicons-edit-large',
            30
        );
        
        // 子選單
        $submenus = [
            'dashboard' => ['儀表板', 'render_dashboard'],
            'keywords' => ['關鍵字管理', 'render_keywords'],
            'content' => ['內容生成', 'render_content'],
            'scheduler' => ['排程設定', 'render_scheduler'],
            'seo' => ['SEO優化', 'render_seo'],
            'analytics' => ['效能分析', 'render_analytics'],
            'costs' => ['成本控制', 'render_costs'],
            'diagnostics' => ['診斷工具', 'render_diagnostics'],
            'settings' => ['設定', 'render_settings']
        ];
        
        foreach ($submenus as $slug => $data) {
            add_submenu_page(
                'aisc-dashboard',
                sprintf(__('AI SEO生成器 - %s', 'ai-seo-content-generator'), $data[0]),
                __($data[0], 'ai-seo-content-generator'),
                $capability,
                'aisc-' . $slug,
                [$this->modules['admin'], $data[1]]
            );
        }
    }
    
    /**
     * 10. AJAX處理函數
     */
    public function ajax_update_keywords(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('權限不足', 'ai-seo-content-generator'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        $result = $this->modules['keyword_manager']->update_keywords($type);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_generate_content(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('權限不足', 'ai-seo-content-generator'));
        }
        
        $params = [
            'keyword' => sanitize_text_field($_POST['keyword'] ?? ''),
            'length' => intval($_POST['length'] ?? 8000),
            'model' => sanitize_text_field($_POST['model'] ?? 'gpt-4-turbo-preview'),
            'images' => intval($_POST['images'] ?? 3)
        ];
        
        $result = $this->modules['content_generator']->generate_article($params);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_run_diagnostics(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('權限不足', 'ai-seo-content-generator'));
        }
        
        $test = sanitize_text_field($_POST['test'] ?? 'all');
        $result = $this->modules['diagnostics']->run_test($test);
        
        wp_send_json_success($result);
    }
    
    /**
     * 11. Cron處理函數
     */
    public function cron_update_keywords(): void {
        $this->modules['logger']->info('開始執行關鍵字更新排程');
        
        try {
            $result = $this->modules['keyword_manager']->update_keywords('all');
            $this->modules['logger']->info('關鍵字更新完成', $result);
        } catch (Exception $e) {
            $this->modules['logger']->error('關鍵字更新失敗', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function cron_generate_content(): void {
        $this->modules['logger']->info('開始執行內容生成排程');
        
        try {
            $schedules = $this->modules['scheduler']->get_active_schedules();
            
            foreach ($schedules as $schedule) {
                if ($this->modules['scheduler']->should_run($schedule)) {
                    $result = $this->modules['content_generator']->generate_scheduled_content($schedule);
                    $this->modules['logger']->info('排程內容生成完成', [
                        'schedule_id' => $schedule['id'],
                        'result' => $result
                    ]);
                }
            }
        } catch (Exception $e) {
            $this->modules['logger']->error('內容生成排程執行失敗', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 12. 自訂Cron排程
     */
    public function add_cron_schedules($schedules): array {
        $schedules['every_30_minutes'] = [
            'interval' => 1800,
            'display' => __('每30分鐘', 'ai-seo-content-generator')
        ];
        
        $schedules['every_2_hours'] = [
            'interval' => 7200,
            'display' => __('每2小時', 'ai-seo-content-generator')
        ];
        
        $schedules['every_6_hours'] = [
            'interval' => 21600,
            'display' => __('每6小時', 'ai-seo-content-generator')
        ];
        
        return $schedules;
    }
    
    /**
     * 13. 註冊自訂文章類型
     */
    private function register_post_types(): void {
        // 如果需要自訂文章類型，在此註冊
    }
    
    /**
     * 14. 註冊自訂分類
     */
    private function register_taxonomies(): void {
        // 使用現有的分類系統，不需要註冊新的
    }
    
    /**
     * 15. 公開方法 - 獲取模組
     */
    public function get_module(string $name) {
        return $this->modules[$name] ?? null;
    }
    
    /**
     * 16. 錯誤處理
     */
    public function handle_error($errno, $errstr, $errfile, $errline): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $this->modules['logger']->error('PHP錯誤', [
            'errno' => $errno,
            'errstr' => $errstr,
            'errfile' => $errfile,
            'errline' => $errline
        ]);
        
        return true;
    }
}

// 17. 初始化外掛
function aisc_init() {
    return AI_SEO_Content_Generator::get_instance();
}

// 18. 全域函數
if (!function_exists('aisc_log')) {
    function aisc_log($message, $level = 'info', $context = []) {
        $logger = AI_SEO_Content_Generator::get_instance()->get_module('logger');
        if ($logger) {
            $logger->log($level, $message, $context);
        }
    }
}

if (!function_exists('aisc_get_setting')) {
    function aisc_get_setting($key, $default = null) {
        $settings = AI_SEO_Content_Generator::get_instance()->get_module('settings');
        return $settings ? $settings->get($key, $default) : $default;
    }
}

// 19. 載入外掛
add_action('plugins_loaded', 'aisc_init');

// 20. 解除安裝處理
register_uninstall_hook(__FILE__, 'aisc_uninstall');

function aisc_uninstall() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    
    // 刪除選項
    $options = [
        'aisc_openai_api_key',
        'aisc_gpt_model',
        'aisc_content_length',
        'aisc_keyword_density',
        'aisc_images_per_article',
        'aisc_auto_internal_links',
        'aisc_generate_faq',
        'aisc_faq_count',
        'aisc_daily_budget',
        'aisc_timezone',
        'aisc_categories',
        'aisc_db_version'
    ];
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // 刪除資料表
    global $wpdb;
    $tables = [
        $wpdb->prefix . 'aisc_keywords',
        $wpdb->prefix . 'aisc_content_history',
        $wpdb->prefix . 'aisc_schedules',
        $wpdb->prefix . 'aisc_costs',
        $wpdb->prefix . 'aisc_logs',
        $wpdb->prefix . 'aisc_performance'
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // 刪除檔案目錄
    $dirs = [
        AISC_PLUGIN_DIR . 'logs',
        AISC_PLUGIN_DIR . 'cache',
        AISC_PLUGIN_DIR . 'temp',
        AISC_PLUGIN_DIR . 'exports'
    ];
    
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            aisc_delete_directory($dir);
        }
    }
}

function aisc_delete_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!aisc_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}