<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;


//Add Signal messages overview shortcode
add_shortcode('tsjippy_signal_messages', __NAMESPACE__ . '\signalMessages');
function signalMessages()
{
    $signalMessages = get_option('signal_bot_messages');

    $html             = '';

    //Perform remove action
    if (isset($_POST['recipient-number']) && isset($_POST['key'])) {
        if (wp_get_environment_type() === 'local') {
            $html .= '<div class="success">Succesfully removed all the messages</div>';
            delete_option('signal_bot_messages');
        } else {
            $html .= '<div class="success">Succesfully removed the message</div>';

            unset($signalMessages[TSJIPPY\sanitize($_POST['recipient-number'])][TSJIPPY\sanitize($_POST['key'], 'key')]);

            if (count($signalMessages[$_POST['recipient-number'] ?? []]) == 0) unset($signalMessages[$_POST['recipient-number']]);

            update_option('signal_bot_messages', $signalMessages);
        }
    }

    if (is_array($signalMessages) && !empty($signalMessages)) {
        foreach ($signalMessages as $recipient_number => $recipient) {
            $html .= "<strong>Messages to $recipient_number</strong><br>";
            foreach ($recipient as $key => $signal_message) {
                $html .= 'Message ' . ($key + 1) . ":<br>";
                $html .= $signal_message[0] . '<br>';
                $html .= '<form action="" method="post">
                    <input type="hidden" class="no-reset" id="recipient-number" name="recipient-number" value="' . $recipient_number . '">
                    <input type="hidden" class="no-reset" id="key" name="key" value="' . $key . '">
                    <button class="button remove signal-message tsjippy" type="submit" style="margin-top:10px;">Remove this message</button>
                </form>';
            }
        }
    } else {
        $html .= "No Signal messages found";
    }
    return $html;
}
