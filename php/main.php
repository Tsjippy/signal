<?php
namespace SIM\SIGNAL;
use SIM;

/**
 * Check returns the required signal instance: cmd, dbus or jsonrpc
 * 
 * @param   bool        $getResult  Whether we should return the result, default true
 */
function getSignalInstance($getResult=true){
    global $signalTrue;
    global $signalFalse;

    if($getResult && !empty($signalTrue)){
        return $signalTrue;
    }elseif(!empty($signalFalse)){
        return $signalFalse;
    }

    if(str_contains(php_uname(), 'Linux')){
        $signal = new SignalJsonRpc(true, $getResult);
    }else{
		//$signal = new SignalCommandLine($getResult);
        $signal = new SignalJsonRpc(true, $getResult);
	}

    if($getResult){
        $signalTrue     = $signal;
    }else{
        $signalFalse    = $signal;
    }
    
    return $signal;
}

 // Send an signal message before sending a mail. Do not continue sending the e-mail if not needed
 add_filter('wp_mail', __NAMESPACE__.'\sendEmailBySignal', 2);
 function sendEmailBySignal($args){
    $signal = getSignalInstance();

    $signal->sendEmailBySignal($args);

    return $args;
 }