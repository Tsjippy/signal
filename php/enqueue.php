<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

add_action('wp_enqueue_scripts', __NAMESPACE__ . '\loadAssets');
function loadAssets()
{
    wp_register_script('tsjippy_signal_options', TSJIPPY\pathToUrl(PLUGINPATH . 'js/signal.min.js'), array('tsjippy_formsubmit_script'), PLUGINVERSION, true);
    wp_register_script('tsjippy_signal_admin', TSJIPPY\pathToUrl(PLUGINPATH . 'js/admin.min.js'), array('tsjippy_formsubmit_script'), PLUGINVERSION, true);
}

add_action('admin_enqueue_scripts', __NAMESPACE__ . '\loadAdminAssets');
function loadAdminAssets($hook)
{
    //Only load on tsjippysettings pages
    if (!str_contains($hook, 'tsjippy-settings_page_tsjippy_signal')) {
        return;
    }

    wp_enqueue_script('tsjippy_signal_admin', TSJIPPY\pathToUrl(PLUGINPATH . 'js/admin.min.js'), array('tsjippy_script'), PLUGINVERSION, true);
}
