<?php
/**
 * Audio Cache Manager - Admin UI for Sound Cache Management
 *
 * Provides admin interface for viewing, managing, and invalidating
 * cached sound metadata.
 *
 * @package BitesizeCursai\Admin
 */

namespace BitesizeCursai\Admin;

use BitesizeCursai\Audio\SoundService;

if (!defined('ABSPATH')) {
    exit;
}

class AudioCacheManager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_bitesize_invalidate_sound', [$this, 'handle_invalidate_sound']);
        add_action('admin_post_bitesize_clear_all_caches', [$this, 'handle_clear_all_caches']);
    }

    /**
     * Register admin menu item
     */
    public function register_menu()
    {
        // Add top-level "Bitesize Cúrsaí" menu
        add_menu_page(
            __('Bitesize Cúrsaí', 'bitesize-cursai'),
            __('Bitesize Cúrsaí', 'bitesize-cursai'),
            'manage_options',
            'bitesize-cursai',
            [$this, 'render_page'],
            'dashicons-format-audio',
            30
        );
        
        // Add "Audio Cache" submenu (will replace the duplicate top-level link)
        add_submenu_page(
            'bitesize-cursai',
            __('Audio Cache', 'bitesize-cursai'),
            __('Audio Cache', 'bitesize-cursai'),
            'manage_options',
            'bitesize-cursai',
            [$this, 'render_page']
        );
    }

    /**
     * Render admin page
     */
    public function render_page()
    {
        // Handle cache status check if sound_id is provided
        $check_sound_id = isset($_GET['check_sound_id']) ? intval($_GET['check_sound_id']) : 0;
        $cache_status = null;
        $sound_data = null;
        $cache_expiry = null;
        
        if ($check_sound_id > 0) {
            $service = new SoundService();
            $sound_data = $service->getCachedSound($check_sound_id);
            $meta = $service->getLastMeta();
            $cache_status = $meta['cache'];
            $cache_expiry = $service->getCacheExpiry($check_sound_id);
        }

        // Get cache statistics
        $service = new SoundService();
        $total_cached = $service->getCachedSoundsCount();
        $recent_sounds = $service->getAllCachedSounds(20);

        // Check API configuration
        $api_configured = defined('BITESIZE_API_URL') && 
                         defined('BITESIZE_API_KEY') && 
                         defined('BITESIZE_API_CLIENT_NAME');

        // Include template
        include BITESIZE_CURSAI_PLUGIN_DIR . 'templates/admin/audio-cache.php';
    }

    /**
     * Handle sound cache invalidation
     */
    public function handle_invalidate_sound()
    {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bitesize_invalidate_sound')) {
            wp_die(__('Security check failed', 'bitesize-cursai'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'bitesize-cursai'));
        }

        $sound_id = isset($_POST['sound_id']) ? intval($_POST['sound_id']) : 0;

        if ($sound_id > 0) {
            $service = new SoundService();
            $service->invalidate($sound_id);

            wp_redirect(add_query_arg([
                'page' => 'bitesize-cursai',
                'invalidated' => $sound_id,
            ], admin_url('admin.php')));
            exit;
        }

        wp_redirect(add_query_arg('page', 'bitesize-cursai', admin_url('admin.php')));
        exit;
    }

    /**
     * Handle clearing all caches
     */
    public function handle_clear_all_caches()
    {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bitesize_clear_all_caches')) {
            wp_die(__('Security check failed', 'bitesize-cursai'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'bitesize-cursai'));
        }

        $service = new SoundService();
        $count = $service->clearAllCaches();

        wp_redirect(add_query_arg([
            'page' => 'bitesize-cursai',
            'cleared_all' => $count,
        ], admin_url('admin.php')));
        exit;
    }
}

