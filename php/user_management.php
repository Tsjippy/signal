<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

/*
	Add a signal page to user management screen
*/
add_filter('tsjippy_user_info_page', __NAMESPACE__.'\userInfoPage', 10, 4);
function userInfoPage($filteredHtml, $showCurrentUserData, $user){

	$shouldShow	= apply_filters('tsjippy-should-show-vaccination-form', true, $user->ID);

	if(!$shouldShow){
		return $filteredHtml;
	}

	//Add an extra tab
	$filteredHtml['tabs']['Signal']	= "<li class='tablink' id='show-signal-options' data-target='signal_options'>Signal options</li>";
	
    wp_enqueue_script( 'tsjippy_signal_options');

	//Content
	ob_start();
    
	?>
	<div id='signal-options' class='tabcontent hidden'>
        <form>
			<input type='hidden' class='no-reset' name='user-id' value='<?php echo $user->ID;?>'>
            <h3>Signal Options</h3>
            <?php
                $prefs      = get_user_meta($user->ID, 'signal_preferences', true);
                echo apply_filters('tsjippy_personal_signal_settings', '', $user, $prefs);

                echo TSJIPPY\addSaveButton('save_signal_preferences','Update Preferences');
            ?>
        </form>
	</div>
	<?php

	$result	= ob_get_clean();

	$filteredHtml['html']	.= $result;

	return $filteredHtml;
}