<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim-library-send-book-of-the-day', __NAMESPACE__.'\sendBook', 10, 3);
function sendBook($description, $title, $image, $url){
  $recipients		= SIM\getModuleOption(MODULE_SLUG, 'groups');

  $description = str_replace('<br>', "\n", $description);
  $message = "Book of the day: <b>$title</b>\n\n$description\n\nFind it here:$url";

  foreach($recipients as $recipient){
    sendSignalMessage($message, $recipient, $image);
  }
}