<?php
/**
 * Elementor 內容生成器
 * 將內容轉換為 Elementor 格式
 */

class AICG_Elementor_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 將 HTML 內容轉換為 Elementor 格式
     * @param array $post_data 包含標題、內容、圖片等資訊
     * @return array Elementor 資料結構
     */
    public function convert_to_elementor($post_data) {
        $elements = array();
        $element_id = 1;
        
        // 添加標題區塊
        if (!empty($post_data['title'])) {
            $elements[] = $this->create_heading_element($post_data['title'], 'h1', $element_id++);
        }
        
        // 添加精選圖片
        if (!empty($post_data['featured_image_id'])) {
            $elements[] = $this->create_image_element($post_data['featured_image_id'], $element_id++);
        }
        
        // 解析內容並轉換
        $content_elements = $this->parse_content($post_data['content'], $element_id);
        $elements = array_merge($elements, $content_elements);
        
        // 建立 Elementor 資料結構
        $elementor_data = array(
            array(
                'id' => $this->generate_unique_id(),
                'elType' => 'section',
                'settings' => array(
                    'structure' => '10'
                ),
                'elements' => array(
                    array(
                        'id' => $this->generate_unique_id(),
                        'elType' => 'column',
                        'settings' => array(
                            '_column_size' => 100
                        ),
                        'elements' => $elements
                    )
                )
            )
        );
        
        return $elementor_data;
    }
    
    /**
     * 解析 HTML 內容
     * @param string $content HTML 內容
     * @param int &$element_id 元素 ID 計數器
     * @return array
     */
    private function parse_content($content, &$element_id) {
        $elements = array();
        
        // 使用 DOMDocument 解析 HTML
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            // 如果沒有 body，直接處理根節點
            $nodes = $dom->childNodes;
        } else {
            $nodes = $body->childNodes;
        }
        
        foreach ($nodes as $node) {
            $element = $this->process_node($node, $element_id);
            if ($element) {
                $elements[] = $element;
            }
        }
        
        return $elements;
    }
    
    /**
     * 處理 DOM 節點
     * @param DOMNode $node
     * @param int &$element_id
     * @return array|null
     */
    private function process_node($node, &$element_id) {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return null;
        }
        
        $tag = strtolower($node->nodeName);
        
        switch ($tag) {
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                return $this->create_heading_element($node->textContent, $tag, $element_id++);
                
            case 'p':
                $text = trim($node->textContent);
                if (!empty($text)) {
                    return $this->create_text_element($text, $element_id++);
                }
                break;
                
            case 'img':
                $src = $node->getAttribute('src');
                if ($src) {
                    // 嘗試從 URL 找到附件 ID
                    $attachment_id = $this->get_attachment_id_from_url($src);
                    if ($attachment_id) {
                        return $this->create_image_element($attachment_id, $element_id++);
                    }
                }
                break;
                
            case 'ul':
            case 'ol':
                return $this->create_list_element($node, $tag, $element_id++);
                
            case 'figure':
                // 處理 WordPress 的圖片區塊
                $img = $node->getElementsByTagName('img')->item(0);
                if ($img) {
                    $src = $img->getAttribute('src');
                    $attachment_id = $this->get_attachment_id_from_url($src);
                    if ($attachment_id) {
                        return $this->create_image_element($attachment_id, $element_id++);
                    }
                }
                break;
        }
        
        return null;
    }
    
    /**
     * 創建標題元素
     */
    private function create_heading_element($text, $tag, $id) {
        return array(
            'id' => $this->generate_unique_id(),
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => array(
                'title' => $text,
                'header_size' => $tag,
                'align' => 'left',
                'title_color' => '#333333'
            )
        );
    }
    
    /**
     * 創建文字元素
     */
    private function create_text_element($text, $id) {
        return array(
            'id' => $this->generate_unique_id(),
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'settings' => array(
                'editor' => '<p>' . esc_html($text) . '</p>'
            )
        );
    }
    
    /**
     * 創建圖片元素
     */
    private function create_image_element($attachment_id, $id) {
        $image_url = wp_get_attachment_url($attachment_id);
        
        return array(
            'id' => $this->generate_unique_id(),
            'elType' => 'widget',
            'widgetType' => 'image',
            'settings' => array(
                'image' => array(
                    'id' => $attachment_id,
                    'url' => $image_url
                ),
                'image_size' => 'large',
                'align' => 'center'
            )
        );
    }
    
    /**
     * 創建列表元素
     */
    private function create_list_element($node, $list_type, $id) {
        $items = array();
        $list_items = $node->getElementsByTagName('li');
        
        foreach ($list_items as $index => $li) {
            $items[] = array(
                '_id' => $this->generate_unique_id(),
                'text' => trim($li->textContent),
                'icon' => array(
                    'value' => $list_type === 'ul' ? 'fas fa-check' : '',
                    'library' => 'fa-solid'
                )
            );
        }
        
        return array(
            'id' => $this->generate_unique_id(),
            'elType' => 'widget',
            'widgetType' => 'icon-list',
            'settings' => array(
                'icon_list' => $items,
                'ordered_list' => $list_type === 'ol' ? 'yes' : ''
            )
        );
    }
    
    /**
     * 從 URL 獲取附件 ID
     */
    private function get_attachment_id_from_url($url) {
        global $wpdb;
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE guid = %s",
            $url
        ));
        
        return $attachment_id;
    }
    
    /**
     * 生成唯一 ID
     */
    private function generate_unique_id() {
        return substr(md5(uniqid(rand(), true)), 0, 8);
    }
    
    /**
     * 保存 Elementor 資料到文章
     * @param int $post_id
     * @param array $elementor_data
     */
    public function save_elementor_data($post_id, $elementor_data) {
        // 更新 Elementor 資料
        update_post_meta($post_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
        
        // 標記為使用 Elementor 編輯
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');
        
        // 設定 Elementor 版本
        update_post_meta($post_id, '_elementor_version', ELEMENTOR_VERSION);
        
        // 設定頁面設定
        $page_settings = array(
            'hide_title' => 'yes',
            'post_status' => 'publish'
        );
        update_post_meta($post_id, '_elementor_page_settings', $page_settings);
        
        // 清除 Elementor 快取
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }
}