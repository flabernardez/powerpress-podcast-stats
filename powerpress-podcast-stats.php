<?php
/**
 * Plugin Name: PowerPress Podcast Stats
 * Plugin URI: https://github.com/flabernardez/powerpress-podcast-stats
 * Description: Track RSS feed access statistics for PowerPress podcasts with platform detection
 * Version: 1.5.0
 * Author: Flavia Bernárdez Rodríguez
 * Author URI: https://flabernardez.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: powerpress-podcast-stats
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PPPS_VERSION', '1.5.0');
define('PPPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PPPS_PLUGIN_URL', plugin_dir_url(__FILE__));

class PowerPress_Podcast_Stats {

    private static $instance = null;
    private $table_name;
    private $feeds_table;
    private $episodes_table;

    public static function ppps_get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'powerpress_feed_stats';
        $this->feeds_table = $wpdb->prefix . 'powerpress_registered_feeds';
        $this->episodes_table = $wpdb->prefix . 'powerpress_episodes';

        $this->ppps_check_database();
        add_action('plugins_loaded', array($this, 'ppps_load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'ppps_activate'));
        register_deactivation_hook(__FILE__, array($this, 'ppps_deactivate'));
        add_action('admin_menu', array($this, 'ppps_add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'ppps_enqueue_admin_scripts'));
        add_action('template_redirect', array($this, 'ppps_track_feed_access'), 1);
        add_action('wp_ajax_ppps_get_overview', array($this, 'ppps_ajax_get_overview'));
        add_action('wp_ajax_ppps_get_podcast_stats', array($this, 'ppps_ajax_get_podcast_stats'));
        add_action('wp_ajax_ppps_add_feed', array($this, 'ppps_ajax_add_feed'));
        add_action('wp_ajax_ppps_delete_feed', array($this, 'ppps_ajax_delete_feed'));
        add_action('wp_ajax_ppps_refresh_episodes', array($this, 'ppps_ajax_refresh_episodes'));
    }

    public function ppps_load_textdomain() {
        load_plugin_textdomain('powerpress-podcast-stats', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function ppps_check_database() {
        global $wpdb;
        $installed_version = get_option('ppps_db_version');
        if ($installed_version !== PPPS_VERSION) {
            $this->ppps_create_tables();
            update_option('ppps_db_version', PPPS_VERSION);
        }
    }

    private function ppps_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feed_slug varchar(255) NOT NULL,
            feed_name varchar(255) NOT NULL,
            podcast_id bigint(20) DEFAULT 0,
            episode_id bigint(20) DEFAULT 0,
            platform varchar(100) DEFAULT '',
            user_agent text,
            ip_hash varchar(64) NOT NULL,
            access_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY feed_slug (feed_slug),
            KEY podcast_id (podcast_id),
            KEY episode_id (episode_id),
            KEY platform (platform),
            KEY access_time (access_time),
            KEY ip_hash (ip_hash)
        ) $charset_collate;";
        dbDelta($sql);

        $sql_feeds = "CREATE TABLE {$this->feeds_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            podcast_name varchar(255) NOT NULL,
            feed_url varchar(500) NOT NULL,
            feed_slug varchar(255) NOT NULL,
            thumbnail_url varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY feed_url (feed_url),
            UNIQUE KEY feed_slug (feed_slug),
            KEY podcast_name (podcast_name)
        ) $charset_collate;";
        dbDelta($sql_feeds);

        $sql_episodes = "CREATE TABLE {$this->episodes_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            podcast_id bigint(20) NOT NULL,
            episode_title varchar(500) NOT NULL,
            episode_guid varchar(500) NOT NULL,
            episode_url varchar(500) DEFAULT '',
            pub_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY podcast_id (podcast_id),
            UNIQUE KEY unique_episode (podcast_id, episode_guid)
        ) $charset_collate;";
        dbDelta($sql_episodes);
    }

    public function ppps_activate() {
        $this->ppps_create_tables();
        add_option('ppps_db_version', PPPS_VERSION);
    }

    public function ppps_deactivate() {}

    public function ppps_track_feed_access() {
        if (!is_feed()) {
            return;
        }

        global $wpdb;
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $current_url_clean = rtrim(strtok($current_url, '?'), '/');

        $registered_feed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->feeds_table} WHERE feed_url = %s OR feed_url = %s",
            $current_url_clean,
            $current_url_clean . '/'
        ));

        if (!$registered_feed) {
            return;
        }

        $podcast_id = $registered_feed->id;
        $feed_name = $registered_feed->podcast_name;
        $feed_slug = $registered_feed->feed_slug;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $platform = $this->ppps_detect_platform($user_agent);
        $ip = $this->ppps_get_client_ip();
        $ip_hash = hash('sha256', $ip . wp_salt());

        $recent_access = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
            WHERE ip_hash = %s AND podcast_id = %d AND platform = %s
            AND access_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1",
            $ip_hash, $podcast_id, $platform
        ));

        if (!$recent_access) {
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'feed_slug' => $feed_slug,
                    'feed_name' => $feed_name,
                    'podcast_id' => $podcast_id,
                    'episode_id' => 0,
                    'platform' => $platform,
                    'user_agent' => $user_agent,
                    'ip_hash' => $ip_hash,
                    'access_time' => current_time('mysql')
                ),
                array('%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s')
            );

            if ($result) {
                error_log("PPPS TRACKED: Podcast={$feed_name}, Platform={$platform}, UA=" . substr($user_agent, 0, 100));
            }
        }
    }

    private function ppps_detect_platform($user_agent) {
        if (empty($user_agent)) {
            return 'Unknown';
        }

        error_log("PPPS DETECT: {$user_agent}");

        if (preg_match('/iTMS|AppleCoreMedia|Podcasts\/|iTunes/i', $user_agent)) {
            return 'Apple Podcasts';
        }
        if (preg_match('/Spotify/i', $user_agent)) {
            return 'Spotify';
        }
        if (preg_match('/Google-Podcast|GoogleChirp|Google Podcasts/i', $user_agent)) {
            return 'Google Podcasts';
        }
        if (preg_match('/YouTube/i', $user_agent) && !preg_match('/bot|crawler/i', $user_agent)) {
            return 'YouTube Music';
        }
        if (preg_match('/Pocket[\s\-]?Casts|PocketCasts|pktc/i', $user_agent)) {
            return 'Pocket Casts';
        }
        if (preg_match('/Amazon[\s\-]?Music|AmazonMusic|Alexa/i', $user_agent)) {
            return 'Amazon Music';
        }
        if (preg_match('/Podimo/i', $user_agent)) {
            return 'Podimo';
        }
        if (preg_match('/iVoox/i', $user_agent)) {
            return 'iVoox';
        }
        if (preg_match('/Overcast/i', $user_agent)) {
            return 'Overcast';
        }
        if (preg_match('/Castro/i', $user_agent)) {
            return 'Castro';
        }
        if (preg_match('/Castbox/i', $user_agent)) {
            return 'Castbox';
        }
        if (preg_match('/Podcast[\s\-]?Addict|PodcastAddict/i', $user_agent)) {
            return 'Podcast Addict';
        }
        if (preg_match('/Player[\s\-]?FM|PlayerFM/i', $user_agent)) {
            return 'Player FM';
        }
        if (preg_match('/Stitcher/i', $user_agent)) {
            return 'Stitcher';
        }
        if (preg_match('/TuneIn/i', $user_agent)) {
            return 'TuneIn';
        }
        if (preg_match('/Deezer/i', $user_agent)) {
            return 'Deezer';
        }
        if (preg_match('/iHeartRadio|iHeart/i', $user_agent)) {
            return 'iHeartRadio';
        }
        if (preg_match('/Audible/i', $user_agent)) {
            return 'Audible';
        }
        if (preg_match('/AntennaPod/i', $user_agent)) {
            return 'AntennaPod';
        }
        if (preg_match('/Podcast[\s\-]?Republic|PodcastRepublic/i', $user_agent)) {
            return 'Podcast Republic';
        }
        if (preg_match('/Podbean/i', $user_agent)) {
            return 'Podbean';
        }
        if (preg_match('/Downcast/i', $user_agent)) {
            return 'Downcast';
        }
        if (preg_match('/iCatcher/i', $user_agent)) {
            return 'iCatcher';
        }
        if (preg_match('/Podcast[\s\-]?Guru/i', $user_agent)) {
            return 'Podcast Guru';
        }
        if (preg_match('/Fountain/i', $user_agent)) {
            return 'Fountain';
        }
        if (preg_match('/Curiocaster/i', $user_agent)) {
            return 'Curiocaster';
        }
        if (preg_match('/Podfriend/i', $user_agent)) {
            return 'Podfriend';
        }
        if (preg_match('/Luminary/i', $user_agent)) {
            return 'Luminary';
        }
        if (preg_match('/Spreaker/i', $user_agent)) {
            return 'Spreaker';
        }
        if (preg_match('/Acast/i', $user_agent)) {
            return 'Acast';
        }
        if (preg_match('/Podchaser/i', $user_agent)) {
            return 'Podchaser';
        }
        if (preg_match('/Wondery/i', $user_agent)) {
            return 'Wondery';
        }
        if (preg_match('/Pandora/i', $user_agent)) {
            return 'Pandora';
        }
        if (preg_match('/Himalaya/i', $user_agent)) {
            return 'Himalaya';
        }
        if (preg_match('/PodcastIndex|podcastindex/i', $user_agent)) {
            return 'Podcast Index';
        }
        if (preg_match('/Feedly|Feedbin|NewsBlur|Inoreader|FeedMaster|FeedReader|RSS/i', $user_agent)) {
            return 'RSS Reader';
        }
        if (preg_match('/bot|crawler|spider|slurp|bingpreview|googlebot|facebookexternalhit/i', $user_agent)) {
            return 'Bot/Crawler';
        }
        if (preg_match('/^(axios|curl|wget|python|go-http|java|okhttp|apache-httpclient)/i', $user_agent)) {
            return 'HTTP Library';
        }
        if (preg_match('/WordPress/i', $user_agent)) {
            return 'WordPress';
        }
        if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera)/i', $user_agent) &&
            !preg_match('/(mobile|android|iphone|ipad)/i', $user_agent)) {
            return 'Web Browser';
        }
        if (preg_match('/(mobile|android|iphone|ipad)/i', $user_agent) &&
            preg_match('/(Chrome|Firefox|Safari|Edge)/i', $user_agent)) {
            return 'Mobile Browser';
        }

        error_log("PPPS DETECT: No match, returning Other");
        return 'Other';
    }

    private function ppps_get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    private function ppps_parse_feed($feed_url, $podcast_id) {
        global $wpdb;
        $response = wp_remote_get($feed_url, array('timeout' => 10));
        if (is_wp_error($response)) {
            return 0;
        }
        $body = wp_remote_retrieve_body($response);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            return 0;
        }

        $namespaces = $xml->getNamespaces(true);
        $thumbnail_url = '';

        if (isset($namespaces['itunes'])) {
            $itunes = $xml->channel->children($namespaces['itunes']);
            if (isset($itunes->image)) {
                $attrs = $itunes->image->attributes();
                if (isset($attrs['href'])) {
                    $thumbnail_url = (string) $attrs['href'];
                }
            }
        }

        if (empty($thumbnail_url) && isset($xml->channel->image->url)) {
            $thumbnail_url = (string) $xml->channel->image->url;
        }

        if (!empty($thumbnail_url)) {
            $wpdb->update($this->feeds_table, array('thumbnail_url' => $thumbnail_url), array('id' => $podcast_id), array('%s'), array('%d'));
        }

        $episodes_added = 0;
        foreach ($xml->channel->item as $item) {
            $title = (string) $item->title;
            $guid = (string) $item->guid;
            $pub_date = isset($item->pubDate) ? date('Y-m-d H:i:s', strtotime((string) $item->pubDate)) : '';
            $episode_url = '';

            if (isset($item->enclosure)) {
                $attrs = $item->enclosure->attributes();
                if (isset($attrs['url'])) {
                    $episode_url = (string) $attrs['url'];
                }
            }

            if (empty($guid)) {
                $guid = md5($title . $episode_url);
            }

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->episodes_table} WHERE podcast_id = %d AND episode_guid = %s",
                $podcast_id, $guid
            ));

            if (!$existing) {
                $result = $wpdb->insert(
                    $this->episodes_table,
                    array(
                        'podcast_id' => $podcast_id,
                        'episode_title' => $title,
                        'episode_guid' => $guid,
                        'episode_url' => $episode_url,
                        'pub_date' => $pub_date,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s')
                );
                if ($result) $episodes_added++;
            }
        }
        return $episodes_added;
    }

    public function ppps_add_admin_menu() {
        add_menu_page(
            __('Podcast Stats', 'powerpress-podcast-stats'),
            __('Podcast Stats', 'powerpress-podcast-stats'),
            'manage_options',
            'powerpress-podcast-stats',
            array($this, 'ppps_render_admin_page'),
            'dashicons-chart-area',
            30
        );
    }

    public function ppps_enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_powerpress-podcast-stats') return;

        wp_enqueue_style('ppps-admin-css', PPPS_PLUGIN_URL . 'assets/admin.css', array(), PPPS_VERSION);
        wp_enqueue_script('ppps-admin-js', PPPS_PLUGIN_URL . 'assets/admin.js', array('jquery'), PPPS_VERSION, true);
        wp_localize_script('ppps-admin-js', 'pppsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppps_stats_nonce'),
            'strings' => array(
                'confirmRemove' => __('Are you sure you want to remove this feed from statistics? The feed will continue to work, but it will no longer be tracked.', 'powerpress-podcast-stats'),
                'fillAllFields' => __('Please fill in all fields.', 'powerpress-podcast-stats'),
                'errorLoading' => __('Error loading statistics. Please try again.', 'powerpress-podcast-stats'),
                'errorDeleting' => __('Error removing feed. Please try again.', 'powerpress-podcast-stats'),
                'errorAdding' => __('Error adding feed. Please try again.', 'powerpress-podcast-stats'),
                'adding' => __('Adding...', 'powerpress-podcast-stats'),
                'addFeed' => __('Add Feed', 'powerpress-podcast-stats'),
                'noData' => __('No data available', 'powerpress-podcast-stats'),
                'accesses' => __('accesses', 'powerpress-podcast-stats'),
            )
        ));
    }

    public function ppps_render_admin_page() {
        include PPPS_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function ppps_ajax_get_overview() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'powerpress-podcast-stats'));
        }
        global $wpdb;
        $podcasts = $wpdb->get_results(
            "SELECT f.id, f.podcast_name, f.feed_url, f.thumbnail_url, f.created_at, COUNT(s.id) as total_accesses
            FROM {$this->feeds_table} f
            LEFT JOIN {$this->table_name} s ON f.id = s.podcast_id
            GROUP BY f.id ORDER BY f.podcast_name ASC"
        );
        wp_send_json_success(array('podcasts' => $podcasts));
    }

    public function ppps_ajax_get_podcast_stats() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'powerpress-podcast-stats'));
        }
        global $wpdb;
        $podcast_id = isset($_POST['podcast_id']) ? intval($_POST['podcast_id']) : 0;
        if ($podcast_id <= 0) {
            wp_send_json_error(__('Invalid podcast ID', 'powerpress-podcast-stats'));
        }

        $total_accesses = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE podcast_id = %d", $podcast_id
        ));
        $by_platform = $wpdb->get_results($wpdb->prepare(
            "SELECT platform, COUNT(*) as count FROM {$this->table_name} 
            WHERE podcast_id = %d AND platform != '' GROUP BY platform ORDER BY count DESC", $podcast_id
        ));
        $by_episode = $wpdb->get_results($wpdb->prepare(
            "SELECT e.episode_title, COUNT(s.id) as count FROM {$this->episodes_table} e
            LEFT JOIN {$this->table_name} s ON e.id = s.episode_id WHERE e.podcast_id = %d
            GROUP BY e.id, e.episode_title HAVING count > 0 ORDER BY count DESC LIMIT 20", $podcast_id
        ));
        $podcast = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->feeds_table} WHERE id = %d", $podcast_id));

        wp_send_json_success(array(
            'podcast' => $podcast,
            'total_accesses' => (int) $total_accesses,
            'by_platform' => $by_platform,
            'by_episode' => $by_episode
        ));
    }

    public function ppps_ajax_add_feed() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'powerpress-podcast-stats'));
        }
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        $podcast_name = isset($_POST['podcast_name']) ? sanitize_text_field($_POST['podcast_name']) : '';
        if (empty($feed_url) || empty($podcast_name)) {
            wp_send_json_error(__('Feed URL and Podcast Name are required.', 'powerpress-podcast-stats'));
        }
        $feed_url = rtrim($feed_url, '/');
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->feeds_table} WHERE feed_url = %s OR feed_url = %s", $feed_url, $feed_url . '/'
        ));
        if ($exists) {
            wp_send_json_error(__('This feed URL is already registered.', 'powerpress-podcast-stats'));
        }

        $feed_slug = sanitize_title($podcast_name);
        $original_slug = $feed_slug;
        $counter = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->feeds_table} WHERE feed_slug = %s", $feed_slug))) {
            $feed_slug = $original_slug . '-' . $counter++;
            if ($counter > 100) {
                wp_send_json_error(__('Unable to generate unique feed slug.', 'powerpress-podcast-stats'));
                return;
            }
        }

        $result = $wpdb->insert(
            $this->feeds_table,
            array('podcast_name' => $podcast_name, 'feed_url' => $feed_url, 'feed_slug' => $feed_slug, 'thumbnail_url' => '', 'created_at' => current_time('mysql')),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            $podcast_id = $wpdb->insert_id;
            $episodes_count = $this->ppps_parse_feed($feed_url, $podcast_id);
            $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->feeds_table} WHERE id = %d", $podcast_id));
            wp_send_json_success(array(
                'message' => __('Feed added successfully!', 'powerpress-podcast-stats') . ' ' . sprintf(__('%d episodes imported.', 'powerpress-podcast-stats'), $episodes_count),
                'feed' => $feed
            ));
        } else {
            wp_send_json_error(__('Error saving feed to database.', 'powerpress-podcast-stats'));
        }
    }

    public function ppps_ajax_delete_feed() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'powerpress-podcast-stats'));
        }
        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        if ($feed_id <= 0) {
            wp_send_json_error(__('Invalid feed ID.', 'powerpress-podcast-stats'));
        }
        global $wpdb;
        $wpdb->delete($this->episodes_table, array('podcast_id' => $feed_id), array('%d'));
        $wpdb->delete($this->table_name, array('podcast_id' => $feed_id), array('%d'));
        $result = $wpdb->delete($this->feeds_table, array('id' => $feed_id), array('%d'));

        if ($result) {
            wp_send_json_success(array('message' => __('Feed removed from statistics successfully!', 'powerpress-podcast-stats')));
        } else {
            wp_send_json_error(__('Error removing feed.', 'powerpress-podcast-stats'));
        }
    }

    public function ppps_ajax_refresh_episodes() {
        check_ajax_referer('ppps_stats_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'powerpress-podcast-stats'));
        }
        global $wpdb;
        $podcast_id = isset($_POST['podcast_id']) ? intval($_POST['podcast_id']) : 0;
        if ($podcast_id <= 0) {
            wp_send_json_error(__('Invalid podcast ID', 'powerpress-podcast-stats'));
        }
        $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->feeds_table} WHERE id = %d", $podcast_id));
        if (!$feed) {
            wp_send_json_error(__('Feed not found', 'powerpress-podcast-stats'));
        }
        $episodes_count = $this->ppps_parse_feed($feed->feed_url, $podcast_id);
        wp_send_json_success(array('message' => sprintf(__('%d episodes refreshed.', 'powerpress-podcast-stats'), $episodes_count)));
    }
}

PowerPress_Podcast_Stats::ppps_get_instance();
