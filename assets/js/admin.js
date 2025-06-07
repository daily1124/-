/**
 * 檔案：assets/js/admin.js
 * 功能：管理介面JavaScript
 * 
 * @package AI_SEO_Content_Generator
 */

(function($) {
    'use strict';

    /**
     * 1. 全域變數和設定
     */
    const AISC = {
        ajaxUrl: aisc_admin.ajax_url,
        apiUrl: aisc_admin.api_url,
        nonce: aisc_admin.nonce,
        strings: aisc_admin.strings,
        currentPage: null,
        isProcessing: false
    };

    /**
     * 2. 初始化
     */
    $(document).ready(function() {
        // 識別當前頁面
        AISC.currentPage = $('#adminmenu .current').find('a[href*="aisc-"]').attr('href');
        
        // 初始化對應功能
        if (AISC.currentPage) {
            const page = AISC.currentPage.match(/page=aisc-([^&]+)/);
            if (page && page[1]) {
                initializePage(page[1]);
            }
        }
        
        // 通用事件綁定
        bindGlobalEvents();
    });

    /**
     * 3. 頁面初始化路由
     */
    function initializePage(page) {
        switch (page) {
            case 'dashboard':
                initDashboard();
                break;
            case 'keywords':
                initKeywords();
                break;
            case 'content':
                initContentGenerator();
                break;
            case 'scheduler':
                initScheduler();
                break;
            case 'analytics':
                initAnalytics();
                break;
            case 'costs':
                initCosts();
                break;
            case 'diagnostics':
                initDiagnostics();
                break;
            case 'settings':
                initSettings();
                break;
        }
    }

    /**
     * 4. 儀表板功能
     */
    function initDashboard() {
        // 自動更新統計
        setInterval(updateDashboardStats, 60000); // 每分鐘更新
        
        // 綁定快速操作按鈕
        $('.aisc-quick-actions .button').on('click', function(e) {
            if ($(this).hasClass('aisc-generate-now')) {
                e.preventDefault();
                quickGenerateContent();
            }
        });
    }

    function updateDashboardStats() {
        $.post(AISC.ajaxUrl, {
            action: 'aisc_get_dashboard_stats',
            nonce: AISC.nonce
        }, function(response) {
            if (response.success) {
                updateStatCards(response.data);
            }
        });
    }

    function updateStatCards(stats) {
        $('.aisc-stat-card').each(function() {
            const $card = $(this);
            const statType = $card.data('stat');
            if (stats[statType] !== undefined) {
                $card.find('.stat-value').text(stats[statType]);
            }
        });
    }

    /**
     * 5. 關鍵字管理功能
     */
    function initKeywords() {
        // 立即更新按鈕
        $('#aisc-update-keywords').on('click', function(e) {
            e.preventDefault();
            updateKeywords();
        });
        
        // 批量操作
        $('form').on('submit', function(e) {
            const action = $('#bulk-action-selector-top').val();
            if (!action) {
                e.preventDefault();
                alert('請選擇要執行的操作');
                return false;
            }
            
            const checked = $('input[name="keyword_ids[]"]:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('請至少選擇一個關鍵字');
                return false;
            }
            
            if (action === 'delete') {
                if (!confirm('確定要刪除選中的關鍵字嗎？')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // 全選功能
        $('#cb-select-all-1').on('change', function() {
            $('input[name="keyword_ids[]"]').prop('checked', this.checked);
        });
    }

    function updateKeywords() {
        if (AISC.isProcessing) return;
        
        const $button = $('#aisc-update-keywords');
        const originalText = $button.text();
        
        AISC.isProcessing = true;
        $button.text('更新中...').prop('disabled', true);
        
        showNotice('正在更新關鍵字，請稍候...', 'info');
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_update_keywords',
            nonce: AISC.nonce,
            type: 'all'
        }, function(response) {
            if (response.success) {
                showNotice('關鍵字更新成功！共更新 ' + response.data.count + ' 個關鍵字', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotice('更新失敗：' + response.data.message, 'error');
            }
        }).fail(function() {
            showNotice('網路錯誤，請重試', 'error');
        }).always(function() {
            AISC.isProcessing = false;
            $button.text(originalText).prop('disabled', false);
        });
    }

    /**
     * 6. 內容生成功能
     */
    function initContentGenerator() {
        // 成本估算
        $('#word_count, #model, #images').on('change', estimateCost);
        estimateCost();
        
        // 表單提交
        $('#aisc-content-form').on('submit', function(e) {
            e.preventDefault();
            generateContent();
        });
        
        // 分類多選優化
        $('#categories').select2({
            placeholder: '選擇分類',
            width: '100%'
        });
    }

    function estimateCost() {
        const wordCount = parseInt($('#word_count').val()) || 0;
        const model = $('#model').val();
        const imageCount = parseInt($('#images').val()) || 0;
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_estimate_cost',
            nonce: AISC.nonce,
            word_count: wordCount,
            model: model,
            images: imageCount
        }, function(response) {
            if (response.success) {
                $('#estimated-cost').html(
                    `NT$ ${response.data.text_cost} (文字) + NT$ ${response.data.image_cost} (圖片) = <strong>NT$ ${response.data.total_cost}</strong>`
                );
            }
        });
    }

    function generateContent() {
        if (AISC.isProcessing) return;
        
        const formData = $('#aisc-content-form').serialize();
        
        AISC.isProcessing = true;
        $('#generation-progress').show();
        updateProgress(0, '初始化...');
        
        // 建立 EventSource 來接收生成進度
        const eventSource = new EventSource(AISC.apiUrl + 'generate-content?' + formData);
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            
            if (data.progress) {
                updateProgress(data.progress, data.message);
            }
            
            if (data.complete) {
                eventSource.close();
                AISC.isProcessing = false;
                
                if (data.success) {
                    showNotice('文章生成成功！', 'success');
                    window.location.href = data.edit_link;
                } else {
                    showNotice('生成失敗：' + data.message, 'error');
                    $('#generation-progress').hide();
                }
            }
        };
        
        eventSource.onerror = function() {
            eventSource.close();
            AISC.isProcessing = false;
            showNotice('連接錯誤，請重試', 'error');
            $('#generation-progress').hide();
        };
    }

    function updateProgress(percent, message) {
        $('.progress-fill').css('width', percent + '%').text(percent + '%');
        $('.progress-status').text(message);
    }

    /**
     * 7. 排程管理功能
     */
    function initScheduler() {
        // 新增排程
        $('#aisc-add-schedule').on('click', function(e) {
            e.preventDefault();
            openScheduleModal();
        });
        
        // 編輯排程
        $('.aisc-edit-schedule').on('click', function(e) {
            e.preventDefault();
            const scheduleId = $(this).data('id');
            openScheduleModal(scheduleId);
        });
        
        // 切換排程狀態
        $('.aisc-toggle-schedule').on('click', function(e) {
            e.preventDefault();
            const scheduleId = $(this).data('id');
            toggleSchedule(scheduleId, $(this));
        });
        
        // 刪除排程
        $('.aisc-delete-schedule').on('click', function(e) {
            e.preventDefault();
            if (confirm('確定要刪除這個排程嗎？')) {
                deleteSchedule($(this).data('id'));
            }
        });
        
        // 排程表單提交
        $('#schedule-form').on('submit', function(e) {
            e.preventDefault();
            saveSchedule();
        });
    }

    function openScheduleModal(scheduleId = null) {
        if (scheduleId) {
            // 載入排程資料
            $.post(AISC.ajaxUrl, {
                action: 'aisc_get_schedule',
                nonce: AISC.nonce,
                id: scheduleId
            }, function(response) {
                if (response.success) {
                    fillScheduleForm(response.data);
                    $('#schedule-form-modal').show();
                }
            });
        } else {
            // 清空表單
            $('#schedule-form')[0].reset();
            $('#schedule_id').val('');
            $('#schedule-form-modal').show();
        }
    }

    function fillScheduleForm(data) {
        $('#schedule_id').val(data.id);
        $('#schedule_name').val(data.name);
        $('#schedule_type').val(data.type);
        $('#frequency').val(data.frequency);
        $('#start_time').val(data.start_time);
        $('#keyword_count').val(data.keyword_count);
        
        // 填充內容設定
        if (data.content_settings) {
            $('input[name="word_count"]').val(data.content_settings.word_count || 8000);
            $('input[name="image_count"]').val(data.content_settings.image_count || 5);
            $('select[name="ai_model"]').val(data.content_settings.ai_model || 'gpt-4-turbo-preview');
        }
    }

    function saveSchedule() {
        const formData = $('#schedule-form').serialize();
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_save_schedule',
            nonce: AISC.nonce,
            data: formData
        }, function(response) {
            if (response.success) {
                showNotice('排程已儲存', 'success');
                $('#schedule-form-modal').hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotice('儲存失敗：' + response.data.message, 'error');
            }
        });
    }

    function toggleSchedule(scheduleId, $button) {
        const currentStatus = $button.text().trim() === '停用' ? 'active' : 'inactive';
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_toggle_schedule',
            nonce: AISC.nonce,
            id: scheduleId,
            status: newStatus
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                showNotice('操作失敗', 'error');
            }
        });
    }

    function deleteSchedule(scheduleId) {
        $.post(AISC.ajaxUrl, {
            action: 'aisc_delete_schedule',
            nonce: AISC.nonce,
            id: scheduleId
        }, function(response) {
            if (response.success) {
                showNotice('排程已刪除', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotice('刪除失敗', 'error');
            }
        });
    }

    // 關閉模態框
    window.closeScheduleModal = function() {
        $('#schedule-form-modal').hide();
    };

    /**
     * 8. 效能分析功能
     */
    function initAnalytics() {
        // 時間篩選
        $('#analytics-period').on('change', function() {
            const period = $(this).val();
            window.location.href = updateQueryString('period', period);
        });
        
        // 載入圖表
        if (typeof Chart !== 'undefined') {
            loadAnalyticsCharts();
        }
    }

    function loadAnalyticsCharts() {
        $.post(AISC.ajaxUrl, {
            action: 'aisc_get_analytics_data',
            nonce: AISC.nonce,
            period: $('#analytics-period').val()
        }, function(response) {
            if (response.success) {
                renderCharts(response.data);
            }
        });
    }

    function renderCharts(data) {
        // 流量趨勢圖
        if (data.traffic_chart_data) {
            const ctx = document.getElementById('traffic-chart');
            if (ctx) {
                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: data.traffic_chart_data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }
        
        // 關鍵字表現圖
        if (data.keywords_chart_data) {
            const ctx = document.getElementById('keywords-chart');
            if (ctx) {
                new Chart(ctx.getContext('2d'), {
                    type: 'bar',
                    data: data.keywords_chart_data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }
    }

    /**
     * 9. 成本控制功能
     */
    function initCosts() {
        // 自動更新成本顯示
        updateCostDisplay();
        
        // 匯出報告
        $('#export-cost-report').on('click', function() {
            exportCostReport();
        });
    }

    function updateCostDisplay() {
        $('.cost-stat').each(function() {
            const $stat = $(this);
            const used = parseFloat($stat.data('used'));
            const budget = parseFloat($stat.data('budget'));
            
            if (budget > 0) {
                const percentage = Math.min(100, (used / budget) * 100);
                $stat.find('.progress-bar').css('width', percentage + '%');
                
                // 變更顏色
                if (percentage >= 90) {
                    $stat.find('.progress-bar').css('background', 'var(--aisc-error)');
                } else if (percentage >= 80) {
                    $stat.find('.progress-bar').css('background', 'var(--aisc-warning)');
                }
            }
        });
    }

    function exportCostReport() {
        const format = $('#export-format').val() || 'csv';
        const period = $('#export-period').val() || '30days';
        
        window.location.href = AISC.ajaxUrl + '?action=aisc_export_cost_report&format=' + format + '&period=' + period + '&nonce=' + AISC.nonce;
    }

    /**
     * 10. 診斷工具功能
     */
    function initDiagnostics() {
        // 功能測試
        $('.test-buttons .button').on('click', function() {
            const testType = $(this).data('test');
            runDiagnosticTest(testType);
        });
        
        // 日誌控制
        $('#refresh-logs').on('click', refreshLogs);
        $('#clear-logs').on('click', clearLogs);
        $('#log-level, #log-date').on('change', refreshLogs);
        
        // 資料庫工具
        $('#optimize-tables').on('click', function() {
            runDatabaseAction('optimize');
        });
        
        $('#repair-tables').on('click', function() {
            runDatabaseAction('repair');
        });
        
        $('#backup-data').on('click', function() {
            runDatabaseAction('backup');
        });
        
        $('#reset-plugin').on('click', function() {
            if (confirm('警告：這將刪除所有外掛資料！確定要繼續嗎？')) {
                if (confirm('再次確認：這個操作無法撤銷！')) {
                    runDatabaseAction('reset');
                }
            }
        });
    }

    function runDiagnosticTest(testType) {
        const $button = $(`[data-test="${testType}"]`);
        const originalText = $button.text();
        
        $button.text('測試中...').prop('disabled', true);
        $('#test-results').show();
        $('.test-output').html('<div class="aisc-loading"></div> 正在執行測試...');
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_run_diagnostic_test',
            nonce: AISC.nonce,
            test: testType
        }, function(response) {
            let output = '';
            
            if (response.success) {
                output = `<strong style="color: green;">✓ ${response.message}</strong>\n\n`;
                if (response.details) {
                    output += '詳細資訊：\n' + JSON.stringify(response.details, null, 2);
                }
            } else {
                output = `<strong style="color: red;">✗ ${response.message}</strong>\n\n`;
                if (response.error) {
                    output += '錯誤：' + response.error + '\n';
                }
                if (response.details) {
                    output += '\n詳細資訊：\n' + JSON.stringify(response.details, null, 2);
                }
            }
            
            $('.test-output').text(output);
        }).fail(function() {
            $('.test-output').html('<strong style="color: red;">測試失敗：網路錯誤</strong>');
        }).always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    }

    function refreshLogs() {
        const level = $('#log-level').val();
        const date = $('#log-date').val();
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_get_logs',
            nonce: AISC.nonce,
            level: level,
            date: date
        }, function(response) {
            if (response.success) {
                $('#log-display').text(response.data);
            }
        });
    }

    function clearLogs() {
        if (!confirm('確定要清除日誌嗎？')) return;
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_clear_logs',
            nonce: AISC.nonce,
            level: $('#log-level').val()
        }, function(response) {
            if (response.success) {
                showNotice('日誌已清除', 'success');
                refreshLogs();
            }
        });
    }

    function runDatabaseAction(action) {
        const $button = $(`#${action}-tables, #${action}-data, #${action}-plugin`);
        const originalText = $button.text();
        
        $button.text('處理中...').prop('disabled', true);
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_database_action',
            nonce: AISC.nonce,
            db_action: action
        }, function(response) {
            if (response.success) {
                showNotice(response.data.message, 'success');
                
                if (action === 'backup' && response.data.download_url) {
                    window.location.href = response.data.download_url;
                } else if (action === 'reset') {
                    setTimeout(() => location.reload(), 2000);
                }
            } else {
                showNotice('操作失敗：' + response.data.message, 'error');
            }
        }).fail(function() {
            showNotice('網路錯誤', 'error');
        }).always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    }

    /**
     * 11. 設定頁面功能
     */
    function initSettings() {
        // API Key 顯示/隱藏
        $('.toggle-password').on('click', function() {
            const $input = $(this).prev('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).text(type === 'password' ? '顯示' : '隱藏');
        });
        
        // 測試API連接
        $('#test-api-connection').on('click', function() {
            testAPIConnection();
        });
    }

    function testAPIConnection() {
        const apiKey = $('#openai_api_key').val();
        
        if (!apiKey) {
            showNotice('請先輸入API Key', 'error');
            return;
        }
        
        const $button = $('#test-api-connection');
        const originalText = $button.text();
        
        $button.text('測試中...').prop('disabled', true);
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_test_api_connection',
            nonce: AISC.nonce,
            api_key: apiKey
        }, function(response) {
            if (response.success) {
                showNotice('API連接成功！', 'success');
            } else {
                showNotice('API連接失敗：' + response.data.message, 'error');
            }
        }).fail(function() {
            showNotice('網路錯誤', 'error');
        }).always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    }

    /**
     * 12. 通用功能
     */
    function bindGlobalEvents() {
        // 通知關閉按鈕
        $(document).on('click', '.notice-dismiss', function() {
            $(this).parent('.notice').fadeOut();
        });
        
        // Tab 切換
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.tab-content').hide();
            $(target).show();
        });
        
        // 確認對話框
        $('[data-confirm]').on('click', function(e) {
            const message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
        
        // AJAX 載入指示器
        $(document).ajaxStart(function() {
            $('body').addClass('aisc-loading');
        }).ajaxStop(function() {
            $('body').removeClass('aisc-loading');
        });
    }

    /**
     * 13. 輔助函數
     */
    function showNotice(message, type = 'info') {
        const noticeClass = type === 'error' ? 'notice-error' : 
                          type === 'success' ? 'notice-success' : 
                          type === 'warning' ? 'notice-warning' : 'notice-info';
        
        const $notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">關閉通知</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend($notice);
        
        // 自動關閉
        setTimeout(() => {
            $notice.fadeOut(() => $notice.remove());
        }, 5000);
    }

    function updateQueryString(key, value) {
        const url = new URL(window.location);
        url.searchParams.set(key, value);
        return url.toString();
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('zh-TW').format(num);
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('zh-TW', {
            style: 'currency',
            currency: 'TWD',
            minimumFractionDigits: 0
        }).format(amount);
    }

    /**
     * 14. 快速內容生成
     */
    function quickGenerateContent() {
        const modal = `
            <div class="aisc-quick-generate-modal" style="display: none;">
                <div class="modal-content">
                    <h2>快速生成文章</h2>
                    <form id="quick-generate-form">
                        <p>
                            <label>關鍵字：</label>
                            <input type="text" name="keyword" required>
                        </p>
                        <p>
                            <label>字數：</label>
                            <input type="number" name="word_count" value="8000" min="1500">
                        </p>
                        <p>
                            <label>模型：</label>
                            <select name="model">
                                <option value="gpt-4-turbo-preview">GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                            </select>
                        </p>
                        <p class="submit">
                            <button type="submit" class="button button-primary">開始生成</button>
                            <button type="button" class="button cancel">取消</button>
                        </p>
                    </form>
                </div>
            </div>
        `;
        
        $('body').append(modal);
        const $modal = $('.aisc-quick-generate-modal');
        $modal.show();
        
        $modal.find('.cancel').on('click', function() {
            $modal.remove();
        });
        
        $modal.find('form').on('submit', function(e) {
            e.preventDefault();
            const data = $(this).serialize();
            $modal.remove();
            
            // 跳轉到內容生成頁面並帶參數
            window.location.href = AISC.adminUrl + 'admin.php?page=aisc-content&' + data;
        });
    }

    /**
     * 15. 數據匯出功能
     */
    window.exportData = function(type, format) {
        const params = {
            action: 'aisc_export_data',
            type: type,
            format: format || 'csv',
            nonce: AISC.nonce
        };
        
        const queryString = $.param(params);
        window.location.href = AISC.ajaxUrl + '?' + queryString;
    };

    /**
     * 16. 自動儲存功能
     */
    function initAutoSave() {
        let saveTimer;
        const $forms = $('form[data-autosave="true"]');
        
        $forms.find('input, select, textarea').on('change keyup', function() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                autoSaveForm($(this).closest('form'));
            }, 2000);
        });
    }

    function autoSaveForm($form) {
        const formData = $form.serialize();
        const formId = $form.attr('id');
        
        $.post(AISC.ajaxUrl, {
            action: 'aisc_autosave',
            nonce: AISC.nonce,
            form_id: formId,
            data: formData
        }, function(response) {
            if (response.success) {
                showNotice('自動儲存成功', 'info');
            }
        });
    }

    /**
     * 17. 鍵盤快捷鍵
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl+S 儲存
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                $('form:visible').first().submit();
            }
            
            // Esc 關閉模態框
            if (e.key === 'Escape') {
                $('.aisc-modal:visible').hide();
            }
        });
    }

    // 初始化額外功能
    initAutoSave();
    initKeyboardShortcuts();

})(jQuery);
