<?php
namespace SIM\SIGNAL;
use SIM;

/**
 * Check returns the required signal instance: cmd, dbus or jsonrpc
 * 
 * @param   bool        $getResult  Whether we should return the result, default true
 */
function getSignalInstance($getResult=true){
    if(str_contains(php_uname(), 'Linux')){
        $type   = SIM\getModuleOption(MODULE_SLUG, 'type');
        
        if($type && $type == 'dbus'){
            $signal	= new SignalBus($getResult);
        }else{
            $signal = new SignalJsonRpc(true, $getResult);
        }
    }else{
		$signal = new SignalCommandLine($getResult);
	}     
    
    return $signal;
}

