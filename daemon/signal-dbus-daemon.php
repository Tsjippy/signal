<?php

/**
 * find signal config here: nano /home/simnige1/.local/share/signal-cli/data/accounts.json
 * this file should be run from cron
 * crontab -e -u simnige1
 *
 * Something like:
 * @reboot export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1001/bus;/home/simnige1/web/simnigeria.org/public_html/wp-content/signal-cli/program/bin/signal-cli -o json --trust-new-identities=always daemon | while read -r line; do find -name signal-daemon.php 2>/dev/null -exec php "{}" "$line" \; ; done;
 * @reboot export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1001/bus;/home/simnige1/web/simnigeria.org/public_html/wp-content/signal-cli/program/signal-cli -o json --trust-new-identities=always daemon --dbus-system| while read -r line; do find -name signal-daemon.php 2>/dev/null -exec php "{}" "$line" \; ; done;
 */
use SIM\SIGNAL\SignalBus;
//use SIM;

///$myfile = fopen("/home/simnige1//web/simnigeria.org/public_html/wp-content/debug1.log", "a") or die("Unable to open file!");
//fwrite($myfile,print_r($argv, true));

if(!empty($argv) && count($argv) == 2){
    $data      = json_decode($argv[1]);
    //fwrite($myfile,print_r($data, true));
    //fclose($myfile);

    // no message found
    if(!isset($data->envelope->dataMessage) || empty($data->envelope->dataMessage->message)){
        return;
    }

    // load wp
    ob_start();
    define( 'WP_USE_THEMES', false ); // Do not use the theme files
    define( 'COOKIE_DOMAIN', false ); // Do not append verify the domain to the cookie

    require(__DIR__."/../../../../../../../wp-load.php");
    require_once ABSPATH . WPINC . '/functions.php';

    $discard = ob_get_clean();

    /* Remove the execution time limit */
    set_time_limit(0);

    include_once __DIR__.'/../php/__module_menu.php';
    include_once __DIR__.'/../php/classes/Signal.php';

    
    //SIM\printArray($data);

    $signal = new SignalBus();

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
        $groupId    = $signal->groupIdToByteArray($data->envelope->dataMessage->groupInfo->groupId);

        // we are mentioned
        if( isset($data->envelope->dataMessage->mentions)){
            foreach($data->envelope->dataMessage->mentions as $mention){
                if($mention->number == $signal->phoneNumber || $mention->name == '8fc6c236-f07b-4a3c-97c5-0a78efe488ee'){
                    $signal->sendMessageReaction($data->envelope->source, $data->envelope->timestamp, $groupId, '👍🏽');

                    $signal->sendGroupTyping($groupId);

                    // Remove mention from message
                    $message    = utf8_decode($message);
                    $message    = substr($message, $data->envelope->dataMessage->mentions[0]->length);
                    $answer     = getAnswer(trim($message, " \t\n\r\0\x0B?"), $data->envelope->source);

                    $signal->sendGroupMessage($answer['response'], $groupId, $answer['pictures']);
                }
            }
        }
    }elseif(!isset($data->envelope->dataMessage->groupInfo)){
        $signal->sendMessageReaction($data->envelope->source, $data->envelope->timestamp, '', '👍🏽');

        $signal->sentTyping($data->envelope->source, $data->envelope->timestamp);

        $answer = getAnswer($message, $data->envelope->source);

        $signal->send($data->envelope->source, $answer['response'], $answer['pictures']);
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
    }elseif($lowerMessage == 'thanks'){
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
    $timeStamp      = get_user_meta($users[0]->ID, 'pending-prayer-update', true);
    if(!$timeStamp || !is_numeric($timeStamp)){
        return "Could not find prayer request to update for timestamp '$timeStamp'";
    }

    $signal         = new SignalBus();

    $sendMessage    = $signal->getSendMessageByTimestamp($timeStamp);

    if(!preg_match_all("/[\d]{2}-[\d]{2}-[\d]{4}/m", $sendMessage, $matches, PREG_SET_ORDER, 0)){
        return "Could not find prayer request to update 2";
    }

    $replaceDate	= $matches[0][0];

    // get the prayer request to be replaced
    $prayer	= SIM\PRAYER\prayerRequest(false, false, $replaceDate);

    if(!$prayer){
        return "Could not find prayer request to update 3";
    }

    // Split on the - 
    $exploded   = explode('-', $prayer['message']);
    if(count($exploded) < 2){
        return "Could not find prayer request to update 4";
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