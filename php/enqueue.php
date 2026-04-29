<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

add_action( 'wp_enqueue_scripts', __NAMESPACE__.'\loadAssets');
function loadAssets(){
    wp_register_script( 'tsjippy_signal_options', TSJIPPY\pathToUrl(PLUGINPATH.'js/signal.min.js'), array('tsjippy_formsubmit_script'), PLUGINVERSION, true);
    wp_register_script( 'tsjippy_signal_admin', TSJIPPY\pathToUrl(PLUGINPATH.'js/admin.min.js'), array('tsjippy_formsubmit_script'), PLUGINVERSION, true);

	wp_register_script('tsjippy_frontend_signal_script', TSJIPPY\pathToUrl(PLUGINPATH.'js/frontend-signal.min.js'), [], PLUGINVERSION, true);

}

add_filter('tsjippy-frontend-content-js', __NAMESPACE__.'\addSignalJs');

function addSignalJs($dependables){
    $dependables[]  = 'tsjippy_frontend_signal_script';

    return $dependables;
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__.'\loadAdminAssets');
function loadAdminAssets($hook) {
	//Only load on sim settings pages
	if(!str_contains($hook, 'tsjippy-settings_page_tsjippy_signal')) {
		return;
	}

	wp_enqueue_script('tsjippy_signal_admin', TSJIPPY\pathToUrl(PLUGINPATH.'js/admin.min.js'), array() ,PLUGINVERSION, true);
}