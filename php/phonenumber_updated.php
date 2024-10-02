<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim-phonenumber-updated', function($phonenumber, $userId){

    $groupPaths		= SIM\getModuleOption(MODULE_SLUG, 'invgroups');

    $link			= '';
    if(is_array($groupPaths)){
        $signal	= getSignalInstance();

        foreach($groupPaths as $path){
            $result	= 	$signal->getGroupInvitationLink($path);
            if(empty($signal->error)){
                $link	.= $result;
            }
        }
    }else{
        $link		= SIM\getModuleOption(MODULE_SLUG, 'group_link');
    }

    $send   = true;
    
    // we send a signal message directly from the server
	if(SIM\getModuleOption(MODULE_SLUG, 'local')){
		$signal	= getSignalInstance();

        // check if valid signal number
        if($signal->isRegistered($phonenumber)){
            // Mark this number as the signal number
            update_user_meta( $userId, 'signal_number', $phonenumber );
        }else{
           $send    = false;
        }
	}

    // check if we need to remove the signal numbers
    if(!$send){
        $signalNumber   = get_user_meta( $userId, 'signal_number', $phonenumber );
        $phoneNumbers   = (array)get_user_meta( $userId, 'phonenumbers', $phonenumber );

        if(!in_array($signalNumber, $phoneNumbers)){
            delete_user_meta( $userId, 'signal_number');
        }
    }

    if(!empty($link) && $send){
	    $firstName	= get_userdata($userId)->first_name;
        $message	= "Hi $firstName\n\nI noticed you just updated your phonenumber on ".SITEURLWITHOUTSCHEME.".\n\nIf you want to join our Signal group with this number you can use this url:\n$link";
        sendSignalMessage($message, $phonenumber);
    }
}, 10, 2);