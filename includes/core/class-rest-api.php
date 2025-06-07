<?php
/**
 * 檔案：includes/class-rest-api.php
 * 功能：REST API端點
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Core
 */

namespace AISC\Core;

use AISC\Modules\ContentGenerator;
use AISC\Modules\KeywordManager;
use AISC\Modules\PerformanceTracker;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API類別
 * 
 * 提供REST API端點供前端使用
 */
class RestAPI {
    
    /**
     * API命名空間
     */
    private const NAMESPACE = 'aisc/v1';
    
    /**
     * 建構函數
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * 註冊REST API路由
     */
    public function register_routes(): void {
        // 內容生成端點
        register_rest_route(self::NAMESPACE, '/generate-content', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_content'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => $this->get_generate_content_args()
        ]);
        
        // 關鍵字搜尋端點
        register_rest_route(self::NAMESPACE, '/keywords/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_keywords'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // 效能數據端點
        register_rest_route(self::NAMESPACE, '/performance/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_performance'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args' => [
                'post_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // 批量操作端點
        register_rest_route(self::NAMESPACE, '/batch', [
            'methods' => 'POST',
            'callback' => [$this, 'batch_operation'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'operation' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['generate', 'optimize', 'analyze']
                ],
                'items' => [
                    'required' => true,
                    'type' => 'array'
                ]
            ]
        ]);
        
        // 健康檢查端點
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * 生成內容
     */
    public function generate_content(\WP_REST_Request $request): \WP_REST_Response {
        $params = $request->get_params();
        
        try {
            $content_generator = new ContentGenerator();
            
            // 設定SSE回應
            if ($request->get_header('Accept') === 'text/event-stream') {
                return $this->stream_content_generation($content_generator, $params);
            }
            
            // 一般回應
            $post_id = $content_generator->generate($params);
            
            if ($post_id) {
                return new \WP_REST_Response([
                    'success' => true,
                    'post_id' => $post_id,
                    'edit_link' => get_edit_post_link($post_id, 'raw'),
                    'view_link' => get_permalink($post_id)
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '內容生成失敗'
                ], 500);
            }
            
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 搜尋關鍵字
     */
    public function search_keywords(\WP_REST_Request $request): \WP_REST_Response {
        $query = $request->get_param('q');
        
        $keyword_manager = new KeywordManager();
        $results = $keyword_manager->search_keywords($query);
        
        return new \WP_REST_Response([
            'keywords' => $results,
            'count' => count($results)
        ], 200);
    }
    
    /**
     * 取得效能數據
     */
    public function get_performance(\WP_REST_Request $request): \WP_REST_Response {
        $post_id = intval($request->get_param('post_id'));
        $period = $request->get_param('period') ?? '7days';
        
        $performance_tracker = new PerformanceTracker();
        $data = $performance_tracker->get_post_performance($post_id, $period);
        
        return new \WP_REST_Response($data, 200);
    }
    
    /**
     * 批量操作
     */
    public function batch_operation(\WP_REST_Request $request): \WP_REST_Response {
        $operation = $request->get_param('operation');
        $items = $request->get_param('items');
        
        $results = [];
        
        switch ($operation) {
            case 'generate':
                $content_generator = new ContentGenerator();
                foreach ($items as $item) {
                    try {
                        $post_id = $content_generator->generate($item);
                        $results[] = [
                            'success' => true,
                            'item' => $item,
                            'post_id' => $post_id
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'success' => false,
                            'item' => $item,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                break;
                
            // 其他批量操作...
        }
        
        return new \WP_REST_Response([
            'operation' => $operation,
            'results' => $results,
            'success_count' => count(array_filter($results, fn($r) => $r['success']))
        ], 200);
    }
    
    /**
     * 健康檢查
     */
    public function health_check(): \WP_REST_Response {
        $db = new Database();
        
        $health = [
            'status' => 'healthy',
            'version' => AISC_VERSION,
            'database' => $db->check_tables_exist(),
            'api_key' => !empty(get_option('aisc_openai_api_key')),
            'timestamp' => current_time('mysql')
        ];
        
        $status_code = ($health['database'] && $health['api_key']) ? 200 : 503;
        
        if (!$health['database'] || !$health['api_key']) {
            $health['status'] = 'unhealthy';
        }
        
        return new \WP_REST_Response($health, $status_code);
    }
    
    /**
     * 串流內容生成
     */
    private function stream_content_generation($content_generator, $params) {
        // 設定SSE標頭
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        // 設定進度回調
        $content_generator->set_progress_callback(function($progress, $message) {
            echo "data: " . json_encode([
                'progress' => $progress,
                'message' => $message
            ]) . "\n\n";
            
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        });
        
        try {
            $post_id = $content_generator->generate($params);
            
            echo "data: " . json_encode([
                'complete' => true,
                'success' => true,
                'post_id' => $post_id,
                'edit_link' => get_edit_post_link($post_id, 'raw'),
                'view_link' => get_permalink($post_id)
            ]) . "\n\n";
            
        } catch (\Exception $e) {
            echo "data: " . json_encode([
                'complete' => true,
                'success' => false,
                'message' => $e->getMessage()
            ]) . "\n\n";
        }
        
        exit;
    }
    
    /**
     * 權限檢查：編輯權限
     */
    public function check_edit_permission(): bool {
        return current_user_can('edit_posts');
    }
    
    /**
     * 權限檢查：讀取權限
     */
    public function check_read_permission(): bool {
        return current_user_can('read');
    }
    
    /**
     * 權限檢查：管理員權限
     */
    public function check_admin_permission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * 取得內容生成參數定義
     */
    private function get_generate_content_args(): array {
        return [
            'keyword' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'word_count' => [
                'type' => 'integer',
                'default' => 8000,
                'minimum' => 1500,
                'maximum' => 20000
            ],
            'model' => [
                'type' => 'string',
                'default' => 'gpt-4-turbo-preview',
                'enum' => ['gpt-4', 'gpt-4-turbo-preview', 'gpt-3.5-turbo']
            ],
            'images' => [
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0,
                'maximum' => 10
            ],
            'optimize_featured_snippet' => [
                'type' => 'boolean',
                'default' => true
            ],
            'include_faq' => [
                'type' => 'boolean',
                'default' => true
            ],
            'add_schema' => [
                'type' => 'boolean',
                'default' => true
            ],
            'auto_internal_links' => [
                'type' => 'boolean',
                'default' => true
            ],
            'tone' => [
                'type' => 'string',
                'default' => 'professional',
                'enum' => ['professional', 'conversational', 'educational', 'persuasive']
            ],
            'categories' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer'
                ],
                'default' => []
            ]
        ];
    }
}
