<?php
namespace SIM\SIGNAL;
use SIM;

/*
	Add a signal page to user management screen
*/
add_filter('sim_user_info_page', __NAMESPACE__.'\userInfoPage', 10, 4);
function userInfoPage($filteredHtml, $showCurrentUserData, $user){

	$shouldShow	= apply_filters('sim-should-show-vaccination-form', true, $user->ID);

	if(!$shouldShow){
		return $filteredHtml;
	}

	//Add an extra tab
	$filteredHtml['tabs']['Signal']	= "<li class='tablink' id='show-signal-options' data-target='signal_options'>Signal options</li>";
	
    wp_enqueue_script( 'sim_signal_options');

	//Content
	ob_start();
    
	?>
	<div id='signal-options' class='tabcontent hidden'>
        <form>
			<input type='hidden' name='user-id' value='<?php echo $user->ID;?>'>
            <h3>Signal Options</h3>
            <?php
                $prefs      = get_user_meta($user->ID, 'signal_preferences', true);
                echo apply_filters('sim_personal_signal_settings', '', $user, $prefs);

                echo SIM\addSaveButton('save_signal_preferences','Update Preferences');
            ?>
        </form>
	</div>
	<?php

	$result	= ob_get_clean();

	$filteredHtml['html']	.= $result;

	return $filteredHtml;
}