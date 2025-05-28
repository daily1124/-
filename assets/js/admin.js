jQuery(document).ready(function($) {
    // 生成文章按鈕
    $('#aicg-generate-now').on('click', function() {
        var keywordType = $('#aicg-keyword-type').val();
        
        if (!confirm('確定要使用' + $('#aicg-keyword-type option:selected').text() + '生成文章嗎？')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        button.text('正在生成文章...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_generate_post',
            keyword_type: keywordType,
            nonce: aicg_ajax.nonce
        }, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                // 使用更友善的提示方式
                showNotice('文章生成成功！', 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotice('錯誤: ' + (response.data.message || '未知錯誤'), 'error');
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            showNotice('請求失敗，請稍後再試', 'error');
        });
    });
    
    // 更新關鍵字按鈕
    $('#aicg-update-keywords').on('click', function() {
        if (!confirm('確定要更新關鍵字嗎？這將覆蓋現有關鍵字。')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        button.text('正在抓取關鍵字...').prop('disabled', true);
        
        $.post(aicg_ajax.ajax_url, {
            action: 'aicg_fetch_keywords',
            type: 'taiwan',
            nonce: aicg_ajax.nonce
        }, function(response) {
            button.text(originalText).prop('disabled', false);
            
            if (response.success) {
                showNotice(response.data.message || '關鍵字更新成功！', 'success');
            } else {
                showNotice('錯誤: ' + (response.data.message || '關鍵字更新失敗'), 'error');
            }
        }).fail(function() {
            button.text(originalText).prop('disabled', false);
            showNotice('請求失敗，請稍後再試', 'error');
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
                showNotice(response.data.message || '台灣關鍵字更新成功！', 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotice('錯誤: ' + (response.data.message || '關鍵字更新失敗'), 'error');
            }
        }).fail(function(xhr, status, error) {
            button.html(originalText).prop('disabled', false);
            console.error('AJAX Error:', status, error);
            showNotice('請求失敗，請稍後再試。錯誤: ' + error, 'error');
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
                showNotice(response.data.message || '娛樂城關鍵字更新成功！', 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotice('錯誤: ' + (response.data.message || '關鍵字更新失敗'), 'error');
            }
        }).fail(function(xhr, status, error) {
            button.html(originalText).prop('disabled', false);
            console.error('AJAX Error:', status, error);
            showNotice('請求失敗，請稍後再試。錯誤: ' + error, 'error');
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
                showNotice(response.data.message || '排程已更新', 'success');
                if (response.data.next_run) {
                    console.log('下次執行時間:', response.data.next_run);
                }
            } else {
                showNotice('錯誤: ' + (response.data.message || '排程更新失敗'), 'error');
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
                showNotice(response.data.message || '排程任務已執行', 'success');
            } else {
                showNotice('錯誤: ' + (response.data.message || '執行失敗'), 'error');
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
                showNotice(response.data.message || '關鍵字更新成功！', 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotice('錯誤: ' + (response.data.message || '更新失敗'), 'error');
            }
        }).fail(function() {
            button.html(originalHtml).prop('disabled', false);
            showNotice('請求失敗，請稍後再試', 'error');
        });
    });
    
    // 使用關鍵字按鈕
    $('.aicg-use-keyword').on('click', function() {
        var keyword = $(this).data('keyword');
        showNotice('已選擇關鍵字: ' + keyword, 'info');
    });
    
    // 匯出關鍵字
    $('.aicg-export-keywords').on('click', function() {
        var type = $(this).data('type');
        var nonce = $(this).data('nonce');
        
        // 創建下載連結
        var exportUrl = aicg_ajax.ajax_url + '?action=aicg_export_keywords&type=' + type + '&nonce=' + nonce;
        window.location.href = exportUrl;
    });
    
    // 友善的通知函數
    function showNotice(message, type) {
        // 移除現有通知
        $('.aicg-notice-temp').remove();
        
        var noticeClass = 'notice notice-' + (type === 'error' ? 'error' : type === 'success' ? 'success' : 'info');
        var notice = $('<div class="' + noticeClass + ' aicg-notice-temp is-dismissible" style="position: fixed; top: 50px; right: 20px; z-index: 9999; max-width: 400px;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">關閉此通知。</span></button></div>');
        
        $('body').append(notice);
        
        // 自動關閉
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // 點擊關閉
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }
    
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
});