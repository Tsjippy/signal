<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

add_action('wp_enqueue_scripts', __NAMESPACE__ . '\loadAssets');
function loadAssets()
{
    $deps   = SCRIPT_DEBUG ? [  
        '@tsjippy/form_exports'
    ] :
    [];
    wp_register_script_module('@tsjippy/signal_admin', TSJIPPY\pathToUrl(PLUGINPATH . 'js/admin' . TSJIPPY\JSEXTENSION), $deps, PLUGINVERSION);

    $deps   = SCRIPT_DEBUG ? [  
        '@tsjippy/form_submit_functions', 
        "@tsjippy/display_message"
    ] :
    [];
    wp_register_script_module('@tsjippy/signal_options', TSJIPPY\pathToUrl(PLUGINPATH . 'js/signal' . TSJIPPY\JSEXTENSION), $deps, PLUGINVERSION);
}
