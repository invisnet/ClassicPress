<?php
/**
 * ClassicPress Plugin Dependencies
 *
 * @package ClassicPress
 * @subpackage Administration 
 * 
 * @author invisnet 
 * @since 2.0.0 
 */

declare(strict_types=1);

namespace ClassicPress\Core\Admin\Plugin\Dependencies;

/**
 * This is a minimally-invasive implementation of plugin dependencies. 
 *  
 * The basic concept is that a plugin Provides and/or Requires a list of Features. Like 
 * pre-Gutenberg WordPress, ClassicPress is all about giving users the choice of how their site 
 * works, so by defining requirements as Features instead of specific plugins those Features 
 * can be provided in different ways by different plugins.
 *  
 *  
 * Provides: 
 * ========= 
 *  
 * Plugins declare the Features they Provide by adding a line to their plugin header block: 
 * 
 *     `Provides: MyPlugin_Foo`
 *  
 * Plugins MAY Provide multiple features in a comma-separated list, but this SHOULD be avoided 
 * wherever possible: 
 *  
 *     `Provides: MyPlugin_Foo, MyPlugin_Bar`
 *  
 * Remember that ClassicPress supports multiple plugins within the same directory - the two
 * features above SHOULD be implemented that way. 
 *  
 * Where there is only ONE plugin in a directory it MAY Provide itself. This MUST be the base 
 * directory name of the plugin (see Feature Names).
 *  
 *  
 * Requires: 
 * ========= 
 *  
 * Plugins declare the Features they Require by adding a line to their plugin header block: 
 *  
 *     `Requires: FooPlugin_Baz, BarPlugin_Fuz`
 *  
 *  
 * Feature Names 
 * ============= 
 *  
 * New Feature names MUST start with the base directory name of the plugin followed by an 
 * underscore. Feature names are case-insensitive. 
 *  
 * Plugins are assumed to Provide themselves, and this is automatically generated as 
 * <dirname>_<basename>. For example, a plugin in directory `CP-FooBar` with the plugin header 
 * in `Snafu.php` would automatically Provide `CP-FOOBAR_SNAFU`. 
 *  
 *  
 * Plugin Activate/Deactivation 
 * ============================ 
 *  
 * Plugins cannot be activated until all the Required Features are Provided by active 
 * plugin(s); the Activate link is removed.
 *  
 * A plugin cannot be deactivated while any of its Features are being used by an active plugin;
 * the Deactivate link is removed. 
 *  
 *  
 * Limitations 
 * =========== 
 *  
 * At the time of writing (2018/09/23) ClassicPress has no plugin repo, so some parts of this 
 * cannot be completed, e.g FORCED_DEPENDENCIES and searching for plugins to fulfill missing 
 * Features.  
 *  
 * The current implementation DOES NOT: 
 *  
 *  + Detect or handle dependency loops
 *  + Detect or handle Feature conflicts
 *  + Actually prevent the activation/deactivation of a plugin
 *  + Address the obvious need for a cannonical representation of a Feature interface
 *  + Claim to be perfect in any way
 *  
 */

/**
 * For plugins that can't or won't add Provides and Requires header lines.
 *  
 * This should be a lump of JSON pulled down from the ClassicPress.net server and cached. 
 *  
 * @since 2.0.0 
 */
const FORCED_DEPENDENCIES = [
    'Provides' => [
    ],
    'Requires' => [
        'test-requires2.php' => [
            '__CORE__XML-RPC',
        ],
        'jetpack/jetpack.php' => [
            '__CORE__XML-RPC',
        ]
    ]
];

/**
 * 
 * 
 * 
 * @param string $plugin_file 
 * @param array  $plugin_data 
 * @param string $which 
 */
function _get_plugin_features(string $plugin_file, array $plugin_data, string $which = 'Provides')
{
    switch ($which) {
        case 'Provides':
            // plugins always Provide themselves
            $features = array_merge($plugin_data['Provides'], [strtoupper(dirname($plugin_file).'_'.basename($plugin_file))]);
            break;
        case 'Requires':
            $features = $plugin_data['Requires'];
            break;
        default:
            throw new \InvalidArgumentException(__FUNCTION__.' $which expects (Provides|Requires)');
    }
    if (array_key_exists($plugin_file, FORCED_DEPENDENCIES[$which])) {
        $features = array_merge($features, FORCED_DEPENDENCIES[$which][$plugin_file]);
    }

    return $features;
}

/**
 * Get a list of Required Features not Provided by any installed plugin. 
 *  
 * @since 2.0.0
 * 
 * @param string $plugin_file 
 * @param array  $plugin_data 
 *  
 * @return array A list of Features. 
 */
function _get_unfulfilled_features(string $plugin_file, array $plugin_data): array
{
    $installed_plugins = get_plugins();
    $plugin_features = _get_plugin_features($plugin_file, $plugin_data, 'Requires');

    foreach ($installed_plugins as $installed_plugin => $installed_plugin_data) {
        $installed_plugin_features = _get_plugin_features($installed_plugin, $installed_plugin_data, 'Provides');
        $plugin_features = array_diff($plugin_features, $installed_plugin_features);
    }

    return $plugin_features;
}

/**
 *  
 *  
 * @since 2.0.0
 *  
 * @param string $plugin_file 
 * @param array  $plugin_data 
 * @param array  $args 
 */
function _calculate_plugin_dependencies(string $plugin_file, array $plugin_data, array $args = []): array
{
    $depends = [];
    $default_args = [
        'which'     => 'Requires',
        'active'    => null
    ];
    $args = array_merge($default_args, $args);

    switch ($args['which']) {
        case 'Requires':
            $from = 'Requires';
            $to = 'Provides';
            break;
        case 'Provides':
            $from = 'Provides';
            $to = 'Requires';
            break;
        default:
            throw new \InvalidArgumentException(__FUNCTION__.' $args[which] expects (Provides/Requires)');
    }

    $features = _get_plugin_features($plugin_file, $plugin_data, $from);
    if (count($features)) {
        $installed_plugins = get_plugins();

        foreach ($installed_plugins as $installed_plugin => $installed_plugin_data) {
            if (is_null($args['active']) || ($args['active'] && is_plugin_active($installed_plugin))) {
                $overlap = _get_plugin_features($installed_plugin, $installed_plugin_data, $to);
                if (count(array_intersect($features, $overlap))) {
                    $depends[$installed_plugin] = $installed_plugin_data;
                }
            }
        }
    }

    return $depends;
}

/**
 *  
 *  
 * @since 2.0.0 
 *  
 * @param array  $plugin_data An array of plugin data. See `get_plugin_data()`. 
 * @param string $plugin_file Not used.
 * @param bool   $markup      Not used.
 * @param bool   $translated  Not used.
 */
function get_plugin_data_filter(array $plugin_data, string $plugin_file, bool $markup, bool $translate): array
{
    if ($plugin_data['Requires']) {
        // TODO: make this robust
        $plugin_data['Requires'] = array_map('trim', explode(',', strtoupper($plugin_data['Requires'])));
    } else {
        $plugin_data['Requires'] = [];
    }
    if ($plugin_data['Provides']) {
        // TODO: make this robust
        $plugin_data['Provides'] = array_map('trim', explode(',', strtoupper($plugin_data['Provides'])));
    } else {
        $plugin_data['Provides'] = [];
    }

    return $plugin_data;
}
add_filter('get_plugin_data', __NAMESPACE__.'\get_plugin_data_filter', 10, 4);

/**
 * 
 *  
 * @since 2.0.0 
 * 
 * @param array  $actions     An array of plugin action links. By default this can include 'activate',
 *                            'deactivate', and 'delete'. With Multisite active this can also include
 *                            'network_active' and 'network_only' items.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @param array  $plugin_data An array of plugin data. See `get_plugin_data()`.
 * @param string $context     The plugin context. By default this can include 'all', 'active', 'inactive',
 *                            'recently_activated', 'upgrade', 'mustuse', 'dropins', and 'search'.
 *  
 * @return array              A possibly modified array of plugin action links.
 */
function plugin_action_links_filter(array $actions, string $plugin_file, array $plugin_data, string $context): array
{
    $args = [
        'active' => true
    ];

    $features = _get_plugin_features($plugin_file, $plugin_data, 'Requires');
    if (count($features)) {
        $args['which'] = 'Requires';
        if (count($features) > count(_calculate_plugin_dependencies($plugin_file, $plugin_data, $args)) ) {
            unset($actions['activate']);
        }
    }

    $features = _get_plugin_features($plugin_file, $plugin_data, 'Provides');
    if (count($features)) {
        $args['which'] = 'Provides';
        if (count(_calculate_plugin_dependencies($plugin_file, $plugin_data, $args))) {
            unset($actions['deactivate']);
        }
    }

    return $actions;
}
add_filter('plugin_action_links', __NAMESPACE__.'\plugin_action_links_filter', 10, 4);

/**
 *  
 *  
 * @since 2.0.0
 * 
 * @param array $plugins A list of plugin data arrays. 
 *  
 * @return array List of plugin names. 
 */
function _get_plugin_names(array $plugins, string $context): array
{
    return array_map(
        function ($k, $v) use ($context) {
            if (is_plugin_active($k)) {
                return "<b>{$v['Name']}</b>";
            } else {
                /**
                 * No-one thought to create a helper function for this mess 
                 *  
                 * @see WP_Plugins_List_Table::single_row 
                 */
                global $page, $s;

                $url = 'plugins.php?action=activate&amp;plugin='.urlencode($k).'&amp;plugin_status='.$context.'&amp;paged='.$page.'&amp;s='.$s;
                $label = esc_attr(sprintf(_x('Activate %s', 'plugin'), $v['Name']));

                return '<i>'.$v['Name'].'</i> <a href="'.wp_nonce_url($url, 'activate-plugin_'.$k).'" class="edit" aria-label="'.$label.'">('.__('Activate').')</a>';
            }
        },
        array_keys($plugins),
        $plugins
    );
}

/**
 * 
 *
 * @since 2.0.0
 *
 * @param array  $plugin_meta An array of the plugin's metadata,
 *                            including the version, author,
 *                            author URI, and plugin URI.
 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
 * @param array  $plugin_data An array of plugin data.
 * @param string $status      Status of the plugin. Defaults are 'All', 'Active',
 *                            'Inactive', 'Recently Activated', 'Upgrade', 'Must-Use',
 *                            'Drop-ins', 'Search'.
 * 
 * @return array              An extended $plugin_meta.
 */
function plugin_row_meta_filter(array $plugin_meta, string $plugin_file, array $plugin_data, string $status): array
{
    $plugin_meta = (array)$plugin_meta;

    $required_features = _get_plugin_features($plugin_file, $plugin_data, 'Requires');
    if (count($required_features)) {
        $dependencies = _calculate_plugin_dependencies($plugin_file, $plugin_data, ['which' => 'Requires']);
        $names = _get_plugin_names($dependencies, $status);

        if (count($required_features) > count($names)) {
            foreach (_get_unfulfilled_features($plugin_file, $plugin_data) as $feature) {
                $names[] = '<u>'.__('NOT INSTALLED').'</u> <a href="#?s='.$feature.'">('.__('Search').')</a>';
            }
        }
        $plugin_meta[] = __('Depends on: ').implode(', ', $names);
    } else {
        $plugin_meta[] = '<i>'.__('No other plugins needed').'</i>';
    }

    $dependencies = _calculate_plugin_dependencies($plugin_file, $plugin_data, ['which' => 'Provides']);
    $names = _get_plugin_names($dependencies, $status);

    if (count($names)) {
        $plugin_meta[] = __('Required by: ').implode(', ', $names);
    } else {
        $plugin_meta[] = '<i>'.__('Not required by any installed plugins').'</i>';
    }

    return $plugin_meta;
}
add_filter('plugin_row_meta', __NAMESPACE__.'\plugin_row_meta_filter', 10, 4);
