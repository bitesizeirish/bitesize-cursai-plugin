<?php
/**
 * Audio Button Main Class
 *
 * Manages the Bitesize audio button functionality including:
 * - Enqueueing scripts and styles
 * - Registering Gutenberg/ACF block
 * - Server-side prehydration of sound data
 *
 * @package BitesizeCursai\Audio
 */

namespace BitesizeCursai\Audio;

if (!defined('ABSPATH')) {
    exit;
}

class AudioButton
{
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('acf/init', [$this, 'register_acf_block']);
        
        // Provide function for theme to check if plugin handles audio
        add_action('init', [$this, 'register_capability_check']);
    }

    /**
     * Register capability check function for theme
     */
    public function register_capability_check()
    {
        if (!function_exists('bitesize_cursai_has_audio')) {
            function bitesize_cursai_has_audio() {
                return true;
            }
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts()
    {
        // Register Vue.js from CDN
        wp_register_script(
            'vuejs',
            'https://cdn.jsdelivr.net/npm/vue@2.6.10/dist/vue.min.js',
            [],
            '2.6.10',
            true
        );

        // Register the Bitesize Audio Button script
        wp_register_script(
            'bitesize_audio_button',
            BITESIZE_CURSAI_PLUGIN_URL . 'assets/js/audio-button.js',
            ['vuejs'],
            BITESIZE_CURSAI_VERSION,
            true
        );

        // Enqueue FontAwesome for the audio button icons
        wp_enqueue_style(
            'fontawesome',
            'https://use.fontawesome.com/releases/v5.6.1/css/all.css',
            [],
            '5.6.1'
        );

        // Enqueue the audio button CSS
        wp_enqueue_style(
            'bitesize_audio_style',
            BITESIZE_CURSAI_PLUGIN_URL . 'assets/css/audio-button.css',
            [],
            BITESIZE_CURSAI_VERSION
        );
    }

    /**
     * Register ACF/Gutenberg block
     */
    public function register_acf_block()
    {
        // Try both old and new ACF function names for compatibility
        if (function_exists('acf_register_block_type')) {
            // Newer ACF (5.8+)
            acf_register_block_type([
                'name'              => 'bitesizeaudio',
                'title'             => __('Bitesize Audio', 'bitesize-cursai'),
                'description'       => __('Bitesize audio player.', 'bitesize-cursai'),
                'render_callback'   => [$this, 'render_acf_block'],
                'category'          => 'formatting',
                'icon'              => 'admin-comments',
                'keywords'          => ['bitesize', 'audio'],
            ]);
        } elseif (function_exists('acf_register_block')) {
            // Older ACF (pre-5.8) - use old function name
            acf_register_block([
                'name'              => 'bitesizeaudio',
                'title'             => __('Bitesize Audio', 'bitesize-cursai'),
                'description'       => __('Bitesize audio player.', 'bitesize-cursai'),
                'render_callback'   => [$this, 'render_acf_block'],
                'category'          => 'formatting',
                'icon'              => 'admin-comments',
                'keywords'          => ['bitesize', 'audio'],
            ]);
        }
    }

    /**
     * Render ACF block
     */
    public function render_acf_block($block)
    {
        // Get the sound_id from ACF
        $sound_id = get_field('sound_id');
        
        // Check if we're in the editor (admin/preview mode)
        $is_editor = is_admin() || (defined('REST_REQUEST') && REST_REQUEST);
        
        if (empty($sound_id)) {
            // Show placeholder only in editor
            if ($is_editor) {
                ?>
                <div style="padding: 20px; background: #f0f0f1; border: 2px dashed #0073aa; border-radius: 4px; text-align: center;">
                    <p style="margin: 0; color: #0073aa;">
                        <span class="dashicons dashicons-format-audio" style="font-size: 24px; width: 24px; height: 24px;"></span><br>
                        <strong>Bitesize Audio</strong><br>
                        <span style="font-size: 14px;">Please enter a Sound ID in the block settings →</span>
                    </p>
                </div>
                <?php
            } else {
                echo '<p><!-- Audio block: No sound ID --></p>';
            }
            return;
        }

        // Show placeholder ONLY in editor
        if ($is_editor) {
            ?>
            <div style="padding: 15px; background: #f0f0f1; border: 2px solid #38b000; border-radius: 4px; margin: 10px 0;">
                <p style="margin: 0; color: #38b000;">
                    <span class="dashicons dashicons-format-audio" style="font-size: 20px; width: 20px; height: 20px; vertical-align: middle;"></span>
                    <strong>Bitesize Audio</strong> - Sound ID: <?php echo esc_html($sound_id); ?><br>
                    <span style="font-size: 12px; color: #666;">Audio button will display on the frontend</span>
                </p>
            </div>
            <?php
        }
        
        // ALWAYS render the actual audio button component for frontend
        // Generate unique ID for Vue instance
        $element_id = 'bitesize-audio--' . uniqid();

        // Server-side prehydration: Fetch sound data and embed in HTML
        try {
            $service = new \BitesizeCursai\Audio\SoundService();
            $sound_data = $service->getSound((int) $sound_id);
            
            if (!is_wp_error($sound_data) && is_array($sound_data)) {
                // Embed prehydrated JSON for Vue component
                echo '<script type="application/json" id="' . esc_attr($element_id) . '-data">';
                echo wp_json_encode($sound_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo '</script>' . "\n";
            } else {
                // Silent failure with HTML comment for debugging
                $error_code = is_wp_error($sound_data) ? $sound_data->get_error_code() : 'unknown_error';
                echo '<!-- Bitesize Audio: prehydrate failed: ' . esc_html($error_code) . ' for sound ' . esc_html($sound_id) . ' -->' . "\n";
            }
        } catch (\Throwable $e) {
            // Silent failure with comment only
            echo '<!-- Bitesize Audio: prehydrate exception: ' . esc_html($e->getMessage()) . ' -->' . "\n";
        }

        // Enqueue scripts for this block
        wp_enqueue_script('vuejs');
        wp_enqueue_script('bitesize_audio_button');

        // Render the actual audio button (hidden in editor, visible on frontend)
        $wrapper_style = $is_editor ? 'display: none;' : '';
        ?>
        <div id="<?php echo esc_attr($element_id); ?>" class="bitesize-audio-wrapper" style="<?php echo esc_attr($wrapper_style); ?>">
            <bitesize-audio-button :sound-id="<?php echo esc_attr($sound_id); ?>"></bitesize-audio-button>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Vue !== 'undefined') {
                new Vue({
                    el: '#<?php echo esc_js($element_id); ?>'
                });
            }
        });
        </script>
        <?php
    }
}

