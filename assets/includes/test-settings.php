<?php
/**
 * AI Content Generator 設定診斷腳本
 * 使用方法：將此檔案放在外掛目錄，然後訪問 /wp-content/plugins/ai-content-generator/test-settings.php
 */

// 載入 WordPress
require_once('../../../wp-load.php');

// 檢查是否為管理員
if (!current_user_can('manage_options')) {
    die('需要管理員權限');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>AICG 設定診斷</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            overflow-x: auto;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>AI Content Generator 設定診斷</h1>
    
    <div class="test-section">
        <h2>1. 環境檢查</h2>
        <p>WordPress 版本: <span class="info"><?php echo get_bloginfo('version'); ?></span></p>
        <p>PHP 版本: <span class="info"><?php echo phpversion(); ?></span></p>
        <p>目前使用者: <span class="info"><?php echo wp_get_current_user()->user_login; ?></span></p>
        <p>資料庫前綴: <span class="info"><?php global $wpdb; echo $wpdb->prefix; ?></span></p>
    </div>
    
    <div class="test-section">
        <h2>2. 選項值檢查</h2>
        <?php
        $options = array(
            'aicg_openai_api_key',
            'aicg_jimeng_api_key',
            'aicg_unsplash_access_key',
            'aicg_auto_publish',
            'aicg_post_frequency',
            'aicg_posts_per_batch',
            'aicg_min_word_count',
            'aicg_max_word_count',
            'aicg_keyword_source_url',
            'aicg_casino_keyword_source_url'
        );
        
        foreach ($options as $option) {
            $value = get_option($option);
            $exists = get_option($option) !== false;
            ?>
            <p>
                <strong><?php echo $option; ?>:</strong> 
                <?php if ($exists): ?>
                    <span class="success">存在</span> - 
                    值: <code><?php 
                        if (strpos($option, 'api_key') !== false || strpos($option, 'access_key') !== false) {
                            echo $value ? '***已設定*** (長度: ' . strlen($value) . ')' : '(空值)';
                        } else {
                            echo var_export($value, true);
                        }
                    ?></code>
                <?php else: ?>
                    <span class="error">不存在</span>
                <?php endif; ?>
            </p>
            <?php
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. 資料表檢查</h2>
        <?php
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'aicg_keywords',
            $wpdb->prefix . 'aicg_generation_log'
        );
        
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            ?>
            <p>
                <strong><?php echo $table; ?>:</strong> 
                <?php if ($exists): ?>
                    <span class="success">存在</span>
                    <?php
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                    echo " (記錄數: $count)";
                    ?>
                <?php else: ?>
                    <span class="error">不存在</span>
                <?php endif; ?>
            </p>
            <?php
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>4. 測試儲存功能</h2>
        <?php
        // 測試儲存
        $test_key = 'aicg_test_' . time();
        $test_value = 'test_value_' . uniqid();
        
        update_option($test_key, $test_value);
        $retrieved = get_option($test_key);
        $success = ($retrieved === $test_value);
        
        if ($success) {
            echo '<p class="success">✓ 選項儲存功能正常</p>';
            delete_option($test_key); // 清理測試資料
        } else {
            echo '<p class="error">✗ 選項儲存功能異常</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>5. 手動設定 API Key</h2>
        <p>如果無法透過設定頁面儲存，可以使用下面的表單手動設定：</p>
        
        <?php
        if (isset($_POST['manual_save'])) {
            if (isset($_POST['openai_key'])) {
                update_option('aicg_openai_api_key', sanitize_text_field($_POST['openai_key']));
                echo '<p class="success">OpenAI API Key 已儲存</p>';
            }
            if (isset($_POST['jimeng_key'])) {
                update_option('aicg_jimeng_api_key', sanitize_text_field($_POST['jimeng_key']));
                echo '<p class="success">即夢 AI API Key 已儲存</p>';
            }
        }
        ?>
        
        <form method="post">
            <p>
                <label>OpenAI API Key:<br>
                <input type="text" name="openai_key" style="width: 400px;" 
                       value="<?php echo esc_attr(get_option('aicg_openai_api_key')); ?>">
                </label>
            </p>
            <p>
                <label>即夢 AI API Key:<br>
                <input type="text" name="jimeng_key" style="width: 400px;"
                       value="<?php echo esc_attr(get_option('aicg_jimeng_api_key')); ?>">
                </label>
            </p>
            <p>
                <input type="submit" name="manual_save" value="手動儲存" class="button button-primary">
            </p>
        </form>
    </div>
    
    <div class="test-section">
        <h2>6. 註冊的設定</h2>
        <?php
        global $wp_settings_fields, $wp_settings_sections;
        echo '<pre>';
        echo "設定群組:\n";
        print_r(array_keys($wp_settings_fields));
        echo '</pre>';
        ?>
    </div>
    
    <div class="test-section">
        <h2>7. 建議操作</h2>
        <ol>
            <li>確認已使用我提供的修正版 class-admin-settings.php</li>
            <li>清除瀏覽器快取和 WordPress 快取</li>
            <li>如果使用快取外掛，暫時停用它們</li>
            <li>確認 wp-config.php 中沒有禁用選項儲存的設定</li>
            <li>檢查資料庫 wp_options 表的寫入權限</li>
        </ol>
    </div>
    
    <p><a href="<?php echo admin_url('admin.php?page=ai-content-generator-settings'); ?>">返回設定頁面</a></p>
</body>
</html>