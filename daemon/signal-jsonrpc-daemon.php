<?php

/**
 * find signal config here: nano /home/simnige1/.local/share/signal-cli/data/accounts.json
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

include_once __DIR__.'/../php/__module_menu.php';
include_once __DIR__.'/../php/classes/SignalJsonRpc.php';

$signal = new SIGNAL\SignalJsonRpc(false, true);

if(!$signal->socket){
   print("Invalid socket: $signal->error\n");
   SIM\printArray("Invalid socket: $signal->error\n", true);
   return;
}

while(1){
    $response = '';

    $x      = 0;
    $base   = '{"jsonrpc":';
    while (!feof($signal->socket)) {
        $response       .= fgets($signal->socket, 4096);

        // somehow we are reading the second one already
        if(substr_count($response, $base) > 1){
            // loop over each jsonrpc response to find the one with a result property
            foreach(explode($base, $response) as $jsonString){
                $decoded    = json_decode($base.$jsonString);

                if(!empty($decoded) && isset($decoded->method) && $decoded->method == 'receive'){
                    $response   = json_encode($decoded);
                    break 2;
                }
            }
        }

        //SIM\printArray($response, true);

        if(!empty(json_decode($response))){
            //SIM\printArray(json_decode($response));
            break;
        }

        $streamMetaData  = stream_get_meta_data($signal->socket);

        if($streamMetaData['unread_bytes'] <= 0){
            $x++;

            if( $x > 10 ){
                break;
            }
        }
    }
    flush();

    $response   = trim($response);

    if(empty($response)){
        continue;
    }

    $json   = json_decode($response);

    if(empty($json)){
        if(empty($response)){
            SIM\printArray("Response is empty");
        }else{
            SIM\printArray("Response is '$response'");
        }

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                SIM\printArray(' No errors'.$response, true);
                break;
            case JSON_ERROR_DEPTH:
                SIM\printArray(' Maximum stack depth exceeded'.$response, true);
                break;
            case JSON_ERROR_STATE_MISMATCH:
                SIM\printArray(' Underflow or the modes mismatch'.$response, true);
                break;
            case JSON_ERROR_CTRL_CHAR:
                SIM\printArray(' Unexpected control character found'.$response, true);
                break;
            case JSON_ERROR_SYNTAX:
                SIM\printArray(' Syntax error, malformed JSON: '.$response, true);
                break;
            case JSON_ERROR_UTF8:
                SIM\printArray(' Malformed UTF-8 characters, possibly incorrectly encoded'.$response, true);
                break;
            default:
                break;
        }

        continue;
    }

    // incoming message
    if($json->method == 'receive'){
        print("receive");
        processMessage($json->params);
    }elseif(isset($json->result)){
        SIM\printArray($json);
        $signalResults              = get_option('sim-signal-results', []);

        $signalResults[$json->id]   = $json;

        update_option('sim-signal-results', $signalResults);
    }
}

SIM\printArray("The end", true);

function processMessage($data){
    global $signal;

    //SIM\printArray($data, true);

    // no message found
    if(!isset($data->envelope->dataMessage) || empty($data->envelope->dataMessage->message)){
        return;
    }

    if($data->account != $signal->phoneNumber){
        SIM\printArray($data);
        return;
    }

    $message        = $data->envelope->dataMessage->message;
    $groupId        = $data->envelope->source;

    $attachments    = [];

    if(isset($data->envelope->dataMessage->attachments)){
        foreach($data->envelope->dataMessage->attachments as $attachment){
            $path       = "$signal->homeFolder/.local/share/signal-cli/attachments/{$attachment->id}";

            $newPath    = "$signal->attachmentsPath/{$attachment->filename}";

            // move the attachment
            $result = rename($path, $newPath);
            if($result){
                $attachments[]      = $newPath;
            }else{
                SIM\printArray("Failed to move $path to $newPath ");
            }
        }
    }

    // message to group
    if(isset($data->envelope->dataMessage->groupInfo)){
        $groupId    = $data->envelope->dataMessage->groupInfo->groupId;

        // we are mentioned
        if( isset($data->envelope->dataMessage->mentions)){
            foreach($data->envelope->dataMessage->mentions as $mention){
                if($mention->number == $signal->phoneNumber){
                    $signal->sendMessageReaction($data->envelope->source, $data->envelope->timestamp, $groupId, '👍🏽');

                    $signal->sentTyping($data->envelope->source, '', $groupId);

                    // Remove mention from message
                    $message    = utf8_decode($message);
                    $message    = substr($message, $data->envelope->dataMessage->mentions[0]->length);
                    $answer     = getAnswer(trim($message, " \t\n\r\0\x0B?"), $data->envelope->source);

                    $signal->send($groupId, $answer['message'], $answer['pictures'], $data->envelope->timestamp, $data->envelope->source, $data->envelope->dataMessage->message);
                }
            }
        }
    }elseif(!isset($data->envelope->dataMessage->groupInfo)){
        $signal->sendMessageReaction($data->envelope->source, $data->envelope->timestamp, '', '👍🏽');

        $signal->sentTyping($data->envelope->source, $data->envelope->timestamp);

        $answer = getAnswer($message, $data->envelope->source);

        $signal->send($data->envelope->source, $answer['message'], $answer['pictures'], $data->envelope->timestamp, $data->envelope->source, $data->envelope->dataMessage->message);
    }

    // add message to the received table
    $signal->addToReceivedMessageLog($data->envelope->source, $message, $data->envelope->timestamp, $groupId, $attachments);
}

function getAnswer($message, $source){
    global $signal;

    $lowerMessage = strtolower($message);

    //Change the user to the adminaccount otherwise get_users will not work
    wp_set_current_user(1);

    // Find the first name
    $name = false;
    $users = get_users(array(
        'meta_key'     => 'signal_number',
        'meta_value'   => $source ,
    ));

    if(!empty($users)){
        $name = $users[0]->first_name;
    }

    $pictures   = [];
    $response   = '';

    if($lowerMessage == 'test'){
        $response    = 'Awesome!';
    }elseif(str_contains($lowerMessage, 'where are you')){
        $response    = 'Sorry for being away, but I am now back in full capacity!';
    }elseif($lowerMessage == 'thanks' || str_contains($lowerMessage, 'thanks')){
        $response = 'You`re welcome!';
    }elseif($lowerMessage == 'hi' || str_contains($lowerMessage, 'hello')){
        $response = "Hi ";
        if($name){
            $response   .= $name;
        }
    }elseif($lowerMessage == 'good morning'){
        $response = "Good morning ";
        if($name){
            $response   .= $name;
        }
    }elseif($lowerMessage == 'good afternoon'){
        $response = "Good afternoon ";
        if($name){
            $response   .= $name;
        }
    }elseif($lowerMessage == 'good evening'){
        $response = "Good evening ";
        if($name){
            $response   .= $name;
        }
    }elseif($lowerMessage == 'good night'){
        $response = "Good night ";
        if($name){
            $response   .= $name;
        }
    }elseif(str_contains($lowerMessage, 'thank you')){
        $response = "You are welcome ";
        if($name){
            $response   .= $name;
        }
    }elseif(str_contains($lowerMessage, 'help')){
        $response = "";
        if($name){
            $response   .= $name.', ';
        }
        $response .= "I am so sorry to hear you need help. I am afraid I am not a good councelor";
    }

    $response   = [
        'message'   => $response,
        'pictures'  => $pictures
    ];

    $response   = apply_filters('sim-signal-daemon-response', $response, $lowerMessage, $source, $users, $name, $signal);

    if(empty($response) && !empty($lowerMessage)){
        SIM\printArray("No answer found for '$message'");

        $response = 'I have no clue, do you know?';
    }

    return $response;
}


