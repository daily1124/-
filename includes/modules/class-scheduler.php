<?php
/**
 * 檔案：includes/modules/class-scheduler.php
 * 功能：排程管理模組
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Modules
 */

namespace AISC\Modules;

use AISC\Core\Database;
use AISC\Core\Logger;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 排程管理類別
 * 
 * 負責管理內容自動生成排程，支援獨立的台灣和娛樂城關鍵字排程
 */
class Scheduler {
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 日誌實例
     */
    private Logger $logger;
    
    /**
     * 內容生成器實例
     */
    private ?ContentGenerator $content_generator = null;
    
    /**
     * 排程頻率選項
     */
    private const FREQUENCIES = [
        'once' => '單次',
        'hourly' => '每小時',
        'twicedaily' => '每天兩次',
        'daily' => '每天',
        'weekly' => '每週',
        'monthly' => '每月'
    ];
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
        
        // 設定時區為台灣時間
        date_default_timezone_set('Asia/Taipei');
        
        // 初始化排程掛鉤
        $this->init_hooks();
    }
    
    /**
     * 1. 初始化和設定
     */
    
    /**
     * 1.1 初始化掛鉤
     */
    private function init_hooks(): void {
        // 排程執行掛鉤
        add_action('aisc_run_schedule', [$this, 'run_schedule'], 10, 1);
        
        // 排程檢查掛鉤
        add_action('aisc_check_schedules', [$this, 'check_and_run_schedules']);
        
        // 確保排程檢查任務存在
        if (!wp_next_scheduled('aisc_check_schedules')) {
            wp_schedule_event(time(), 'every_30_minutes', 'aisc_check_schedules');
        }
    }
    
    /**
     * 2. 排程管理主要方法
     */
    
    /**
     * 2.1 創建排程
     */
    public function create_schedule(array $data): int|false {
        $this->logger->info('創建新排程', $data);
        
        $defaults = [
            'name' => '未命名排程',
            'type' => 'general',
            'frequency' => 'daily',
            'keyword_count' => 1,
            'status' => 'active',
            'content_settings' => []
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // 驗證資料
        if (!$this->validate_schedule_data($data)) {
            return false;
        }
        
        // 計算下次執行時間
        $data['next_run'] = $this->calculate_next_run($data['frequency'], $data['start_time'] ?? null);
        
        // 序列化設定
        $data['content_settings'] = wp_json_encode($data['content_settings']);
        
        // 插入資料庫
        $schedule_id = $this->db->insert('schedules', [
            'name' => $data['name'],
            'type' => $data['type'],
            'frequency' => $data['frequency'],
            'next_run' => $data['next_run'],
            'keyword_count' => $data['keyword_count'],
            'content_settings' => $data['content_settings'],
            'status' => $data['status']
        ]);
        
        if ($schedule_id) {
            // 如果是單次執行且時間已到，立即執行
            if ($data['frequency'] === 'once' && strtotime($data['next_run']) <= time()) {
                $this->run_schedule($schedule_id);
            } else {
                // 否則設定WordPress cron
                $this->schedule_wp_cron($schedule_id, $data['next_run'], $data['frequency']);
            }
            
            $this->logger->info('排程創建成功', ['schedule_id' => $schedule_id]);
        }
        
        return $schedule_id;
    }
    
    /**
     * 2.2 更新排程
     */
    public function update_schedule(int $schedule_id, array $data): bool {
        $schedule = $this->get_schedule($schedule_id);
        
        if (!$schedule) {
            return false;
        }
        
        // 準備更新資料
        $update_data = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
        }
        
        if (isset($data['type'])) {
            $update_data['type'] = $data['type'];
        }
        
        if (isset($data['frequency'])) {
            $update_data['frequency'] = $data['frequency'];
            
            // 重新計算下次執行時間
            $update_data['next_run'] = $this->calculate_next_run(
                $data['frequency'], 
                $data['start_time'] ?? null
            );
            
            // 清除舊的cron
            $this->clear_wp_cron($schedule_id);
            
            // 設定新的cron
            if ($data['status'] ?? $schedule->status === 'active') {
                $this->schedule_wp_cron($schedule_id, $update_data['next_run'], $data['frequency']);
            }
        }
        
        if (isset($data['keyword_count'])) {
            $update_data['keyword_count'] = intval($data['keyword_count']);
        }
        
        if (isset($data['content_settings'])) {
            $update_data['content_settings'] = wp_json_encode($data['content_settings']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
            
            // 如果停用，清除cron
            if ($data['status'] === 'paused') {
                $this->clear_wp_cron($schedule_id);
            }
        }
        
        $result = $this->db->update('schedules', $update_data, ['id' => $schedule_id]);
        
        if ($result !== false) {
            $this->logger->info('排程更新成功', [
                'schedule_id' => $schedule_id,
                'updates' => array_keys($update_data)
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * 2.3 刪除排程
     */
    public function delete_schedule(int $schedule_id): bool {
        // 清除cron
        $this->clear_wp_cron($schedule_id);
        
        // 刪除資料庫記錄
        $result = $this->db->delete('schedules', ['id' => $schedule_id]);
        
        if ($result) {
            $this->logger->info('排程已刪除', ['schedule_id' => $schedule_id]);
        }
        
        return $result !== false;
    }
    
    /**
     * 2.4 取得排程
     */
    public function get_schedule(int $schedule_id): ?object {
        return $this->db->get_row('schedules', ['id' => $schedule_id]);
    }
    
    /**
     * 2.5 取得所有排程
     */
    public function get_schedules(array $filters = []): array {
        $where = [];
        
        if (!empty($filters['type'])) {
            $where['type'] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        
        $schedules = $this->db->get_results('schedules', $where, [
            'orderby' => 'next_run',
            'order' => 'ASC'
        ]);
        
        // 解析設定
        foreach ($schedules as &$schedule) {
            $schedule->content_settings = json_decode($schedule->content_settings, true) ?: [];
            $schedule->frequency_label = self::FREQUENCIES[$schedule->frequency] ?? $schedule->frequency;
            $schedule->next_run_formatted = $this->format_datetime($schedule->next_run);
            $schedule->last_run_formatted = $schedule->last_run ? $this->format_datetime($schedule->last_run) : '從未執行';
        }
        
        return $schedules;
    }
    
    /**
     * 2.6 取得活動排程
     */
    public function get_active_schedules(): array {
        return $this->get_schedules(['status' => 'active']);
    }
    
    /**
     * 3. 排程執行
     */
    
    /**
     * 3.1 執行排程
     */
    public function run_schedule(int $schedule_id): array {
        $schedule = $this->get_schedule($schedule_id);
        
        if (!$schedule || $schedule->status !== 'active') {
            $this->logger->warning('嘗試執行無效或非活動排程', ['schedule_id' => $schedule_id]);
            return ['success' => false, 'message' => '排程無效或未啟用'];
        }
        
        $this->logger->info('開始執行排程', [
            'schedule_id' => $schedule_id,
            'name' => $schedule->name
        ]);
        
        // 檢查是否已暫停所有排程
        if (get_option('aisc_schedules_paused', false)) {
            $this->logger->warning('所有排程已暫停');
            return ['success' => false, 'message' => '所有排程已暫停'];
        }
        
        // 更新執行時間
        $this->db->update('schedules', [
            'last_run' => current_time('mysql')
        ], ['id' => $schedule_id]);
        
        try {
            // 取得內容生成器
            if (!$this->content_generator) {
                $this->content_generator = new ContentGenerator();
            }
            
            // 執行內容生成
            $result = $this->content_generator->generate_scheduled_content((array) $schedule);
            
            // 更新下次執行時間
            if ($schedule->frequency !== 'once') {
                $next_run = $this->calculate_next_run($schedule->frequency);
                
                $this->db->update('schedules', [
                    'next_run' => $next_run
                ], ['id' => $schedule_id]);
                
                // 重新設定cron
                $this->schedule_wp_cron($schedule_id, $next_run, $schedule->frequency);
            } else {
                // 單次執行完成，設為已完成
                $this->db->update('schedules', [
                    'status' => 'completed'
                ], ['id' => $schedule_id]);
            }
            
            $this->logger->info('排程執行完成', [
                'schedule_id' => $schedule_id,
                'result' => $result
            ]);
            
            return [
                'success' => true,
                'result' => $result
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('排程執行失敗', [
                'schedule_id' => $schedule_id,
                'error' => $e->getMessage()
            ]);
            
            // 更新失敗計數
            $this->db->update('schedules', [
                'failure_count' => $schedule->failure_count + 1
            ], ['id' => $schedule_id]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 3.2 檢查並執行到期的排程
     */
    public function check_and_run_schedules(): void {
        $this->logger->debug('檢查到期排程');
        
        // 取得所有需要執行的排程
        $schedules = $this->get_due_schedules();
        
        foreach ($schedules as $schedule) {
            $this->run_schedule($schedule->id);
        }
    }
    
    /**
     * 3.3 取得到期的排程
     */
    private function get_due_schedules(): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('schedules');
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE status = 'active' 
            AND next_run <= %s 
            ORDER BY next_run ASC",
            current_time('mysql')
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * 3.4 檢查排程是否應該執行
     */
    public function should_run(array $schedule): bool {
        // 檢查狀態
        if ($schedule['status'] !== 'active') {
            return false;
        }
        
        // 檢查時間
        $next_run_time = strtotime($schedule['next_run']);
        $current_time = current_time('timestamp');
        
        if ($next_run_time > $current_time) {
            return false;
        }
        
        // 檢查是否在執行時間窗口內
        $settings = json_decode($schedule['content_settings'], true) ?: [];
        
        if (!empty($settings['execution_window'])) {
            $hour = date('H');
            $start_hour = $settings['execution_window']['start'] ?? 0;
            $end_hour = $settings['execution_window']['end'] ?? 23;
            
            if ($hour < $start_hour || $hour > $end_hour) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 4. WordPress Cron管理
     */
    
    /**
     * 4.1 設定WordPress cron
     */
    private function schedule_wp_cron(int $schedule_id, string $next_run, string $frequency): void {
        $timestamp = strtotime($next_run);
        $hook = 'aisc_run_schedule';
        
        // 清除舊的
        $this->clear_wp_cron($schedule_id);
        
        if ($frequency === 'once') {
            wp_schedule_single_event($timestamp, $hook, [$schedule_id]);
        } else {
            // 將自定義頻率映射到WordPress cron頻率
            $wp_frequency = $this->map_to_wp_frequency($frequency);
            wp_schedule_event($timestamp, $wp_frequency, $hook, [$schedule_id]);
        }
    }
    
    /**
     * 4.2 清除WordPress cron
     */
    private function clear_wp_cron(int $schedule_id): void {
        $hook = 'aisc_run_schedule';
        $timestamp = wp_next_scheduled($hook, [$schedule_id]);
        
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook, [$schedule_id]);
        }
    }
    
    /**
     * 4.3 映射到WordPress cron頻率
     */
    private function map_to_wp_frequency(string $frequency): string {
        $mapping = [
            'hourly' => 'hourly',
            'twicedaily' => 'twicedaily',
            'daily' => 'daily',
            'weekly' => 'weekly',
            'monthly' => 'monthly'
        ];
        
        return $mapping[$frequency] ?? 'daily';
    }
    
    /**
     * 5. 時間計算方法
     */
    
    /**
     * 5.1 計算下次執行時間
     */
    private function calculate_next_run(string $frequency, ?string $start_time = null): string {
        $base_time = $start_time ? strtotime($start_time) : time();
        
        // 如果基準時間已過，從當前時間開始計算
        if ($base_time < time()) {
            $base_time = time();
        }
        
        switch ($frequency) {
            case 'once':
                $next = $base_time;
                break;
                
            case 'hourly':
                $next = strtotime('+1 hour', $base_time);
                break;
                
            case 'twicedaily':
                $next = strtotime('+12 hours', $base_time);
                break;
                
            case 'daily':
                // 設定為明天的同一時間
                $next = strtotime('+1 day', $base_time);
                break;
                
            case 'weekly':
                $next = strtotime('+1 week', $base_time);
                break;
                
            case 'monthly':
                $next = strtotime('+1 month', $base_time);
                break;
                
            default:
                $next = strtotime('+1 day', $base_time);
        }
        
        return date('Y-m-d H:i:s', $next);
    }
    
    /**
     * 5.2 格式化日期時間
     */
    private function format_datetime(string $datetime): string {
        $timestamp = strtotime($datetime);
        
        // 使用台灣時間格式
        return date('Y年m月d日 H:i', $timestamp);
    }
    
    /**
     * 6. 驗證方法
     */
    
    /**
     * 6.1 驗證排程資料
     */
    private function validate_schedule_data(array $data): bool {
        // 驗證名稱
        if (empty($data['name'])) {
            $this->logger->error('排程名稱不能為空');
            return false;
        }
        
        // 驗證類型
        if (!in_array($data['type'], ['general', 'casino'])) {
            $this->logger->error('無效的排程類型', ['type' => $data['type']]);
            return false;
        }
        
        // 驗證頻率
        if (!array_key_exists($data['frequency'], self::FREQUENCIES)) {
            $this->logger->error('無效的排程頻率', ['frequency' => $data['frequency']]);
            return false;
        }
        
        // 驗證關鍵字數量
        if ($data['keyword_count'] < 1 || $data['keyword_count'] > 10) {
            $this->logger->error('關鍵字數量必須在1-10之間', ['count' => $data['keyword_count']]);
            return false;
        }
        
        return true;
    }
    
    /**
     * 7. 統計和報告
     */
    
    /**
     * 7.1 取得排程統計
     */
    public function get_statistics(): array {
        $stats = [
            'total' => $this->db->count('schedules'),
            'active' => $this->db->count('schedules', ['status' => 'active']),
            'paused' => $this->db->count('schedules', ['status' => 'paused']),
            'completed' => $this->db->count('schedules', ['status' => 'completed']),
            'total_runs' => $this->db->sum('schedules', 'run_count'),
            'total_success' => $this->db->sum('schedules', 'success_count'),
            'total_failure' => $this->db->sum('schedules', 'failure_count'),
            'success_rate' => 0,
            'by_type' => [
                'general' => $this->db->count('schedules', ['type' => 'general']),
                'casino' => $this->db->count('schedules', ['type' => 'casino'])
            ],
            'by_frequency' => []
        ];
        
        // 計算成功率
        $total_attempts = $stats['total_success'] + $stats['total_failure'];
        if ($total_attempts > 0) {
            $stats['success_rate'] = round(($stats['total_success'] / $total_attempts) * 100, 2);
        }
        
        // 按頻率統計
        foreach (self::FREQUENCIES as $freq => $label) {
            $stats['by_frequency'][$freq] = $this->db->count('schedules', ['frequency' => $freq]);
        }
        
        return $stats;
    }
    
    /**
     * 7.2 取得排程執行歷史
     */
    public function get_execution_history(int $schedule_id, int $limit = 50): array {
        global $wpdb;
        
        // 從內容歷史中取得相關記錄
        $table_name = $this->db->get_table_name('content_history');
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE meta_data LIKE %s 
            ORDER BY created_at DESC 
            LIMIT %d",
            '%"schedule_id":' . $schedule_id . '%',
            $limit
        );
        
        $history = $wpdb->get_results($query);
        
        // 格式化歷史記錄
        foreach ($history as &$record) {
            $record->created_at_formatted = $this->format_datetime($record->created_at);
            $record->meta_data = json_decode($record->meta_data, true) ?: [];
        }
        
        return $history;
    }
    
    /**
     * 8. 排程控制方法
     */
    
    /**
     * 8.1 暫停排程
     */
    public function pause_schedule(int $schedule_id): bool {
        // 清除cron
        $this->clear_wp_cron($schedule_id);
        
        // 更新狀態
        $result = $this->db->update('schedules', [
            'status' => 'paused'
        ], ['id' => $schedule_id]);
        
        if ($result !== false) {
            $this->logger->info('排程已暫停', ['schedule_id' => $schedule_id]);
        }
        
        return $result !== false;
    }
    
    /**
     * 8.2 恢復排程
     */
    public function resume_schedule(int $schedule_id): bool {
        $schedule = $this->get_schedule($schedule_id);
        
        if (!$schedule) {
            return false;
        }
        
        // 更新狀態
        $result = $this->db->update('schedules', [
            'status' => 'active'
        ], ['id' => $schedule_id]);
        
        if ($result !== false) {
            // 重新設定cron
            $this->schedule_wp_cron($schedule_id, $schedule->next_run, $schedule->frequency);
            
            $this->logger->info('排程已恢復', ['schedule_id' => $schedule_id]);
        }
        
        return $result !== false;
    }
    
    /**
     * 8.3 暫停所有排程
     */
    public function pause_all_schedules(): bool {
        update_option('aisc_schedules_paused', true);
        
        // 清除所有活動排程的cron
        $active_schedules = $this->get_active_schedules();
        
        foreach ($active_schedules as $schedule) {
            $this->clear_wp_cron($schedule->id);
        }
        
        $this->logger->warning('所有排程已暫停');
        
        return true;
    }
    
    /**
     * 8.4 恢復所有排程
     */
    public function resume_all_schedules(): bool {
        update_option('aisc_schedules_paused', false);
        
        // 重新設定所有活動排程的cron
        $active_schedules = $this->get_active_schedules();
        
        foreach ($active_schedules as $schedule) {
            $this->schedule_wp_cron($schedule->id, $schedule->next_run, $schedule->frequency);
        }
        
        $this->logger->info('所有排程已恢復');
        
        return true;
    }
    
    /**
     * 8.5 立即執行排程
     */
    public function run_now(int $schedule_id): array {
        $schedule = $this->get_schedule($schedule_id);
        
        if (!$schedule) {
            return ['success' => false, 'message' => '排程不存在'];
        }
        
        // 暫時將狀態設為活動
        $original_status = $schedule->status;
        if ($original_status !== 'active') {
            $this->db->update('schedules', ['status' => 'active'], ['id' => $schedule_id]);
        }
        
        // 執行排程
        $result = $this->run_schedule($schedule_id);
        
        // 恢復原始狀態
        if ($original_status !== 'active') {
            $this->db->update('schedules', ['status' => $original_status], ['id' => $schedule_id]);
        }
        
        return $result;
    }
    
    /**
     * 9. 排程模板
     */
    
    /**
     * 9.1 取得預設內容設定
     */
    public function get_default_content_settings(string $type = 'general'): array {
        $defaults = [
            'model' => 'gpt-4-turbo-preview',
            'length' => 8000,
            'images' => 3,
            'auto_internal_links' => true,
            'generate_faq' => true,
            'faq_count' => 10,
            'execution_window' => [
                'start' => 6,  // 早上6點
                'end' => 22    // 晚上10點
            ]
        ];
        
        // 娛樂城類型的特殊設定
        if ($type === 'casino') {
            $defaults['length'] = 10000;  // 更長的內容
            $defaults['images'] = 5;      // 更多圖片
        }
        
        return $defaults;
    }
    
    /**
     * 9.2 建立快速排程
     */
    public function create_quick_schedule(string $type, string $frequency): int|false {
        $presets = [
            'daily_general' => [
                'name' => '每日一般關鍵字文章',
                'type' => 'general',
                'frequency' => 'daily',
                'keyword_count' => 2,
                'start_time' => date('Y-m-d 09:00:00', strtotime('+1 day'))
            ],
            'daily_casino' => [
                'name' => '每日娛樂城文章',
                'type' => 'casino',
                'frequency' => 'daily',
                'keyword_count' => 1,
                'start_time' => date('Y-m-d 14:00:00', strtotime('+1 day'))
            ],
            'weekly_mixed' => [
                'name' => '每週混合文章',
                'type' => 'general',
                'frequency' => 'weekly',
                'keyword_count' => 5,
                'start_time' => date('Y-m-d 10:00:00', strtotime('next monday'))
            ]
        ];
        
        $preset_key = $frequency . '_' . $type;
        
        if (!isset($presets[$preset_key])) {
            return false;
        }
        
        $data = $presets[$preset_key];
        $data['content_settings'] = $this->get_default_content_settings($type);
        
        return $this->create_schedule($data);
    }
    
    /**
     * 10. 清理和維護
     */
    
    /**
     * 10.1 清理已完成的單次排程
     */
    public function cleanup_completed_schedules(int $days = 30): int {
        global $wpdb;
        $table_name = $this->db->get_table_name('schedules');
        
        $query = $wpdb->prepare(
            "DELETE FROM $table_name 
            WHERE status = 'completed' 
            AND frequency = 'once' 
            AND last_run < %s",
            date('Y-m-d H:i:s', strtotime("-$days days"))
        );
        
        $deleted = $wpdb->query($query);
        
        if ($deleted > 0) {
            $this->logger->info('清理已完成排程', ['count' => $deleted]);
        }
        
        return $deleted;
    }
    
    /**
     * 10.2 修復排程
     */
    public function repair_schedules(): array {
        $repaired = 0;
        $errors = [];
        
        $schedules = $this->get_schedules();
        
        foreach ($schedules as $schedule) {
            try {
                // 檢查下次執行時間
                if ($schedule->status === 'active' && 
                    strtotime($schedule->next_run) < time() - 86400) {
                    
                    // 重新計算下次執行時間
                    $next_run = $this->calculate_next_run($schedule->frequency);
                    
                    $this->db->update('schedules', [
                        'next_run' => $next_run
                    ], ['id' => $schedule->id]);
                    
                    // 重新設定cron
                    $this->schedule_wp_cron($schedule->id, $next_run, $schedule->frequency);
                    
                    $repaired++;
                }
                
                // 檢查WordPress cron
                $hook = 'aisc_run_schedule';
                $timestamp = wp_next_scheduled($hook, [$schedule->id]);
                
                if ($schedule->status === 'active' && !$timestamp) {
                    // 重新設定cron
                    $this->schedule_wp_cron($schedule->id, $schedule->next_run, $schedule->frequency);
                    $repaired++;
                }
                
            } catch (\Exception $e) {
                $errors[] = sprintf('排程 %d 修復失敗: %s', $schedule->id, $e->getMessage());
            }
        }
        
        return [
            'repaired' => $repaired,
            'errors' => $errors
        ];
    }
    
    /**
     * 11. 匯出和匯入
     */
    
    /**
     * 11.1 匯出排程設定
     */
    public function export_schedules(): array {
        $schedules = $this->get_schedules();
        
        $export_data = [];
        
        foreach ($schedules as $schedule) {
            $export_data[] = [
                'name' => $schedule->name,
                'type' => $schedule->type,
                'frequency' => $schedule->frequency,
                'keyword_count' => $schedule->keyword_count,
                'content_settings' => $schedule->content_settings,
                'status' => $schedule->status
            ];
        }
        
        return $export_data;
    }
    
    /**
     * 11.2 匯入排程設定
     */
    public function import_schedules(array $schedules): array {
        $imported = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($schedules as $schedule_data) {
            try {
                $id = $this->create_schedule($schedule_data);
                if ($id) {
                    $imported++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    /**
     * 12. 除錯方法
     */
    
    /**
     * 12.1 取得排程除錯資訊
     */
    public function get_debug_info(): array {
        global $wpdb;
        
        $info = [
            'current_time' => current_time('mysql'),
            'timezone' => get_option('timezone_string', 'Not set'),
            'schedules_paused' => get_option('aisc_schedules_paused', false),
            'active_schedules' => [],
            'wp_cron_events' => [],
            'recent_executions' => []
        ];
        
        // 活動排程資訊
        $active = $this->get_active_schedules();
        foreach ($active as $schedule) {
            $info['active_schedules'][] = [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'next_run' => $schedule->next_run,
                'overdue' => strtotime($schedule->next_run) < time()
            ];
        }
        
        // WordPress cron事件
        $crons = _get_cron_array();
        foreach ($crons as $timestamp => $cron) {
            if (isset($cron['aisc_run_schedule'])) {
                foreach ($cron['aisc_run_schedule'] as $hook) {
                    $info['wp_cron_events'][] = [
                        'timestamp' => $timestamp,
                        'datetime' => date('Y-m-d H:i:s', $timestamp),
                        'args' => $hook['args']
                    ];
                }
            }
        }
        
        // 最近執行記錄
        $table_name = $this->db->get_table_name('schedules');
        $recent = $wpdb->get_results(
            "SELECT id, name, last_run, run_count, success_count, failure_count 
            FROM $table_name 
            WHERE last_run IS NOT NULL 
            ORDER BY last_run DESC 
            LIMIT 10"
        );
        
        $info['recent_executions'] = $recent;
        
        return $info;
    }
}