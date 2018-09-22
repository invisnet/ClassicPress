<?php
/**
 * ClassicPress Plugin Dependencies
 *
 * @package ClassicPress
 * @subpackage Administration
 */

namespace ClassicPress\Core\Admin\Plugin;

/**
 * For plugins that can't or won't add Provides and Requires header lines
 */
const PLUGINS = [
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

function _get_plugin_features(string $plugin_file, array $plugin_data, string $which)
{
    switch ($which) {
        case 'Provides':
            $depends = array_merge($plugin_data['Provides'], [strtoupper(dirname($plugin_file))]);
            break;
        case 'Requires':
            $depends = $plugin_data['Requires'];
            break;
        default:
            throw new \InvalidArgumentException(__FUNCTION__.' $which expects (Provides|Requires)');
    }
    if (array_key_exists($plugin_file, PLUGINS[$which])) {
        $depends = array_merge($depends, PLUGINS[$which][$plugin_file]);
    }

    return $depends;
}

function _get_unfulfilled_features(string $plugin_file, array $plugin_data)
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
function _calculate_plugin_dependencies(string $plugin_file, array $plugin_data, array $args = [])
{
    $depends = [];
    $defaults = [
        'which'     => 'Requires',
        'active'    => null
    ];
    $args = array_merge($defaults, $args);

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
 * @param array  $actions     An array of plugin action links. By default this can include 'activate',
 *                            'deactivate', and 'delete'. With Multisite active this can also include
 *                            'network_active' and 'network_only' items.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @param array  $plugin_data An array of plugin data. See `get_plugin_data()`.
 * @param string $context     The plugin context. By default this can include 'all', 'active', 'inactive',
 *                            'recently_activated', 'upgrade', 'mustuse', 'dropins', and 'search'.
 */
function plugin_action_links_filter(array $actions, string $plugin_file, array $plugin_data, string $context)
{
    $args = [
        'active' => true
    ];

    $requires = _get_plugin_features($plugin_file, $plugin_data, 'Requires');
    if (count($requires)) {
        $args['which'] = 'Requires';
        if (count($requires) > count(_calculate_plugin_dependencies($plugin_file, $plugin_data, $args)) ) {
            unset($actions['activate']);
        }
    }

    $provides = _get_plugin_features($plugin_file, $plugin_data, 'Provides');
    if (count($provides)) {
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
function _get_plugin_names(array $plugins, string $context)
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
 */
function plugin_row_meta_filter(array $plugin_meta, string $plugin_file, array $plugin_data, string $status)
{
    $plugin_meta = (array)$plugin_meta;

    $depends = _get_plugin_features($plugin_file, $plugin_data, 'Requires');
    if (count($depends)) {
        $dependencies = _calculate_plugin_dependencies($plugin_file, $plugin_data, ['which' => 'Requires']);
        $names = _get_plugin_names($dependencies, $status);

        if (count($depends) > count($names)) {
            foreach (_get_unfulfilled_features($plugin_file, $plugin_data) as $feature) {
                $names[] = '<u>'.__('NOT INSTALLED').'</u> <a href="#?s='.$feature.'">('.__('Search').')</a>';
            }
        }
        $plugin_meta[] = __('Depends on: ').implode(', ', $names);
    } else {
        $plugin_meta[] = '<i>'.__('Needs no other plugins').'</i>';
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
