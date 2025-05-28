<?php
/**
 * 文章排程器 - 增強版（支援台灣和娛樂城分開排程）
 * 支援自訂發布時間和分開的關鍵字類型
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
        
        // 註冊排程動作
        add_action('aicg_scheduled_taiwan_post_generation', array($this, 'generate_scheduled_taiwan_post'));
        add_action('aicg_scheduled_casino_post_generation', array($this, 'generate_scheduled_casino_post'));
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
        // 分別處理台灣和娛樂城的排程
        $this->update_taiwan_schedule();
        $this->update_casino_schedule();
    }
    
    /**
     * 更新台灣關鍵字排程
     */
    private function update_taiwan_schedule() {
        $auto_publish = get_option('aicg_auto_publish_taiwan', false);
        $frequency = get_option('aicg_post_frequency_taiwan', 'daily');
        
        // 清除現有排程
        wp_clear_scheduled_hook('aicg_scheduled_taiwan_post_generation');
        wp_clear_scheduled_hook('aicg_daily_taiwan_schedule_check');
        
        if (!$auto_publish) {
            return;
        }
        
        if ($frequency === 'custom') {
            // 自訂時間模式
            $this->setup_custom_schedule('taiwan');
        } else {
            // 標準排程模式
            $next_time = time() + 60;
            wp_schedule_event($next_time, $frequency, 'aicg_scheduled_taiwan_post_generation');
            
            error_log('AICG: 台灣關鍵字排程已更新 - 頻率: ' . $frequency);
        }
    }
    
    /**
     * 更新娛樂城關鍵字排程
     */
    private function update_casino_schedule() {
        $auto_publish = get_option('aicg_auto_publish_casino', false);
        $frequency = get_option('aicg_post_frequency_casino', 'daily');
        
        // 清除現有排程
        wp_clear_scheduled_hook('aicg_scheduled_casino_post_generation');
        wp_clear_scheduled_hook('aicg_daily_casino_schedule_check');
        
        if (!$auto_publish) {
            return;
        }
        
        if ($frequency === 'custom') {
            // 自訂時間模式
            $this->setup_custom_schedule('casino');
        } else {
            // 標準排程模式
            $next_time = time() + 120; // 錯開2分鐘避免同時執行
            wp_schedule_event($next_time, $frequency, 'aicg_scheduled_casino_post_generation');
            
            error_log('AICG: 娛樂城關鍵字排程已更新 - 頻率: ' . $frequency);
        }
    }
    
    /**
     * 設置自訂時間排程
     * @param string $type 關鍵字類型 (taiwan 或 casino)
     */
    private function setup_custom_schedule($type) {
        $publish_time = get_option('aicg_publish_time_' . $type, '09:00');
        
        // 設置每日檢查排程（在午夜運行）
        if (!wp_next_scheduled('aicg_daily_' . $type . '_schedule_check')) {
            $midnight = strtotime('tomorrow midnight');
            wp_schedule_event($midnight, 'daily', 'aicg_daily_' . $type . '_schedule_check');
        }
        
        // 註冊每日檢查動作
        add_action('aicg_daily_' . $type . '_schedule_check', array($this, 'schedule_daily_' . $type . '_post'));
        
        // 立即檢查今天是否需要發布
        $this->{'schedule_daily_' . $type . '_post'}();
    }
    
    /**
     * 安排每日台灣關鍵字文章發布
     */
    public function schedule_daily_taiwan_post() {
        $publish_time = get_option('aicg_publish_time_taiwan', '09:00');
        
        // 計算今天的發布時間
        $today_publish_time = strtotime(date('Y-m-d') . ' ' . $publish_time);
        
        // 如果今天的發布時間還沒過，且沒有排程，則設置排程
        if ($today_publish_time > current_time('timestamp') && 
            !wp_next_scheduled('aicg_scheduled_taiwan_post_generation', array($today_publish_time))) {
            
            wp_schedule_single_event($today_publish_time, 'aicg_scheduled_taiwan_post_generation');
            error_log('AICG: 已安排今日 ' . $publish_time . ' 發布台灣關鍵字文章');
        }
    }
    
    /**
     * 安排每日娛樂城關鍵字文章發布
     */
    public function schedule_daily_casino_post() {
        $publish_time = get_option('aicg_publish_time_casino', '09:00');
        
        // 計算今天的發布時間
        $today_publish_time = strtotime(date('Y-m-d') . ' ' . $publish_time);
        
        // 如果今天的發布時間還沒過，且沒有排程，則設置排程
        if ($today_publish_time > current_time('timestamp') && 
            !wp_next_scheduled('aicg_scheduled_casino_post_generation', array($today_publish_time))) {
            
            wp_schedule_single_event($today_publish_time, 'aicg_scheduled_casino_post_generation');
            error_log('AICG: 已安排今日 ' . $publish_time . ' 發布娛樂城關鍵字文章');
        }
    }
    
    /**
     * 生成排程的台灣關鍵字文章
     */
    public function generate_scheduled_taiwan_post() {
        $auto_publish = get_option('aicg_auto_publish_taiwan', false);
        
        if (!$auto_publish) {
            error_log('AICG: 台灣關鍵字自動發布未啟用');
            return;
        }
        
        $posts_per_batch = get_option('aicg_posts_per_batch_taiwan', 3);
        $generator = AICG_Content_Generator::get_instance();
        
        error_log('AICG: 開始排程生成 ' . $posts_per_batch . ' 篇台灣關鍵字文章');
        
        for ($i = 0; $i < $posts_per_batch; $i++) {
            $result = $generator->generate_single_post('taiwan');
            
            if ($result['success']) {
                error_log('AICG: 成功生成台灣關鍵字文章 #' . ($i + 1));
            } else {
                error_log('AICG: 生成台灣關鍵字文章 #' . ($i + 1) . ' 失敗: ' . $result['message']);
            }
            
            // 避免API限制，休息10秒
            if ($i < $posts_per_batch - 1) {
                sleep(10);
            }
        }
        
        error_log('AICG: 台灣關鍵字排程生成完成');
    }
    
    /**
     * 生成排程的娛樂城關鍵字文章
     */
    public function generate_scheduled_casino_post() {
        $auto_publish = get_option('aicg_auto_publish_casino', false);
        
        if (!$auto_publish) {
            error_log('AICG: 娛樂城關鍵字自動發布未啟用');
            return;
        }
        
        $posts_per_batch = get_option('aicg_posts_per_batch_casino', 3);
        $generator = AICG_Content_Generator::get_instance();
        
        error_log('AICG: 開始排程生成 ' . $posts_per_batch . ' 篇娛樂城關鍵字文章');
        
        for ($i = 0; $i < $posts_per_batch; $i++) {
            $result = $generator->generate_single_post('casino');
            
            if ($result['success']) {
                error_log('AICG: 成功生成娛樂城關鍵字文章 #' . ($i + 1));
            } else {
                error_log('AICG: 生成娛樂城關鍵字文章 #' . ($i + 1) . ' 失敗: ' . $result['message']);
            }
            
            // 避免API限制，休息10秒
            if ($i < $posts_per_batch - 1) {
                sleep(10);
            }
        }
        
        error_log('AICG: 娛樂城關鍵字排程生成完成');
    }
    
    /**
     * 取得下次執行時間
     * @param string $type 關鍵字類型 (taiwan, casino, 或 all)
     * @return array|false
     */
    public function get_next_scheduled_time($type = 'all') {
        if ($type === 'all') {
            // 返回兩種類型的排程資訊
            return array(
                'taiwan' => $this->get_next_scheduled_time('taiwan'),
                'casino' => $this->get_next_scheduled_time('casino')
            );
        }
        
        $frequency = get_option('aicg_post_frequency_' . $type, 'daily');
        $auto_publish = get_option('aicg_auto_publish_' . $type, false);
        
        if (!$auto_publish) {
            return false;
        }
        
        if ($frequency === 'custom') {
            // 自訂時間模式
            $publish_time = get_option('aicg_publish_time_' . $type, '09:00');
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
            $timestamp = wp_next_scheduled('aicg_scheduled_' . $type . '_post_generation');
            
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
        
        // 更新兩種類型的排程
        $this->update_taiwan_schedule();
        $this->update_casino_schedule();
        
        wp_send_json_success(array(
            'message' => '排程已更新',
            'next_run' => $this->get_next_scheduled_time('all')
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
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        if ($type === 'taiwan' || $type === 'all') {
            do_action('aicg_scheduled_taiwan_post_generation');
        }
        
        if ($type === 'casino' || $type === 'all') {
            do_action('aicg_scheduled_casino_post_generation');
        }
        
        wp_send_json_success(array(
            'message' => '排程任務已執行'
        ));
    }
    
    /**
     * 取得排程狀態資訊
     * @return array
     */
    public function get_schedule_status() {
        return array(
            'taiwan' => array(
                'enabled' => get_option('aicg_auto_publish_taiwan', false),
                'frequency' => get_option('aicg_post_frequency_taiwan', 'daily'),
                'posts_per_batch' => get_option('aicg_posts_per_batch_taiwan', 3),
                'custom_time' => get_option('aicg_publish_time_taiwan', '09:00'),
                'next_run' => $this->get_next_scheduled_time('taiwan')
            ),
            'casino' => array(
                'enabled' => get_option('aicg_auto_publish_casino', false),
                'frequency' => get_option('aicg_post_frequency_casino', 'daily'),
                'posts_per_batch' => get_option('aicg_posts_per_batch_casino', 3),
                'custom_time' => get_option('aicg_publish_time_casino', '09:00'),
                'next_run' => $this->get_next_scheduled_time('casino')
            )
        );
    }
    
    /**
     * 顯示排程狀態
     */
    public function display_schedule_status() {
        $status = $this->get_schedule_status();
        ?>
        <div class="aicg-schedule-status">
            <h3>排程狀態</h3>
            
            <!-- 台灣關鍵字排程 -->
            <div style="margin-bottom: 20px;">
                <h4>台灣熱門關鍵字</h4>
                <table class="widefat">
                    <tr>
                        <th>自動發布</th>
                        <td><?php echo $status['taiwan']['enabled'] ? '<span style="color: green;">啟用</span>' : '<span style="color: red;">停用</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>發布頻率</th>
                        <td><?php echo $status['taiwan']['frequency']; ?></td>
                    </tr>
                    <tr>
                        <th>每次發布文章數</th>
                        <td><?php echo $status['taiwan']['posts_per_batch']; ?> 篇</td>
                    </tr>
                    <?php if ($status['taiwan']['frequency'] === 'custom'): ?>
                    <tr>
                        <th>自訂發布時間</th>
                        <td><?php echo $status['taiwan']['custom_time']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($status['taiwan']['next_run']): ?>
                    <tr>
                        <th>下次執行時間</th>
                        <td>
                            <?php echo $status['taiwan']['next_run']['date_time']; ?> 
                            <small>(<?php echo $status['taiwan']['next_run']['human_time']; ?>)</small>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- 娛樂城關鍵字排程 -->
            <div>
                <h4>娛樂城關鍵字</h4>
                <table class="widefat">
                    <tr>
                        <th>自動發布</th>
                        <td><?php echo $status['casino']['enabled'] ? '<span style="color: green;">啟用</span>' : '<span style="color: red;">停用</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>發布頻率</th>
                        <td><?php echo $status['casino']['frequency']; ?></td>
                    </tr>
                    <tr>
                        <th>每次發布文章數</th>
                        <td><?php echo $status['casino']['posts_per_batch']; ?> 篇</td>
                    </tr>
                    <?php if ($status['casino']['frequency'] === 'custom'): ?>
                    <tr>
                        <th>自訂發布時間</th>
                        <td><?php echo $status['casino']['custom_time']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($status['casino']['next_run']): ?>
                    <tr>
                        <th>下次執行時間</th>
                        <td>
                            <?php echo $status['casino']['next_run']['date_time']; ?> 
                            <small>(<?php echo $status['casino']['next_run']['human_time']; ?>)</small>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div style="margin-top: 15px;">
                <button id="aicg-update-schedule" class="button">更新排程</button>
                <button id="aicg-run-taiwan-now" class="button" data-type="taiwan">立即執行台灣關鍵字</button>
                <button id="aicg-run-casino-now" class="button" data-type="casino">立即執行娛樂城關鍵字</button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#aicg-run-taiwan-now, #aicg-run-casino-now').on('click', function() {
                if (!confirm('確定要立即執行排程任務嗎？')) {
                    return;
                }
                
                var button = $(this);
                var type = button.data('type');
                var originalText = button.text();
                button.text('執行中...').prop('disabled', true);
                
                $.post(aicg_ajax.ajax_url, {
                    action: 'aicg_run_schedule_now',
                    type: type,
                    nonce: aicg_ajax.nonce
                }, function(response) {
                    button.text(originalText).prop('disabled', false);
                    
                    if (response.success) {
                        alert(response.data.message || '排程任務已執行');
                    } else {
                        alert('錯誤: ' + (response.data.message || '執行失敗'));
                    }
                });
            });
        });
        </script>
        <?php
    }
}