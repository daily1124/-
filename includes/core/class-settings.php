<?php
/**
 * 檔案：includes/core/class-settings.php
 * 功能：設定管理核心類別
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Core
 */

namespace AISC\Core;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 設定管理類別
 * 
 * 統一管理外掛的所有設定選項
 */
class Settings {
    
    /**
     * 設定前綴
     */
    private const PREFIX = 'aisc_';
    
    /**
     * 設定分組
     */
    private array $setting_groups = [];
    
    /**
     * 快取的設定值
     */
    private array $cached_settings = [];
    
    /**
     * 預設設定
     */
    private array $defaults = [];
    
    /**
     * 日誌實例
     */
    private ?Logger $logger = null;
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->init_setting_groups();
        $this->init_defaults();
        $this->load_settings();
        
        // 註冊設定
        add_action('admin_init', [$this, 'register_settings']);
        
        // 設定變更掛鉤
        add_action('update_option', [$this, 'on_option_update'], 10, 3);
    }
    
    /**
     * 1. 初始化設定
     */
    
    /**
     * 1.1 初始化設定分組
     */
    private function init_setting_groups(): void {
        $this->setting_groups = [
            'general' => [
                'title' => __('一般設定', 'ai-seo-content-generator'),
                'priority' => 10,
                'fields' => [
                    'openai_api_key' => [
                        'type' => 'password',
                        'label' => __('OpenAI API金鑰', 'ai-seo-content-generator'),
                        'description' => __('輸入您的OpenAI API金鑰', 'ai-seo-content-generator'),
                        'required' => true,
                        'sanitize' => 'sanitize_text_field'
                    ],
                    'gpt_model' => [
                        'type' => 'select',
                        'label' => __('預設GPT模型', 'ai-seo-content-generator'),
                        'options' => [
                            'gpt-4' => 'GPT-4 (最高品質)',
                            'gpt-4-turbo-preview' => 'GPT-4 Turbo (推薦)',
                            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (經濟)'
                        ],
                        'default' => 'gpt-4-turbo-preview',
                        'description' => __('選擇預設使用的語言模型', 'ai-seo-content-generator')
                    ],
                    'timezone' => [
                        'type' => 'select',
                        'label' => __('時區設定', 'ai-seo-content-generator'),
                        'options' => $this->get_timezone_options(),
                        'default' => 'Asia/Taipei',
                        'description' => __('設定外掛使用的時區', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'content' => [
                'title' => __('內容生成設定', 'ai-seo-content-generator'),
                'priority' => 20,
                'fields' => [
                    'content_length' => [
                        'type' => 'number',
                        'label' => __('預設文章長度', 'ai-seo-content-generator'),
                        'default' => 8000,
                        'min' => 1000,
                        'max' => 20000,
                        'step' => 500,
                        'description' => __('設定生成文章的預設字數', 'ai-seo-content-generator')
                    ],
                    'images_per_article' => [
                        'type' => 'number',
                        'label' => __('每篇文章圖片數', 'ai-seo-content-generator'),
                        'default' => 3,
                        'min' => 0,
                        'max' => 10,
                        'description' => __('設定每篇文章插入的圖片數量', 'ai-seo-content-generator')
                    ],
                    'content_language' => [
                        'type' => 'select',
                        'label' => __('內容語言', 'ai-seo-content-generator'),
                        'options' => [
                            'zh-TW' => '繁體中文',
                            'zh-CN' => '簡體中文',
                            'en-US' => 'English'
                        ],
                        'default' => 'zh-TW',
                        'description' => __('設定生成內容的語言', 'ai-seo-content-generator')
                    ],
                    'content_tone' => [
                        'type' => 'select',
                        'label' => __('內容語氣', 'ai-seo-content-generator'),
                        'options' => [
                            'professional' => '專業',
                            'casual' => '輕鬆',
                            'friendly' => '友善',
                            'authoritative' => '權威'
                        ],
                        'default' => 'professional',
                        'description' => __('設定生成內容的語氣風格', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'seo' => [
                'title' => __('SEO優化設定', 'ai-seo-content-generator'),
                'priority' => 30,
                'fields' => [
                    'keyword_density' => [
                        'type' => 'range',
                        'label' => __('關鍵字密度', 'ai-seo-content-generator'),
                        'default' => 1.5,
                        'min' => 0.5,
                        'max' => 3.0,
                        'step' => 0.1,
                        'unit' => '%',
                        'description' => __('設定目標關鍵字密度', 'ai-seo-content-generator')
                    ],
                    'auto_internal_links' => [
                        'type' => 'checkbox',
                        'label' => __('自動內部連結', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('自動為相關內容添加內部連結', 'ai-seo-content-generator')
                    ],
                    'generate_faq' => [
                        'type' => 'checkbox',
                        'label' => __('生成FAQ段落', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('在文章末尾自動生成FAQ常見問題', 'ai-seo-content-generator')
                    ],
                    'faq_count' => [
                        'type' => 'number',
                        'label' => __('FAQ問題數量', 'ai-seo-content-generator'),
                        'default' => 10,
                        'min' => 5,
                        'max' => 20,
                        'description' => __('設定生成的FAQ問題數量', 'ai-seo-content-generator'),
                        'dependency' => ['generate_faq' => true]
                    ],
                    'auto_meta_description' => [
                        'type' => 'checkbox',
                        'label' => __('自動生成Meta描述', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('自動生成SEO優化的Meta描述', 'ai-seo-content-generator')
                    ],
                    'schema_markup' => [
                        'type' => 'checkbox',
                        'label' => __('結構化資料標記', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('自動添加Schema.org結構化資料', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'keywords' => [
                'title' => __('關鍵字設定', 'ai-seo-content-generator'),
                'priority' => 40,
                'fields' => [
                    'keyword_source' => [
                        'type' => 'multiselect',
                        'label' => __('關鍵字來源', 'ai-seo-content-generator'),
                        'options' => [
                            'google_trends' => 'Google Trends',
                            'google_suggest' => 'Google搜尋建議',
                            'manual' => '手動輸入'
                        ],
                        'default' => ['google_trends', 'google_suggest'],
                        'description' => __('選擇關鍵字抓取來源', 'ai-seo-content-generator')
                    ],
                    'keyword_update_frequency' => [
                        'type' => 'select',
                        'label' => __('關鍵字更新頻率', 'ai-seo-content-generator'),
                        'options' => [
                            'hourly' => '每小時',
                            'daily' => '每天',
                            'weekly' => '每週',
                            'manual' => '手動'
                        ],
                        'default' => 'daily',
                        'description' => __('設定自動更新關鍵字的頻率', 'ai-seo-content-generator')
                    ],
                    'keyword_limit_general' => [
                        'type' => 'number',
                        'label' => __('一般關鍵字數量', 'ai-seo-content-generator'),
                        'default' => 30,
                        'min' => 10,
                        'max' => 100,
                        'description' => __('設定抓取的一般關鍵字數量', 'ai-seo-content-generator')
                    ],
                    'keyword_limit_casino' => [
                        'type' => 'number',
                        'label' => __('娛樂城關鍵字數量', 'ai-seo-content-generator'),
                        'default' => 30,
                        'min' => 10,
                        'max' => 100,
                        'description' => __('設定抓取的娛樂城相關關鍵字數量', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'scheduler' => [
                'title' => __('排程設定', 'ai-seo-content-generator'),
                'priority' => 50,
                'fields' => [
                    'enable_scheduler' => [
                        'type' => 'checkbox',
                        'label' => __('啟用自動排程', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('啟用自動內容生成排程功能', 'ai-seo-content-generator')
                    ],
                    'default_schedule_time' => [
                        'type' => 'time',
                        'label' => __('預設發布時間', 'ai-seo-content-generator'),
                        'default' => '09:00',
                        'description' => __('設定排程文章的預設發布時間', 'ai-seo-content-generator')
                    ],
                    'max_concurrent_generation' => [
                        'type' => 'number',
                        'label' => __('同時生成數量', 'ai-seo-content-generator'),
                        'default' => 3,
                        'min' => 1,
                        'max' => 10,
                        'description' => __('設定同時生成文章的最大數量', 'ai-seo-content-generator')
                    ],
                    'execution_window_start' => [
                        'type' => 'number',
                        'label' => __('執行時段開始', 'ai-seo-content-generator'),
                        'default' => 6,
                        'min' => 0,
                        'max' => 23,
                        'unit' => '時',
                        'description' => __('設定排程執行的開始時間（24小時制）', 'ai-seo-content-generator')
                    ],
                    'execution_window_end' => [
                        'type' => 'number',
                        'label' => __('執行時段結束', 'ai-seo-content-generator'),
                        'default' => 22,
                        'min' => 0,
                        'max' => 23,
                        'unit' => '時',
                        'description' => __('設定排程執行的結束時間（24小時制）', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'costs' => [
                'title' => __('成本控制設定', 'ai-seo-content-generator'),
                'priority' => 60,
                'fields' => [
                    'daily_budget' => [
                        'type' => 'number',
                        'label' => __('每日預算', 'ai-seo-content-generator'),
                        'default' => 1000,
                        'min' => 0,
                        'step' => 100,
                        'unit' => 'NT$',
                        'description' => __('設定每日API使用預算（0表示無限制）', 'ai-seo-content-generator')
                    ],
                    'monthly_budget' => [
                        'type' => 'number',
                        'label' => __('每月預算', 'ai-seo-content-generator'),
                        'default' => 20000,
                        'min' => 0,
                        'step' => 1000,
                        'unit' => 'NT$',
                        'description' => __('設定每月API使用預算（0表示無限制）', 'ai-seo-content-generator')
                    ],
                    'budget_warning_threshold' => [
                        'type' => 'range',
                        'label' => __('預算警告門檻', 'ai-seo-content-generator'),
                        'default' => 80,
                        'min' => 50,
                        'max' => 95,
                        'step' => 5,
                        'unit' => '%',
                        'description' => __('當預算使用達到此比例時發送警告', 'ai-seo-content-generator')
                    ],
                    'cost_notification_email' => [
                        'type' => 'email',
                        'label' => __('成本通知信箱', 'ai-seo-content-generator'),
                        'default' => get_option('admin_email'),
                        'description' => __('接收成本相關通知的電子郵件', 'ai-seo-content-generator')
                    ],
                    'pause_on_budget_exceed' => [
                        'type' => 'checkbox',
                        'label' => __('超預算自動暫停', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('當超出預算時自動暫停所有自動功能', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'performance' => [
                'title' => __('效能追蹤設定', 'ai-seo-content-generator'),
                'priority' => 70,
                'fields' => [
                    'enable_performance_tracking' => [
                        'type' => 'checkbox',
                        'label' => __('啟用效能追蹤', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('追蹤AI生成文章的瀏覽和互動數據', 'ai-seo-content-generator')
                    ],
                    'ga_measurement_id' => [
                        'type' => 'text',
                        'label' => __('GA Measurement ID', 'ai-seo-content-generator'),
                        'placeholder' => 'G-XXXXXXXXXX',
                        'description' => __('Google Analytics 4 Measurement ID', 'ai-seo-content-generator')
                    ],
                    'ga_property_id' => [
                        'type' => 'text',
                        'label' => __('GA Property ID', 'ai-seo-content-generator'),
                        'placeholder' => '123456789',
                        'description' => __('Google Analytics Property ID（用於API）', 'ai-seo-content-generator')
                    ],
                    'performance_retention' => [
                        'type' => 'number',
                        'label' => __('數據保留天數', 'ai-seo-content-generator'),
                        'default' => 90,
                        'min' => 30,
                        'max' => 365,
                        'unit' => '天',
                        'description' => __('效能數據保留的天數', 'ai-seo-content-generator')
                    ],
                    'track_anonymous' => [
                        'type' => 'checkbox',
                        'label' => __('匿名追蹤', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('不收集可識別的使用者資訊', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'advanced' => [
                'title' => __('進階設定', 'ai-seo-content-generator'),
                'priority' => 80,
                'fields' => [
                    'log_level' => [
                        'type' => 'select',
                        'label' => __('日誌級別', 'ai-seo-content-generator'),
                        'options' => [
                            'debug' => 'Debug',
                            'info' => 'Info',
                            'warning' => 'Warning',
                            'error' => 'Error',
                            'critical' => 'Critical'
                        ],
                        'default' => 'info',
                        'description' => __('設定日誌記錄的詳細程度', 'ai-seo-content-generator')
                    ],
                    'log_retention' => [
                        'type' => 'number',
                        'label' => __('日誌保留天數', 'ai-seo-content-generator'),
                        'default' => 30,
                        'min' => 7,
                        'max' => 90,
                        'unit' => '天',
                        'description' => __('日誌檔案保留的天數', 'ai-seo-content-generator')
                    ],
                    'log_max_size' => [
                        'type' => 'number',
                        'label' => __('日誌檔案大小限制', 'ai-seo-content-generator'),
                        'default' => 10,
                        'min' => 1,
                        'max' => 50,
                        'unit' => 'MB',
                        'description' => __('單一日誌檔案的最大大小', 'ai-seo-content-generator')
                    ],
                    'enable_debug_mode' => [
                        'type' => 'checkbox',
                        'label' => __('除錯模式', 'ai-seo-content-generator'),
                        'default' => false,
                        'description' => __('啟用除錯模式以顯示詳細錯誤訊息', 'ai-seo-content-generator')
                    ],
                    'api_timeout' => [
                        'type' => 'number',
                        'label' => __('API逾時時間', 'ai-seo-content-generator'),
                        'default' => 60,
                        'min' => 30,
                        'max' => 300,
                        'unit' => '秒',
                        'description' => __('API請求的逾時時間', 'ai-seo-content-generator')
                    ],
                    'retry_attempts' => [
                        'type' => 'number',
                        'label' => __('重試次數', 'ai-seo-content-generator'),
                        'default' => 3,
                        'min' => 0,
                        'max' => 5,
                        'description' => __('API請求失敗時的重試次數', 'ai-seo-content-generator')
                    ],
                    'cache_duration' => [
                        'type' => 'number',
                        'label' => __('快取時間', 'ai-seo-content-generator'),
                        'default' => 3600,
                        'min' => 300,
                        'max' => 86400,
                        'unit' => '秒',
                        'description' => __('API回應快取的持續時間', 'ai-seo-content-generator')
                    ]
                ]
            ],
            
            'categories' => [
                'title' => __('分類設定', 'ai-seo-content-generator'),
                'priority' => 90,
                'fields' => [
                    'categories' => [
                        'type' => 'category_manager',
                        'label' => __('管理分類', 'ai-seo-content-generator'),
                        'description' => __('管理AI內容使用的分類', 'ai-seo-content-generator'),
                        'default' => [
                            '所有文章', '娛樂城教學', '虛擬貨幣', '體育', '科技', 
                            '健康', '新聞', '明星', '汽車', '理財', 
                            '生活', '社會', '美食', '追劇'
                        ]
                    ],
                    'auto_categorize' => [
                        'type' => 'checkbox',
                        'label' => __('自動分類', 'ai-seo-content-generator'),
                        'default' => true,
                        'description' => __('根據內容自動分配文章分類', 'ai-seo-content-generator')
                    ],
                    'max_categories_per_post' => [
                        'type' => 'number',
                        'label' => __('每篇文章最大分類數', 'ai-seo-content-generator'),
                        'default' => 3,
                        'min' => 1,
                        'max' => 5,
                        'description' => __('限制每篇文章的分類數量', 'ai-seo-content-generator')
                    ]
                ]
            ]
        ];
    }
    
    /**
     * 1.2 初始化預設值
     */
    private function init_defaults(): void {
        foreach ($this->setting_groups as $group_key => $group) {
            foreach ($group['fields'] as $field_key => $field) {
                if (isset($field['default'])) {
                    $this->defaults[$field_key] = $field['default'];
                }
            }
        }
    }
    
    /**
     * 1.3 載入設定
     */
    private function load_settings(): void {
        foreach ($this->defaults as $key => $default) {
            $this->cached_settings[$key] = get_option(self::PREFIX . $key, $default);
        }
    }
    
    /**
     * 2. 設定存取方法
     */
    
    /**
     * 2.1 取得設定值
     */
    public function get(string $key, $default = null) {
        // 檢查快取
        if (isset($this->cached_settings[$key])) {
            return $this->cached_settings[$key];
        }
        
        // 從資料庫取得
        $value = get_option(self::PREFIX . $key, $default ?? $this->defaults[$key] ?? null);
        
        // 更新快取
        $this->cached_settings[$key] = $value;
        
        return $value;
    }
    
    /**
     * 2.2 設定值
     */
    public function set(string $key, $value): bool {
        // 驗證和清理
        $value = $this->sanitize_field($key, $value);
        
        // 驗證是否通過
        if (!$this->validate_field($key, $value)) {
            return false;
        }
        
        // 更新選項
        $result = update_option(self::PREFIX . $key, $value);
        
        if ($result) {
            // 更新快取
            $this->cached_settings[$key] = $value;
            
            // 觸發變更事件
            do_action('aisc_setting_changed', $key, $value);
        }
        
        return $result;
    }
    
    /**
     * 2.3 批量取得設定
     */
    public function get_all(): array {
        return $this->cached_settings;
    }
    
    /**
     * 2.4 批量設定
     */
    public function set_multiple(array $settings): array {
        $results = [];
        
        foreach ($settings as $key => $value) {
            $results[$key] = $this->set($key, $value);
        }
        
        return $results;
    }
    
    /**
     * 2.5 重設為預設值
     */
    public function reset(string $key): bool {
        if (!isset($this->defaults[$key])) {
            return false;
        }
        
        return $this->set($key, $this->defaults[$key]);
    }
    
    /**
     * 2.6 重設所有設定
     */
    public function reset_all(): void {
        foreach ($this->defaults as $key => $default) {
            $this->set($key, $default);
        }
    }
    
    /**
     * 3. WordPress設定API整合
     */
    
    /**
     * 3.1 註冊設定
     */
    public function register_settings(): void {
        foreach ($this->setting_groups as $group_key => $group) {
            // 註冊設定區段
            add_settings_section(
                'aisc_' . $group_key,
                $group['title'],
                function() use ($group) {
                    if (isset($group['description'])) {
                        echo '<p>' . esc_html($group['description']) . '</p>';
                    }
                },
                'aisc_settings'
            );
            
            // 註冊設定欄位
            foreach ($group['fields'] as $field_key => $field) {
                add_settings_field(
                    self::PREFIX . $field_key,
                    $field['label'],
                    [$this, 'render_field'],
                    'aisc_settings',
                    'aisc_' . $group_key,
                    [
                        'key' => $field_key,
                        'field' => $field
                    ]
                );
                
                // 註冊設定
                register_setting(
                    'aisc_settings_group',
                    self::PREFIX . $field_key,
                    [
                        'sanitize_callback' => function($value) use ($field_key) {
                            return $this->sanitize_field($field_key, $value);
                        }
                    ]
                );
            }
        }
    }
    
    /**
     * 3.2 渲染欄位
     */
    public function render_field(array $args): void {
        $key = $args['key'];
        $field = $args['field'];
        $value = $this->get($key);
        $name = self::PREFIX . $key;
        $id = 'field-' . $key;
        
        switch ($field['type']) {
            case 'text':
                $this->render_text_field($name, $id, $value, $field);
                break;
                
            case 'password':
                $this->render_password_field($name, $id, $value, $field);
                break;
                
            case 'number':
                $this->render_number_field($name, $id, $value, $field);
                break;
                
            case 'select':
                $this->render_select_field($name, $id, $value, $field);
                break;
                
            case 'multiselect':
                $this->render_multiselect_field($name, $id, $value, $field);
                break;
                
            case 'checkbox':
                $this->render_checkbox_field($name, $id, $value, $field);
                break;
                
            case 'range':
                $this->render_range_field($name, $id, $value, $field);
                break;
                
            case 'time':
                $this->render_time_field($name, $id, $value, $field);
                break;
                
            case 'email':
                $this->render_email_field($name, $id, $value, $field);
                break;
                
            case 'category_manager':
                $this->render_category_manager($name, $id, $value, $field);
                break;
                
            default:
                do_action('aisc_render_custom_field_' . $field['type'], $name, $id, $value, $field);
        }
        
        // 描述文字
        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
        
        // 相依性
        if (!empty($field['dependency'])) {
            $this->render_field_dependency($id, $field['dependency']);
        }
    }
    
    /**
     * 4. 欄位渲染方法
     */
    
    /**
     * 4.1 文字欄位
     */
    private function render_text_field(string $name, string $id, $value, array $field): void {
        ?>
        <input type="text" 
               name="<?php echo esc_attr($name); ?>" 
               id="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>
               <?php echo !empty($field['required']) ? 'required' : ''; ?> />
        <?php
    }
    
    /**
     * 4.2 密碼欄位
     */
    private function render_password_field(string $name, string $id, $value, array $field): void {
        ?>
        <input type="password" 
               name="<?php echo esc_attr($name); ?>" 
               id="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               <?php echo !empty($field['required']) ? 'required' : ''; ?> />
        <button type="button" class="button" onclick="togglePasswordVisibility('<?php echo esc_attr($id); ?>')">
            <span class="dashicons dashicons-visibility"></span>
        </button>
        <?php
    }
    
    /**
     * 4.3 數字欄位
     */
    private function render_number_field(string $name, string $id, $value, array $field): void {
        ?>
        <input type="number" 
               name="<?php echo esc_attr($name); ?>" 
               id="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="small-text"
               <?php echo isset($field['min']) ? 'min="' . esc_attr($field['min']) . '"' : ''; ?>
               <?php echo isset($field['max']) ? 'max="' . esc_attr($field['max']) . '"' : ''; ?>
               <?php echo isset($field['step']) ? 'step="' . esc_attr($field['step']) . '"' : ''; ?> />
        <?php if (!empty($field['unit'])): ?>
            <span class="unit"><?php echo esc_html($field['unit']); ?></span>
        <?php endif; ?>
        <?php
    }
    
    /**
     * 4.4 選擇欄位
     */
    private function render_select_field(string $name, string $id, $value, array $field): void {
        ?>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>">
            <?php foreach ($field['options'] as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" 
                        <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * 4.5 多選欄位
     */
    private function render_multiselect_field(string $name, string $id, $value, array $field): void {
        if (!is_array($value)) {
            $value = [];
        }
        ?>
        <select name="<?php echo esc_attr($name); ?>[]" 
                id="<?php echo esc_attr($id); ?>" 
                multiple="multiple" 
                class="aisc-multiselect">
            <?php foreach ($field['options'] as $option_value => $option_label): ?>
                <option value="<?php echo esc_attr($option_value); ?>" 
                        <?php echo in_array($option_value, $value) ? 'selected' : ''; ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * 4.6 核取方塊
     */
    private function render_checkbox_field(string $name, string $id, $value, array $field): void {
        ?>
        <label for="<?php echo esc_attr($id); ?>">
            <input type="checkbox" 
                   name="<?php echo esc_attr($name); ?>" 
                   id="<?php echo esc_attr($id); ?>" 
                   value="1" 
                   <?php checked($value, true); ?> />
            <?php if (!empty($field['label_after'])): ?>
                <?php echo esc_html($field['label_after']); ?>
            <?php endif; ?>
        </label>
        <?php
    }
    
    /**
     * 4.7 範圍滑桿
     */
    private function render_range_field(string $name, string $id, $value, array $field): void {
        ?>
        <div class="aisc-range-field">
            <input type="range" 
                   name="<?php echo esc_attr($name); ?>" 
                   id="<?php echo esc_attr($id); ?>" 
                   value="<?php echo esc_attr($value); ?>"
                   min="<?php echo esc_attr($field['min'] ?? 0); ?>"
                   max="<?php echo esc_attr($field['max'] ?? 100); ?>"
                   step="<?php echo esc_attr($field['step'] ?? 1); ?>"
                   oninput="document.getElementById('<?php echo esc_attr($id); ?>-value').textContent = this.value" />
            <span class="range-value">
                <span id="<?php echo esc_attr($id); ?>-value"><?php echo esc_html($value); ?></span>
                <?php if (!empty($field['unit'])): ?>
                    <?php echo esc_html($field['unit']); ?>
                <?php endif; ?>
            </span>
        </div>
        <?php
    }
    
    /**
     * 4.8 時間欄位
     */
    private function render_time_field(string $name, string $id, $value, array $field): void {
        ?>
        <input type="time" 
               name="<?php echo esc_attr($name); ?>" 
               id="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" />
        <?php
    }
    
    /**
     * 4.9 電子郵件欄位
     */
    private function render_email_field(string $name, string $id, $value, array $field): void {
        ?>
        <input type="email" 
               name="<?php echo esc_attr($name); ?>" 
               id="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <?php
    }
    
    /**
     * 4.10 分類管理器
     */
    private function render_category_manager(string $name, string $id, $value, array $field): void {
        if (!is_array($value)) {
            $value = $field['default'] ?? [];
        }
        ?>
        <div class="aisc-category-manager" id="<?php echo esc_attr($id); ?>">
            <div class="category-list">
                <?php foreach ($value as $index => $category): ?>
                    <div class="category-item">
                        <input type="text" 
                               name="<?php echo esc_attr($name); ?>[]" 
                               value="<?php echo esc_attr($category); ?>" 
                               class="regular-text" />
                        <button type="button" class="button" onclick="removeCategory(this)">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" onclick="addCategory('<?php echo esc_attr($id); ?>')">
                <span class="dashicons dashicons-plus"></span> 新增分類
            </button>
        </div>
        <?php
    }
    
    /**
     * 5. 驗證和清理
     */
    
    /**
     * 5.1 清理欄位值
     */
    private function sanitize_field(string $key, $value) {
        $field = $this->get_field_config($key);
        
        if (!$field) {
            return $value;
        }
        
        // 使用自訂清理函數
        if (isset($field['sanitize']) && is_callable($field['sanitize'])) {
            return call_user_func($field['sanitize'], $value);
        }
        
        // 根據類型清理
        switch ($field['type']) {
            case 'text':
            case 'password':
                return sanitize_text_field($value);
                
            case 'email':
                return sanitize_email($value);
                
            case 'number':
            case 'range':
                return is_numeric($value) ? floatval($value) : ($field['default'] ?? 0);
                
            case 'checkbox':
                return (bool) $value;
                
            case 'select':
                return isset($field['options'][$value]) ? $value : ($field['default'] ?? '');
                
            case 'multiselect':
                if (!is_array($value)) {
                    return [];
                }
                return array_filter($value, function($v) use ($field) {
                    return isset($field['options'][$v]);
                });
                
            case 'category_manager':
                if (!is_array($value)) {
                    return [];
                }
                return array_map('sanitize_text_field', $value);
                
            default:
                return apply_filters('aisc_sanitize_field_' . $field['type'], $value, $field);
        }
    }
    
    /**
     * 5.2 驗證欄位值
     */
    private function validate_field(string $key, $value): bool {
        $field = $this->get_field_config($key);
        
        if (!$field) {
            return true;
        }
        
        // 必填檢查
        if (!empty($field['required']) && empty($value)) {
            add_settings_error(
                self::PREFIX . $key,
                'required',
                sprintf(__('%s 為必填欄位', 'ai-seo-content-generator'), $field['label'])
            );
            return false;
        }
        
        // 使用自訂驗證函數
        if (isset($field['validate']) && is_callable($field['validate'])) {
            return call_user_func($field['validate'], $value, $field);
        }
        
        // 根據類型驗證
        switch ($field['type']) {
            case 'email':
                if (!empty($value) && !is_email($value)) {
                    add_settings_error(
                        self::PREFIX . $key,
                        'invalid_email',
                        sprintf(__('%s 必須是有效的電子郵件地址', 'ai-seo-content-generator'), $field['label'])
                    );
                    return false;
                }
                break;
                
            case 'number':
            case 'range':
                if (isset($field['min']) && $value < $field['min']) {
                    add_settings_error(
                        self::PREFIX . $key,
                        'min_value',
                        sprintf(__('%s 不能小於 %s', 'ai-seo-content-generator'), $field['label'], $field['min'])
                    );
                    return false;
                }
                
                if (isset($field['max']) && $value > $field['max']) {
                    add_settings_error(
                        self::PREFIX . $key,
                        'max_value',
                        sprintf(__('%s 不能大於 %s', 'ai-seo-content-generator'), $field['label'], $field['max'])
                    );
                    return false;
                }
                break;
        }
        
        return true;
    }
    
    /**
     * 6. 輔助方法
     */
    
    /**
     * 6.1 取得欄位設定
     */
    private function get_field_config(string $key): ?array {
        foreach ($this->setting_groups as $group) {
            if (isset($group['fields'][$key])) {
                return $group['fields'][$key];
            }
        }
        
        return null;
    }
    
    /**
     * 6.2 取得時區選項
     */
    private function get_timezone_options(): array {
        $timezones = [
            'Asia/Taipei' => '台北 (UTC+8)',
            'Asia/Shanghai' => '上海 (UTC+8)',
            'Asia/Hong_Kong' => '香港 (UTC+8)',
            'Asia/Tokyo' => '東京 (UTC+9)',
            'Asia/Seoul' => '首爾 (UTC+9)',
            'America/New_York' => '紐約 (UTC-5)',
            'America/Los_Angeles' => '洛杉磯 (UTC-8)',
            'Europe/London' => '倫敦 (UTC+0)',
            'Europe/Berlin' => '柏林 (UTC+1)',
            'Australia/Sydney' => '雪梨 (UTC+10)'
        ];
        
        return $timezones;
    }
    
    /**
     * 6.3 處理選項更新
     */
    public function on_option_update(string $option, $old_value, $value): void {
        // 只處理我們的選項
        if (strpos($option, self::PREFIX) !== 0) {
            return;
        }
        
        $key = str_replace(self::PREFIX, '', $option);
        
        // 更新快取
        $this->cached_settings[$key] = $value;
        
        // 記錄變更
        if ($this->logger) {
            $this->logger->info('設定已更新', [
                'key' => $key,
                'old_value' => $old_value,
                'new_value' => $value
            ]);
        }
    }
    
    /**
     * 6.4 渲染欄位相依性
     */
    private function render_field_dependency(string $field_id, array $dependencies): void {
        ?>
        <script>
        (function() {
            var field = document.getElementById('<?php echo esc_js($field_id); ?>');
            var dependencies = <?php echo json_encode($dependencies); ?>;
            
            function checkDependencies() {
                var show = true;
                
                for (var depField in dependencies) {
                    var depValue = dependencies[depField];
                    var depElement = document.querySelector('[name="<?php echo self::PREFIX; ?>' + depField + '"]');
                    
                    if (!depElement) continue;
                    
                    if (depElement.type === 'checkbox') {
                        if (depElement.checked !== depValue) {
                            show = false;
                            break;
                        }
                    } else {
                        if (depElement.value != depValue) {
                            show = false;
                            break;
                        }
                    }
                }
                
                var row = field.closest('tr');
                if (row) {
                    row.style.display = show ? '' : 'none';
                }
            }
            
            // 初始檢查
            checkDependencies();
            
            // 監聽變化
            for (var depField in dependencies) {
                var depElement = document.querySelector('[name="<?php echo self::PREFIX; ?>' + depField + '"]');
                if (depElement) {
                    depElement.addEventListener('change', checkDependencies);
                }
            }
        })();
        </script>
        <?php
    }
    
    /**
     * 7. 匯出和匯入
     */
    
    /**
     * 7.1 匯出設定
     */
    public function export_settings(): array {
        $export = [
            'version' => AISC_VERSION,
            'timestamp' => current_time('timestamp'),
            'settings' => []
        ];
        
        foreach ($this->cached_settings as $key => $value) {
            // 排除敏感資訊
            if (in_array($key, ['openai_api_key', 'ga_property_id'])) {
                continue;
            }
            
            $export['settings'][$key] = $value;
        }
        
        return $export;
    }
    
    /**
     * 7.2 匯入設定
     */
    public function import_settings(array $import): bool {
        if (!isset($import['settings']) || !is_array($import['settings'])) {
            return false;
        }
        
        // 版本檢查
        if (isset($import['version']) && version_compare($import['version'], AISC_VERSION, '>')) {
            add_settings_error(
                'aisc_import',
                'version_mismatch',
                __('匯入的設定來自較新的版本', 'ai-seo-content-generator')
            );
            return false;
        }
        
        // 匯入設定
        foreach ($import['settings'] as $key => $value) {
            // 跳過不存在的設定
            if (!isset($this->defaults[$key])) {
                continue;
            }
            
            $this->set($key, $value);
        }
        
        return true;
    }
    
    /**
     * 8. 設定頁面助手
     */
    
    /**
     * 8.1 取得分組設定
     */
    public function get_groups(): array {
        return $this->setting_groups;
    }
    
    /**
     * 8.2 取得單一分組
     */
    public function get_group(string $group_key): ?array {
        return $this->setting_groups[$group_key] ?? null;
    }
    
    /**
     * 8.3 檢查是否有必填欄位未填
     */
    public function has_required_empty(): array {
        $empty_required = [];
        
        foreach ($this->setting_groups as $group_key => $group) {
            foreach ($group['fields'] as $field_key => $field) {
                if (!empty($field['required']) && empty($this->get($field_key))) {
                    $empty_required[] = [
                        'key' => $field_key,
                        'label' => $field['label'],
                        'group' => $group['title']
                    ];
                }
            }
        }
        
        return $empty_required;
    }
}