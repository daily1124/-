<?php
/**
 * 火山引擎設定測試腳本
 * 使用方法：將此檔案放在 includes 目錄，然後訪問 /wp-content/plugins/ai-content-generator/includes/test-volcengine.php
 */

// 載入 WordPress
require_once('../../../../wp-load.php');

// 檢查是否為管理員
if (!current_user_can('manage_options')) {
    die('需要管理員權限');
}

// 處理表單提交
$message = '';
$message_type = '';

if (isset($_POST['save_settings'])) {
    check_admin_referer('test_volcengine_settings');
    
    $access_key_id = sanitize_text_field($_POST['access_key_id']);
    $secret_access_key = sanitize_text_field($_POST['secret_access_key']);
    
    // 儲存設定
    update_option('aicg_volcengine_access_key_id', $access_key_id);
    update_option('aicg_volcengine_secret_access_key', $secret_access_key);
    
    $message = '設定已儲存！';
    $message_type = 'success';
}

if (isset($_POST['test_connection'])) {
    check_admin_referer('test_volcengine_settings');
    
    // 載入必要的類別
    require_once(dirname(__FILE__) . '/class-jimeng-handler.php');
    
    // 測試連接
    $jimeng = AICG_Jimeng_Handler::get_instance();
    $jimeng->reload_settings();
    $result = $jimeng->test_connection();
    
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

// 獲取當前設定
$access_key_id = get_option('aicg_volcengine_access_key_id', '');
$secret_access_key = get_option('aicg_volcengine_secret_access_key', '');

?>
<!DOCTYPE html>
<html>
<head>
    <title>火山引擎設定測試</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { 
            color: #4caf50; 
            background: #e8f5e9;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error { 
            color: #f44336; 
            background: #ffebee;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info { 
            color: #2196f3;
            background: #e3f2fd;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            background: #2196f3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background: #1976d2;
        }
        .button-secondary {
            background: #757575;
        }
        .button-secondary:hover {
            background: #616161;
        }
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .status-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .status-table td:first-child {
            font-weight: bold;
            width: 200px;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>火山引擎設定測試</h1>
        
        <?php if ($message): ?>
            <div class="<?php echo $message_type; ?>">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        
        <h2>當前設定狀態</h2>
        <table class="status-table">
            <tr>
                <td>Access Key ID</td>
                <td>
                    <?php 
                    if ($access_key_id) {
                        echo '<span style="color: green;">已設定</span> (長度: ' . strlen($access_key_id) . ' 字元)';
                        echo '<br>前4字元: <code>' . substr($access_key_id, 0, 4) . '...</code>';
                    } else {
                        echo '<span style="color: red;">未設定</span>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>Secret Access Key</td>
                <td>
                    <?php 
                    if ($secret_access_key) {
                        echo '<span style="color: green;">已設定</span> (長度: ' . strlen($secret_access_key) . ' 字元)';
                    } else {
                        echo '<span style="color: red;">未設定</span>';
                    }
                    ?>
                </td>
            </tr>
        </table>
        
        <h2>設定火山引擎認證</h2>
        <form method="post" action="">
            <?php wp_nonce_field('test_volcengine_settings'); ?>
            
            <label for="access_key_id">Access Key ID:</label>
            <input type="text" 
                   id="access_key_id" 
                   name="access_key_id" 
                   value="<?php echo esc_attr($access_key_id); ?>"
                   placeholder="輸入您的 Access Key ID">
            
            <label for="secret_access_key">Secret Access Key:</label>
            <input type="password" 
                   id="secret_access_key" 
                   name="secret_access_key" 
                   value="<?php echo esc_attr($secret_access_key); ?>"
                   placeholder="輸入您的 Secret Access Key">
            
            <div style="margin-top: 20px;">
                <button type="submit" name="save_settings">儲存設定</button>
                <button type="submit" name="test_connection" class="button-secondary">測試連接</button>
            </div>
        </form>
        
        <h2>除錯資訊</h2>
        <div class="info">
            <h3>資料庫中的選項值：</h3>
            <pre><?php
            $db_values = array(
                'aicg_volcengine_access_key_id' => get_option('aicg_volcengine_access_key_id'),
                'aicg_volcengine_secret_access_key' => get_option('aicg_volcengine_secret_access_key') ? '***已設定***' : '(空值)',
                'aicg_jimeng_api_key' => get_option('aicg_jimeng_api_key') ? '***已設定***' : '(空值)'
            );
            print_r($db_values);
            ?></pre>
        </div>
        
        <h2>使用說明</h2>
        <ol>
            <li>從火山引擎控制台獲取您的 Access Key ID 和 Secret Access Key</li>
            <li>在上方表單中輸入這些認證資訊</li>
            <li>點擊「儲存設定」按鈕</li>
            <li>點擊「測試連接」按鈕驗證設定是否正確</li>
        </ol>
        
        <p><a href="<?php echo admin_url('admin.php?page=ai-content-generator-settings'); ?>">返回設定頁面</a></p>
    </div>
    
    <script>
    // 顯示/隱藏密碼
    document.getElementById('secret_access_key').addEventListener('dblclick', function() {
        this.type = this.type === 'password' ? 'text' : 'password';
    });
    </script>
</body>
</html>