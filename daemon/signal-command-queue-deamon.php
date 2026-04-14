<?php

/**
 * find signal config here: nano $HOME/.local/share/signal-cli/data/accounts.json
 * this file should be run from a service, see install/signal-cli-jsonrpc-daemon.service
*/
//use SIM;
use SIM\SIGNAL;

// load wp
//ob_start();
define( 'WP_USE_THEMES', false ); // Do not use the theme files
define( 'COOKIE_DOMAIN', false ); // Do not append verify the domain to the cookie

require(__DIR__."/../../../../wp-load.php");
require_once ABSPATH . WPINC . '/functions.php';

//print(ob_get_clean());

/* Remove the execution time limit */
set_time_limit(0);

$signal = SIGNAL\getSignalInstance($getResult=true);

if(!$signal->socket){
   print("Invalid socket: $signal->error\n");
   SIM\printArray("Invalid socket: $signal->error\n", true);
   return;
}

while(1){
    $command = $signal->getQueue();

    if(!empty($command)){
        $result = $signal->doRequest($command->method, $command->params);
        $signal->updateQueue($command->id, $result);
    }

    sleep(1);
}