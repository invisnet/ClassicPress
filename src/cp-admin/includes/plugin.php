<?php
/**
 * ClassicPress Plugin Administration API
 *
 * @package ClassicPress
 * @subpackage Administration
 */

namespace ClassicPress\Core\Admin\Plugin;

/**
 *  
 *  
 * @since 2.0.0
 * 
 * @param array $plugin_data 
 * @param array $args 
 */
function _get_dependencies(array $plugin_data, array $args = [])
{
    $depends = [];
    $defaults = [
        'direction' => 'requires',
        'active'    => null
    ];
    $args = array_merge($defaults, $args);

    switch ($args['direction']) {
        case 'up':
            $from = 'Requires';
            $to = 'Provides';
            break;
        case 'down':
            $from = 'Provides';
            $to = 'Requires';
            break;
    }

    if (count($plugin_data[$from])) {
        $installed_plugins = get_plugins();
        $depend = $plugin_data[$from];

        foreach ($installed_plugins as $installed_plugin => $installed_plugin_data) {
            if (is_null($args['active']) || ($args['active'] && is_plugin_active($installed_plugin))) {
                if (count(array_intersect($depend, $installed_plugin_data[$to]))) {
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

    if (count($plugin_data['Requires'])) {
        $args['direction'] = 'up';
        if (0 == count(_get_dependencies($plugin_data, $args)) ) {
            unset($actions['activate']);
        }
    }

    if (count($plugin_data['Provides'])) {
        $args['direction'] = 'down';
        if (count(_get_dependencies($plugin_data, $args))) {
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
function _get_plugin_names(array $plugins)
{
    return array_map(
        function ($v) {
            return $v['Name'];
        },
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

    if (count($plugin_data['Requires'])) {
        $names = _get_plugin_names(_get_dependencies($plugin_data, ['direction' => 'up'] ));

        if (0 == count($names)) {
            // TODO: lookup suitable plugins from CP plugin directory
            $names[] = '<strong>'.__('MISSING PLUGIN(S)').'</strong>';
        }
        $plugin_meta[] = __('Depends on: ').implode(', ', $names);
    }

    if (count($plugin_data['Provides'])) {
        $names = _get_plugin_names(_get_dependencies($plugin_data, ['direction' => 'down'] ));

        if (count($names)) {
            $plugin_meta[] = __('Required by: ').implode(', ', $names);
        } else {
            $plugin_meta[] = '<em>'.__('(Not required by any installed plugins)').'</em>';
        }
    }

    return $plugin_meta;
}
add_filter('plugin_row_meta', __NAMESPACE__.'\plugin_row_meta_filter', 10, 4);
