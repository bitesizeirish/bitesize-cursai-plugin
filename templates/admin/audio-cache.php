<?php
/**
 * Admin Template: Audio Cache Management
 *
 * Provides UI for viewing and managing cached sound metadata.
 *
 * @package BitesizeMarketing
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Audio Cache Management', 'bitesize-cursai'); ?></h1>

    <?php
    // Show success messages
    if (isset($_GET['invalidated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(__('Cache invalidated for sound ID: %d', 'bitesize-cursai'), intval($_GET['invalidated']));
        echo '</p></div>';
    }

    if (isset($_GET['cleared_all'])) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(__('Cleared %d cached sounds', 'bitesize-cursai'), intval($_GET['cleared_all']));
        echo '</p></div>';
    }
    ?>

    <!-- Health Overview -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php _e('Cache Health Overview', 'bitesize-cursai'); ?></h2>
        <table class="widefat" style="margin-top: 10px;">
            <tr>
                <td style="width: 200px;"><strong><?php _e('Total Sounds Cached', 'bitesize-cursai'); ?></strong></td>
                <td><?php echo esc_html($total_cached); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Cache Status', 'bitesize-cursai'); ?></strong></td>
                <td>
                    <?php if ($total_cached > 0): ?>
                        <span style="color: #46b450;">✓ <?php _e('Active', 'bitesize-cursai'); ?></span>
                    <?php else: ?>
                        <span style="color: #999;"><?php _e('Empty', 'bitesize-cursai'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e('API Configuration', 'bitesize-cursai'); ?></strong></td>
                <td>
                    <?php if ($api_configured): ?>
                        <span style="color: #46b450;">✓ <?php _e('Configured', 'bitesize-cursai'); ?></span>
                    <?php else: ?>
                        <span style="color: #dc3232;">✗ <?php _e('Missing wp-config.php constants', 'bitesize-cursai'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e('Required Constants', 'bitesize-cursai'); ?></strong></td>
                <td>
                    <code>BITESIZE_API_URL</code>, 
                    <code>BITESIZE_API_KEY</code>, 
                    <code>BITESIZE_API_CLIENT_NAME</code>
                </td>
            </tr>
        </table>
    </div>

    <!-- Cache Status Checker -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php _e('Check Sound Cache Status', 'bitesize-cursai'); ?></h2>
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="bitesize-cursai">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="check_sound_id"><?php _e('Sound ID', 'bitesize-cursai'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="check_sound_id" 
                               id="check_sound_id" 
                               value="<?php echo esc_attr($check_sound_id); ?>" 
                               class="regular-text" 
                               min="1">
                        <p class="description">
                            <?php _e('Enter a sound ID to check its cache status', 'bitesize-cursai'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e('Check Status', 'bitesize-cursai'); ?>">
            </p>
        </form>

        <?php if ($check_sound_id > 0): ?>
            <hr>
            <h3><?php _e('Cache Status for Sound ID:', 'bitesize-cursai'); ?> <?php echo esc_html($check_sound_id); ?></h3>
            
            <?php if ($cache_status === 'HIT' && $sound_data): ?>
                <div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;">
                    <p style="margin: 0;"><strong style="color: #155724;">✓ <?php _e('CACHED', 'bitesize-cursai'); ?></strong></p>
                </div>

                <table class="widefat" style="margin-top: 10px;">
                    <?php if (!empty($sound_data['text'])): ?>
                        <tr>
                            <td style="width: 150px;"><strong><?php _e('Irish Text', 'bitesize-cursai'); ?></strong></td>
                            <td><?php echo esc_html($sound_data['text']); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($sound_data['translation'])): ?>
                        <tr>
                            <td><strong><?php _e('Translation', 'bitesize-cursai'); ?></strong></td>
                            <td><?php echo esc_html($sound_data['translation']); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($sound_data['pronunciation'])): ?>
                        <tr>
                            <td><strong><?php _e('Pronunciation', 'bitesize-cursai'); ?></strong></td>
                            <td>/<?php echo esc_html($sound_data['pronunciation']); ?>/</td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if (isset($sound_data['recorded'])): ?>
                        <tr>
                            <td><strong><?php _e('Recorded', 'bitesize-cursai'); ?></strong></td>
                            <td>
                                <?php if ($sound_data['recorded'] === null): ?>
                                    <span style="color: #f0ad4e;"><?php _e('Not yet recorded', 'bitesize-cursai'); ?></span>
                                    <span style="color: #999;"> (<?php _e('15 min cache', 'bitesize-cursai'); ?>)</span>
                                <?php else: ?>
                                    <span style="color: #46b450;"><?php _e('Yes', 'bitesize-cursai'); ?></span>
                                    <span style="color: #999;"> (<?php _e('24 hour cache', 'bitesize-cursai'); ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <td><strong><?php _e('Cache Key', 'bitesize-cursai'); ?></strong></td>
                        <td><code>bitesize_sound_<?php echo esc_html($check_sound_id); ?>_v1</code></td>
                    </tr>
                    
                    <?php if ($cache_expiry): ?>
                        <tr>
                            <td><strong><?php _e('Expires At', 'bitesize-cursai'); ?></strong></td>
                            <td>
                                <?php
                                $expiry_datetime = date('Y-m-d H:i:s', $cache_expiry);
                                $time_remaining = human_time_diff(time(), $cache_expiry);
                                $is_expired = $cache_expiry < time();
                                
                                if ($is_expired) {
                                    echo '<span style="color: #dc3232;">';
                                    echo esc_html($expiry_datetime);
                                    echo ' (' . __('expired', 'bitesize-cursai') . ')</span>';
                                } else {
                                    echo '<span style="color: #46b450;">';
                                    echo esc_html($expiry_datetime);
                                    echo '</span>';
                                    echo '<br><span style="color: #666; font-size: 12px;">';
                                    printf(__('(expires in %s)', 'bitesize-cursai'), $time_remaining);
                                    echo '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Current Server Time', 'bitesize-cursai'); ?></strong></td>
                            <td>
                                <span style="color: #666;"><?php echo esc_html(date('Y-m-d H:i:s')); ?></span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="bitesize_invalidate_sound">
                    <input type="hidden" name="sound_id" value="<?php echo esc_attr($check_sound_id); ?>">
                    <?php wp_nonce_field('bitesize_invalidate_sound'); ?>
                    <button type="submit" class="button button-secondary">
                        <?php _e('Invalidate This Cache', 'bitesize-cursai'); ?>
                    </button>
                </form>

            <?php else: ?>
                <div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">
                    <p style="margin: 0;"><strong style="color: #721c24;">✗ <?php _e('NOT CACHED', 'bitesize-cursai'); ?></strong></p>
                    <p style="margin: 5px 0 0 0; color: #721c24;">
                        <?php _e('This sound has not been cached yet. It will be cached the next time a page with this sound ID is viewed.', 'bitesize-cursai'); ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Recently Cached Sounds -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php _e('Recently Cached Sounds', 'bitesize-cursai'); ?></h2>
        
        <?php if (empty($recent_sounds)): ?>
            <p><?php _e('No sounds cached yet. Visit pages with audio buttons to populate the cache.', 'bitesize-cursai'); ?></p>
        <?php else: ?>
            <table class="widefat striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th><?php _e('Sound ID', 'bitesize-cursai'); ?></th>
                        <th><?php _e('Irish Text', 'bitesize-cursai'); ?></th>
                        <th><?php _e('Translation', 'bitesize-cursai'); ?></th>
                        <th><?php _e('Status', 'bitesize-cursai'); ?></th>
                        <th><?php _e('Action', 'bitesize-cursai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sounds as $sound_id => $data): ?>
                        <tr>
                            <td><strong><?php echo esc_html($sound_id); ?></strong></td>
                            <td>
                                <?php 
                                $text = !empty($data['text']) ? $data['text'] : (!empty($data['label']) ? $data['label'] : '—');
                                echo esc_html($text);
                                ?>
                            </td>
                            <td><?php echo esc_html(!empty($data['translation']) ? $data['translation'] : '—'); ?></td>
                            <td>
                                <?php if (isset($data['recorded']) && $data['recorded'] === null): ?>
                                    <span style="color: #f0ad4e;" title="<?php _e('Not yet recorded - 15 min cache', 'bitesize-cursai'); ?>">⏱</span>
                                <?php else: ?>
                                    <span style="color: #46b450;" title="<?php _e('Recorded - 24 hour cache', 'bitesize-cursai'); ?>">✓</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'bitesize-cursai', 'check_sound_id' => $sound_id], admin_url('admin.php'))); ?>" 
                                   class="button button-small">
                                    <?php _e('View', 'bitesize-cursai'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Bulk Actions -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php _e('Bulk Actions', 'bitesize-cursai'); ?></h2>
        <p><?php _e('Clear all cached sound metadata. Use this if you need to force-refresh all sounds.', 'bitesize-cursai'); ?></p>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" 
              onsubmit="return confirm('<?php _e('Are you sure you want to clear all cached sounds?', 'bitesize-cursai'); ?>');">
            <input type="hidden" name="action" value="bitesize_clear_all_caches">
            <?php wp_nonce_field('bitesize_clear_all_caches'); ?>
            <button type="submit" class="button button-secondary">
                <?php _e('Clear All Caches', 'bitesize-cursai'); ?> (<?php echo esc_html($total_cached); ?>)
            </button>
        </form>
    </div>

    <!-- Debug Information -->
    <div class="card" style="max-width: 800px; margin-bottom: 20px;">
        <h2><?php _e('Debug Information', 'bitesize-cursai'); ?></h2>
        <table class="widefat">
            <tr>
                <td style="width: 200px;"><strong><?php _e('WP_DEBUG_LOG', 'bitesize-cursai'); ?></strong></td>
                <td>
                    <?php if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG): ?>
                        <span style="color: #46b450;">✓ <?php _e('Enabled', 'bitesize-cursai'); ?></span>
                        <p class="description"><?php _e('Check debug.log for API authentication errors', 'bitesize-cursai'); ?></p>
                    <?php else: ?>
                        <span style="color: #999;"><?php _e('Disabled', 'bitesize-cursai'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e('Cache Type', 'bitesize-cursai'); ?></strong></td>
                <td>
                    <?php _e('WordPress Transients API', 'bitesize-cursai'); ?>
                    <?php if (wp_using_ext_object_cache()): ?>
                        <span style="color: #46b450;"><?php _e('(Using object cache)', 'bitesize-cursai'); ?></span>
                    <?php else: ?>
                        <span style="color: #999;"><?php _e('(Database storage)', 'bitesize-cursai'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .card h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
</style>

