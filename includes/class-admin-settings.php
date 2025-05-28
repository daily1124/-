<?php
/**
 * 管理設定頁面 - 完整修正版
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
    }
    
    /**
     * 註冊設定
     */
    public function register_settings() {
        // API 設定 - 加入 sanitize callback
        register_setting('aicg_api_settings', 'aicg_openai_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aicg_api_settings', 'aicg_openai_model', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-3.5-turbo'
        ));
        register_setting('aicg_api_settings', 'aicg_volcengine_access_key_id', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aicg_api_settings', 'aicg_volcengine_secret_access_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aicg_api_settings', 'aicg_unsplash_access_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // 發布設定
        register_setting('aicg_publish_settings', 'aicg_auto_publish');
        register_setting('aicg_publish_settings', 'aicg_post_frequency');
        register_setting('aicg_publish_settings', 'aicg_publish_time');
        register_setting('aicg_publish_settings', 'aicg_posts_per_batch', array(
            'sanitize_callback' => 'intval'
        ));
        register_setting('aicg_publish_settings', 'aicg_default_category', array(
            'sanitize_callback' => 'intval'
        ));
        register_setting('aicg_publish_settings', 'aicg_default_author', array(
            'sanitize_callback' => 'intval'
        ));
        register_setting('aicg_publish_settings', 'aicg_post_status');
        
        // 內容設定
        register_setting('aicg_content_settings', 'aicg_min_word_count', array(
            'sanitize_callback' => 'intval'
        ));
        register_setting('aicg_content_settings', 'aicg_max_word_count', array(
            'sanitize_callback' => 'intval'
        ));
        register_setting('aicg_content_settings', 'aicg_include_images');
        register_setting('aicg_content_settings', 'aicg_image_source');
        register_setting('aicg_content_settings', 'aicg_content_tone');
        register_setting('aicg_content_settings', 'aicg_content_style');
        
        // 關鍵字設定
        register_setting('aicg_keyword_settings', 'aicg_keyword_source_url', array(
            'sanitize_callback' => 'esc_url_raw'
        ));
        register_setting('aicg_keyword_settings', 'aicg_casino_keyword_source_url', array(
            'sanitize_callback' => 'esc_url_raw'
        ));
        register_setting('aicg_keyword_settings', 'aicg_keywords_per_post', array(
            'sanitize_callback' => 'intval'
        ));
        register_setting('aicg_keyword_settings', 'aicg_keyword_density', array(
            'sanitize_callback' => 'floatval'
        ));
        register_setting('aicg_keyword_settings', 'aicg_keyword_preference');
    }
    
    /**
     * 渲染設定頁面
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>AI 內容生成器設定</h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <h2 class="nav-tab-wrapper">
                    <a href="#api" class="nav-tab nav-tab-active">API 設定</a>
                    <a href="#publish" class="nav-tab">發布設定</a>
                    <a href="#content" class="nav-tab">內容設定</a>
                    <a href="#keywords" class="nav-tab">關鍵字設定</a>
                </h2>
                
                <!-- API 設定 -->
                <div id="api-settings" class="settings-section">
                    <?php settings_fields('aicg_api_settings'); ?>
                    
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
                                <p class="description">選擇要使用的 OpenAI 模型。GPT-4 系列提供更高品質但成本較高。</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row" colspan="2">
                                <h3>火山引擎視覺生成設定</h3>
                                <p class="description">用於生成文章配圖。請從火山引擎控制台獲取您的認證資訊。</p>
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
                                       class="regular-text volcengine-key" />
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
                                       class="regular-text volcengine-key" />
                                <button type="button" class="button test-api" data-api="volcengine">測試連接</button>
                                <p class="description">火山引擎 Secret Access Key</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_combined_key_display">組合金鑰（僅供參考）</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="aicg_combined_key_display" 
                                       class="regular-text" 
                                       readonly 
                                       style="background-color: #f0f0f0;" />
                                <button type="button" class="button" id="copy-combined-key">複製</button>
                                <p class="description">這是組合後的金鑰格式，僅供參考。系統會使用上方分開的 Access Key ID 和 Secret Access Key。</p>
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
                </div>
                
                <!-- 發布設定 -->
                <div id="publish-settings" class="settings-section" style="display:none;">
                    <?php settings_fields('aicg_publish_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aicg_auto_publish">自動發布</label>
                            </th>
                            <td>
                                <input type="checkbox" 
                                       id="aicg_auto_publish" 
                                       name="aicg_auto_publish" 
                                       value="1" 
                                       <?php checked(get_option('aicg_auto_publish'), 1); ?> />
                                <label for="aicg_auto_publish">啟用自動發布功能</label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_post_frequency">發布頻率</label>
                            </th>
                            <td>
                                <select id="aicg_post_frequency" name="aicg_post_frequency">
                                    <option value="halfhourly" <?php selected(get_option('aicg_post_frequency'), 'halfhourly'); ?>>每30分鐘</option>
                                    <option value="hourly" <?php selected(get_option('aicg_post_frequency'), 'hourly'); ?>>每小時</option>
                                    <option value="threehourly" <?php selected(get_option('aicg_post_frequency'), 'threehourly'); ?>>每3小時</option>
                                    <option value="sixhourly" <?php selected(get_option('aicg_post_frequency'), 'sixhourly'); ?>>每6小時</option>
                                    <option value="twicedaily" <?php selected(get_option('aicg_post_frequency'), 'twicedaily'); ?>>每天兩次</option>
                                    <option value="daily" <?php selected(get_option('aicg_post_frequency'), 'daily'); ?>>每天一次</option>
                                    <option value="weekly" <?php selected(get_option('aicg_post_frequency'), 'weekly'); ?>>每週一次</option>
                                    <option value="custom" <?php selected(get_option('aicg_post_frequency'), 'custom'); ?>>自訂時間</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="custom-time-row" style="<?php echo get_option('aicg_post_frequency') === 'custom' ? '' : 'display:none;'; ?>">
                            <th scope="row">
                                <label for="aicg_publish_time">發布時間</label>
                            </th>
                            <td>
                                <input type="time" 
                                       id="aicg_publish_time" 
                                       name="aicg_publish_time" 
                                       value="<?php echo esc_attr(get_option('aicg_publish_time', '09:00')); ?>" />
                                <p class="description">設定每天的發布時間（24小時制）</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_posts_per_batch">每次發布文章數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_posts_per_batch" 
                                       name="aicg_posts_per_batch" 
                                       value="<?php echo esc_attr(get_option('aicg_posts_per_batch', 3)); ?>" 
                                       min="1" 
                                       max="10" />
                                <p class="description">每次排程執行時生成的文章數量</p>
                            </td>
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
                </div>
                
                <!-- 內容設定 -->
                <div id="content-settings" class="settings-section" style="display:none;">
                    <?php settings_fields('aicg_content_settings'); ?>
                    
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
                                       max="5000" />
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
                                       max="10000" />
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
                </div>
                
                <!-- 關鍵字設定 -->
                <div id="keyword-settings" class="settings-section" style="display:none;">
                    <?php settings_fields('aicg_keyword_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="aicg_keyword_source_url">台灣關鍵字來源 URL</label>
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
                                <label for="aicg_casino_keyword_source_url">娛樂城關鍵字來源 URL</label>
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
                                <label for="aicg_keywords_per_post">每篇文章關鍵字數</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_keywords_per_post" 
                                       name="aicg_keywords_per_post" 
                                       value="<?php echo esc_attr(get_option('aicg_keywords_per_post', 3)); ?>" 
                                       min="1" 
                                       max="10" />
                                <p class="description">每篇文章使用的關鍵字數量</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="aicg_keyword_density">關鍵字密度</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="aicg_keyword_density" 
                                       name="aicg_keyword_density" 
                                       value="<?php echo esc_attr(get_option('aicg_keyword_density', 2)); ?>" 
                                       min="1" 
                                       max="5" 
                                       step="0.1" />
                                <span>%</span>
                                <p class="description">文章中關鍵字的目標密度</p>
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
                                <p class="description">選擇生成文章時優先使用的關鍵字類型</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab 切換
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.settings-section').hide();
                
                var target = $(this).attr('href');
                if (target === '#api') {
                    $('#api-settings').show();
                } else if (target === '#publish') {
                    $('#publish-settings').show();
                } else if (target === '#content') {
                    $('#content-settings').show();
                } else if (target === '#keywords') {
                    $('#keyword-settings').show();
                }
            });
            
            // 發布頻率切換
            $('#aicg_post_frequency').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom-time-row').show();
                } else {
                    $('#custom-time-row').hide();
                }
            });
            
            // 更新組合金鑰顯示
            function updateCombinedKey() {
                var accessKeyId = $('#aicg_volcengine_access_key_id').val();
                var secretAccessKey = $('#aicg_volcengine_secret_access_key').val();
                
                if (accessKeyId && secretAccessKey) {
                    $('#aicg_combined_key_display').val(accessKeyId + ':' + secretAccessKey);
                } else {
                    $('#aicg_combined_key_display').val('');
                }
            }
            
            // 監聽火山引擎金鑰變更
            $('.volcengine-key').on('input', function() {
                updateCombinedKey();
            });
            
            // 初始化時更新組合金鑰顯示
            updateCombinedKey();
            
            // 複製組合金鑰
            $('#copy-combined-key').on('click', function() {
                var combinedKey = $('#aicg_combined_key_display').val();
                if (combinedKey) {
                    var tempInput = $('<input>');
                    $('body').append(tempInput);
                    tempInput.val(combinedKey).select();
                    document.execCommand('copy');
                    tempInput.remove();
                    
                    var originalText = $(this).text();
                    $(this).text('已複製！');
                    setTimeout(function() {
                        $('#copy-combined-key').text(originalText);
                    }, 2000);
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
            
            // 修正表單提交以確保所有設定都能儲存
            $('form').on('submit', function() {
                // 確保所有 select 元素的值都能被提交
                $(this).find('select').each(function() {
                    if ($(this).val() === null || $(this).val() === '') {
                        $(this).val($(this).find('option:first').val());
                    }
                });
                
                return true;
            });
        });
        </script>
        
        <style>
        .volcengine-key {
            font-family: monospace;
        }
        #aicg_combined_key_display {
            font-family: monospace;
            font-size: 12px;
        }
        .settings-section {
            margin-top: 20px;
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
        </style>
        <?php
    }
}