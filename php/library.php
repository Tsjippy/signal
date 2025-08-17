<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim-library-send-book-of-the-day', __NAMESPACE_.'\sendBook', 10, 3);
function sendBook($msg, $image, $url){
    sendSignal();
}