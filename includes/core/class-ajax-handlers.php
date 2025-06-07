<?php
/**
 * 檔案：includes/class-ajax-handlers.php
 * 功能：AJAX請求處理
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Core
 */

namespace AISC\Core;

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
 * AJAX處理器類別
 * 
 * 負責處理所有AJAX請求
 */
class AjaxHandlers {
    
    /**
     * 模組實例
     */
    private ?KeywordManager $keyword_manager = null;
    private ?ContentGenerator $content_generator = null;
    private ?SEOOptimizer $seo_optimizer = null;
    private ?Scheduler $scheduler = null;
    private ?CostController $cost_controller = null;
    private ?Diagnostics $diagnostics = null;
    private ?PerformanceTracker $performance_tracker = null;
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->register_ajax_handlers();
    }
    
    /**
     * 1. 註冊AJAX處理器
     */
    private function register_ajax_handlers(): void {
        // 管理員AJAX
        $admin_actions = [
            // 儀表板
            'aisc_get_dashboard_stats' => 'handle_get_dashboard_stats',
            
            // 關鍵字管理
            'aisc_update_keywords' => 'handle_update_keywords',
            'aisc_get_keyword_suggestions' => 'handle_get_keyword_suggestions',
            
            // 內容生成
            'aisc_estimate_cost' => 'handle_estimate_cost',
            'aisc_generate_content' => 'handle_generate_content',
            'aisc_cancel_generation' => 'handle_cancel_generation',
            
            // 排程管理
            'aisc_get_schedule' => 'handle_get_schedule',
            'aisc_save_schedule' => 'handle_save_schedule',
            'aisc_toggle_schedule' => 'handle_toggle_schedule',
            'aisc_delete_schedule' => 'handle_delete_schedule',
            
            // 效能分析
            'aisc_get_analytics_data' => 'handle_get_analytics_data',
            'aisc_export_analytics' => 'handle_export_analytics',
            
            // 成本控制
            'aisc_get_cost_data' => 'handle_get_cost_data',
            'aisc_export_cost_report' => 'handle_export_cost_report',
            
            // 診斷工具
            'aisc_run_diagnostic_test' => 'handle_run_diagnostic_test',
            'aisc_get_logs' => 'handle_get_logs',
            'aisc_clear_logs' => 'handle_clear_logs',
            'aisc_database_action' => 'handle_database_action',
            
            // 設定
            'aisc_test_api_connection' => 'handle_test_api_connection',
            'aisc_autosave' => 'handle_autosave',
            
            // 資料匯出
            'aisc_export_data' => 'handle_export_data'
        ];
        
        foreach ($admin_actions as $action => $handler) {
            add_action('wp_ajax_' . $action, [$this, $handler]);
        }
        
        // 公開AJAX（包含未登入用戶）
        $public_actions = [
            'aisc_track_pageview' => 'handle_track_pageview',
            'aisc_track_event' => 'handle_track_event',
            'aisc_track_scroll_depth' => 'handle_track_scroll_depth',
            'aisc_track_time_on_page' => 'handle_track_time_on_page',
            'aisc_load_more_related' => 'handle_load_more_related'
        ];
        
        foreach ($public_actions as $action => $handler) {
            add_action('wp_ajax_' . $action, [$this, $handler]);
            add_action('wp_ajax_nopriv_' . $action, [$this, $handler]);
        }
    }
    
    /**
     * 2. 儀表板相關處理器
     */
    
    /**
     * 2.1 取得儀表板統計
     */
    public function handle_get_dashboard_stats(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $cost_controller = $this->get_module('cost_controller');
        $db = new Database();
        
        $stats = [
            'today_generated' => $db->count('content_history', [
                'created_at' => ['LIKE', current_time('Y-m-d') . '%']
            ]),
            'today_cost' => $cost_controller->get_today_cost(),
            'budget_percentage' => $cost_controller->get_budget_usage_percentage(),
            'active_keywords' => $db->count('keywords', ['status' => 'active']),
            'active_schedules' => $db->count('schedules', ['status' => 'active'])
        ];
        
        wp_send_json_success($stats);
    }
    
    /**
     * 3. 關鍵字管理處理器
     */
    
    /**
     * 3.1 更新關鍵字
     */
    public function handle_update_keywords(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        $keyword_manager = $this->get_module('keyword_manager');
        
        try {
            $result = $keyword_manager->update_keywords($type);
            wp_send_json_success([
                'count' => $result['total'],
                'general' => $result['general'],
                'casino' => $result['casino'],
                'message' => sprintf('成功更新 %d 個關鍵字', $result['total'])
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => '更新失敗: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 3.2 取得關鍵字建議
     */
    public function handle_get_keyword_suggestions(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('權限不足');
        }
        
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($keyword)) {
            wp_send_json_error('請提供關鍵字');
        }
        
        $keyword_manager = $this->get_module('keyword_manager');
        $suggestions = $keyword_manager->get_keyword_suggestions($keyword);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * 4. 內容生成處理器
     */
    
    /**
     * 4.1 估算成本
     */
    public function handle_estimate_cost(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('權限不足');
        }
        
        $word_count = intval($_POST['word_count'] ?? 8000);
        $model = sanitize_text_field($_POST['model'] ?? 'gpt-4-turbo-preview');
        $images = intval($_POST['images'] ?? 0);
        
        $cost_controller = $this->get_module('cost_controller');
        
        // 估算文字成本
        $text_cost = $cost_controller->estimate_content_cost($word_count, $model);
        
        // 估算圖片成本
        $image_cost = 0;
        if ($images > 0) {
            $image_params = [
                'model' => 'dall-e-3',
                'quality' => 'standard',
                'size' => '1024x1024',
                'count' => $images
            ];
            $image_cost = $cost_controller->calculate_image_cost($image_params, 'dall-e-3') * 30; // 轉換為台幣
        }
        
        wp_send_json_success([
            'text_cost' => number_format($text_cost, 2),
            'image_cost' => number_format($image_cost, 2),
            'total_cost' => number_format($text_cost + $image_cost, 2)
        ]);
    }
    
    /**
     * 4.2 生成內容
     */
    public function handle_generate_content(): void {
        // 使用 Server-Sent Events
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        // 驗證nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'aisc_admin_nonce')) {
            $this->send_sse_message(['error' => '安全驗證失敗'], 'error');
            exit;
        }
        
        if (!current_user_can('edit_posts')) {
            $this->send_sse_message(['error' => '權限不足'], 'error');
            exit;
        }
        
        // 取得參數
        $params = [
            'keyword' => sanitize_text_field($_GET['keyword'] ?? ''),
            'word_count' => intval($_GET['word_count'] ?? 8000),
            'model' => sanitize_text_field($_GET['model'] ?? 'gpt-4-turbo-preview'),
            'images' => intval($_GET['images'] ?? 0),
            'optimize_featured_snippet' => isset($_GET['optimize_featured_snippet']),
            'include_faq' => isset($_GET['include_faq']),
            'add_schema' => isset($_GET['add_schema']),
            'auto_internal_links' => isset($_GET['auto_internal_links']),
            'tone' => sanitize_text_field($_GET['tone'] ?? 'professional'),
            'categories' => array_map('intval', $_GET['categories'] ?? [])
        ];
        
        if (empty($params['keyword'])) {
            $this->send_sse_message(['error' => '請提供關鍵字'], 'error');
            exit;
        }
        
        $content_generator = $this->get_module('content_generator');
        
        // 設定進度回調
        $content_generator->set_progress_callback(function($progress, $message) {
            $this->send_sse_message([
                'progress' => $progress,
                'message' => $message
            ]);
            flush();
        });
        
        try {
            // 生成內容
            $post_id = $content_generator->generate($params);
            
            if ($post_id) {
                $this->send_sse_message([
                    'complete' => true,
                    'success' => true,
                    'post_id' => $post_id,
                    'edit_link' => get_edit_post_link($post_id, 'raw'),
                    'view_link' => get_permalink($post_id)
                ]);
            } else {
                $this->send_sse_message([
                    'complete' => true,
                    'success' => false,
                    'message' => '內容生成失敗'
                ]);
            }
        } catch (\Exception $e) {
            $this->send_sse_message([
                'complete' => true,
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    /**
     * 4.3 取消生成
     */
    public function handle_cancel_generation(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('權限不足');
        }
        
        $generation_id = sanitize_text_field($_POST['generation_id'] ?? '');
        
        // 設定取消標記
        set_transient('aisc_cancel_' . $generation_id, true, 300);
        
        wp_send_json_success(['message' => '已發送取消請求']);
    }
    
    /**
     * 5. 排程管理處理器
     */
    
    /**
     * 5.1 取得排程資料
     */
    public function handle_get_schedule(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $schedule_id = intval($_POST['id'] ?? 0);
        $scheduler = $this->get_module('scheduler');
        
        $schedule = $scheduler->get_schedule($schedule_id);
        
        if ($schedule) {
            wp_send_json_success($schedule);
        } else {
            wp_send_json_error('排程不存在');
        }
    }
    
    /**
     * 5.2 儲存排程
     */
    public function handle_save_schedule(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        parse_str($_POST['data'] ?? '', $data);
        
        $schedule_data = [
            'name' => sanitize_text_field($data['schedule_name'] ?? ''),
            'type' => sanitize_text_field($data['schedule_type'] ?? 'general'),
            'frequency' => sanitize_text_field($data['frequency'] ?? 'daily'),
            'start_time' => sanitize_text_field($data['start_time'] ?? ''),
            'keyword_count' => intval($data['keyword_count'] ?? 1),
            'content_settings' => [
                'word_count' => intval($data['word_count'] ?? 8000),
                'image_count' => intval($data['image_count'] ?? 5),
                'ai_model' => sanitize_text_field($data['ai_model'] ?? 'gpt-4-turbo-preview')
            ]
        ];
        
        $scheduler = $this->get_module('scheduler');
        $schedule_id = intval($data['schedule_id'] ?? 0);
        
        if ($schedule_id > 0) {
            $result = $scheduler->update_schedule($schedule_id, $schedule_data);
        } else {
            $result = $scheduler->create_schedule($schedule_data);
        }
        
        if ($result) {
            wp_send_json_success(['message' => '排程已儲存']);
        } else {
            wp_send_json_error(['message' => '儲存失敗']);
        }
    }
    
    /**
     * 5.3 切換排程狀態
     */
    public function handle_toggle_schedule(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $schedule_id = intval($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'active');
        
        $scheduler = $this->get_module('scheduler');
        $result = $scheduler->update_schedule_status($schedule_id, $status);
        
        if ($result) {
            wp_send_json_success(['message' => '狀態已更新']);
        } else {
            wp_send_json_error(['message' => '更新失敗']);
        }
    }
    
    /**
     * 5.4 刪除排程
     */
    public function handle_delete_schedule(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $schedule_id = intval($_POST['id'] ?? 0);
        
        $scheduler = $this->get_module('scheduler');
        $result = $scheduler->delete_schedule($schedule_id);
        
        if ($result) {
            wp_send_json_success(['message' => '排程已刪除']);
        } else {
            wp_send_json_error(['message' => '刪除失敗']);
        }
    }
    
    /**
     * 6. 效能分析處理器
     */
    
    /**
     * 6.1 取得分析資料
     */
    public function handle_get_analytics_data(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '7days');
        $performance_tracker = $this->get_module('performance_tracker');
        
        $data = $performance_tracker->get_analytics_data($period);
        
        wp_send_json_success($data);
    }
    
    /**
     * 6.2 匯出分析資料
     */
    public function handle_export_analytics(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        $period = sanitize_text_field($_GET['period'] ?? '30days');
        
        $performance_tracker = $this->get_module('performance_tracker');
        $data = $performance_tracker->export_analytics($format, $period);
        
        $filename = 'aisc_analytics_' . date('Y-m-d') . '.' . $format;
        
        header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'application/json'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $data;
        exit;
    }
    
    /**
     * 7. 成本控制處理器
     */
    
    /**
     * 7.1 取得成本資料
     */
    public function handle_get_cost_data(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '30days');
        $cost_controller = $this->get_module('cost_controller');
        
        $data = [
            'summary' => $cost_controller->get_cost_summary(),
            'chart_data' => $cost_controller->get_cost_chart_data($period),
            'model_distribution' => $cost_controller->get_model_distribution_data(),
            'hourly_distribution' => $cost_controller->get_hourly_distribution_data()
        ];
        
        wp_send_json_success($data);
    }
    
    /**
     * 7.2 匯出成本報告
     */
    public function handle_export_cost_report(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        $period = sanitize_text_field($_GET['period'] ?? '30days');
        
        $cost_controller = $this->get_module('cost_controller');
        $data = $cost_controller->export_cost_report($format, $period);
        
        $filename = 'aisc_cost_report_' . date('Y-m-d') . '.' . $format;
        
        header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'application/json'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $data;
        exit;
    }
    
    /**
     * 8. 診斷工具處理器
     */
    
    /**
     * 8.1 執行診斷測試
     */
    public function handle_run_diagnostic_test(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $test = sanitize_text_field($_POST['test'] ?? 'all');
        $diagnostics = $this->get_module('diagnostics');
        
        $result = $diagnostics->run_test($test);
        
        wp_send_json($result);
    }
    
    /**
     * 8.2 取得日誌
     */
    public function handle_get_logs(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $level = sanitize_text_field($_POST['level'] ?? 'all');
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        $diagnostics = $this->get_module('diagnostics');
        $logs = $diagnostics->get_recent_logs(100, $level, $date);
        
        wp_send_json_success($logs);
    }
    
    /**
     * 8.3 清除日誌
     */
    public function handle_clear_logs(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $level = sanitize_text_field($_POST['level'] ?? 'all');
        
        $diagnostics = $this->get_module('diagnostics');
        $deleted = $diagnostics->clear_logs($level);
        
        wp_send_json_success([
            'message' => sprintf('已清除 %d 筆日誌', $deleted)
        ]);
    }
    
    /**
     * 8.4 資料庫操作
     */
    public function handle_database_action(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $action = sanitize_text_field($_POST['db_action'] ?? '');
        $diagnostics = $this->get_module('diagnostics');
        
        switch ($action) {
            case 'optimize':
                $result = $diagnostics->optimize_tables();
                break;
                
            case 'repair':
                $result = $diagnostics->repair_tables();
                break;
                
            case 'backup':
                $result = $diagnostics->backup_data();
                if ($result['success']) {
                    $result['data']['download_url'] = admin_url('admin-ajax.php?action=aisc_download_backup&file=' . 
                        basename($result['file']) . '&nonce=' . wp_create_nonce('aisc_download_backup'));
                }
                break;
                
            case 'reset':
                $keep_settings = isset($_POST['keep_settings']);
                $result = $diagnostics->reset_plugin($keep_settings);
                break;
                
            default:
                wp_send_json_error(['message' => '未知的操作']);
                return;
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * 9. 設定相關處理器
     */
    
    /**
     * 9.1 測試API連接
     */
    public function handle_test_api_connection(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => '請提供API Key']);
        }
        
        // 測試OpenAI API
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => '連接失敗: ' . $response->get_error_message()
            ]);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            wp_send_json_success([
                'message' => 'API連接成功！'
            ]);
        } elseif ($status_code === 401) {
            wp_send_json_error([
                'message' => 'API Key無效或已過期'
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf('API回應錯誤：%d', $status_code)
            ]);
        }
    }
    
    /**
     * 9.2 自動儲存
     */
    public function handle_autosave(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        parse_str($_POST['data'] ?? '', $data);
        
        // 儲存到暫存
        set_transient('aisc_autosave_' . $form_id . '_' . get_current_user_id(), $data, HOUR_IN_SECONDS);
        
        wp_send_json_success(['message' => '已自動儲存']);
    }
    
    /**
     * 10. 公開追蹤處理器
     */
    
    /**
     * 10.1 追蹤頁面瀏覽
     */
    public function handle_track_pageview(): void {
        // 不需要登入
        check_ajax_referer('aisc_public_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $referrer = sanitize_text_field($_POST['referrer'] ?? '');
        
        if ($post_id <= 0) {
            wp_send_json_error('無效的文章ID');
        }
        
        $performance_tracker = $this->get_module('performance_tracker');
        $performance_tracker->track_pageview($post_id, [
            'referrer' => $referrer,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $this->get_client_ip()
        ]);
        
        wp_send_json_success();
    }
    
    /**
     * 10.2 追蹤事件
     */
    public function handle_track_event(): void {
        check_ajax_referer('aisc_public_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $category = sanitize_text_field($_POST['event_category'] ?? '');
        $action = sanitize_text_field($_POST['event_action'] ?? '');
        $label = sanitize_text_field($_POST['event_label'] ?? '');
        
        if ($post_id <= 0 || empty($category) || empty($action)) {
            wp_send_json_error('參數不完整');
        }
        
        $performance_tracker = $this->get_module('performance_tracker');
        $performance_tracker->track_event($post_id, $category, $action, $label);
        
        wp_send_json_success();
    }
    
    /**
     * 10.3 追蹤滾動深度
     */
    public function handle_track_scroll_depth(): void {
        // 使用Beacon API時的處理
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post_id = intval($_POST['post_id'] ?? 0);
            $depth = intval($_POST['depth'] ?? 0);
            $nonce = $_POST['nonce'] ?? '';
            
            if (!wp_verify_nonce($nonce, 'aisc_public_nonce')) {
                http_response_code(403);
                exit;
            }
            
            if ($post_id > 0 && $depth > 0) {
                $performance_tracker = $this->get_module('performance_tracker');
                $performance_tracker->track_scroll_depth($post_id, $depth);
            }
            
            http_response_code(204);
            exit;
        }
    }
    
    /**
     * 10.4 追蹤停留時間
     */
    public function handle_track_time_on_page(): void {
        // 使用Beacon API時的處理
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post_id = intval($_POST['post_id'] ?? 0);
            $total_time = intval($_POST['total_time'] ?? 0);
            $active_time = intval($_POST['active_time'] ?? 0);
            $engagement_rate = floatval($_POST['engagement_rate'] ?? 0);
            $nonce = $_POST['nonce'] ?? '';
            
            if (!wp_verify_nonce($nonce, 'aisc_public_nonce')) {
                http_response_code(403);
                exit;
            }
            
            if ($post_id > 0 && $total_time > 0) {
                $performance_tracker = $this->get_module('performance_tracker');
                $performance_tracker->track_time_on_page($post_id, [
                    'total_time' => $total_time,
                    'active_time' => $active_time,
                    'engagement_rate' => $engagement_rate
                ]);
            }
            
            http_response_code(204);
            exit;
        }
    }
    
    /**
     * 10.5 載入更多相關文章
     */
    public function handle_load_more_related(): void {
        check_ajax_referer('aisc_public_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $offset = intval($_POST['offset'] ?? 0);
        
        if ($post_id <= 0) {
            wp_send_json_error('無效的文章ID');
        }
        
        // 取得相關文章
        $keyword = get_post_meta($post_id, '_aisc_keyword', true);
        $categories = wp_get_post_categories($post_id);
        
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'offset' => $offset,
            'post__not_in' => [$post_id],
            'meta_query' => [
                [
                    'key' => '_aisc_generated',
                    'value' => '1'
                ]
            ]
        ];
        
        if (!empty($categories)) {
            $args['category__in'] = $categories;
        }
        
        if ($keyword) {
            $args['s'] = $keyword;
        }
        
        $query = new \WP_Query($args);
        $posts = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                    'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'medium')
                ];
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success([
            'posts' => $posts,
            'has_more' => $query->found_posts > ($offset + 3)
        ]);
    }
    
    /**
     * 11. 資料匯出處理器
     */
    public function handle_export_data(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $type = sanitize_text_field($_GET['type'] ?? '');
        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        
        $data = '';
        $filename = '';
        
        switch ($type) {
            case 'keywords':
                $keyword_manager = $this->get_module('keyword_manager');
                $data = $keyword_manager->export_keywords($format);
                $filename = 'keywords';
                break;
                
            case 'content_history':
                $db = new Database();
                $history = $db->get_results("SELECT * FROM {$db->get_table_name('content_history')} ORDER BY created_at DESC");
                $data = $this->format_export_data($history, $format);
                $filename = 'content_history';
                break;
                
            case 'performance':
                $performance_tracker = $this->get_module('performance_tracker');
                $data = $performance_tracker->export_performance_data($format);
                $filename = 'performance';
                break;
                
            default:
                wp_send_json_error('無效的匯出類型');
        }
        
        $filename = 'aisc_' . $filename . '_' . date('Y-m-d') . '.' . $format;
        
        header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'application/json'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $data;
        exit;
    }
    
    /**
     * 12. 輔助方法
     */
    
    /**
     * 12.1 取得模組實例
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
     * 12.2 發送SSE訊息
     */
    private function send_sse_message(array $data, string $event = 'message'): void {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    /**
     * 12.3 取得客戶端IP
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // 處理多個IP的情況
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * 12.4 格式化匯出資料
     */
    private function format_export_data(array $data, string $format): string {
        if ($format === 'csv') {
            if (empty($data)) {
                return '';
            }
            
            $output = '';
            $headers = array_keys((array)$data[0]);
            $output .= implode(',', $headers) . "\n";
            
            foreach ($data as $row) {
                $values = [];
                foreach ($headers as $header) {
                    $value = $row->$header ?? '';
                    $values[] = '"' . str_replace('"', '""', $value) . '"';
                }
                $output .= implode(',', $values) . "\n";
            }
            
            return $output;
        } else {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
}
