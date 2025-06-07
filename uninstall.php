<?php
/**
 * 檔案：uninstall.php
 * 功能：外掛解除安裝腳本
 * 
 * @package AI_SEO_Content_Generator
 */

// 防止直接訪問
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * AI SEO Content Generator 解除安裝程序
 * 
 * 刪除所有外掛資料，包括：
 * - 資料表
 * - 選項
 * - 暫存資料
 * - 排程任務
 * - 文章meta資料
 * - 上傳的檔案
 */

// 1. 載入必要檔案
require_once plugin_dir_path(__FILE__) . 'includes/core/class-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/core/class-logger.php';

use AISC\Core\Database;
use AISC\Core\Logger;

// 2. 初始化
$db = new Database();
$logger = new Logger();

// 記錄解除安裝開始
$logger->info('開始執行外掛解除安裝程序');

// 3. 檢查是否保留資料
$keep_data = get_option('aisc_keep_data_on_uninstall', false);

if (!$keep_data) {
    // 4. 刪除資料表
    $tables = [
        'keywords',
        'content_history',
        'schedules',
        'costs',
        'logs',
        'performance',
        'cache'
    ];
    
    foreach ($tables as $table) {
        $table_name = $db->get_table_name($table);
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        $logger->info("已刪除資料表: {$table_name}");
    }
    
    // 5. 刪除選項
    $options = [
        // API設定
        'aisc_openai_api_key',
        'aisc_google_api_key',
        'aisc_ga_measurement_id',
        
        // 時區設定
        'aisc_timezone',
        
        // 內容設定
        'aisc_default_word_count',
        'aisc_default_images',
        'aisc_default_model',
        
        // SEO設定
        'aisc_keyword_density_min',
        'aisc_keyword_density_max',
        'aisc_meta_length',
        'aisc_auto_internal_links',
        'aisc_auto_external_links',
        'aisc_auto_related_posts',
        'aisc_auto_schema',
        'aisc_featured_snippet_paragraph',
        'aisc_featured_snippet_list',
        'aisc_featured_snippet_table',
        'aisc_featured_snippet_faq',
        'aisc_faq_count',
        'aisc_similarity_threshold',
        'aisc_avg_sentence_length',
        'aisc_paragraph_sentences',
        
        // 成本設定
        'aisc_daily_budget',
        'aisc_monthly_budget',
        'aisc_budget_warning',
        'aisc_budget_stop',
        
        // 進階設定
        'aisc_log_level',
        'aisc_log_retention',
        'aisc_log_max_size',
        'aisc_debug_mode',
        'aisc_test_mode',
        
        // 系統設定
        'aisc_db_version',
        'aisc_last_health_check',
        'aisc_db_stats',
        'aisc_keep_data_on_uninstall'
    ];
    
    foreach ($options as $option) {
        delete_option($option);
        $logger->info("已刪除選項: {$option}");
    }
    
    // 6. 刪除暫存資料
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_aisc_%' 
        OR option_name LIKE '_transient_timeout_aisc_%'"
    );
    $logger->info("已刪除所有暫存資料");
    
    // 7. 清除排程任務
    $schedules = [
        'aisc_keyword_update',
        'aisc_content_generation',
        'aisc_check_schedules',
        'aisc_collect_performance_data',
        'aisc_cleanup_performance_data',
        'aisc_daily_cost_report',
        'aisc_health_check',
        'aisc_db_maintenance'
    ];
    
    foreach ($schedules as $hook) {
        wp_clear_scheduled_hook($hook);
        $logger->info("已清除排程任務: {$hook}");
    }
    
    // 8. 刪除文章meta資料
    $meta_keys = [
        '_aisc_generated',
        '_aisc_keyword',
        '_aisc_model',
        '_aisc_cost',
        '_aisc_generation_time',
        '_aisc_word_count',
        '_aisc_performance_score'
    ];
    
    foreach ($meta_keys as $meta_key) {
        $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key]);
        $logger->info("已刪除文章meta: {$meta_key}");
    }
    
    // 9. 刪除上傳的檔案和目錄
    $directories = [
        WP_CONTENT_DIR . '/uploads/aisc/',
        plugin_dir_path(__FILE__) . 'logs/',
        plugin_dir_path(__FILE__) . 'cache/',
        plugin_dir_path(__FILE__) . 'temp/',
        plugin_dir_path(__FILE__) . 'exports/'
    ];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            aisc_delete_directory($dir);
            $logger->info("已刪除目錄: {$dir}");
        }
    }
    
    // 10. 清除使用者meta資料
    $user_meta_keys = [
        'aisc_dismissed_notices',
        'aisc_dashboard_preferences',
        'aisc_last_login'
    ];
    
    foreach ($user_meta_keys as $meta_key) {
        $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key]);
    }
    
    // 11. 清除快取
    wp_cache_flush();
    
    // 12. 執行最終清理
    do_action('aisc_uninstall_cleanup');
    
    $logger->info('外掛解除安裝程序完成');
    
} else {
    $logger->info('外掛解除安裝但保留資料（根據設定）');
}

/**
 * 遞迴刪除目錄
 */
function aisc_delete_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            aisc_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}

// 13. 通知其他外掛
do_action('aisc_plugin_uninstalled');

// 完成
exit('AI SEO Content Generator 已成功解除安裝。');
