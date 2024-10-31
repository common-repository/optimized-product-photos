<?php

class PIXC_Plugin {
    const PIXC_DOMAIN = 'optimized-product-photos';
    const PIXC_MENU_TITLE = 'Pixc';
    const PIXC_URL = 'https://pixc.com';
    private static $shop = null;
    private static $email = null;
    private static $code = null;
    private static $token = null;
    private static $secret = null;

    public static function init() {
        add_action('admin_menu', array( __CLASS__, 'admin_menu' ));
        add_action('admin_enqueue_scripts', array( __CLASS__, 'admin_files' ));
        add_action('query_vars', array( __CLASS__, 'custom_query_vars' ));
        add_action('parse_request', array( __CLASS__, 'custom_requests' ));

        self::$shop = get_option('siteurl');
        self::$email = get_option('admin_email');
        self::$secret = get_option('pixc_secret');
        self::$code = get_option('pixc_code');
        self::$token = get_option('pixc_token');
    }

    public static function activate() {
        add_rewrite_rule(
            '^pixc(.*)$',
            'index.php?pixc=$matches[1]',
            'top'
        );
        flush_rewrite_rules();
    }

    public static function custom_query_vars($vars) {
        $vars[] = 'pixc';
        return $vars;
    }

    public static function custom_requests($wp) {
        if (isset($wp->query_vars['pixc']) && $wp->query_vars['pixc']) {
            PIXC_Plugin::API_run($wp->query_vars['pixc']);
        }
    }

    public static function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( self::PIXC_MENU_TITLE, self::PIXC_DOMAIN ),
            __( self::PIXC_MENU_TITLE, self::PIXC_DOMAIN ),
            'manage_woocommerce',
            self::PIXC_DOMAIN,
            array( __CLASS__, 'menu' )
        );
    }

    public static function admin_files() {
        wp_enqueue_style(self::PIXC_DOMAIN, plugins_url('../stylesheets/main.css', __FILE__));
        wp_enqueue_script(self::PIXC_DOMAIN, plugins_url('../javascripts/main.js', __FILE__));
    }

    public static function menu() {

        $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        echo implode('', array(
            '<div id="' . self::PIXC_DOMAIN .'">',
                '<iframe id="' . self::PIXC_DOMAIN .'-iframe" src="' . self::PIXC_URL . '/app?provider=woocommerce&shop=' . urlencode(self::$shop) . '&secret=' . urlencode(self::$secret) . '&shopUrl=' . urlencode($url) . '"></iframe>',
            '</div>',
            '<script type="text/javascript">window.addEventListener("load", pixc.init, false);</script>'
        ));
    }

    public static function API_run($uri) {
        $parts = explode('/', trim($uri, '/ '));
        $action = array_shift($parts);
        $get = array();

        for ($i = 0; $i < count($parts); $i += 2) {
            if (!isset($parts[$i + 1])) {
                continue;
            }
            $get[$parts[$i]] = urldecode($parts[$i + 1]);
        }

        if (!$action) {
            return self::API_error('Action not specified');
        }

        $method = 'API_' . $action;

        if (!method_exists('PIXC_Plugin', $method)) {
            return self::API_error('Action not found');
        }

        $data = self::$method($get);

        if ($data) {
            $data['success'] = true;
        }

        self::API_response($data);
    }

    public static function API_error($message, $code = 500) {
        http_response_code($code);

        self::API_response(array(
            'success' => false,
            'message' => $message
        ));
    }

    public static function API_response($data) {
        echo json_encode($data);
        exit();
    }

    public static function API_authorize($get) {
        if (!isset($get['redirect_uri']) || !$get['redirect_uri']) {
            return self::API_error('redirect_uri not specified');
        }

        if (!self::$secret) {
            self::$secret = md5(self::$shop . self::$email . microtime(true));
            update_option('pixc_secret', self::$secret, true);
        }

        self::$code = md5(self::$shop . $get['redirect_uri'] . microtime(true));

        update_option('pixc_code', self::$code, true);

        header('Location: ' . $get['redirect_uri'] . (strpos($get['redirect_uri'], '?') === false ? '?' : '&') . 'code=' . self::$code . '&secret=' . self::$secret);
        exit();
    }

    public static function API_authorized($get) {
        if (!isset($get['access_token']) || $get['access_token'] !== self::$token) {
            return self::API_error('access_token invalid');
        }
    }

    public static function API_token($get) {
        if (!isset($get['code']) || !$get['code']) {
            return self::API_error('code not specified');
        }

        if (self::$code !== $get['code']) {
            return self::API_error('code invalid');
        }

        self::$token = md5(self::$shop . self::$code . microtime(true));
        update_option('pixc_token', self::$token, true);

        return array(
            'access_token' => self::$token
        );
    }

    public static function API_shop($get) {
        self::API_authorized($get);

        return array(
            'email' => self::$email
        );
    }

    public static function API_categories($get) {
        self::API_authorized($get);

        $categories = array_map(function($category) {
            return array(
                'id' => $category->term_id,
                'parent' => $category->parent,
                'title' => $category->name
            );
        }, get_terms('product_cat'));

        return array(
            'categories' => $categories
        );
    }

    public static function API_products($get) {
        self::API_authorized($get);
        
        $category = isset($get['category']) ? (int)$get['category'] : null;

        function processProducts($product) {
            $images = array();
            $productObj = wc_get_product($product->ID);
            $imageIds = array();
            $imageId = $productObj->get_image_id();
            if ($imageId) {
                $imageIds[] = $imageId;
            }
            $imageIdsTemp = $productObj->get_gallery_attachment_ids();
            if ($imageIdsTemp) {
                $imageIds = array_merge($imageIds, $imageIdsTemp);
            }
            if ($imageIds) {
                foreach ($imageIds as $imageId) {
                    $images[] = array(
                        'id' => $imageId,
                        'url' => wp_get_attachment_url($imageId)
                    );
                }
            }
            return array(
                'id' => $product->ID,
                'title' => $product->post_title,
                'images' => $images
            );
        }

        $products = array_map(processProducts, get_posts(array(
            'post_type' => 'product',
            'tax_query' => $category ? array(
                array(
                    'taxonomy'  => 'product_cat',
                    'field'     => 'id',
                    'terms'     => array($get['category'])
                )
            ) : array()
        )));

        return array(
            'products' => $products
        );
    }

    public static function API_replace_image($get) {
        self::API_authorized($get);
        
        global $wpdb;

        if (!isset($get['product']) || !$get['product']) {
            return self::API_error('product not specified');
        }

        if (!isset($get['image']) || !$get['image']) {
            return self::API_error('image not specified');
        }

        if (!isset($get['url']) || !$get['url']) {
            return self::API_error('url not specified');
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $temp_file = download_url($get['url'], 10);

        if (is_wp_error($temp_file)) {
            return self::API_error('file download error');
        }

        $overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_upload' => true,
        );
        $options = array(
            'name' => basename($get['url']),
            'type' => 'image/png',
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => filesize($temp_file),
        );

        $results = wp_handle_sideload($options, $overrides);

        $url = isset($results['url']) ? $results['url'] : false;
        $file = isset($results['file']) ? $results['file'] : false;
        $error = isset($results['error']) ? $results['error'] : false;

        if ($error || !$file) {
            return self::API_error('file replacing error');
        }

        $updated = $wpdb->update($wpdb->posts, array(
            'guid' => $url
        ), array(
            'ID' => $get['image']
        ));

        if (!$updated) {
            return self::API_error('file replacing error');
        }

        $result = update_attached_file($get['image'], $file);

        if (!$result) {
            return self::API_error('file replacing error');
        }

        return array(
            'image' => $get['image']
        );
    }
}