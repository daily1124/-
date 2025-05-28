<?php
/**
 * Plugin Name: AI 自動內容生成器
 * Plugin URI: https://your-website.com/
 * Description: 使用 ChatGPT 和即夢 AI 自動生成並發布文章，包含關鍵字優化
 * Version: 1.0.1
 * Author: Your Name
 * License: GPL v2 or later
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 定義常數
define('AICG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AICG_VERSION', '1.0.1');

// 載入必要的類別檔案
require_once AICG_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once AICG_PLUGIN_DIR . 'includes/class-keyword-fetcher.php';
require_once AICG_PLUGIN_DIR . 'includes/class-openai-handler.php';
require_once AICG_PLUGIN_DIR . 'includes/class-jimeng-handler.php';
require_once AICG_PLUGIN_DIR . 'includes/class-content-generator.php';
require_once AICG_PLUGIN_DIR . 'includes/class-post-scheduler.php';
require_once AICG_PLUGIN_DIR . 'includes/class-category-detector.php';
require_once AICG_PLUGIN_DIR . 'includes/class-security-helper.php';

// 主外掛類別
class AI_Content_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // 外掛啟用/停用鉤子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 初始化
        add_action('init', array($this, 'init'));
        
        // 管理員選單
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 載入資源
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX 處理
        add_action('wp_ajax_aicg_generate_post', array($this, 'ajax_generate_post'));
        add_action('wp_ajax_aicg_fetch_keywords', array($this, 'ajax_fetch_keywords'));
        add_action('wp_ajax_aicg_test_api', array($this, 'ajax_test_api'));
        
        // 排程任務
        add_action('aicg_scheduled_post_generation', array($this, 'generate_scheduled_post'));
    }
    
    public function activate() {
        // 建立資料表
        $this->create_tables();
        
        // 設定預設選項
        $this->set_default_options();
        
        // 設定初始排程
        $scheduler = AICG_Post_Scheduler::get_instance();
        $scheduler->update_schedule();
        
        // 清除快取
        wp_cache_flush();
    }
    
    public function deactivate() {
        // 清除所有排程
        wp_clear_scheduled_hook('aicg_scheduled_post_generation');
        wp_clear_scheduled_hook('aicg_daily_schedule_check');
        
        // 清除快取
        wp_cache_flush();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 關鍵字資料表
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        $sql_keywords = "CREATE TABLE $keywords_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            traffic_volume int(11) DEFAULT 0,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword_type (type),
            KEY traffic_volume (traffic_volume),
            UNIQUE KEY unique_keyword_type (keyword, type)
        ) $charset_collate;";
        
        // 生成記錄表
        $generation_log_table = $wpdb->prefix . 'aicg_generation_log';
        $sql_log = "CREATE TABLE $generation_log_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            keywords_used text,
            generation_time datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'success',
            error_message text,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY generation_time (generation_time),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_keywords);
        dbDelta($sql_log);
    }
    
    private function set_default_options() {
        $defaults = array(
            'aicg_openai_api_key' => '',
            'aicg_jimeng_api_key' => '',
            'aicg_post_frequency' => 'daily',
            'aicg_posts_per_batch' => 3,
            'aicg_min_word_count' => 1000,
            'aicg_max_word_count' => 2000,
            'aicg_auto_publish' => false,
            'aicg_keyword_source_url' => '',
            'aicg_casino_keyword_source_url' => '',
            'aicg_unsplash_access_key' => '',
            'aicg_publish_time' => '09:00',
            'aicg_default_category' => 1,
            'aicg_default_author' => 1
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    public function init() {
        // 初始化各個模組
        AICG_Admin_Settings::get_instance();
        AICG_Keyword_Fetcher::get_instance();
        AICG_OpenAI_Handler::get_instance();
        AICG_Jimeng_Handler::get_instance();
        AICG_Content_Generator::get_instance();
        AICG_Post_Scheduler::get_instance();
        AICG_Category_Detector::get_instance();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'AI 內容生成器',
            'AI 內容生成器',
            'manage_options',
            'ai-content-generator',
            array($this, 'render_admin_page'),
            'dashicons-edit-page',
            30
        );
        
        add_submenu_page(
            'ai-content-generator',
            '設定',
            '設定',
            'manage_options',
            'ai-content-generator-settings',
            array('AICG_Admin_Settings', 'render_settings_page')
        );
        
        add_submenu_page(
            'ai-content-generator',
            '關鍵字管理',
            '關鍵字管理',
            'manage_options',
            'ai-content-generator-keywords',
            array($this, 'render_keywords_page')
        );
        
        add_submenu_page(
            'ai-content-generator',
            '生成記錄',
            '生成記錄',
            'manage_options',
            'ai-content-generator-logs',
            array($this, 'render_logs_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        // 確保在正確的頁面載入資源
        if (strpos($hook, 'ai-content-generator') !== false) {
            wp_enqueue_style('aicg-admin', AICG_PLUGIN_URL . 'assets/css/admin.css', array(), AICG_VERSION);
            wp_enqueue_script('aicg-admin', AICG_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AICG_VERSION, true);
            
            // 本地化腳本
            wp_localize_script('aicg-admin', 'aicg_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aicg_ajax_nonce'),
                'strings' => array(
                    'confirm_generate' => '確定要立即生成文章嗎？',
                    'confirm_fetch' => '確定要更新關鍵字嗎？這將覆蓋現有關鍵字。',
                    'generating' => '正在生成文章...',
                    'fetching' => '正在抓取關鍵字...',
                    'success' => '操作成功！',
                    'error' => '操作失敗，請稍後再試。'
                )
            ));
        }
    }
    
    public function render_admin_page() {
        $scheduler = AICG_Post_Scheduler::get_instance();
        ?>
        <div class="wrap">
            <h1>AI 內容生成器</h1>
            
            <div class="aicg-dashboard">
                <div class="aicg-row">
                    <div class="aicg-col-8">
                        <div class="aicg-panel">
                            <h2>統計資訊</h2>
                            <?php $this->display_stats(); ?>
                        </div>
                        
                        <div class="aicg-panel">
                            <h2>最近生成的文章</h2>
                            <?php $this->display_recent_posts(); ?>
                        </div>
                    </div>
                    
                    <div class="aicg-col-4">
                        <div class="aicg-panel">
                            <h2>快速操作</h2>
                            <div class="aicg-actions">
                                <button id="aicg-generate-now" class="button button-primary button-large" style="width: 100%;">
                                    <span class="dashicons dashicons-edit"></span> 立即生成文章
                                </button>
                                <button id="aicg-update-keywords" class="button button-large" style="width: 100%; margin-top: 10px;">
                                    <span class="dashicons dashicons-update"></span> 更新關鍵字
                                </button>
                            </div>
                        </div>
                        
                        <div class="aicg-panel">
                            <?php $scheduler->display_schedule_status(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function display_stats() {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'aicg_generation_log';
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        
        // 統計數據
        $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'success'");
        $total_keywords = $wpdb->get_var("SELECT COUNT(*) FROM $keywords_table");
        $today_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table WHERE status = 'success' AND DATE(generation_time) = %s",
            current_time('Y-m-d')
        ));
        $this_month_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table WHERE status = 'success' AND MONTH(generation_time) = %s AND YEAR(generation_time) = %s",
            current_time('m'),
            current_time('Y')
        ));
        
        ?>
        <div class="aicg-stat-cards">
            <div class="stat-card">
                <h3>總生成文章</h3>
                <p class="stat-number"><?php echo number_format(intval($total_posts)); ?></p>
            </div>
            <div class="stat-card">
                <h3>今日生成</h3>
                <p class="stat-number"><?php echo number_format(intval($today_posts)); ?></p>
            </div>
            <div class="stat-card">
                <h3>本月生成</h3>
                <p class="stat-number"><?php echo number_format(intval($this_month_posts)); ?></p>
            </div>
            <div class="stat-card">
                <h3>關鍵字總數</h3>
                <p class="stat-number"><?php echo number_format(intval($total_keywords)); ?></p>
            </div>
        </div>
        <?php
    }
    
    private function display_recent_posts() {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'aicg_generation_log';
        $recent_logs = $wpdb->get_results(
            "SELECT * FROM $log_table ORDER BY generation_time DESC LIMIT 10"
        );
        
        if ($recent_logs) {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>文章標題</th><th>狀態</th><th>生成時間</th><th>操作</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($recent_logs as $log) {
                echo '<tr>';
                
                if ($log->status === 'success' && $log->post_id) {
                    $post = get_post($log->post_id);
                    if ($post) {
                        echo '<td>' . esc_html($post->post_title) . '</td>';
                        echo '<td><span class="status-success">成功</span></td>';
                        echo '<td>' . human_time_diff(strtotime($log->generation_time)) . ' 前</td>';
                        echo '<td>';
                        echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-small">編輯</a> ';
                        echo '<a href="' . get_permalink($post->ID) . '" class="button button-small" target="_blank">查看</a>';
                        echo '</td>';
                    } else {
                        echo '<td>文章已刪除</td>';
                        echo '<td><span class="status-deleted">已刪除</span></td>';
                        echo '<td>' . human_time_diff(strtotime($log->generation_time)) . ' 前</td>';
                        echo '<td>-</td>';
                    }
                } else {
                    echo '<td>' . esc_html($log->error_message ?: '生成失敗') . '</td>';
                    echo '<td><span class="status-error">失敗</span></td>';
                    echo '<td>' . human_time_diff(strtotime($log->generation_time)) . ' 前</td>';
                    echo '<td>-</td>';
                }
                
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>尚無生成記錄</p>';
        }
    }
    
    public function render_keywords_page() {
        $keyword_fetcher = AICG_Keyword_Fetcher::get_instance();
        ?>
        <div class="wrap">
            <h1>關鍵字管理</h1>
            
            <div class="aicg-keywords-actions">
                <button id="aicg-fetch-taiwan-keywords" class="button button-primary">
                    <span class="dashicons dashicons-download"></span> 抓取台灣熱門關鍵字
                </button>
                <button id="aicg-fetch-casino-keywords" class="button button-primary">
                    <span class="dashicons dashicons-download"></span> 抓取娛樂城關鍵字
                </button>
            </div>
            
            <div class="aicg-keywords-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#taiwan-keywords" class="nav-tab nav-tab-active" data-tab="taiwan">台灣熱門關鍵字</a>
                    <a href="#casino-keywords" class="nav-tab" data-tab="casino">娛樂城關鍵字</a>
                </h2>
                
                <div id="taiwan-keywords" class="tab-content active">
                    <?php $keyword_fetcher->display_keywords('taiwan'); ?>
                </div>
                
                <div id="casino-keywords" class="tab-content" style="display:none;">
                    <?php $keyword_fetcher->display_keywords('casino'); ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var tab = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').hide();
                $('#' + tab + '-keywords').show();
            });
        });
        </script>
        <?php
    }
    
    public function render_logs_page() {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'aicg_generation_log';
        
        // 分頁設定
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // 取得總數
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
        $total_pages = ceil($total_items / $per_page);
        
        // 取得記錄
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $log_table ORDER BY generation_time DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        ?>
        <div class="wrap">
            <h1>生成記錄</h1>
            
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>文章</th>
                        <th>使用關鍵字</th>
                        <th>生成時間</th>
                        <th>狀態</th>
                        <th>錯誤訊息</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log->id; ?></td>
                            <td>
                                <?php 
                                if ($log->post_id) {
                                    $post = get_post($log->post_id);
                                    if ($post) {
                                        echo '<a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a>';
                                    } else {
                                        echo '文章已刪除';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $keywords = esc_html($log->keywords_used);
                                echo strlen($keywords) > 50 ? substr($keywords, 0, 50) . '...' : $keywords;
                                ?>
                            </td>
                            <td><?php echo $log->generation_time; ?></td>
                            <td>
                                <span class="status-<?php echo $log->status; ?>">
                                    <?php echo $log->status === 'success' ? '成功' : '失敗'; ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->error_message); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">尚無記錄</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function ajax_generate_post() {
        check_ajax_referer('aicg_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '權限不足'));
            return;
        }
        
        $generator = AICG_Content_Generator::get_instance();
        $result = $generator->generate_single_post();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_fetch_keywords() {
        check_ajax_referer('aicg_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '權限不足'));
            return;
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'taiwan';
        
        $fetcher = AICG_Keyword_Fetcher::get_instance();
        $result = $fetcher->fetch_keywords($type);
        
        if ($result) {
            wp_send_json_success(array('message' => '關鍵字更新成功'));
        } else {
            wp_send_json_error(array('message' => '關鍵字更新失敗'));
        }
    }
    
    public function ajax_test_api() {
        check_ajax_referer('aicg_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '權限不足'));
            return;
        }
        
        $api_type = isset($_POST['api_type']) ? sanitize_text_field($_POST['api_type']) : 'openai';
        
        if ($api_type === 'openai') {
            $handler = AICG_OpenAI_Handler::get_instance();
            $result = $handler->test_connection();
        } elseif ($api_type === 'volcengine') {
            // 獲取實例並重新載入設定
            $handler = AICG_Jimeng_Handler::get_instance();
            $handler->reload_settings();
            $result = $handler->test_connection();
        } else {
            $result = array(
                'success' => false,
                'message' => '未知的 API 類型'
            );
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function generate_scheduled_post() {
        $auto_publish = get_option('aicg_auto_publish', false);
        
        if (!$auto_publish) {
            error_log('AICG: 自動發布未啟用');
            return;
        }
        
        $posts_per_batch = get_option('aicg_posts_per_batch', 3);
        $generator = AICG_Content_Generator::get_instance();
        
        error_log('AICG: 開始排程生成 ' . $posts_per_batch . ' 篇文章');
        
        for ($i = 0; $i < $posts_per_batch; $i++) {
            $result = $generator->generate_single_post();
            
            if ($result['success']) {
                error_log('AICG: 成功生成文章 #' . ($i + 1));
            } else {
                error_log('AICG: 生成文章 #' . ($i + 1) . ' 失敗: ' . $result['message']);
            }
            
            // 避免API限制，休息10秒
            if ($i < $posts_per_batch - 1) {
                sleep(10);
            }
        }
        
        error_log('AICG: 排程生成完成');
    }
}

// 初始化外掛
AI_Content_Generator::get_instance();