<?php
/**
 * Plugin Name: PowerPress Podcast Stats
 * Plugin URI: https://github.com/yourusername/powerpress-podcast-stats
 * Description: Track RSS feed access statistics for PowerPress podcasts with geolocation data
 * Version: 1.1.0
 * Author: Flavia Bernárdez Rodríguez
 * Author URI: https://flabernardez.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: powerpress-podcast-stats
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PPPS_VERSION', '1.1.0');
define('PPPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PPPS_PLUGIN_URL', plugin_dir_url(__FILE__));

class PowerPress_Podcast_Stats {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'powerpress_feed_stats';
        $this->feeds_table = $wpdb->prefix . 'powerpress_registered_feeds';

        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Track feed requests
        add_action('template_redirect', array($this, 'track_feed_access'), 1);

        // AJAX handlers
        add_action('wp_ajax_ppps_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_ppps_detect_feeds', array($this, 'ajax_detect_feeds'));
        add_action('wp_ajax_ppps_save_manual_feed', array($this, 'ajax_save_manual_feed'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Stats table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feed_slug varchar(255) NOT NULL,
            feed_name varchar(255) NOT NULL,
            podcast_id bigint(20) DEFAULT 0,
            user_agent text,
            ip_hash varchar(64) NOT NULL,
            country varchar(100) DEFAULT '',
            city varchar(100) DEFAULT '',
            access_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY feed_slug (feed_slug),
            KEY podcast_id (podcast_id),
            KEY access_time (access_time),
            KEY ip_hash (ip_hash),
            KEY country (country)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Registered feeds table
        $sql_feeds = "CREATE TABLE IF NOT EXISTS {$this->feeds_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            podcast_name varchar(255) NOT NULL,
            feed_url varchar(500) NOT NULL,
            feed_slug varchar(255) NOT NULL,
            source varchar(50) DEFAULT 'manual',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY feed_slug (feed_slug),
            KEY podcast_name (podcast_name)
        ) $charset_collate;";

        dbDelta($sql_feeds);

        // Set version
        add_option('ppps_db_version', PPPS_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Nothing to do on deactivation, we keep the data
    }

    /**
     * Track feed access
     */
    public function track_feed_access() {
        // Only track feed requests
        if (!is_feed()) {
            return;
        }

        global $wpdb;

        // Get feed information
        $feed_slug = get_query_var('feed');

        // If it's a podcast feed (PowerPress creates custom feeds)
        if (empty($feed_slug) || $feed_slug === 'feed') {
            $feed_slug = 'podcast'; // Default podcast feed
        }

        // Check if this feed is registered
        $registered_feed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->feeds_table} WHERE feed_slug = %s",
            $feed_slug
        ));

        // If not registered, try to auto-register it
        if (!$registered_feed) {
            $this->auto_register_feed($feed_slug);
            $registered_feed = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->feeds_table} WHERE feed_slug = %s",
                $feed_slug
            ));
        }

        // If still not found, skip (not a registered podcast feed)
        if (!$registered_feed) {
            return;
        }

        $podcast_id = $registered_feed->id;
        $feed_name = $registered_feed->podcast_name;

        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';

        // Get IP and create hash (for privacy)
        $ip = $this->get_client_ip();
        $ip_hash = hash('sha256', $ip . wp_salt());

        // Check if this IP already accessed this feed in the last hour (avoid duplicates)
        $recent_access = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
            WHERE ip_hash = %s 
            AND podcast_id = %d 
            AND access_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            LIMIT 1",
            $ip_hash,
            $podcast_id
        ));

        // If no recent access, log this one
        if (!$recent_access) {
            // Get geolocation data
            $geo_data = $this->get_geolocation($ip);

            // Insert the record
            $wpdb->insert(
                $this->table_name,
                array(
                    'feed_slug' => $feed_slug,
                    'feed_name' => $feed_name,
                    'podcast_id' => $podcast_id,
                    'user_agent' => $user_agent,
                    'ip_hash' => $ip_hash,
                    'country' => $geo_data['country'],
                    'city' => $geo_data['city'],
                    'access_time' => current_time('mysql')
                ),
                array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Auto-register a feed when accessed
     */
    private function auto_register_feed($feed_slug) {
        global $wpdb;

        // Get a readable feed name
        $feed_name = $this->get_feed_name($feed_slug);
        $feed_url = home_url('/feed/' . $feed_slug . '/');

        // Only register if it seems like a podcast feed
        if (strpos($feed_slug, 'podcast') !== false ||
            is_category() ||
            is_tax() ||
            is_post_type_archive()) {

            $wpdb->insert(
                $this->feeds_table,
                array(
                    'podcast_name' => $feed_name,
                    'feed_url' => $feed_url,
                    'feed_slug' => $feed_slug,
                    'source' => 'auto',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // CloudFlare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get geolocation data from IP
     */
    private function get_geolocation($ip) {
        $default = array(
            'country' => '',
            'city' => ''
        );

        // Don't geolocate local IPs
        if ($ip === '0.0.0.0' || $ip === '127.0.0.1' || strpos($ip, '192.168.') === 0) {
            return $default;
        }

        // Check transient cache first (cache for 7 days)
        $cache_key = 'ppps_geo_' . md5($ip);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Call ip-api.com (free, no key required, 45 req/min)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,city", array(
            'timeout' => 3,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            return $default;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data) && $data['status'] === 'success') {
            $geo_data = array(
                'country' => isset($data['country']) ? sanitize_text_field($data['country']) : '',
                'city' => isset($data['city']) ? sanitize_text_field($data['city']) : ''
            );

            // Cache the result
            set_transient($cache_key, $geo_data, 7 * DAY_IN_SECONDS);

            return $geo_data;
        }

        return $default;
    }

    /**
     * Get a readable name for the feed
     */
    private function get_feed_name($feed_slug) {
        // Try to get PowerPress feed settings
        $powerpress_settings = get_option('powerpress_general');

        // Check if it's a category or taxonomy feed
        if (is_category() || is_tax()) {
            $term = get_queried_object();
            return $term->name . ' Podcast';
        }

        // Check if it's a custom post type feed
        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj) {
                return $post_type_obj->labels->name . ' Podcast';
            }
        }

        // Default feed names
        $feed_names = array(
            'podcast' => 'Main Podcast Feed',
            'rss2' => 'RSS 2.0 Feed',
            'rss' => 'RSS Feed',
            'atom' => 'Atom Feed',
        );

        return isset($feed_names[$feed_slug]) ? $feed_names[$feed_slug] : ucfirst($feed_slug) . ' Feed';
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Podcast Stats', 'powerpress-podcast-stats'),
            __('Podcast Stats', 'powerpress-podcast-stats'),
            'manage_options',
            'powerpress-podcast-stats',
            array($this, 'render_admin_page'),
            'dashicons-chart-area',
            30
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_powerpress-podcast-stats') {
            return;
        }

        wp_enqueue_style(
            'ppps-admin-css',
            PPPS_PLUGIN_URL . 'assets/admin.css',
            array(),
            PPPS_VERSION
        );

        wp_enqueue_script(
            'ppps-admin-js',
            PPPS_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            PPPS_VERSION,
            true
        );

        wp_localize_script('ppps-admin-js', 'pppsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppps_stats_nonce')
        ));
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        global $wpdb;

        // Get registered feeds grouped by podcast
        $feeds = $wpdb->get_results(
            "SELECT * FROM {$this->feeds_table} ORDER BY podcast_name ASC, feed_slug ASC"
        );

        include PPPS_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * AJAX handler to get stats
     */
    public function ajax_get_stats() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;

        $podcast_id = isset($_POST['podcast_id']) ? intval($_POST['podcast_id']) : 0;
        $time_filter = isset($_POST['time_filter']) ? sanitize_text_field($_POST['time_filter']) : 'all';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        // Build WHERE clause
        $where = array('1=1');

        if ($podcast_id > 0) {
            $where[] = $wpdb->prepare('podcast_id = %d', $podcast_id);
        }

        // Time filters
        if ($time_filter === 'custom' && !empty($start_date) && !empty($end_date)) {
            $where[] = $wpdb->prepare(
                'access_time BETWEEN %s AND %s',
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            );
        } elseif ($time_filter !== 'all') {
            $interval_map = array(
                'week' => '7 DAY',
                'month' => '30 DAY',
                'year' => '365 DAY'
            );

            if (isset($interval_map[$time_filter])) {
                $where[] = "access_time > DATE_SUB(NOW(), INTERVAL {$interval_map[$time_filter]})";
            }
        }

        $where_clause = implode(' AND ', $where);

        // Get total accesses
        $total_accesses = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}"
        );

        // Get accesses by feed (within the selected podcast)
        $by_feed = $wpdb->get_results(
            "SELECT feed_name, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE {$where_clause}
            GROUP BY feed_name
            ORDER BY count DESC"
        );

        // Get accesses by country
        $by_country = $wpdb->get_results(
            "SELECT country, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE {$where_clause} AND country != ''
            GROUP BY country
            ORDER BY count DESC
            LIMIT 20"
        );

        // Get accesses by city
        $by_city = $wpdb->get_results(
            "SELECT city, country, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE {$where_clause} AND city != ''
            GROUP BY city, country
            ORDER BY count DESC
            LIMIT 20"
        );

        // Get top user agents (podcast apps)
        $by_agent = $wpdb->get_results(
            "SELECT user_agent, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE {$where_clause} AND user_agent != ''
            GROUP BY user_agent
            ORDER BY count DESC
            LIMIT 10"
        );

        // Get timeline data (last 30 days)
        $timeline = $wpdb->get_results(
            "SELECT DATE(access_time) as date, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE {$where_clause}
            GROUP BY DATE(access_time)
            ORDER BY date DESC
            LIMIT 30"
        );

        wp_send_json_success(array(
            'total_accesses' => (int) $total_accesses,
            'by_feed' => $by_feed,
            'by_country' => $by_country,
            'by_city' => $by_city,
            'by_agent' => $by_agent,
            'timeline' => array_reverse($timeline)
        ));
    }

    /**
     * AJAX handler to detect PowerPress feeds
     */
    public function ajax_detect_feeds() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $detected_feeds = array();

        // Check if PowerPress is active
        if (!function_exists('powerpress_get_settings')) {
            wp_send_json_error('PowerPress plugin not detected. Please install and activate PowerPress.');
        }

        // Get PowerPress settings
        $powerpress_settings = get_option('powerpress_general', array());

        // Main podcast feed
        $detected_feeds[] = array(
            'name' => get_bloginfo('name') . ' - Main Podcast',
            'url' => home_url('/feed/podcast/'),
            'slug' => 'podcast',
            'source' => 'powerpress'
        );

        // Category podcasting
        $cat_casting = get_option('powerpress_cat_casting', array());
        if (!empty($cat_casting)) {
            foreach ($cat_casting as $cat_id => $settings) {
                $category = get_category($cat_id);
                if ($category) {
                    $detected_feeds[] = array(
                        'name' => $category->name . ' Podcast',
                        'url' => get_category_feed_link($cat_id),
                        'slug' => 'category_' . $cat_id,
                        'source' => 'powerpress'
                    );
                }
            }
        }

        // Taxonomy podcasting
        $taxonomies = get_option('powerpress_taxonomy_podcasting', array());
        if (!empty($taxonomies)) {
            foreach ($taxonomies as $tax_slug => $terms) {
                if (!empty($terms)) {
                    foreach ($terms as $term_id => $settings) {
                        $term = get_term($term_id, $tax_slug);
                        if ($term && !is_wp_error($term)) {
                            $detected_feeds[] = array(
                                'name' => $term->name . ' Podcast',
                                'url' => get_term_feed_link($term_id, $tax_slug),
                                'slug' => $tax_slug . '_' . $term_id,
                                'source' => 'powerpress'
                            );
                        }
                    }
                }
            }
        }

        // Post type podcasting
        $post_types = get_option('powerpress_posttype_podcasting', array());
        if (!empty($post_types)) {
            foreach ($post_types as $post_type => $settings) {
                $post_type_obj = get_post_type_object($post_type);
                if ($post_type_obj) {
                    $detected_feeds[] = array(
                        'name' => $post_type_obj->labels->name . ' Podcast',
                        'url' => get_post_type_archive_feed_link($post_type),
                        'slug' => 'post_type_' . $post_type,
                        'source' => 'powerpress'
                    );
                }
            }
        }

        if (empty($detected_feeds)) {
            wp_send_json_error('No PowerPress feeds detected. You may need to configure PowerPress or add feeds manually.');
        }

        wp_send_json_success($detected_feeds);
    }

    /**
     * AJAX handler to save manual feed
     */
    public function ajax_save_manual_feed() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        $podcast_name = isset($_POST['podcast_name']) ? sanitize_text_field($_POST['podcast_name']) : '';

        if (empty($feed_url) || empty($podcast_name)) {
            wp_send_json_error('Feed URL and Podcast Name are required.');
        }

        // Extract slug from URL
        $parsed_url = parse_url($feed_url);
        $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
        $path_parts = explode('/', $path);

        // Try to extract feed slug
        $feed_slug = '';
        if (in_array('feed', $path_parts)) {
            $feed_key = array_search('feed', $path_parts);
            if (isset($path_parts[$feed_key + 1])) {
                $feed_slug = $path_parts[$feed_key + 1];
            }
        }

        if (empty($feed_slug)) {
            $feed_slug = sanitize_title($podcast_name);
        }

        global $wpdb;

        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->feeds_table} WHERE feed_slug = %s",
            $feed_slug
        ));

        if ($exists) {
            wp_send_json_error('This feed is already registered.');
        }

        // Insert the feed
        $result = $wpdb->insert(
            $this->feeds_table,
            array(
                'podcast_name' => $podcast_name,
                'feed_url' => $feed_url,
                'feed_slug' => $feed_slug,
                'source' => 'manual',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Feed registered successfully!',
                'feed' => array(
                    'id' => $wpdb->insert_id,
                    'podcast_name' => $podcast_name,
                    'feed_url' => $feed_url,
                    'feed_slug' => $feed_slug
                )
            ));
        } else {
            wp_send_json_error('Error saving feed to database.');
        }
    }
}

// Initialize the plugin
PowerPress_Podcast_Stats::get_instance();
