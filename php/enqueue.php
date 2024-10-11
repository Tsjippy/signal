<?php
namespace SIM\SIGNAL;
use SIM;

add_action( 'wp_enqueue_scripts', function(){
    wp_register_script( 'sim_signal_options', SIM\pathToUrl(MODULE_PATH.'js/signal.min.js'), array('sim_formsubmit_script'), MODULE_VERSION, true);
    wp_register_script( 'sim_signal_admin', SIM\pathToUrl(MODULE_PATH.'js/admin.min.js'), array('sim_formsubmit_script'), MODULE_VERSION, true);

	wp_register_script('sim_frontend_signal_script', SIM\pathToUrl(MODULE_PATH.'js/frontend-signal.min.js'), [], MODULE_VERSION, true);
    add_filter('sim-frontend-content-js', function($dependables){
        $dependables[]  = 'sim_frontend_signal_script';

        return $dependables;
    });
});

add_action( 'admin_enqueue_scripts', function ($hook) {
	//Only load on sim settings pages
	if(!str_contains($hook, 'sim-settings_page_sim_signal')) {
		return;
	}

	wp_enqueue_script('sim_signal_admin', SIM\pathToUrl(MODULE_PATH.'js/admin.min.js'), array() ,MODULE_VERSION, true);
});