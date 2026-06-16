<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

add_action('init', __NAMESPACE__ . '\taskInit');
function taskInit()
{
    //add action for use in scheduled task
    add_action('tsjippy-check-signal', __NAMESPACE__ . '\checkSignal');

    // needed for async signal messages
    add_action('tsjippy-schedule-signal-message', __NAMESPACE__ . '\sendSignalMessage', 10, 8);

    add_action('tsjippy-check-signal-numbers', __NAMESPACE__ . '\checkSignalNumbers', 10, 3);

    add_action('tsjippy-clean-signal-log', __NAMESPACE__ . '\cleanSignalLog');

    add_action('tsjippy-signal-number-reminder', __NAMESPACE__ . '\signalNumberReminder');

    add_action('tsjippy-signal-process-queue', __NAMESPACE__ . '\processQueue');
}

function checkSignal()
{
    $signal         = TSJIPPY\SIGNAL\getSignalInstance();
    $signal->checkPrerequisites();
}

function scheduleTasks()
{
    TSJIPPY\scheduleTask('check_signal', 'daily');

    TSJIPPY\scheduleTask('clean_signal_log', 'daily');

    TSJIPPY\scheduleTask('check_signal_numbers', 'daily');

    TSJIPPY\scheduleTask('tsjippy_signal_process_queue', 'hourly');

    $freq   = SETTINGS['reminder-freq'] ?? false;
    if ($freq) {
        TSJIPPY\scheduleTask('signal_number_reminder', $freq);
    }
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
            $phoneNumbers   = get_user_meta($user->ID, 'tsjippy_phonenumbers', true);

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
