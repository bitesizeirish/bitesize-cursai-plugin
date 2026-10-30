<?php
/**
 * Unauthorized Redirect
 *
 * MemberPress sends every logged-out visitor on a protected URL to one
 * global address. Course and lesson URLs are the ones search engines know,
 * so they go to the marketing membership page. Everything else (the
 * account page, the members' home) is where existing members come to log
 * in, so those go to the cursai login page instead.
 *
 * @package BitesizeCursai\Access
 */

namespace BitesizeCursai\Access;

if (!defined('ABSPATH')) {
    exit;
}

class UnauthorizedRedirect
{
    const PUBLIC_PATH_PREFIX = '/courses/';

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
        add_filter('mepr_rule_redirect_unauthorized_url', [$this, 'filter_redirect_url'], 10, 3);
    }

    /**
     * Send non-course URLs to the cursai login page
     *
     * @param string $redirect_url The URL MemberPress chose
     * @param string $delim        Query string delimiter
     * @param string $uri          The requested URI
     * @return string
     */
    public function filter_redirect_url($redirect_url, $delim, $uri)
    {
        if (!class_exists('MeprOptions')) {
            return $redirect_url;
        }

        $path = (string) wp_parse_url((string) $uri, PHP_URL_PATH);
        if (strpos($path, self::PUBLIC_PATH_PREFIX) === 0) {
            return $redirect_url;
        }

        return \MeprOptions::fetch()->login_page_url('action=mepr_unauthorized&redirect_to=' . urlencode((string) $uri));
    }
}
