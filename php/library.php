<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim-library-send-book-of-the-day', __NAMESPACE_.'\sendBook', 10, 3);
function sendBook($message, $image, $url){
    $recipients		= SIM\getModuleOption(MODULE_SLUG, 'groups');

    $message = str_replace('<br>', "\n\n", $message);
    $message = $message . "\n\nFind it here:$url";
    
    foreach($recipients as $recipient){
		sendSignalMessage($message, $recipient, $image);
	}
}