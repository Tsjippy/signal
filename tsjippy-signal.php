<?php

namespace TSJIPPY\SIGNAL;

/**
 * Plugin Name:          Tsjippy Signal
 * Description:          This plugin adds the possibility to send Signal messages to users or groups.
 * Version:              10.4.8
 * Author:               Ewald Harmsen
 * AuthorURI:            harmseninnigeria.nl
 * Requires at least:    7.0
 * Requires PHP:         8.3
 * Tested up to:         7.0
 * Plugin URI:            https://github.com/Tsjippy/signal
 * Tested:                7.0
 * TextDomain:            tsjippy
 * Requires Plugins:    
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @author Ewald Harmsen
 */
if (! defined('ABSPATH')) {
    exit;
}

// Load shared code
if(file_exists(__DIR__  . '/shared-functionality/loader.php')){
    require_once(__DIR__  . '/shared-functionality/loader.php');
}

// Define constants
define(__NAMESPACE__ . '\PLUGIN', plugin_basename(__FILE__));
define(__NAMESPACE__ . '\PLUGINPATH', __DIR__ . '/');
define(__NAMESPACE__ . '\PLUGINVERSION', get_plugin_data(__FILE__, false, false)['Version']);
define(__NAMESPACE__ . '\PLUGINSLUG', str_replace('tsjippy-', '', basename(__FILE__, '.php')));
define(__NAMESPACE__ . '\SETTINGS', get_option('tsjippy_' . PLUGINSLUG . '_settings', []));

// run right before activation
register_activation_hook(__FILE__, function () {
    // Load shared code
    if(file_exists(__DIR__  . '/shared-functionality/loader.php')){
        require_once(__DIR__  . '/shared-functionality/loader.php');
    }
    
    $signal    = new Signal();

    $signal->createDbTables();
});
