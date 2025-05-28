<?php
/**
 * 管理設定頁面 - 修正版（修復關鍵字設定頁面）
 */
class AICG_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        // 處理AJAX儲存
        add_action('wp_ajax_aicg_save_settings', array($this, 'ajax_save_settings'));
    }
    
    /**
     * 添加設定頁面到選單
     */
    public function add_settings_page() {
        add_submenu_page(
            'ai-content-generator',
            '設定',
            '設定',
            'manage_options',
            'ai-content-generator-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * 註冊設定
     */
    public function register_settings() {
        // API 設定群組
        register_setting('aicg_api_settings', 'aicg_openai_api_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aicg_api_settings', 'aicg_openai_model', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aicg_api_settings', 'aicg_volcengine_access_key_id', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aicg_api_settings', 'aicg_volcengine_secret_access_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aicg_api_settings', 'aicg_unsplash_access_key', array('sanitize_callback' => 'sanitize_text_field'));
        
        // 發布設定群組
        register_setting('aicg_publish_settings', 'aicg_auto_publish_taiwan');
        register_setting('aicg_publish_settings', 'aicg_auto_publish_casino');
        register_setting('aicg_publish_settings', 'aicg_post_frequency_taiwan');
        register_setting('aicg_publish_settings', 'aicg_post_frequency_casino');
        register_setting('aicg_publish_settings', 'aicg_publish_time_taiwan');
        register_setting('aicg_publish_settings', 'aicg_publish_time_casino');
        register_setting('aicg_publish_settings', 'aicg_posts_per_batch_taiwan');
        register_setting('aicg_publish_settings', 'aicg_posts_per_batch_casino');
        register_setting('aicg_publish_settings', 'aicg_default_category');
        register_setting('aicg_publish_settings', 'aicg_default_author');
        register_setting('aicg_publish_settings', 'aicg_post_status');
        
        // 內容設定群組
        register_setting('aicg_content_settings', 'aicg_min_word_count');
        register_setting('aicg_content_settings', 'aicg_max_word_count');
        register_setting('aicg_content_settings', 'aicg_include_images');
        register_setting('aicg_content_settings', 'aicg_image_source');
        register_setting('aicg_content_settings', 'aicg_content_tone');
        register_setting('aicg_content_settings', 'aicg_content_style');
        
        // 關鍵字設定群組
        register_setting('aicg_keyword_settings', 'aicg_keyword_source_url');
        register_setting('aicg_keyword_settings', 'aicg_casino_keyword_source_url');
        register_setting('aicg_keyword_settings', 'aicg_taiwan_keywords_per_post');
        register_setting('aicg_keyword_settings', 'aicg_casino_keywords_per_post');
        register_setting('aicg_keyword_settings', 'aicg_taiwan_keyword_density');
        register_setting('aicg_keyword_settings', 'aicg_casino_keyword_density');
        register_setting('aicg_keyword_settings', 'aicg_keyword_preference');
    }
    
    /**
     * AJAX 儲存設定
     */
    public function ajax_save_settings() {
        check_ajax_referer('aicg_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '權限不足'));
            return;
        }
        
        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'api';
        
        switch ($tab) {
            case 'api':
                update_option('aicg_openai_api_key', sanitize_text_field($_POST['aicg_openai_api_key'] ?? ''));
                update_option('aicg_openai_model', sanitize_text_field($_POST['aicg_openai_model'] ?? 'gpt-3.5-turbo'));
                update_option('aicg_volcengine_access_key_id', sanitize_text_field($_POST['aicg_volcengine_access_key_id'] ?? ''));
                update_option('aicg_volcengine_secret_access_key', sanitize_text_field($_POST['aicg_volcengine_secret_access_key'] ?? ''));
                update_option('aicg_unsplash_access_key', sanitize_text_field($_POST['aicg_unsplash_access_key'] ?? ''));
                break;
                
            case 'publish':
                update_option('aicg_auto_publish_taiwan', isset($_POST['aicg_auto_publish_taiwan']) ? 1 : 0);
                update_option('aicg_auto_publish_casino', isset($_POST['aicg_auto_publish_casino']) ? 1 : 0);
                update_option('aicg_post_frequency_taiwan', sanitize_text_field($_POST['aicg_post_frequency_taiwan'] ?? 'daily'));
                update_option('aicg_post_frequency_casino', sanitize_text_field($_POST['aicg_post_frequency_casino'] ?? 'daily'));
                update_option('aicg_publish_time_taiwan', sanitize_text_field($_POST['aicg_publish_time_taiwan'] ?? '09:00'));
                update_option('aicg_publish_time_casino', sanitize_text_field($_POST['aicg_publish_time_casino'] ?? '09:00'));
                update_option('aicg_posts_per_batch_taiwan', intval($_POST['aicg_posts_per_batch_taiwan'] ?? 3));
                update_option('aicg_posts_per_batch_casino', intval($_POST['aicg_posts_per_batch_casino'] ?? 3));
                update_option('aicg_default_category', intval($_POST['aicg_default_category'] ?? 1));
                update_option('aicg_default_author', intval($_POST['aicg_default_author'] ?? 1));
                update_option('aicg_post_status', sanitize_text_field($_POST['aicg_post_status'] ?? 'publish'));
                break;
                
            case 'content':
                update_option('aicg_min_word_count', intval($_POST['aicg_min_word_count'] ?? 1000));
                update_option('aicg_max_word_count', intval($_POST['aicg_max_word_count'] ?? 2000));
                update_option('aicg_include_images', isset($_POST['aicg_include_images']) ? 1 : 0);
                update_option('aicg_image_source', sanitize_text_field($_POST['aicg_image_source'] ?? 'volcengine'));
                update_option('aicg_content_tone', sanitize_text_field($_POST['aicg_content_tone'] ?? 'professional'));
                update_option('aicg_content_style', sanitize_text_field($_POST['aicg_content_style'] ?? 'informative'));
                break;
                
            case 'keywords':
                update_option('aicg_keyword_source_url', esc_url_raw($_POST['aicg_keyword_source_url'] ?? ''));
                update_option('aicg_casino_keyword_source_url', esc_url_raw($_POST['aicg_casino_keyword_source_url'] ?? ''));
                update_option('aicg_taiwan_keywords_per_post', intval($_POST['aicg_taiwan_keywords_per_post'] ?? 2));
                update_option('aicg_casino_keywords_per_post', intval($_POST['aicg_casino_keywords_per_post'] ?? 1));
                update_option('aicg_taiwan_keyword_density', floatval($_POST['aicg_taiwan_keyword_density'] ?? 2));
                update_option('aicg_casino_keyword_density', floatval($_POST['aicg_casino_keyword_density'] ?? 1.5));
                update_option('aicg_keyword_preference', sanitize_text_field($_POST['aicg_keyword_preference'] ?? 'mixed'));
                break;
        }
        
        wp_send_json_success(array('message' => '設定已儲存'));
    }
    
    /**
     * 渲染設定頁面
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>AI 內容生成器設定</h1>
            
            <div id="aicg-settings-messages"></div>
            
            <h2 class="nav-tab-wrapper">
                <a href="#api" class="nav-tab nav-tab-active" data-tab="api">API 設定</a>
                <a href="#publish" class="nav-tab" data-tab="publish">發布設定</a>
                <a href="#content" class="nav-tab" data-tab="content">內容設定</a>
                <a href="#keywords" class="nav-tab" data-tab="keywords">關鍵字設定</a>
            </h2>
            
            <!-- 重要：移除 form 的 action="options.php"，改用 JavaScript 處理 -->
            <form method="post" id="aicg-settings-form" onsubmit="return false;">
                <!-- API 設定 -->
                <div id="api-settings" class="settings-section active">
                    <input type="hidden" name="current_tab" value="api">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>OpenAI 設定</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_openai_api_key">OpenAI API Key</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="aicg_openai_api_key" 
                                       name="aicg_openai_api_key" 
                                       value="<?php echo esc_attr(get_option('aicg_openai_api_key')); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button test-api" data-api="openai">測試連接</button>
                                <p class="description">請輸入您的 OpenAI API Key（用於生成文章內容）</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_openai_model">OpenAI 模型</label>
                            </th>
                            <td>
                                <select id="aicg_openai_model" name="aicg_openai_model">
                                    <option value="gpt-3.5-turbo" <?php selected(get_option('aicg_openai_model', 'gpt-3.5-turbo'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo（推薦）</option>
                                    <option value="gpt-3.5-turbo-16k" <?php selected(get_option('aicg_openai_model'), 'gpt-3.5-turbo-16k'); ?>>GPT-3.5 Turbo 16K（長文章）</option>
                                    <option value="gpt-4" <?php selected(get_option('aicg_openai_model'), 'gpt-4'); ?>>GPT-4（高品質）</option>
                                    <option value="gpt-4-turbo" <?php selected(get_option('aicg_openai_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo（最新）</option>
                                    <option value="gpt-4-turbo-preview" <?php selected(get_option('aicg_openai_model'), 'gpt-4-turbo-preview'); ?>>GPT-4 Turbo Preview</option>
                                </select>
                                <p class="description">選擇要使用的 OpenAI 模型</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>火山引擎視覺生成設定</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_volcengine_access_key_id">Access Key ID</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="aicg_volcengine_access_key_id" 
                                       name="aicg_volcengine_access_key_id" 
                                       value="<?php echo esc_attr(get_option('aicg_volcengine_access_key_id')); ?>" 
                                       class="regular-text" />
                                <p class="description">火山引擎 Access Key ID</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_volcengine_secret_access_key">Secret Access Key</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="aicg_volcengine_secret_access_key" 
                                       name="aicg_volcengine_secret_access_key" 
                                       value="<?php echo esc_attr(get_option('aicg_volcengine_secret_access_key')); ?>" 
                                       class="regular-text" />
                                <button type="button" class="button test-api" data-api="volcengine">測試連接</button>
                                <p class="description">火山引擎 Secret Access Key</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>其他圖片來源</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_unsplash_access_key">Unsplash Access Key</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="aicg_unsplash_access_key" 
                                       name="aicg_unsplash_access_key" 
                                       value="<?php echo esc_attr(get_option('aicg_unsplash_access_key')); ?>" 
                                       class="regular-text" />
                                <p class="description">選用：如果不使用 AI 生成圖片，可使用 Unsplash 免費圖片</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" class="button-primary aicg-save-settings" data-tab="api">儲存 API 設定</button>
                    </p>
                </div>
                
                <!-- 發布設定 -->
                <div id="publish-settings" class="settings-section" style="display:none;">
                    <input type="hidden" name="current_tab" value="publish">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>台灣熱門關鍵字發布設定</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_auto_publish_taiwan">自動發布台灣關鍵字文章</label>
                            </th>
                            <td>
                                <input type="checkbox" 
                                       id="aicg_auto_publish_taiwan" 
                                       name="aicg_auto_publish_taiwan" 
                                       value="1" 
                                       <?php checked(get_option('aicg_auto_publish_taiwan'), 1); ?> />
                                <label for="aicg_auto_publish_taiwan">啟用自動發布</label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_post_frequency_taiwan">發布頻率</label>
                            </th>
                            <td>
                                <select id="aicg_post_frequency_taiwan" name="aicg_post_frequency_taiwan">
                                    <option value="halfhourly" <?php selected(get_option('aicg_post_frequency_taiwan'), 'halfhourly'); ?>>每30分鐘</option>
                                    <option value="hourly" <?php selected(get_option('aicg_post_frequency_taiwan'), 'hourly'); ?>>每小時</option>
                                    <option value="threehourly" <?php selected(get_option('aicg_post_frequency_taiwan'), 'threehourly'); ?>>每3小時</option>
                                    <option value="sixhourly" <?php selected(get_option('aicg_post_frequency_taiwan'), 'sixhourly'); ?>>每6小時</option>
                                    <option value="twicedaily" <?php selected(get_option('aicg_post_frequency_taiwan'), 'twicedaily'); ?>>每天兩次</option>
                                    <option value="daily" <?php selected(get_option('aicg_post_frequency_taiwan', 'daily'), 'daily'); ?>>每天一次</option>
                                    <option value="weekly" <?php selected(get_option('aicg_post_frequency_taiwan'), 'weekly'); ?>>每週一次</option>
                                    <option value="custom" <?php selected(get_option('aicg_post_frequency_taiwan'), 'custom'); ?>>自訂時間</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="custom-time-taiwan-row" style="<?php echo get_option('aicg_post_frequency_taiwan') === 'custom' ? '' : 'display:none;'; ?>">
                            <th scope="row">
                                <label for="aicg_publish_time_taiwan">發布時間</label>
                            </th>
                            <td>
                                <input type="time" 
                                       id="aicg_publish_time_taiwan" 
                                       name="aicg_publish_time_taiwan" 
                                       value="<?php echo esc_attr(get_option('aicg_publish_time_taiwan', '09:00')); ?>" />
                                <p class="description">設定每天的發布時間（24小時制）</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_posts_per_batch_taiwan">每次發布文章數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_posts_per_batch_taiwan" 
                                       name="aicg_posts_per_batch_taiwan" 
                                       value="<?php echo esc_attr(get_option('aicg_posts_per_batch_taiwan', 3)); ?>" 
                                       min="1" 
                                       max="10" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>娛樂城關鍵字發布設定</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_auto_publish_casino">自動發布娛樂城關鍵字文章</label>
                            </th>
                            <td>
                                <input type="checkbox" 
                                       id="aicg_auto_publish_casino" 
                                       name="aicg_auto_publish_casino" 
                                       value="1" 
                                       <?php checked(get_option('aicg_auto_publish_casino'), 1); ?> />
                                <label for="aicg_auto_publish_casino">啟用自動發布</label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_post_frequency_casino">發布頻率</label>
                            </th>
                            <td>
                                <select id="aicg_post_frequency_casino" name="aicg_post_frequency_casino">
                                    <option value="halfhourly" <?php selected(get_option('aicg_post_frequency_casino'), 'halfhourly'); ?>>每30分鐘</option>
                                    <option value="hourly" <?php selected(get_option('aicg_post_frequency_casino'), 'hourly'); ?>>每小時</option>
                                    <option value="threehourly" <?php selected(get_option('aicg_post_frequency_casino'), 'threehourly'); ?>>每3小時</option>
                                    <option value="sixhourly" <?php selected(get_option('aicg_post_frequency_casino'), 'sixhourly'); ?>>每6小時</option>
                                    <option value="twicedaily" <?php selected(get_option('aicg_post_frequency_casino'), 'twicedaily'); ?>>每天兩次</option>
                                    <option value="daily" <?php selected(get_option('aicg_post_frequency_casino', 'daily'), 'daily'); ?>>每天一次</option>
                                    <option value="weekly" <?php selected(get_option('aicg_post_frequency_casino'), 'weekly'); ?>>每週一次</option>
                                    <option value="custom" <?php selected(get_option('aicg_post_frequency_casino'), 'custom'); ?>>自訂時間</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="custom-time-casino-row" style="<?php echo get_option('aicg_post_frequency_casino') === 'custom' ? '' : 'display:none;'; ?>">
                            <th scope="row">
                                <label for="aicg_publish_time_casino">發布時間</label>
                            </th>
                            <td>
                                <input type="time" 
                                       id="aicg_publish_time_casino" 
                                       name="aicg_publish_time_casino" 
                                       value="<?php echo esc_attr(get_option('aicg_publish_time_casino', '09:00')); ?>" />
                                <p class="description">設定每天的發布時間（24小時制）</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_posts_per_batch_casino">每次發布文章數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_posts_per_batch_casino" 
                                       name="aicg_posts_per_batch_casino" 
                                       value="<?php echo esc_attr(get_option('aicg_posts_per_batch_casino', 3)); ?>" 
                                       min="1" 
                                       max="10" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>通用發布設定</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_default_category">預設分類</label>
                            </th>
                            <td>
                                <?php
                                wp_dropdown_categories(array(
                                    'name' => 'aicg_default_category',
                                    'id' => 'aicg_default_category',
                                    'selected' => get_option('aicg_default_category', 1),
                                    'show_option_none' => '選擇分類',
                                    'option_none_value' => '0',
                                    'hierarchical' => true,
                                ));
                                ?>
                                <p class="description">如果無法自動偵測分類時使用的預設分類</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_default_author">預設作者</label>
                            </th>
                            <td>
                                <?php
                                wp_dropdown_users(array(
                                    'name' => 'aicg_default_author',
                                    'id' => 'aicg_default_author',
                                    'selected' => get_option('aicg_default_author', 1),
                                    'who' => 'authors',
                                ));
                                ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_post_status">文章狀態</label>
                            </th>
                            <td>
                                <select id="aicg_post_status" name="aicg_post_status">
                                    <option value="publish" <?php selected(get_option('aicg_post_status', 'publish'), 'publish'); ?>>已發布</option>
                                    <option value="draft" <?php selected(get_option('aicg_post_status'), 'draft'); ?>>草稿</option>
                                    <option value="pending" <?php selected(get_option('aicg_post_status'), 'pending'); ?>>待審核</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" class="button-primary aicg-save-settings" data-tab="publish">儲存發布設定</button>
                    </p>
                </div>
                
                <!-- 內容設定 -->
                <div id="content-settings" class="settings-section" style="display:none;">
                    <input type="hidden" name="current_tab" value="content">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aicg_min_word_count">最少字數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_min_word_count" 
                                       name="aicg_min_word_count" 
                                       value="<?php echo esc_attr(get_option('aicg_min_word_count', 1000)); ?>" 
                                       min="500" 
                                       max="50000" />
                                <p class="description">文章的最少字數</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_max_word_count">最多字數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_max_word_count" 
                                       name="aicg_max_word_count" 
                                       value="<?php echo esc_attr(get_option('aicg_max_word_count', 2000)); ?>" 
                                       min="1000" 
                                       max="50000" />
                                <p class="description">文章的最多字數</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_include_images">包含圖片</label>
                            </th>
                            <td>
                                <input type="checkbox" 
                                       id="aicg_include_images" 
                                       name="aicg_include_images" 
                                       value="1" 
                                       <?php checked(get_option('aicg_include_images', 1), 1); ?> />
                                <label for="aicg_include_images">在文章中包含圖片</label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_image_source">圖片來源</label>
                            </th>
                            <td>
                                <select id="aicg_image_source" name="aicg_image_source">
                                    <option value="volcengine" <?php selected(get_option('aicg_image_source', 'volcengine'), 'volcengine'); ?>>火山引擎 AI 生成</option>
                                    <option value="unsplash" <?php selected(get_option('aicg_image_source'), 'unsplash'); ?>>Unsplash 免費圖片</option>
                                    <option value="none" <?php selected(get_option('aicg_image_source'), 'none'); ?>>不使用圖片</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_content_tone">內容語調</label>
                            </th>
                            <td>
                                <select id="aicg_content_tone" name="aicg_content_tone">
                                    <option value="professional" <?php selected(get_option('aicg_content_tone', 'professional'), 'professional'); ?>>專業</option>
                                    <option value="casual" <?php selected(get_option('aicg_content_tone'), 'casual'); ?>>輕鬆</option>
                                    <option value="friendly" <?php selected(get_option('aicg_content_tone'), 'friendly'); ?>>友善</option>
                                    <option value="authoritative" <?php selected(get_option('aicg_content_tone'), 'authoritative'); ?>>權威</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_content_style">內容風格</label>
                            </th>
                            <td>
                                <select id="aicg_content_style" name="aicg_content_style">
                                    <option value="informative" <?php selected(get_option('aicg_content_style', 'informative'), 'informative'); ?>>資訊性</option>
                                    <option value="persuasive" <?php selected(get_option('aicg_content_style'), 'persuasive'); ?>>說服性</option>
                                    <option value="narrative" <?php selected(get_option('aicg_content_style'), 'narrative'); ?>>敘事性</option>
                                    <option value="educational" <?php selected(get_option('aicg_content_style'), 'educational'); ?>>教育性</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" class="button-primary aicg-save-settings" data-tab="content">儲存內容設定</button>
                    </p>
                </div>
                
                <!-- 關鍵字設定 -->
                <div id="keyword-settings" class="settings-section" style="display:none;">
                    <input type="hidden" name="current_tab" value="keywords">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>台灣熱門關鍵字設定</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_keyword_source_url">關鍵字來源 URL</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="aicg_keyword_source_url" 
                                       name="aicg_keyword_source_url" 
                                       value="<?php echo esc_attr(get_option('aicg_keyword_source_url')); ?>" 
                                       class="large-text" />
                                <p class="description">選用：自定義關鍵字 API 來源（JSON 格式）</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_taiwan_keywords_per_post">每篇文章使用的台灣關鍵字數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_taiwan_keywords_per_post" 
                                       name="aicg_taiwan_keywords_per_post" 
                                       value="<?php echo esc_attr(get_option('aicg_taiwan_keywords_per_post', 2)); ?>" 
                                       min="0" 
                                       max="10" />
                                <p class="description">每篇文章中使用的台灣熱門關鍵字數量</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_taiwan_keyword_density">台灣關鍵字密度</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_taiwan_keyword_density" 
                                       name="aicg_taiwan_keyword_density" 
                                       value="<?php echo esc_attr(get_option('aicg_taiwan_keyword_density', 2)); ?>" 
                                       min="1" 
                                       max="5" 
                                       step="0.1" />
                                <span>%</span>
                                <p class="description">台灣關鍵字在文章中的目標密度</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>娛樂城關鍵字設定</h3>
                            </th>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_casino_keyword_source_url">關鍵字來源 URL</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="aicg_casino_keyword_source_url" 
                                       name="aicg_casino_keyword_source_url" 
                                       value="<?php echo esc_attr(get_option('aicg_casino_keyword_source_url')); ?>" 
                                       class="large-text" />
                                <p class="description">選用：自定義娛樂城關鍵字 API 來源（JSON 格式）</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_casino_keywords_per_post">每篇文章使用的娛樂城關鍵字數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_casino_keywords_per_post" 
                                       name="aicg_casino_keywords_per_post" 
                                       value="<?php echo esc_attr(get_option('aicg_casino_keywords_per_post', 1)); ?>" 
                                       min="0" 
                                       max="10" />
                                <p class="description">每篇文章中使用的娛樂城關鍵字數量</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_casino_keyword_density">娛樂城關鍵字密度</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_casino_keyword_density" 
                                       name="aicg_casino_keyword_density" 
                                       value="<?php echo esc_attr(get_option('aicg_casino_keyword_density', 1.5)); ?>" 
                                       min="1" 
                                       max="5" 
                                       step="0.1" />
                                <span>%</span>
                                <p class="description">娛樂城關鍵字在文章中的目標密度</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_keyword_preference">關鍵字偏好</label>
                            </th>
                            <td>
                                <select id="aicg_keyword_preference" name="aicg_keyword_preference">
                                    <option value="mixed" <?php selected(get_option('aicg_keyword_preference', 'mixed'), 'mixed'); ?>>混合使用</option>
                                    <option value="taiwan" <?php selected(get_option('aicg_keyword_preference'), 'taiwan'); ?>>優先台灣關鍵字</option>
                                    <option value="casino" <?php selected(get_option('aicg_keyword_preference'), 'casino'); ?>>優先娛樂城關鍵字</option>
                                </select>
                                <p class="description">選擇生成文章時的關鍵字使用策略</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" class="button-primary aicg-save-settings" data-tab="keywords">儲存關鍵字設定</button>
                    </p>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab 切換
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.settings-section').removeClass('active').hide();
                
                var target = $(this).data('tab');
                $('#' + target + '-settings').addClass('active').show();
            });
            
            // AJAX 儲存設定 - 修正版本，移除旋轉動畫
            $('.aicg-save-settings').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var button = $(this);
                var tab = button.data('tab');
                var originalText = button.text();
                var section = $('#' + tab + '-settings');
                
                // 收集表單資料
                var formData = new FormData();
                formData.append('action', 'aicg_save_settings');
                formData.append('nonce', aicg_ajax.nonce);
                formData.append('tab', tab);
                
                // 收集該區塊的所有輸入
                section.find('input, select, textarea').each(function() {
                    var $input = $(this);
                    var name = $input.attr('name');
                    var type = $input.attr('type');
                    
                    if (name) {
                        if (type === 'checkbox') {
                            if ($input.is(':checked')) {
                                formData.append(name, $input.val());
                            }
                        } else {
                            formData.append(name, $input.val());
                        }
                    }
                });
                
                // 顯示載入狀態（不使用旋轉動畫）
                button.text('儲存中...').prop('disabled', true);
                
                // 發送 AJAX 請求
                $.ajax({
                    url: aicg_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // 顯示成功訊息（簡單的文字提示）
                            $('#aicg-settings-messages').html(
                                '<div class="notice notice-success is-dismissible"><p>' + 
                                response.data.message + 
                                '</p></div>'
                            );
                            
                            // 淡入效果而非旋轉
                            $('#aicg-settings-messages .notice').hide().fadeIn(300);
                            
                            // 滾動到頂部看訊息
                            $('html, body').animate({ scrollTop: 0 }, 300);
                        } else {
                            alert('錯誤: ' + (response.data.message || '儲存失敗'));
                        }
                    },
                    error: function() {
                        alert('儲存失敗，請稍後再試');
                    },
                    complete: function() {
                        // 恢復按鈕狀態
                        button.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // 發布頻率切換 - 台灣
            $('#aicg_post_frequency_taiwan').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom-time-taiwan-row').show();
                } else {
                    $('#custom-time-taiwan-row').hide();
                }
            });
            
            // 發布頻率切換 - 娛樂城
            $('#aicg_post_frequency_casino').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom-time-casino-row').show();
                } else {
                    $('#custom-time-casino-row').hide();
                }
            });
            
            // 測試 API 連接
            $('.test-api').on('click', function() {
                var button = $(this);
                var api_type = button.data('api');
                var original_text = button.text();
                
                // 驗證必要欄位
                if (api_type === 'volcengine') {
                    var accessKeyId = $('#aicg_volcengine_access_key_id').val();
                    var secretAccessKey = $('#aicg_volcengine_secret_access_key').val();
                    
                    if (!accessKeyId || !secretAccessKey) {
                        alert('請先填寫 Access Key ID 和 Secret Access Key');
                        return;
                    }
                } else if (api_type === 'openai') {
                    var openaiKey = $('#aicg_openai_api_key').val();
                    if (!openaiKey) {
                        alert('請先填寫 OpenAI API Key');
                        return;
                    }
                }
                
                button.text('測試中...').prop('disabled', true);
                
                // 測試連接
                $.post(aicg_ajax.ajax_url, {
                    action: 'aicg_test_api',
                    api_type: api_type,
                    nonce: aicg_ajax.nonce
                }, function(response) {
                    button.text(original_text).prop('disabled', false);
                    
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                    } else {
                        alert('❌ 錯誤: ' + response.data.message);
                    }
                }).fail(function() {
                    button.text(original_text).prop('disabled', false);
                    alert('❌ 測試請求失敗，請檢查網路連接');
                });
            });
            
            // 字數驗證
            $('#aicg_min_word_count, #aicg_max_word_count').on('change', function() {
                var min = parseInt($('#aicg_min_word_count').val());
                var max = parseInt($('#aicg_max_word_count').val());
                
                if (min > max) {
                    alert('最少字數不能大於最多字數');
                    if ($(this).attr('id') === 'aicg_min_word_count') {
                        $(this).val(max);
                    } else {
                        $(this).val(min);
                    }
                }
            });
            
            // 關鍵字數量驗證
            $('#aicg_taiwan_keywords_per_post, #aicg_casino_keywords_per_post').on('change', function() {
                var taiwan = parseInt($('#aicg_taiwan_keywords_per_post').val()) || 0;
                var casino = parseInt($('#aicg_casino_keywords_per_post').val()) || 0;
                var total = taiwan + casino;
                
                if (total > 10) {
                    alert('總關鍵字數不能超過 10 個');
                    if ($(this).attr('id') === 'aicg_taiwan_keywords_per_post') {
                        $(this).val(10 - casino);
                    } else {
                        $(this).val(10 - taiwan);
                    }
                }
                
                if (total === 0) {
                    alert('至少需要設定一個關鍵字');
                    $(this).val(1);
                }
            });
        });
        </script>
        
        <style>
        /* 移除所有旋轉相關的動畫 */
        .settings-section {
            background: #fff;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .form-table th {
            font-weight: 600;
        }
        .form-table h3 {
            margin-bottom: 10px;
            color: #23282d;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        #aicg-settings-messages {
            margin-top: 20px;
        }
        
        /* 確保沒有任何旋轉動畫 */
        * {
            animation: none !important;
            -webkit-animation: none !important;
            transform: none !important;
            -webkit-transform: none !important;
        }
        
        /* 只使用淡入淡出效果 */
        .notice {
            transition: opacity 0.3s ease;
        }
        </style>
        <?php
    }
}