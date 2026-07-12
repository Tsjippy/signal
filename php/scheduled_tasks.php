<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

add_action('init', __NAMESPACE__ . '\scheduleTasks');
/**
 * Schedule all tasks for this plugin
 */
function scheduleTasks()
{
    TSJIPPY\scheduleTask('tsjippy-signal-check-signal', 'daily', __NAMESPACE__, 'checkSignal');

    TSJIPPY\scheduleTask('tsjippy-signal-clean-signal-log', 'daily', __NAMESPACE__, 'cleanSignalLog');

    TSJIPPY\scheduleTask('tsjippy-signal-check-signal-numbers', 'daily', __NAMESPACE__, 'checkSignalNumbers');

    TSJIPPY\scheduleTask('tsjippy-signal-process-queue', 'hourly', __NAMESPACE__, 'processQueue');

    $freq   = SETTINGS['reminder-freq'] ?? false;
    if ($freq) {
        TSJIPPY\scheduleTask('tsjippy-signal-number-reminder', $freq, __NAMESPACE__, 'signalNumberReminder');
    }

    // needed for async signal messages
    add_action('tsjippy-signal-schedule-signal-message', __NAMESPACE__ . '\sendSignalMessage', 10, 8);
}

function checkSignal()
{
    $signal         = TSJIPPY\SIGNAL\getSignalInstance();
    $signal->checkPrerequisites();
}

/**
 * Check for updated signal numbers
 */
function checkSignalNumbers()
{
    // we can send a signal message directly from the server
    if (!SETTINGS['local'] ?? false) {
        return;
    }

    $signal    = getSignalInstance();

    foreach (TSJIPPY\getUserAccounts() as $user) {
        $phonenumber    = get_user_meta($user->ID, 'tsjippy_signal_number', true);

        // check if valid signal number
        if (empty($phonenumber) || !$signal->getUserStatus($phonenumber)) {
            // remove the stored signal number
            delete_user_meta($user->ID, 'tsjippy_signal_number');

            // loop over all phonenumbers to find the one connected with signal
            $phoneNumbers   = get_user_meta($user->ID, 'tsjippy_phonenumbers');

            if (!empty($phoneNumbers)) {
                foreach ($phoneNumbers as $phonenumber) {
                    // store if registered
                    if ($signal->getUserStatus($phonenumber)) {
                        update_user_meta($user->ID, 'tsjippy_signal_number', $phonenumber);

                        // go to the next user
                        continue 2;
                    }
                }
            }
        }
    }
}

/**
 * Remind people to add their signal message to the website
 */
function signalNumberReminder()
{
    $users = get_users([
        'meta_key'     => 'tsjippy_signal_number',
        'meta_compare' => 'NOT EXISTS',
    ]);

    foreach ($users as $user) {
        $email          = new SignalEmail($user);
        $email->filterMail();

        $subject        = $email->subject;
        $message        = $email->message;
        $recipients        = $user->user_email;

        wp_mail($recipients, $subject, $message);
    }
}

function cleanSignalLog()
{
    $period     = SETTINGS['clean-period'] ?? false;
    $amount     = SETTINGS['clean-amount'] ?? false;

    $maxDate    = gmdate('Y-m-d', strtotime("-$amount $period"));

    $signal     = new Signal();

    $signal->clearMessageLog($maxDate);
}

function processQueue()
{
    $signal    = getSignalInstance();

    $signal->processQueue();
}
