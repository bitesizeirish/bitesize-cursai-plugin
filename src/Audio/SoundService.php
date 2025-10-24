<?php
/**
 * Sound Service - Server-Side Sound Metadata Fetching with Caching
 *
 * Centralized service for fetching and caching sound metadata from the
 * Bitesize Sounds API with authentication and TTL policy.
 *
 * @package BitesizeCursai\Audio
 */

namespace BitesizeCursai\Audio;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized service for fetching and caching sound metadata from the
 * Bitesize Sounds API with authentication and TTL policy.
 */
final class SoundService
{
    /**
     * Per-request in-memory cache to avoid duplicate upstream fetches
     * for the same sound ID during a single page render.
     * @var array<int, array>|array<int, \WP_Error>
     */
    private static array $inRequestResults = [];

    /**
     * Meta about the last operation, for observability (e.g., REST headers).
     * Keys: cache (HIT|MISS), upstream_status (int|null)
     * @var array{cache:string, upstream_status:int|null}
     */
    private array $lastMeta = [
        'cache' => 'MISS',
        'upstream_status' => null,
    ];

    /**
     * Return the last meta info for observability.
     * @return array{cache:string, upstream_status:int|null}
     */
    public function getLastMeta(): array
    {
        return $this->lastMeta;
    }

    /**
     * Get sound metadata. If no valid transient exists, fetch upstream now,
     * cache it according to TTL policy, and return the fresh data.
     * @param int $id
     * @return array|\WP_Error
     */
    public function getSound(int $id)
    {
        if ($id <= 0) {
            return new \WP_Error('invalid_id', 'Invalid sound ID');
        }

        // Per-request de-duplication
        if (array_key_exists($id, self::$inRequestResults)) {
            $this->lastMeta['cache'] = 'HIT';
            $this->lastMeta['upstream_status'] = null;
            return self::$inRequestResults[$id];
        }

        $key = $this->buildCacheKey($id);
        $cached = \get_transient($key);
        if (is_array($cached)) {
            $this->lastMeta['cache'] = 'HIT';
            $this->lastMeta['upstream_status'] = null;
            self::$inRequestResults[$id] = $cached;
            return $cached;
        }

        // Fetch upstream now (strict revalidation on miss/expired)
        $fetched = $this->fetchUpstream($id);
        if (\is_wp_error($fetched)) {
            // Do not negative-cache; surface the failure
            self::$inRequestResults[$id] = $fetched;
            return $fetched;
        }

        $ttl = $this->resolveTtl($fetched, 200);
        \set_transient($key, $fetched, $ttl);

        $this->lastMeta['cache'] = 'MISS';
        $this->lastMeta['upstream_status'] = 200;
        self::$inRequestResults[$id] = $fetched;
        return $fetched;
    }

    /**
     * Read cache only without attempting any upstream fetch.
     * @param int $id
     * @return array|null
     */
    public function getCachedSound(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $key = $this->buildCacheKey($id);
        $cached = \get_transient($key);
        if (is_array($cached)) {
            $this->lastMeta['cache'] = 'HIT';
            $this->lastMeta['upstream_status'] = null;
            return $cached;
        }
        $this->lastMeta['cache'] = 'MISS';
        $this->lastMeta['upstream_status'] = null;
        return null;
    }

    /**
     * Get expiry time for a cached sound.
     * @param int $id
     * @return int|null UNIX timestamp when cache expires, or null if not cached
     */
    public function getCacheExpiry(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }
        
        $timeout_key = '_transient_timeout_' . $this->buildCacheKey($id);
        $expiry = \get_option($timeout_key);
        
        return $expiry ? (int) $expiry : null;
    }

    /**
     * Invalidate a cached sound.
     * @param int $id
     * @return void
     */
    public function invalidate(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        \delete_transient($this->buildCacheKey($id));
        unset(self::$inRequestResults[$id]);
    }

    /**
     * Get all cached sound IDs and their data.
     * Returns array of sound data keyed by sound ID.
     * @param int $limit Maximum number of sounds to return
     * @return array
     */
    public function getAllCachedSounds(int $limit = 20): array
    {
        global $wpdb;
        
        // Query transients matching our pattern
        $pattern = '_transient_bitesize_sound_%_v1';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value 
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                ORDER BY option_id DESC 
                LIMIT %d",
                $pattern,
                $limit
            )
        );
        
        $sounds = [];
        foreach ($results as $row) {
            // Extract sound ID from option_name: _transient_bitesize_sound_347_v1
            if (preg_match('/_transient_bitesize_sound_(\d+)_v1/', $row->option_name, $matches)) {
                $sound_id = (int) $matches[1];
                $data = maybe_unserialize($row->option_value);
                if (is_array($data)) {
                    $sounds[$sound_id] = $data;
                }
            }
        }
        
        return $sounds;
    }

    /**
     * Get total count of cached sounds.
     * @return int
     */
    public function getCachedSoundsCount(): int
    {
        global $wpdb;
        
        $pattern = '_transient_bitesize_sound_%_v1';
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                $pattern
            )
        );
        
        return (int) $count;
    }

    /**
     * Clear all cached sounds.
     * @return int Number of caches cleared
     */
    public function clearAllCaches(): int
    {
        global $wpdb;
        
        // Get all transient keys
        $pattern = '_transient_bitesize_sound_%_v1';
        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name 
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                $pattern
            )
        );
        
        $count = 0;
        foreach ($keys as $key) {
            // Extract sound ID
            if (preg_match('/_transient_bitesize_sound_(\d+)_v1/', $key, $matches)) {
                $sound_id = (int) $matches[1];
                $this->invalidate($sound_id);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Fetch upstream with authentication headers.
     * @param int $id
     * @return array|\WP_Error
     */
    private function fetchUpstream(int $id)
    {
        $urlHost = defined('BITESIZE_API_URL') ? constant('BITESIZE_API_URL') : '';
        $apiKey = defined('BITESIZE_API_KEY') ? constant('BITESIZE_API_KEY') : '';
        $clientName = defined('BITESIZE_API_CLIENT_NAME') ? constant('BITESIZE_API_CLIENT_NAME') : '';

        if (empty($urlHost) || empty($apiKey) || empty($clientName)) {
            $this->log('CONFIG', "Missing API configuration constants");
            $this->lastMeta['upstream_status'] = null;
            return new \WP_Error('config_error', 'Missing API configuration');
        }

        $schemeHost = preg_match('#^https?://#i', $urlHost) ? $urlHost : ('https://' . $urlHost);
        $url = rtrim($schemeHost, '/') . '/api/sounds/' . $id;

        $args = [
            'method' => 'GET',
            'timeout' => 3,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => $apiKey,
                'X-Client-Name' => $clientName,
            ],
        ];

        $response = \wp_remote_get($url, $args);
        if (\is_wp_error($response)) {
            $this->lastMeta['upstream_status'] = null;
            $this->log('NETWORK', 'Error contacting Sounds API: ' . $response->get_error_message(), $id);
            return new \WP_Error('api_error', 'Error contacting Sounds API');
        }

        $status = (int) \wp_remote_retrieve_response_code($response);
        $this->lastMeta['upstream_status'] = $status;

        if ($status === 200) {
            $body = \wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (!is_array($data)) {
                $this->log('PARSE', 'Invalid JSON from upstream', $id);
                return new \WP_Error('api_error', 'Invalid JSON from upstream');
            }
            return $data;
        }

        if ($status === 401 || $status === 403) {
            $this->log('AUTH', 'AUTH FAILURE contacting Sounds API (status ' . $status . ')', $id);
            return new \WP_Error('unauthorized', 'Unauthorized contacting Sounds API', ['status' => $status]);
        }

        // Log other non-200 statuses for visibility
        $this->log('HTTP', 'Upstream returned status ' . $status, $id);
        return new \WP_Error('upstream_error', 'Upstream returned error', ['status' => $status]);
    }

    /**
     * Determine TTL based on data contents/status.
     * @param array $data
     * @param int|null $status
     * @return int TTL in seconds
     */
    private function resolveTtl(array $data, ?int $status): int
    {
        if ($status !== null && $status >= 500) {
            return 60; // 1m for server errors
        }
        // Not-yet-recorded heuristic: recorded === null
        if (array_key_exists('recorded', $data) && $data['recorded'] === null) {
            return 15 * 60; // 15m
        }
        return 24 * 60 * 60; // 24h default for recorded/normal
    }

    private function buildCacheKey(int $id): string
    {
        return 'bitesize_sound_' . $id . '_v1';
    }

    private function log(string $type, string $message, ?int $id = null): void
    {
        if (\defined('WP_DEBUG_LOG') && \constant('WP_DEBUG_LOG')) {
            $prefix = '[Bitesize Sound Service][' . $type . '] ';
            if ($id !== null) {
                $prefix .= '(ID ' . $id . ') ';
            }
            error_log($prefix . $message);
        }
    }
}

