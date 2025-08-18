<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim-library-send-book-of-the-day', __NAMESPACE__.'\sendBook', 10, 4);
function sendBook($description, $title, $image, $url){
  $recipients		= SIM\getModuleOption(MODULE_SLUG, 'groups');

  $message = "Book of the day:\n\n<b>$title</b>\n\n$description\n\nFind it here:\nhttps:\\\\$url";

  foreach($recipients as $recipient){
    sendSignalMessage($message, $recipient, $image);
  }
}