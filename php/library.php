<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim-library-send-book-of-the-day', __NAMESPACE_.'\sendBook', 10, 3);
function sendBook($message, $image, $url){
    $recipients		= SIM\getModuleOption(MODULE_SLUG, 'groups');

    foreach($recipients as $recipient){
		sendSignalMessage($message, $recipient, $image);
	}
}