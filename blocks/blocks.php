<?php
namespace SIM\SIGNAL;
use SIM;

// Load the js file to filter all blocks
add_action( 'enqueue_block_editor_assets', __NAMESPACE__.'\loadBlockAssets');
function loadBlockAssets() {
    wp_enqueue_script(
        'sim-signal-block',
        SIM\pathToUrl(MODULE_PATH.'blocks/signal_options/build/index.js'),
        [ 'wp-blocks', 'wp-dom', 'wp-dom-ready', 'wp-edit-post' ],
        MODULE_VERSION
    );
}

// register custom meta tag field
add_action( 'init', __NAMESPACE__.'\blockInit' );
function blockInit(){
	register_post_meta( '', 'send_signal', array(
        'show_in_rest' 	    => true,
        'single' 		    => true,
        'type' 			    => 'boolean',
		'sanitize_callback' => 'sanitize_text_field'
    ) );

	register_post_meta( '', 'signal_message_type', array(
        'show_in_rest' 	    => true,
        'single' 		    => true,
        'type' 			    => 'string',
		'sanitize_callback' => 'sanitize_text_field'
    ) );

	register_post_meta( '', 'signal_extra_message', array(
        'show_in_rest' 	    => true,
        'single' 		    => true,
        'type' 			    => 'string',
		'sanitize_callback' => 'sanitize_text_field'
    ) );

    register_post_meta( '', 'signal_url', array(
        'show_in_rest' 	    => true,
        'single' 		    => true,
        'type' 			    => 'boolean',
		'sanitize_callback' => 'sanitize_text_field'
    ) );
}