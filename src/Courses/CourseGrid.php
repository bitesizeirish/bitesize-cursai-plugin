<?php
/**
 * Course Grid
 *
 * Renders MemberPress Courses as a card grid via [bitesize_course_grid],
 * replacing the LearnDash [ld_course_list] shortcode.
 *
 * Usage:
 *   [bitesize_course_grid tag="foundations" scope="mine" cols="3"]
 *
 * scope: "mine" = courses the current visitor can open,
 *        "other" = courses they cannot, "all" = everything.
 *
 * @package BitesizeCursai\Courses
 */

namespace BitesizeCursai\Courses;

if (!defined('ABSPATH')) {
    exit;
}

class CourseGrid
{
    const COURSE_CPT = 'mpcs-course';
    const TAG_TAXONOMY = 'mpcs-course-tags';

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Whether the inline styles have been printed on this request
     */
    private $styles_printed = false;

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
        add_shortcode('bitesize_course_grid', [$this, 'render']);
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render($atts)
    {
        $atts = shortcode_atts([
            'tag'        => '',
            'scope'      => 'all',
            'cols'       => 3,
            'locked_url' => '',
        ], $atts, 'bitesize_course_grid');

        $args = [
            'post_type'      => self::COURSE_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => ['menu_order' => 'ASC', 'ID' => 'ASC'],
            'no_found_rows'  => true,
        ];

        if ($atts['tag'] !== '') {
            $args['tax_query'] = [[
                'taxonomy' => self::TAG_TAXONOMY,
                'field'    => 'slug',
                'terms'    => array_map('trim', explode(',', $atts['tag'])),
            ]];
        }

        $cards = '';
        foreach (get_posts($args) as $course) {
            $locked = $this->is_locked($course);

            if ($atts['scope'] === 'mine' && $locked) {
                continue;
            }
            if ($atts['scope'] === 'other' && !$locked) {
                continue;
            }

            $url = ($locked && $atts['locked_url'] !== '') ? $atts['locked_url'] : get_permalink($course);
            $cards .= $this->render_card($course, $url, $locked);
        }

        if ($cards === '') {
            return '';
        }

        $cols = max(1, min(4, (int) $atts['cols']));

        return $this->styles() . sprintf(
            '<div class="bsi-course-grid" style="--bsi-cols:%d">%s</div>',
            $cols,
            $cards
        );
    }

    /**
     * Whether the current visitor is locked out of a course by MemberPress
     */
    private function is_locked($course)
    {
        if (!class_exists('MeprRule')) {
            return false;
        }
        return (bool) \MeprRule::is_locked($course);
    }

    /**
     * Render a single course card
     */
    private function render_card($course, $url, $locked)
    {
        $image = get_the_post_thumbnail($course, 'medium_large', ['loading' => 'lazy']);

        return sprintf(
            '<a class="bsi-course-card%s" href="%s"><span class="bsi-course-card__image">%s</span><span class="bsi-course-card__title">%s</span></a>',
            $locked ? ' is-locked' : '',
            esc_url($url),
            $image,
            esc_html(get_the_title($course))
        );
    }

    /**
     * Inline styles, printed once per request
     */
    private function styles()
    {
        if ($this->styles_printed) {
            return '';
        }
        $this->styles_printed = true;

        return '<style>'
            . '.bsi-course-grid{display:grid;grid-template-columns:repeat(var(--bsi-cols),1fr);gap:24px;margin:24px 0}'
            . '@media(max-width:768px){.bsi-course-grid{grid-template-columns:1fr}}'
            . '.bsi-course-card{display:flex;flex-direction:column;background:#fefefe;border-radius:25px;overflow:hidden;text-decoration:none;color:inherit;box-shadow:0 2px 8px rgba(0,0,0,.08)}'
            . '.bsi-course-card:hover .bsi-course-card__title{color:#CB491D}'
            . '.bsi-course-card.is-locked{opacity:.75}'
            . '.bsi-course-card__image img{display:block;width:100%;height:auto}'
            . '.bsi-course-card__title{font-family:"Roboto Condensed",sans-serif;font-weight:700;font-size:1.2em;color:#fa5923;padding:16px 20px}'
            . '</style>';
    }
}
