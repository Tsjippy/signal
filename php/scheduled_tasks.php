<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

add_action('init', __NAMESPACE__.'\taskInit');
function taskInit(){
	//add action for use in scheduled task
	add_action( 'check_signal_action', __NAMESPACE__.'\checkSignal');

    // needed for async signal messages
    add_action( 'schedule_signal_message_action', __NAMESPACE__.'\sendSignalMessage', 10, 8);

    add_action( 'check_signal_numbers_action', __NAMESPACE__.'\checkSignalNumbers', 10, 3);

    add_action( 'clean_signal_log_action', __NAMESPACE__.'\cleanSignalLog');

    add_action( 'signal_number_reminder_action', __NAMESPACE__.'\signalNumberReminder');
}

function checkSignal(){
    $signal 		= TSJIPPY\SIGNAL\getSignalInstance();
    $signal->checkPrerequisites();
}

function scheduleTasks(){
    TSJIPPY\scheduleTask('check_signal_action', 'daily');

    TSJIPPY\scheduleTask('clean_signal_log_action', 'daily');

    TSJIPPY\scheduleTask('check_signal_numbers_action', 'daily');

    TSJIPPY\scheduleTask('retry_failed_signal_messages_action', 'quarterly');

    $freq   = SETTINGS['reminder-freq'] ?? false;
    if($freq){
        TSJIPPY\scheduleTask('signal_number_reminder_action', $freq);
    }
}

/**
 * Check for updated signal numbers
 */
function checkSignalNumbers(){
    // we can send a signal message directly from the server
	if(!SETTINGS['local'] ?? false){
        return;
    }
    
    $signal	= getSignalInstance();

    foreach(TSJIPPY\getUserAccounts() as $user){
        $phonenumber    = get_user_meta( $user->ID, 'signal_number', true );

        // check if valid signal number
        if(empty($phonenumber) || !$signal->isRegistered($phonenumber)){
            // remove the stored signal number
            delete_user_meta( $user->ID, 'signal_number');

            // loop over all phonenumbers to find the one connected with signal
            foreach(get_user_meta( $user->ID, 'phonenumbers', true ) as $phonenumber){
                // store if registered
                if($signal->isRegistered($phonenumber)){
                    update_user_meta( $user->ID, 'signal_number', $phonenumber );

                    // go to the next user
                    continue 2;
                }
            }
        }
    }
}

/**
 * Remind people to add their signal message to the website
 */
function signalNumberReminder(){
    $users = get_users([
        'meta_key'     => 'signal_number',
        'meta_compare' => 'NOT EXISTS',
    ]);

    foreach($users as $user){
        $email          = new SignalEmail($user);
        $email->filterMail();
            
        $subject        = $email->subject;
        $message        = $email->message;
        $recipients	    = $user->user_email;

        wp_mail( $recipients, $subject, $message);
    }
}

function cleanSignalLog(){
    $period     = SETTINGS['clean-period'] ?? false;
    $amount     = SETTINGS['clean-amount'] ?? false;

    $maxDate    = date('Y-m-d', strtotime("-$amount $period"));

    $signal     = new Signal();

    $signal->clearMessageLog($maxDate);
}