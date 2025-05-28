<?php
/**
 * 火山引擎 API 診斷工具
 * 使用方法：訪問 /wp-content/plugins/ai-content-generator/includes/test-volcengine-api.php
 */

// 載入 WordPress
require_once('../../../../wp-load.php');

// 檢查是否為管理員
if (!current_user_can('manage_options')) {
    die('需要管理員權限');
}

// 處理測試請求
$test_result = '';
$test_type = '';

if (isset($_POST['test_auth'])) {
    $test_type = 'auth';
    $access_key_id = sanitize_text_field($_POST['access_key_id']);
    $secret_access_key = sanitize_text_field($_POST['secret_access_key']);
    
    // 臨時保存認證資訊
    update_option('aicg_volcengine_access_key_id', $access_key_id);
    update_option('aicg_volcengine_secret_access_key', $secret_access_key);
    
    // 執行簡單的簽名測試
    $test_result = test_volcengine_signature($access_key_id, $secret_access_key);
}

// 簽名測試函數
function test_volcengine_signature($access_key_id, $secret_access_key) {
    $result = "=== 火山引擎簽名測試 ===\n\n";
    
    // 測試參數
    $service = 'cv';
    $region = 'cn-north-1';
    $host = 'visual.volcengineapi.com';
    $action = 'GetImageTemplate';
    $version = '2022-08-31';
    
    // 生成時間戳
    $timestamp = time();
    $datetime = gmdate('Ymd\THis\Z', $timestamp);
    $date = gmdate('Ymd', $timestamp);
    
    $result .= "測試時間: $datetime\n";
    $result .= "Access Key ID 長度: " . strlen($access_key_id) . "\n";
    $result .= "Secret Access Key 長度: " . strlen($secret_access_key) . "\n\n";
    
    // Step 1: 構建規範請求
    $method = 'POST';
    $canonical_uri = '/';
    $query_string = "Action=$action&Version=$version";
    
    // 請求體
    $body = json_encode(['TemplateId' => 'test']);
    $content_hash = hash('sha256', $body);
    
    // 請求頭
    $headers = [
        'content-type' => 'application/json',
        'host' => $host,
        'x-content-sha256' => $content_hash,
        'x-date' => $datetime,
    ];
    
    // 規範請求頭
    $canonical_headers = '';
    $signed_headers_arr = [];
    ksort($headers);
    foreach ($headers as $key => $value) {
        $canonical_headers .= $key . ':' . trim($value) . "\n";
        $signed_headers_arr[] = $key;
    }
    $signed_headers = implode(';', $signed_headers_arr);
    
    // 構建規範請求
    $canonical_request = implode("\n", [
        $method,
        $canonical_uri,
        $query_string,
        $canonical_headers,
        $signed_headers,
        $content_hash
    ]);
    
    $result .= "=== 規範請求 ===\n";
    $result .= $canonical_request . "\n\n";
    
    // Step 2: 構建待簽名字符串
    $credential_scope = "$date/$region/$service/request";
    $string_to_sign = implode("\n", [
        'HMAC-SHA256',
        $datetime,
        $credential_scope,
        hash('sha256', $canonical_request)
    ]);
    
    $result .= "=== 待簽名字符串 ===\n";
    $result .= $string_to_sign . "\n\n";
    
    // Step 3: 計算簽名
    $k_secret = 'VOLC' . $secret_access_key;
    $k_date = hash_hmac('sha256', $date, $k_secret, true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', $service, $k_region, true);
    $k_signing = hash_hmac('sha256', 'request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
    
    $result .= "=== 簽名結果 ===\n";
    $result .= "Signature: $signature\n\n";
    
    // Step 4: 構建授權頭
    $authorization = sprintf(
        'HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
        $access_key_id,
        $credential_scope,
        $signed_headers,
        $signature
    );
    
    $result .= "=== Authorization Header ===\n";
    $result .= $authorization . "\n\n";
    
    // Step 5: 發送測試請求
    $url = "https://$host/?$query_string";
    $request_headers = [
        'Authorization' => $authorization,
        'Content-Type' => 'application/json',
        'Host' => $host,
        'X-Content-Sha256' => $content_hash,
        'X-Date' => $datetime,
    ];
    
    $result .= "=== 發送請求 ===\n";
    $result .= "URL: $url\n";
    $result .= "Headers:\n";
    foreach ($request_headers as $key => $value) {
        $result .= "  $key: $value\n";
    }
    $result .= "\nBody: $body\n\n";
    
    // 發送請求
    $response = wp_remote_post($url, [
        'timeout' => 30,
        'headers' => $request_headers,
        'body' => $body,
    ]);
    
    if (is_wp_error($response)) {
        $result .= "=== 請求錯誤 ===\n";
        $result .= $response->get_error_message() . "\n";
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $result .= "=== 回應 ===\n";
        $result .= "Status Code: $response_code\n";
        $result .= "Response Body:\n" . $response_body . "\n\n";
        
        // 解析錯誤訊息
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            if (isset($error_data['ResponseMetadata']['Error'])) {
                $error = $error_data['ResponseMetadata']['Error'];
                $result .= "\n=== 錯誤分析 ===\n";
                $result .= "錯誤代碼: " . ($error['Code'] ?? 'Unknown') . "\n";
                $result .= "錯誤訊息: " . ($error['Message'] ?? 'Unknown') . "\n";
                
                if (strpos($error['Message'] ?? '', 'signature') !== false) {
                    $result .= "\n建議檢查：\n";
                    $result .= "1. Access Key ID 和 Secret Access Key 是否正確\n";
                    $result .= "2. 確認密鑰沒有多餘的空格或換行\n";
                    $result .= "3. 確認密鑰是否已啟用\n";
                    $result .= "4. 確認時區設定是否正確（伺服器時間）\n";
                }
            }
        } else {
            $result .= "\n✅ 認證成功！\n";
        }
    }
    
    return $result;
}

// 獲取當前設定
$current_access_key_id = get_option('aicg_volcengine_access_key_id', '');
$current_secret_access_key = get_option('aicg_volcengine_secret_access_key', '');

?>
<!DOCTYPE html>
<html>
<head>
    <title>火山引擎 API 診斷工具</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1200px;
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
        h1 {
            color: #333;
            border-bottom: 2px solid #2196f3;
            padding-bottom: 10px;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            box-sizing: border-box;
        }
        button {
            background: #2196f3;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background: #1976d2;
        }
        .result {
            background: #000;
            color: #0f0;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            white-space: pre-wrap;
            font-size: 12px;
            line-height: 1.5;
            max-height: 600px;
            overflow-y: auto;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .current-status {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .toggle-password {
            cursor: pointer;
            color: #2196f3;
            font-size: 12px;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 火山引擎 API 診斷工具</h1>
        
        <div class="info">
            <strong>說明：</strong>此工具用於診斷火山引擎 API 簽名問題。它會顯示詳細的簽名計算過程，幫助您找出問題所在。
        </div>
        
        <div class="current-status">
            <h3>當前設定狀態</h3>
            <p><strong>Access Key ID：</strong> 
                <?php 
                if ($current_access_key_id) {
                    echo '已設定（' . strlen($current_access_key_id) . ' 字元）';
                } else {
                    echo '<span style="color: red;">未設定</span>';
                }
                ?>
            </p>
            <p><strong>Secret Access Key：</strong> 
                <?php 
                if ($current_secret_access_key) {
                    echo '已設定（' . strlen($current_secret_access_key) . ' 字元）';
                } else {
                    echo '<span style="color: red;">未設定</span>';
                }
                ?>
            </p>
            <p><strong>伺服器時間：</strong> <?php echo gmdate('Y-m-d H:i:s') . ' UTC'; ?></p>
            <p><strong>時區：</strong> <?php echo date_default_timezone_get(); ?></p>
        </div>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="access_key_id">Access Key ID:</label>
                <input type="text" 
                       id="access_key_id" 
                       name="access_key_id" 
                       value="<?php echo esc_attr($current_access_key_id); ?>"
                       placeholder="請輸入火山引擎 Access Key ID">
            </div>
            
            <div class="form-group">
                <label for="secret_access_key">
                    Secret Access Key: 
                    <span class="toggle-password" onclick="togglePassword()">顯示/隱藏</span>
                </label>
                <input type="password" 
                       id="secret_access_key" 
                       name="secret_access_key" 
                       value="<?php echo esc_attr($current_secret_access_key); ?>"
                       placeholder="請輸入火山引擎 Secret Access Key">
            </div>
            
            <button type="submit" name="test_auth">🚀 執行簽名測試</button>
        </form>
        
        <?php if ($test_result): ?>
        <div class="result">
<?php echo htmlspecialchars($test_result); ?>
        </div>
        <?php endif; ?>
        
        <div class="warning">
            <strong>注意事項：</strong>
            <ul>
                <li>請確保 Access Key ID 和 Secret Access Key 正確無誤</li>
                <li>密鑰前後不要有空格或換行符號</li>
                <li>確認密鑰在火山引擎控制台已啟用</li>
                <li>如果仍有問題，請檢查伺服器時間是否準確（誤差不能超過5分鐘）</li>
            </ul>
        </div>
        
        <p style="text-align: center; margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=ai-content-generator-settings'); ?>">返回設定頁面</a>
        </p>
    </div>
    
    <script>
    function togglePassword() {
        var field = document.getElementById('secret_access_key');
        field.type = field.type === 'password' ? 'text' : 'password';
    }
    </script>
</body>
</html>