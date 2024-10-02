<?php

/**
 * find signal config here: nano /home/simnige1/.local/share/signal-cli/data/accounts.json
 * this file should be run from cron
 * crontab -e -u simnige1
 *
 * Something like:
 * @reboot export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1001/bus;/home/simnige1/web/simnigeria.org/public_html/wp-content/signal-cli/program/bin/signal-cli -o json --trust-new-identities=always daemon | while read -r line; do find -name signal-daemon.php 2>/dev/null -exec php "{}" "$line" \; ; done;
 * @reboot export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1001/bus;/home/simnige1/web/simnigeria.org/public_html/wp-content/signal-cli/program/signal-cli -o json --trust-new-identities=always daemon | while read -r line; do find -name signal-daemon.php 2>/dev/null -exec php "{}" "$line" \; ; done;'
 * 
 *  php "/home/simnige1/web/simnigeria.org/public_html/wp-content/plugins/sim-plugin/includes/modules/signal/daemon/signal-daemon copy.php"
 * /home/simnige1/web/simnigeria.org/public_html/wp-content/signal-cli/program/signal-cli --config /home/simnige1/.local/share/signal-cli/ -o json --trust-new-identities=always -a +2349011531222 daemon --http
 */
//use SIM;
use SIM\SIGNAL;

// load wp
//ob_start();
define( 'WP_USE_THEMES', false ); // Do not use the theme files
define( 'COOKIE_DOMAIN', false ); // Do not append verify the domain to the cookie

require(__DIR__."/../../../../../../../wp-load.php");
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

                    $signal->send($groupId, $answer['response'], $answer['pictures'], $data->envelope->timestamp, $data->envelope->source, $data->envelope->dataMessage->message);
                }
            }
        }
    }elseif(!isset($data->envelope->dataMessage->groupInfo)){
        $signal->sendMessageReaction($data->envelope->source, $data->envelope->timestamp, '', '👍🏽');

        $signal->sentTyping($data->envelope->source, $data->envelope->timestamp);

        $answer = getAnswer($message, $data->envelope->source);

        $signal->send($data->envelope->source, $answer['response'], $answer['pictures'], $data->envelope->timestamp, $data->envelope->source, $data->envelope->dataMessage->message);
    }

    // add message to the received table
    $signal->addToReceivedMessageLog($data->envelope->source, $message, $data->envelope->timestamp, $groupId, $attachments);
}

function getAnswer($message, $source){
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
    }elseif($lowerMessage == 'thanks' || str_contains($lowerMessage, 'thanks')){
        $response = 'You`re welcome!';
    }elseif(str_starts_with($lowerMessage, 'update prayer')){
        $response = updatePrayerRequest($message, $users);
    }elseif(str_contains($lowerMessage, 'prayer') && $name){
        $prayerRequest  = SIM\PRAYER\prayerRequest(true, true);
        $response       = "This is the prayer for today:\n\n{$prayerRequest['message']}";
        $pictures       = $prayerRequest['pictures'];
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
    }elseif(!empty($lowerMessage)){
        SIM\printArray("No answer found for '$message'");

        $response = 'I have no clue, do you know?';
    }else{
        $response = ' ';
    }

    return [
        'response'  => $response,
        'pictures'  => $pictures
    ];
}

function updatePrayerRequest($message, $users){
    global $signal;

    $timeStamp      = get_user_meta($users[0]->ID, 'pending-prayer-update', true);
    if(!$timeStamp || !is_numeric($timeStamp)){
        return "You do not have a pending prayer request";
    }

    $sendMessage    = $signal->getSendMessageByTimestamp($timeStamp);

    if(!preg_match_all("/[\d]{2}-[\d]{2}-[\d]{4}/m", $sendMessage, $matches, PREG_SET_ORDER, 0)){
        return "Not sure which prayer request is pending for you";
    }

    $replaceDate	= $matches[0][0];

    // get the prayer request to be replaced
    $prayer	= SIM\PRAYER\prayerRequest(false, false, $replaceDate);

    if(!$prayer){
        return "Could not find prayer request to update for $replaceDate";
    }

    // Split on the - 
    $exploded   = explode('-', $prayer['message']);
    if(count($exploded) < 2){
        return "Could not find prayer request to update.\nNot sure whose prayer request it is";
    }
    $prayerMessage = trim($exploded[1]);

    // perform the replacement
    if(strtolower($message) == 'update prayer correct'){
        foreach($users as $user){
            delete_user_meta($user->ID, 'pending-prayer-update');
        }

        $replacetext    = get_user_meta($user->ID, 'pending-prayer-update-text', true);
        delete_user_meta($user->ID, 'pending-prayer-update-text');

        if(empty($replacetext)){
            return 'Something went wrong';
        }

        $post               = get_post($prayer['post']);

        if(empty($post)){
            return 'no post found to replace in'.implode(';', $prayer);
        }

        $post->post_content = str_replace($prayerMessage, $replacetext, $post->post_content);
        // do the actual replacement
        wp_update_post(
            $post,
            false,
            false
        );

        return "Replaced:\n'$prayerMessage'\n\nwith:\n'$replacetext'";
    }

    // confirm the replacement
    $replacetext    = trim(str_ireplace('update prayer', '', $message));

    foreach($users as $user){
        update_user_meta($user->ID, 'pending-prayer-update-text', $replacetext);
    }

	return "I am going to replace:\n'$prayerMessage'\n\nwith\n'$replacetext'\n\nReply with 'update prayer correct' if I should continue";
}
