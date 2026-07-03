<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

/**
 * Check returns the required signal instance: cmd, dbus or jsonrpc
 *
 * @param   bool        $getResult  Whether we should return the result, default true
 */
function getSignalInstance($getResult = true)
{
    global $signalTrue;
    global $signalFalse;

    if ($getResult && !empty($signalTrue)) {
        return $signalTrue;
    } elseif (!empty($signalFalse)) {
        return $signalFalse;
    }

    if (str_contains(php_uname(), 'Linux')) {
        include_once __DIR__ . '/../php/classes/SignalJsonRpc.php';
        $signal = new SignalJsonRpc(true, $getResult);
    } else {
        include_once __DIR__ . '/../php/classes/SignalCommandLine.php';
        $signal = new SignalCommandLine($getResult);
    }

    if ($getResult) {
        $signalTrue     = $signal;
    } else {
        $signalFalse    = $signal;
    }

    return $signal;
}

// Send an signal message before sending a mail. Do not continue sending the e-mail if not needed
add_filter('wp_mail', __NAMESPACE__ . '\sendEmailBySignal', 2);
function sendEmailBySignal($args)
{
    $signal = getSignalInstance(false);

    $signal->sendEmailBySignal($args);

    return $args;
}

/**
 * Show phonenumber
 */
add_filter('tsjippy-forms-transform-formtable-data', function($string, $element, $submission, $object){
    if (gettype($string) == 'string' && $string[0] == '+') {
        $numbers      = explode(" ", $string);
        $output       = '';
        $signalNumber = '';

        $userIdKey    = false;
        if (isset($submission->user_id)) {
            $userIdKey = 'user_id';
        } elseif (isset($submission->user_id)) {
            $userIdKey = 'user_id';
        }

        if ($userIdKey) {
            $signalNumber = get_user_meta($submission->$userIdKey, 'tsjippy_signal_number', true);
        }

        foreach ($numbers as $number) {
            if ($userIdKey && $number == $signalNumber) {
                $output    .= "<a href='https://signal.me/#p/$number'>$number</a><br>";
            } else {
                $output    .= "<a href='https://api.whatsapp.com/send?phone=$number&text=Regarding%20your%20submission%20of%20{$object->formData->name}%20with%20id%20$submission->id'>$number</a><br>";
            }
        }
    } 

    return $string;
}, 10, 4);