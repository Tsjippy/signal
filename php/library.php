<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim-library-send-book-of-the-day', __NAMESPACE__.'\sendBook', 10, 5);
function sendBook($description, $title, $image, $url, $locations){
  $recipients		= SIM\getModuleOption(MODULE_SLUG, 'groups');

  $excerptMore 		= apply_filters('excerpt_more', ' [...]');

  $description    = str_replace($excerptMore, '', $description);
  $message        = "Book of the day:\n\n<b>$title</b>\n\n$description\n\nRead more on:\n$url\n\nFind the book in the library at ".implode(' & ', $locations);

  foreach($recipients as $recipient){
    sendSignalMessage($message, $recipient, [$image]);
  }
}