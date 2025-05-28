<?php
/**
 * 分類初始化腳本
 * 使用方法：將此檔案放在 includes 目錄，然後訪問 /wp-content/plugins/ai-content-generator/includes/init-categories.php
 */

// 載入 WordPress
require_once('../../../../wp-load.php');

// 檢查是否為管理員
if (!current_user_can('manage_options')) {
    die('需要管理員權限');
}

// 定義要創建的分類
$categories_to_create = array(
    '所有文章',
    '娛樂城教學',
    '虛擬貨幣',
    '體育',
    '科技',
    '健康',
    '新聞',
    '明星',
    '汽車',
    '理財',
    '生活',
    '社會',
    '美食',
    '追劇'
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>AICG 分類初始化</title>
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
        .category-item {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>AI Content Generator 分類初始化</h1>
    
    <h2>開始創建分類...</h2>
    
    <?php
    $created_count = 0;
    $existing_count = 0;
    $error_count = 0;
    
    foreach ($categories_to_create as $category_name) {
        echo '<div class="category-item">';
        echo '<strong>' . esc_html($category_name) . '</strong>: ';
        
        // 檢查分類是否已存在
        $existing_category = get_term_by('name', $category_name, 'category');
        
        if ($existing_category) {
            echo '<span class="info">已存在 (ID: ' . $existing_category->term_id . ')</span>';
            $existing_count++;
        } else {
            // 創建分類
            $result = wp_insert_term($category_name, 'category');
            
            if (is_wp_error($result)) {
                echo '<span class="error">創建失敗 - ' . $result->get_error_message() . '</span>';
                $error_count++;
            } else {
                echo '<span class="success">創建成功 (ID: ' . $result['term_id'] . ')</span>';
                $created_count++;
            }
        }
        
        echo '</div>';
    }
    ?>
    
    <h2>初始化結果</h2>
    <ul>
        <li>新創建的分類：<strong><?php echo $created_count; ?></strong> 個</li>
        <li>已存在的分類：<strong><?php echo $existing_count; ?></strong> 個</li>
        <li>創建失敗的分類：<strong><?php echo $error_count; ?></strong> 個</li>
    </ul>
    
    <h2>現有分類列表</h2>
    <?php
    $all_categories = get_categories(array(
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if ($all_categories) {
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<thead>';
        echo '<tr style="background: #f0f0f0;">';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">ID</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">名稱</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">代稱</th>';
        echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">文章數</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($all_categories as $category) {
            $is_target = in_array($category->name, $categories_to_create);
            $row_style = $is_target ? 'background: #e8f5e9;' : '';
            
            echo '<tr style="' . $row_style . '">';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . $category->term_id . '</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>' . esc_html($category->name) . '</strong></td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($category->slug) . '</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . $category->count . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    ?>
    
    <h2>設定"所有文章"為預設分類</h2>
    <?php
    $all_articles_cat = get_term_by('name', '所有文章', 'category');
    if ($all_articles_cat) {
        update_option('default_category', $all_articles_cat->term_id);
        echo '<p class="success">已將"所有文章"設定為預設分類 (ID: ' . $all_articles_cat->term_id . ')</p>';
    } else {
        echo '<p class="error">找不到"所有文章"分類</p>';
    }
    ?>
    
    <h2>完成！</h2>
    <p>分類初始化已完成。</p>
    <p><a href="<?php echo admin_url('edit-tags.php?taxonomy=category'); ?>">前往分類管理頁面</a></p>
    <p><a href="<?php echo admin_url('admin.php?page=ai-content-generator'); ?>">返回 AI Content Generator</a></p>
</body>
</html>