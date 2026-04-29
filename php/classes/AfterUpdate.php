<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

class AfterUpdate extends TSJIPPY\AfterPluginUpdate {

    public function afterPluginUpdate($oldVersion){
        global $wpdb;

        TSJIPPY\printArray('Running update actions');

        if(version_compare('10.0.0', $oldVersion)){
            /**
             * Rename tables to tsjippy_
             */
            $wpdb->query(
                "ALTER TABLE `{$wpdb->prefix}tsjippy_received_signal_messages`
                RENAME COLUMN `timesend` to `time_send`;"
            );

            $wpdb->query(
                "ALTER TABLE `{$wpdb->prefix}tsjippy_signal_message_queue`
                RENAME COLUMN `timeadded` to `time_added`;"
            );

            $wpdb->query(
                "ALTER TABLE `{$wpdb->prefix}tsjippy_signal_messages`
                RENAME COLUMN `timesend` to `time_send`;"
            );
        }
    }
}
