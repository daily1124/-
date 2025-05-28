<?php
/**
 * 文章排程器 - 增強版
 * 支援自訂發布時間
 */
class AICG_Post_Scheduler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 註冊自定義排程間隔
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        
        // 排程相關的 AJAX 操作
        add_action('wp_ajax_aicg_update_schedule', array($this, 'ajax_update_schedule'));
        add_action('wp_ajax_aicg_run_schedule_now', array($this, 'ajax_run_schedule_now'));
        
        // 檢查是否需要更新排程
        add_action('init', array($this, 'check_schedule_update'));
    }
    
    /**
     * 添加自定義排程間隔
     */
    public function add_custom_schedules($schedules) {
        // 每30分鐘
        $schedules['halfhourly'] = array(
            'interval' => 1800,
            'display' => '每30分鐘'
        );
        
        // 每3小時
        $schedules['threehourly'] = array(
            'interval' => 10800,
            'display' => '每3小時'
        );
        
        // 每6小時
        $schedules['sixhourly'] = array(
            'interval' => 21600,
            'display' => '每6小時'
        );
        
        return $schedules;
    }
    
    /**
     * 檢查並更新排程
     */
    public function check_schedule_update() {
        $frequency = get_option('aicg_post_frequency', 'daily');
        
        if ($frequency === 'custom') {
            // 如果是自訂時間，使用特殊處理
            $this->setup_custom_schedule();
        } else {
            // 使用標準排程
            $this->update_schedule();
        }
    }
    
    /**
     * 設置自訂時間排程
     */
    private function setup_custom_schedule() {
        $publish_time = get_option('aicg_publish_time', '09:00');
        $auto_publish = get_option('aicg_auto_publish', false);
        
        // 清除現有排程
        wp_clear_scheduled_hook('aicg_scheduled_post_generation');
        wp_clear_scheduled_hook('aicg_daily_schedule_check');
        
        if (!$auto_publish) {
            return;
        }
        
        // 設置每日檢查排程（在午夜運行）
        if (!wp_next_scheduled('aicg_daily_schedule_check')) {
            $midnight = strtotime('tomorrow midnight');
            wp_schedule_event($midnight, 'daily', 'aicg_daily_schedule_check');
        }
        
        // 註冊每日檢查動作
        add_action('aicg_daily_schedule_check', array($this, 'schedule_daily_post'));
        
        // 立即檢查今天是否需要發布
        $this->schedule_daily_post();
    }
    
    /**
     * 安排每日文章發布
     */
    public function schedule_daily_post() {
        $publish_time = get_option('aicg_publish_time', '09:00');
        
        // 計算今天的發布時間
        $today_publish_time = strtotime(date('Y-m-d') . ' ' . $publish_time);
        
        // 如果今天的發布時間還沒過，且沒有排程，則設置排程
        if ($today_publish_time > current_time('timestamp') && 
            !wp_next_scheduled('aicg_scheduled_post_generation', array($today_publish_time))) {
            
            wp_schedule_single_event($today_publish_time, 'aicg_scheduled_post_generation');
            error_log('AICG: 已安排今日 ' . $publish_time . ' 發布文章');
        }
    }
    
    /**
     * 更新排程設定
     */
    public function update_schedule() {
        $frequency = get_option('aicg_post_frequency', 'daily');
        $auto_publish = get_option('aicg_auto_publish', false);
        
        // 清除現有排程
        wp_clear_scheduled_hook('aicg_scheduled_post_generation');
        wp_clear_scheduled_hook('aicg_daily_schedule_check');
        
        if ($auto_publish && $frequency !== 'custom') {
            // 設定新排程
            $next_time = time() + 60;
            wp_schedule_event($next_time, $frequency, 'aicg_scheduled_post_generation');
            
            error_log('AICG: 排程已更新 - 頻率: ' . $frequency . ', 下次執行: ' . date('Y-m-d H:i:s', $next_time));
        }
    }
    
    /**
     * 取得下次執行時間
     */
    public function get_next_scheduled_time() {
        $frequency = get_option('aicg_post_frequency', 'daily');
        
        if ($frequency === 'custom') {
            // 自訂時間模式
            $publish_time = get_option('aicg_publish_time', '09:00');
            $today_publish_time = strtotime(date('Y-m-d') . ' ' . $publish_time);
            
            if ($today_publish_time > current_time('timestamp')) {
                // 今天的時間還沒到
                $next_time = $today_publish_time;
            } else {
                // 今天已過，顯示明天的時間
                $next_time = strtotime('tomorrow ' . $publish_time);
            }
            
            return array(
                'timestamp' => $next_time,
                'human_time' => human_time_diff(current_time('timestamp'), $next_time) . ' 後',
                'date_time' => date_i18n('Y-m-d H:i:s', $next_time),
                'mode' => '自訂時間 - 每日 ' . $publish_time
            );
        } else {
            // 標準排程模式
            $timestamp = wp_next_scheduled('aicg_scheduled_post_generation');
            
            if ($timestamp) {
                $mode_text = array(
                    'halfhourly' => '每30分鐘',
                    'hourly' => '每小時',
                    'threehourly' => '每3小時',
                    'sixhourly' => '每6小時',
                    'twicedaily' => '每天兩次',
                    'daily' => '每天一次',
                    'weekly' => '每週一次'
                );
                
                return array(
                    'timestamp' => $timestamp,
                    'human_time' => human_time_diff(current_time('timestamp'), $timestamp) . ' 後',
                    'date_time' => date_i18n('Y-m-d H:i:s', $timestamp),
                    'mode' => isset($mode_text[$frequency]) ? $mode_text[$frequency] : $frequency
                );
            }
        }
        
        return false;
    }
    
    /**
     * AJAX 更新排程
     */
    public function ajax_update_schedule() {
        check_ajax_referer('aicg_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '權限不足'));
            return;
        }
        
        // 根據頻率類型更新排程
        $frequency = get_option('aicg_post_frequency', 'daily');
        if ($frequency === 'custom') {
            $this->setup_custom_schedule();
        } else {
            $this->update_schedule();
        }
        
        wp_send_json_success(array(
            'message' => '排程已更新',
            'next_run' => $this->get_next_scheduled_time()
        ));
    }
    
    /**
     * AJAX 立即執行排程
     */
    public function ajax_run_schedule_now() {
        check_ajax_referer('aicg_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '權限不足'));
            return;
        }
        
        // 執行排程任務
        do_action('aicg_scheduled_post_generation');
        
        wp_send_json_success(array(
            'message' => '排程任務已執行'
        ));
    }
    
    /**
     * 取得排程狀態資訊
     * @return array
     */
    public function get_schedule_status() {
        $auto_publish = get_option('aicg_auto_publish', false);
        $frequency = get_option('aicg_post_frequency', 'daily');
        $posts_per_batch = get_option('aicg_posts_per_batch', 3);
        $next_run = $this->get_next_scheduled_time();
        
        $status = array(
            'enabled' => $auto_publish,
            'frequency' => $frequency,
            'posts_per_batch' => $posts_per_batch,
            'next_run' => $next_run
        );
        
        if ($frequency === 'custom') {
            $status['custom_time'] = get_option('aicg_publish_time', '09:00');
        }
        
        // 檢查所有相關的 cron 任務
        $status['cron_jobs'] = array(
            'aicg_scheduled_post_generation' => wp_next_scheduled('aicg_scheduled_post_generation'),
            'aicg_daily_schedule_check' => wp_next_scheduled('aicg_daily_schedule_check')
        );
        
        return $status;
    }
    
    /**
     * 顯示排程狀態
     */
    public function display_schedule_status() {
        $status = $this->get_schedule_status();
        ?>
        <div class="aicg-schedule-status">
            <h3>排程狀態</h3>
            <table class="widefat">
                <tr>
                    <th>自動發布</th>
                    <td><?php echo $status['enabled'] ? '啟用' : '停用'; ?></td>
                </tr>
                <tr>
                    <th>發布頻率</th>
                    <td><?php echo $status['frequency']; ?></td>
                </tr>
                <tr>
                    <th>每次發布文章數</th>
                    <td><?php echo $status['posts_per_batch']; ?> 篇</td>
                </tr>
                <?php if ($status['frequency'] === 'custom'): ?>
                <tr>
                    <th>自訂發布時間</th>
                    <td><?php echo $status['custom_time']; ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($status['next_run']): ?>
                <tr>
                    <th>下次執行時間</th>
                    <td>
                        <?php echo $status['next_run']['date_time']; ?> 
                        <small>(<?php echo $status['next_run']['human_time']; ?>)</small>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }
}