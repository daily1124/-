jQuery(document).ready(function($) {
    // 生成文章按鈕
    $('#aicg-generate-now').on('click', function() {
        if (!confirm(aicg_ajax.strings.confirm_generate || '確定要立即生成文章嗎？')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        button.text(aicg_ajax.strings.generating || '正在生成文章...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_generate_post',
            nonce: aicg_ajax.nonce
        }, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(aicg_ajax.strings.success || '文章生成成功！');
                location.reload();
            } else {
                alert((aicg_ajax.strings.error || '錯誤') + ': ' + (response.data.message || '未知錯誤'));
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert(aicg_ajax.strings.error || '請求失敗，請稍後再試');
        });
    });
    
    // 更新關鍵字按鈕
    $('#aicg-update-keywords').on('click', function() {
        if (!confirm(aicg_ajax.strings.confirm_fetch || '確定要更新關鍵字嗎？這將覆蓋現有關鍵字。')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        button.text(aicg_ajax.strings.fetching || '正在抓取關鍵字...').prop('disabled', true);
        
        // 預設抓取台灣關鍵字
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_fetch_keywords',
            type: 'taiwan',
            nonce: aicg_ajax.nonce
        }, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(response.data.message || '關鍵字更新成功！');
            } else {
                alert('錯誤: ' + (response.data.message || '關鍵字更新失敗'));
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            alert('請求失敗，請稍後再試');
        });
    });
    
    // 抓取台灣關鍵字
    $('#aicg-fetch-taiwan-keywords').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        button.html('<span class="spinner is-active"></span> 抓取中...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_fetch_keywords',
            type: 'taiwan',
            nonce: aicg_ajax.nonce
        }, function(response) {
            button.html(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(response.data.message || '台灣關鍵字更新成功！');
                location.reload();
            } else {
                alert('錯誤: ' + (response.data.message || '關鍵字更新失敗'));
            }
        }).fail(function(xhr, status, error) {
            button.html(originalText).prop('disabled', false);
            console.error('AJAX Error:', status, error);
            alert('請求失敗，請稍後再試。錯誤: ' + error);
        });
    });
    
    // 抓取娛樂城關鍵字
    $('#aicg-fetch-casino-keywords').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        button.html('<span class="spinner is-active"></span> 抓取中...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_fetch_keywords',
            type: 'casino',
            nonce: aicg_ajax.nonce
        }, function(response) {
            button.html(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(response.data.message || '娛樂城關鍵字更新成功！');
                location.reload();
            } else {
                alert('錯誤: ' + (response.data.message || '關鍵字更新失敗'));
            }
        }).fail(function(xhr, status, error) {
            button.html(originalText).prop('disabled', false);
            console.error('AJAX Error:', status, error);
            alert('請求失敗，請稍後再試。錯誤: ' + error);
        });
    });
    
    // 更新排程
    $('#aicg-update-schedule').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        button.text('更新中...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_update_schedule',
            nonce: aicg_ajax.nonce
        }, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                alert(response.data.message || '排程已更新');
                if (response.data.next_run) {
                    console.log('下次執行時間:', response.data.next_run);
                }
            } else {
                alert('錯誤: ' + (response.data.message || '排程更新失敗'));
            }
        });
    });
    
    // 立即執行排程
    $('#aicg-run-schedule-now').on('click', function() {
        if (!confirm('確定要立即執行排程任務嗎？')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        button.text('執行中...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_run_schedule_now',
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
    
    // 關鍵字頁面 - 立即更新按鈕
    $('.aicg-refresh-keywords').on('click', function() {
        var button = $(this);
        var type = button.data('type');
        var nonce = button.data('nonce');
        var originalHtml = button.html();
        
        button.html('<span class="spinner is-active"></span> 更新中...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_fetch_keywords',
            type: type,
            nonce: nonce
        }, function(response) {
            button.html(originalHtml).prop('disabled', false);
            
            if (response.success) {
                alert(response.data.message || '關鍵字更新成功！');
                location.reload();
            } else {
                alert('錯誤: ' + (response.data.message || '更新失敗'));
            }
        }).fail(function() {
            button.html(originalHtml).prop('disabled', false);
            alert('請求失敗，請稍後再試');
        });
    });
    
    // 使用關鍵字按鈕
    $('.aicg-use-keyword').on('click', function() {
        var keyword = $(this).data('keyword');
        
        // 這裡可以實現將關鍵字添加到某個地方的功能
        alert('已選擇關鍵字: ' + keyword);
        
        // TODO: 實現將關鍵字添加到生成器的功能
    });
    
    // 匯出關鍵字
    $('.aicg-export-keywords').on('click', function() {
        var type = $(this).data('type');
        var nonce = $(this).data('nonce');
        
        // 創建下載連結
        var exportUrl = aicg_ajax.ajax_url + '?action=aicg_export_keywords&type=' + type + '&nonce=' + nonce;
        window.location.href = exportUrl;
    });
    
    // 添加載入狀態指示器
    $(document).ajaxStart(function() {
        $('body').addClass('aicg-loading');
    }).ajaxStop(function() {
        $('body').removeClass('aicg-loading');
    });
    
    // 錯誤處理
    $(document).ajaxError(function(event, xhr, settings, error) {
        console.error('AJAX Error:', {
            url: settings.url,
            type: settings.type,
            data: settings.data,
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText,
            error: error
        });
    });
    
    // 修正：確保表單提交前先儲存火山引擎的認證資訊
    $('form').on('submit', function(e) {
        // 如果在 API 設定頁面
        if ($('#aicg_volcengine_access_key_id').length && $('#aicg_volcengine_secret_access_key').length) {
            // 確保值不為空
            var accessKeyId = $('#aicg_volcengine_access_key_id').val();
            var secretAccessKey = $('#aicg_volcengine_secret_access_key').val();
            
            console.log('提交前檢查火山引擎金鑰:', {
                accessKeyId: accessKeyId ? '已設定 (' + accessKeyId.length + ' 字元)' : '未設定',
                secretAccessKey: secretAccessKey ? '已設定 (' + secretAccessKey.length + ' 字元)' : '未設定'
            });
        }
        
        return true;
    });
    
    // 調試：監控火山引擎金鑰輸入
    $('#aicg_volcengine_access_key_id, #aicg_volcengine_secret_access_key').on('change', function() {
        console.log('火山引擎金鑰已更改:', {
            field: $(this).attr('id'),
            length: $(this).val().length
        });
    });
});