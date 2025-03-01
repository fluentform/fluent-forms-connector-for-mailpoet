<?php
/**
 * Plugin Name: Fluent Forms Connector for MailPoet
 * Plugin URI:  https://github.com/fluentform/fluent-forms-connector-for-mailpoet
 * Description: Connect Fluent Forms with MailPoet.
 * Author: WPManageNinja LLC
 * Author URI:  https://wpmanageninja.com/wp-fluent-form/
 * Version: 1.0.6
 * Text Domain: ffmailpoet
 */

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright 2019 WPManageNinja LLC. All rights reserved.
 */


defined('ABSPATH') or die;
define('FFMAILPOET_DIR', plugin_dir_path(__FILE__));
define('FFMAILPOET_URL', plugin_dir_url(__FILE__));

class FluentFormMailPoet
{

    public function boot()
    {
        if (!defined('FLUENTFORM')) {
            return $this->injectDependency();
        }

        $this->includeFiles();

        if (function_exists('wpFluentForm')) {
            return $this->registerHooks(wpFluentForm());
        }
    }

    protected function includeFiles()
    {
        if (!class_exists('\FluentForm\App\Http\Controllers\IntegrationManagerController')) {
            $this->injectDependency("FluentForm is not updated. Please install and activate the latest Fluent Forms plugin first.");
    
            return;
        }
        include_once FFMAILPOET_DIR . 'Integrations/Bootstrap.php';
    }

    protected function registerHooks($fluentForm)
    {
        if (class_exists('\MailPoet\API\API')) {
            if (!class_exists('\FluentForm\App\Http\Controllers\IntegrationManagerController')) {
                return;
            }
         
            new \FluentFormMailPoet\Integrations\Bootstrap($fluentForm);
        }
    }

    /**
     * Notify the user about the FluentForm dependency and instructs to install it.
     */
    protected function injectDependency($message = '')
    {
        add_action('admin_notices', function () use($message    ){
            $pluginInfo = $this->getFluentFormInstallationDetails();

            $class = 'notice notice-error';

            $install_url_text = 'Click Here to Install the Plugin';

            if ($pluginInfo->action == 'activate') {
                $install_url_text = 'Click Here to Activate the Plugin';
            }
            if(empty($message)) {
                $message = 'Fluent Forms is not installed or activated. Please install and activate the Fluent Forms plugin first.';
            }
            $text = $message.', <b><a href="' . $pluginInfo->url
                . '">' . $install_url_text . '</a></b>';

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $text);
        });
    }

    protected function getFluentFormInstallationDetails()
    {
        $activation = (object)[
            'action' => 'install',
            'url' => ''
        ];

        $allPlugins = get_plugins();

        if (isset($allPlugins['fluentform/fluentform.php'])) {
            $url = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=fluentform/fluentform.php'),
                'activate-plugin_fluentform/fluentform.php'
            );

            $activation->action = 'activate';
        } else {
            $api = (object)[
                'slug' => 'fluentform'
            ];

            $url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $api->slug),
                'install-plugin_' . $api->slug
            );
        }

        $activation->url = $url;

        return $activation;
    }
}

register_activation_hook(__FILE__, function () {
    $globalModules = get_option('fluentform_global_modules_status');
    if (!$globalModules || !is_array($globalModules)) {
        $globalModules = [];
    }

    $globalModules['ff_mailpoet'] = 'yes';
    update_option('fluentform_global_modules_status', $globalModules);
});

add_action('plugins_loaded', function () {
    (new FluentFormMailPoet())->boot();
});
