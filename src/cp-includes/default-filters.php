<?php
/* 
 * @package ClassicPress
 */

namespace ClassicPress\Core
{

    /** 
     * Adapted from "Disable Emojis (GDPR friendly)" 
     *  
     * @since 2.0.0
     */
    if (defined('__CORE__EMOJI')) {
        add_filter( 'the_content_feed', 'wp_staticize_emoji' );
        add_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        add_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        add_action( 'wp_head', 'print_emoji_detection_script', 7 );
        add_action( 'wp_print_styles', 'print_emoji_styles' );
        add_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        add_action( 'admin_print_styles', 'print_emoji_styles' );

    } else {
        add_filter('tiny_mce_plugins', __NAMESPACE__.'\disable_emojis_tinymce');
        add_filter('wp_resource_hints', __NAMESPACE__.'\disable_emojis_remove_dns_prefetch', 10, 2);

        /**
         * Filter function used to remove the tinymce emoji plugin. 
         * 
         * @see _WP_Editors::editor_settings
         *
         * @param    array  $plugins 
         * @return   array             Difference betwen the two arrays
         */
        function disable_emojis_tinymce(array $plugins): array
        {
            return array_diff($plugins, ['wpemoji']);
        }

        /**
         * Remove emoji CDN hostname from DNS prefetching hints.
         *
         * @param  array  $urls          URLs to print for resource hints.
         * @param  string $relation_type The relation type the URLs are printed for.
         * @return array                 Difference betwen the two arrays.
         */
        function disable_emojis_remove_dns_prefetch(array $urls, string $relation_type): array
        {
            if ('dns-prefetch' == $relation_type) {
                // Strip out any URLs referencing the WordPress.org emoji location
                $emoji_svg_url_bit = 'https://s.w.org/images/core/emoji/';

                return array_filter($urls, function ($url) use ($emoji_svg_url_bit) {
                    return (false === strpos($url, $emoji_svg_url_bit));
                });
            }

            return $urls;
        }
    }

}

