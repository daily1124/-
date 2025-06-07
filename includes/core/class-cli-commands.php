<?php
/**
 * 檔案：includes/class-cli-commands.php
 * 功能：WP-CLI命令支援
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Core
 */

namespace AISC\Core;

use WP_CLI;
use WP_CLI_Command;
use AISC\Modules\KeywordManager;
use AISC\Modules\ContentGenerator;
use AISC\Modules\Scheduler;
use AISC\Modules\CostController;
use AISC\Modules\Diagnostics;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI命令類別
 * 
 * 提供命令列介面管理外掛功能
 */
class CLICommands extends WP_CLI_Command {
    
    /**
     * 更新關鍵字
     * 
     * ## OPTIONS
     * 
     * [--type=<type>]
     * : 關鍵字類型 (all, general, casino)
     * ---
     * default: all
     * 
     * ## EXAMPLES
     * 
     *     wp aisc keywords update
     *     wp aisc keywords update --type=general
     * 
     * @when after_wp_load
     */
    public function keywords($args, $assoc_args) {
        $action = $args[0] ?? 'list';
        
        switch ($action) {
            case 'update':
                $this->update_keywords($assoc_args);
                break;
                
            case 'list':
                $this->list_keywords($assoc_args);
                break;
                
            case 'analyze':
                $this->analyze_keywords($assoc_args);
                break;
                
            default:
                WP_CLI::error("未知的命令: {$action}");
        }
    }
    
    /**
     * 生成內容
     * 
     * ## OPTIONS
     * 
     * <keyword>
     * : 目標關鍵字
     * 
     * [--word-count=<count>]
     * : 文章字數
     * ---
     * default: 8000
     * 
     * [--model=<model>]
     * : AI模型
     * ---
     * default: gpt-4-turbo-preview
     * options:
     *   - gpt-4
     *   - gpt-4-turbo-preview
     *   - gpt-3.5-turbo
     * 
     * [--images=<count>]
     * : 圖片數量
     * ---
     * default: 5
     * 
     * ## EXAMPLES
     * 
     *     wp aisc generate "WordPress SEO優化"
     *     wp aisc generate "AI內容生成" --word-count=10000 --model=gpt-4
     * 
     * @when after_wp_load
     */
    public function generate($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('請提供關鍵字');
        }
        
        $keyword = $args[0];
        $params = [
            'keyword' => $keyword,
            'word_count' => intval($assoc_args['word-count'] ?? 8000),
            'model' => $assoc_args['model'] ?? 'gpt-4-turbo-preview',
            'images' => intval($assoc_args['images'] ?? 5),
            'optimize_featured_snippet' => true,
            'include_faq' => true,
            'add_schema' => true,
            'auto_internal_links' => true
        ];
        
        WP_CLI::log("開始生成內容：{$keyword}");
        
        try {
            $content_generator = new ContentGenerator();
            
            // 設定進度回調
            $content_generator->set_progress_callback(function($progress, $message) {
                $bar = \WP_CLI\Utils\make_progress_bar($message, 100);
                $bar->tick($progress);
                
                if ($progress >= 100) {
                    $bar->finish();
                }
            });
            
            $post_id = $content_generator->generate($params);
            
            if ($post_id) {
                WP_CLI::success("文章生成成功！");
                WP_CLI::log("文章ID: {$post_id}");
                WP_CLI::log("編輯連結: " . get_edit_post_link($post_id, 'raw'));
                WP_CLI::log("查看連結: " . get_permalink($post_id));
            } else {
                WP_CLI::error('文章生成失敗');
            }
            
        } catch (\Exception $e) {
            WP_CLI::error($e->getMessage());
        }
    }
    
    /**
     * 管理排程
     * 
     * ## OPTIONS
     * 
     * <action>
     * : 操作 (list, create, update, delete, run)
     * 
     * [--id=<id>]
     * : 排程ID
     * 
     * [--name=<name>]
     * : 排程名稱
     * 
     * [--type=<type>]
     * : 關鍵字類型
     * 
     * [--frequency=<frequency>]
     * : 執行頻率
     * 
     * ## EXAMPLES
     * 
     *     wp aisc schedule list
     *     wp aisc schedule create --name="每日生成" --type=general --frequency=daily
     *     wp aisc schedule run --id=1
     * 
     * @when after_wp_load
     */
    public function schedule($args, $assoc_args) {
        $action = $args[0] ?? 'list';
        $scheduler = new Scheduler();
        
        switch ($action) {
            case 'list':
                $this->list_schedules($scheduler);
                break;
                
            case 'create':
                $this->create_schedule($scheduler, $assoc_args);
                break;
                
            case 'update':
                $this->update_schedule($scheduler, $assoc_args);
                break;
                
            case 'delete':
                $this->delete_schedule($scheduler, $assoc_args);
                break;
                
            case 'run':
                $this->run_schedule($scheduler, $assoc_args);
                break;
                
            default:
                WP_CLI::error("未知的命令: {$action}");
        }
    }
    
    /**
     * 成本報告
     * 
     * ## OPTIONS
     * 
     * [--period=<period>]
     * : 時間範圍
     * ---
     * default: month
     * options:
     *   - today
     *   - week
     *   - month
     *   - year
     * 
     * [--export=<format>]
     * : 匯出格式
     * ---
     * options:
     *   - csv
     *   - json
     * 
     * ## EXAMPLES
     * 
     *     wp aisc cost
     *     wp aisc cost --period=week
     *     wp aisc cost --export=csv
     * 
     * @when after_wp_load
     */
    public function cost($args, $assoc_args) {
        $cost_controller = new CostController();
        $period = $assoc_args['period'] ?? 'month';
        
        // 取得成本摘要
        $summary = $cost_controller->get_cost_summary();
        
        WP_CLI::log("\n=== AI SEO Content Generator 成本報告 ===\n");
        
        WP_CLI::log(sprintf("今日成本: NT$ %.2f", $summary['today']));
        WP_CLI::log(sprintf("昨日成本: NT$ %.2f", $summary['yesterday']));
        WP_CLI::log(sprintf("本週成本: NT$ %.2f", $summary['week']));
        WP_CLI::log(sprintf("本月成本: NT$ %.2f", $summary['month']));
        
        // 預算使用情況
        $daily_budget = floatval(get_option('aisc_daily_budget', 0));
        $monthly_budget = floatval(get_option('aisc_monthly_budget', 0));
        
        if ($daily_budget > 0) {
            $daily_usage = ($summary['today'] / $daily_budget) * 100;
            WP_CLI::log(sprintf("\n每日預算使用: %.1f%% (NT$ %.2f / NT$ %.2f)", 
                $daily_usage, $summary['today'], $daily_budget));
        }
        
        if ($monthly_budget > 0) {
            $monthly_usage = ($summary['month'] / $monthly_budget) * 100;
            WP_CLI::log(sprintf("每月預算使用: %.1f%% (NT$ %.2f / NT$ %.2f)", 
                $monthly_usage, $summary['month'], $monthly_budget));
        }
        
        // 匯出選項
        if (isset($assoc_args['export'])) {
            $this->export_cost_report($cost_controller, $assoc_args['export'], $period);
        }
    }
    
    /**
     * 診斷系統
     * 
     * ## OPTIONS
     * 
     * [--test=<test>]
     * : 執行特定測試
     * ---
     * default: all
     * options:
     *   - all
     *   - system
     *   - api
     *   - database
     *   - performance
     * 
     * [--fix]
     * : 嘗試自動修復問題
     * 
     * ## EXAMPLES
     * 
     *     wp aisc diagnose
     *     wp aisc diagnose --test=api
     *     wp aisc diagnose --fix
     * 
     * @when after_wp_load
     */
    public function diagnose($args, $assoc_args) {
        $diagnostics = new Diagnostics();
        $test = $assoc_args['test'] ?? 'all';
        $fix = isset($assoc_args['fix']);
        
        WP_CLI::log("\n=== AI SEO Content Generator 診斷報告 ===\n");
        
        if ($test === 'all' || $test === 'system') {
            $this->run_system_diagnosis($diagnostics, $fix);
        }
        
        if ($test === 'all' || $test === 'api') {
            $this->run_api_diagnosis($diagnostics);
        }
        
        if ($test === 'all' || $test === 'database') {
            $this->run_database_diagnosis($diagnostics, $fix);
        }
        
        if ($test === 'all' || $test === 'performance') {
            $this->run_performance_diagnosis($diagnostics);
        }
        
        WP_CLI::log("\n診斷完成！");
    }
    
    /**
     * 重置外掛
     * 
     * ## OPTIONS
     * 
     * [--keep-settings]
     * : 保留設定
     * 
     * [--yes]
     * : 跳過確認
     * 
     * ## EXAMPLES
     * 
     *     wp aisc reset
     *     wp aisc reset --keep-settings
     *     wp aisc reset --yes
     * 
     * @when after_wp_load
     */
    public function reset($args, $assoc_args) {
        if (!isset($assoc_args['yes'])) {
            WP_CLI::confirm('確定要重置外掛嗎？這將刪除所有資料！');
        }
        
        $keep_settings = isset($assoc_args['keep-settings']);
        
        WP_CLI::log('開始重置外掛...');
        
        $diagnostics = new Diagnostics();
        $result = $diagnostics->reset_plugin($keep_settings);
        
        if ($result['success']) {
            WP_CLI::success('外掛已成功重置');
            if ($keep_settings) {
                WP_CLI::log('設定已保留');
            }
        } else {
            WP_CLI::error('重置失敗: ' . $result['error']);
        }
    }
    
    /**
     * 私有輔助方法
     */
    
    private function update_keywords($assoc_args) {
        $type = $assoc_args['type'] ?? 'all';
        $keyword_manager = new KeywordManager();
        
        WP_CLI::log("開始更新{$type}關鍵字...");
        
        try {
            $result = $keyword_manager->update_keywords($type);
            
            WP_CLI::success(sprintf(
                "關鍵字更新完成！總計: %d, 一般: %d, 娛樂城: %d",
                $result['total'],
                $result['general'],
                $result['casino']
            ));
            
        } catch (\Exception $e) {
            WP_CLI::error($e->getMessage());
        }
    }
    
    private function list_keywords($assoc_args) {
        $keyword_manager = new KeywordManager();
        $type = $assoc_args['type'] ?? 'all';
        $keywords = $keyword_manager->get_keywords($type);
        
        if (empty($keywords)) {
            WP_CLI::log('沒有找到關鍵字');
            return;
        }
        
        $headers = ['ID', '關鍵字', '類型', '搜尋量', '競爭度', '優先級', '狀態'];
        $data = [];
        
        foreach ($keywords as $keyword) {
            $data[] = [
                $keyword['id'],
                $keyword['keyword'],
                $keyword['type'],
                number_format($keyword['search_volume']),
                round($keyword['competition_level']) . '%',
                round($keyword['priority_score']),
                $keyword['status']
            ];
        }
        
        WP_CLI\Utils\format_items('table', $data, $headers);
    }
    
    private function analyze_keywords($assoc_args) {
        $keyword = $assoc_args['keyword'] ?? '';
        
        if (empty($keyword)) {
            WP_CLI::error('請提供要分析的關鍵字');
        }
        
        $keyword_manager = new KeywordManager();
        $analysis = $keyword_manager->analyze_keyword($keyword);
        
        WP_CLI::log("\n=== 關鍵字分析: {$keyword} ===\n");
        WP_CLI::log("搜尋量: " . number_format($analysis['search_volume']));
        WP_CLI::log("競爭度: " . $analysis['competition_level'] . '%');
        WP_CLI::log("CPC: NT$ " . $analysis['cpc_value']);
        WP_CLI::log("趨勢: " . $analysis['trend']);
        WP_CLI::log("建議: " . $analysis['recommendation']);
    }
    
    private function list_schedules($scheduler) {
        $schedules = $scheduler->get_schedules();
        
        if (empty($schedules)) {
            WP_CLI::log('沒有設定排程');
            return;
        }
        
        $headers = ['ID', '名稱', '類型', '頻率', '下次執行', '狀態'];
        $data = [];
        
        foreach ($schedules as $schedule) {
            $data[] = [
                $schedule['id'],
                $schedule['name'],
                $schedule['type'],
                $schedule['frequency'],
                $schedule['next_run'],
                $schedule['status']
            ];
        }
        
        WP_CLI\Utils\format_items('table', $data, $headers);
    }
    
    private function create_schedule($scheduler, $assoc_args) {
        $required = ['name', 'type', 'frequency'];
        
        foreach ($required as $field) {
            if (!isset($assoc_args[$field])) {
                WP_CLI::error("缺少必要參數: --{$field}");
            }
        }
        
        $data = [
            'name' => $assoc_args['name'],
            'type' => $assoc_args['type'],
            'frequency' => $assoc_args['frequency'],
            'keyword_count' => intval($assoc_args['keyword-count'] ?? 1),
            'status' => $assoc_args['status'] ?? 'active'
        ];
        
        $id = $scheduler->create_schedule($data);
        
        if ($id) {
            WP_CLI::success("排程建立成功！ID: {$id}");
        } else {
            WP_CLI::error('排程建立失敗');
        }
    }
    
    private function run_schedule($scheduler, $assoc_args) {
        if (!isset($assoc_args['id'])) {
            WP_CLI::error('請提供排程ID');
        }
        
        $id = intval($assoc_args['id']);
        $schedule = $scheduler->get_schedule($id);
        
        if (!$schedule) {
            WP_CLI::error('排程不存在');
        }
        
        WP_CLI::log("執行排程: {$schedule['name']}");
        
        try {
            $scheduler->run_schedule($id);
            WP_CLI::success('排程執行完成');
        } catch (\Exception $e) {
            WP_CLI::error($e->getMessage());
        }
    }
    
    private function export_cost_report($cost_controller, $format, $period) {
        $data = $cost_controller->export_cost_report($format, $period);
        $filename = 'aisc_cost_report_' . date('Y-m-d') . '.' . $format;
        
        if (file_put_contents($filename, $data)) {
            WP_CLI::success("報告已匯出: {$filename}");
        } else {
            WP_CLI::error('匯出失敗');
        }
    }
    
    private function run_system_diagnosis($diagnostics, $fix) {
        WP_CLI::log("## 系統檢查\n");
        
        $checks = $diagnostics->run_system_checks();
        
        foreach ($checks as $check) {
            $icon = $check['status'] === 'pass' ? '✓' : 
                   ($check['status'] === 'warning' ? '⚠' : '✗');
            
            WP_CLI::log("{$icon} {$check['name']}: {$check['message']}");
            
            if ($fix && $check['status'] === 'error') {
                // 嘗試自動修復
                $this->try_fix_issue($check['name']);
            }
        }
    }
    
    private function run_api_diagnosis($diagnostics) {
        WP_CLI::log("\n## API連接測試\n");
        
        $result = $diagnostics->run_test('api');
        
        if ($result['success']) {
            WP_CLI::log("✓ API連接正常");
        } else {
            WP_CLI::log("✗ API連接失敗: " . $result['error']);
        }
        
        if (isset($result['details'])) {
            foreach ($result['details'] as $api => $status) {
                $icon = $status['status'] === 'success' ? '✓' : '✗';
                WP_CLI::log("  {$icon} {$api}");
            }
        }
    }
    
    private function run_database_diagnosis($diagnostics, $fix) {
        WP_CLI::log("\n## 資料庫檢查\n");
        
        $db = new Database();
        $tables = ['keywords', 'content_history', 'schedules', 'costs', 'logs', 'performance', 'cache'];
        
        foreach ($tables as $table) {
            if ($db->table_exists($table)) {
                WP_CLI::log("✓ 資料表 {$table} 存在");
            } else {
                WP_CLI::log("✗ 資料表 {$table} 不存在");
                
                if ($fix) {
                    WP_CLI::log("  嘗試建立資料表...");
                    $db->create_tables();
                    WP_CLI::log("  ✓ 資料表已建立");
                }
            }
        }
    }
    
    private function run_performance_diagnosis($diagnostics) {
        WP_CLI::log("\n## 效能分析\n");
        
        $metrics = $diagnostics->get_performance_metrics();
        
        WP_CLI::log("平均生成時間: " . $metrics['avg_generation_time'] . " 秒");
        WP_CLI::log("API錯誤率: " . $metrics['api_error_rate'] . "%");
        WP_CLI::log("最近7天API呼叫: " . $metrics['total_api_calls_7d']);
        WP_CLI::log("失敗的API呼叫: " . $metrics['failed_api_calls_7d']);
    }
    
    private function try_fix_issue($issue_name) {
        switch ($issue_name) {
            case '目錄權限':
                WP_CLI::log("  嘗試修復目錄權限...");
                // 實施修復邏輯
                break;
                
            case '資料庫表':
                WP_CLI::log("  嘗試重建資料表...");
                $db = new Database();
                $db->create_tables();
                break;
                
            // 其他問題的修復邏輯...
        }
    }
}

// 註冊WP-CLI命令
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('aisc', __NAMESPACE__ . '\\CLICommands');
}
