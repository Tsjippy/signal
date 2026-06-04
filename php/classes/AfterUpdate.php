<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

if ( ! defined('ABSPATH')) exit;

class AfterUpdate extends TSJIPPY\AfterPluginUpdate {

    public function afterPluginUpdate($oldVersion) {
        global $wpdb;

        if (version_compare('10.0.5', $oldVersion) === 1) {
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

        if (version_compare('10.3.9', $oldVersion) === 1) {
            $signal = getSignalInstance();

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            maybe_add_column($signal->queueTableName, 'retries', "ALTER TABLE $signal->queueTableName ADD COLUMN `retries` int NOT NULL DEFAULT 0");
            maybe_add_column($signal->queueTableName, 'waiting', "ALTER TABLE $signal->queueTableName ADD COLUMN `waiting` boolean NOT NULL DEFAULT false");
        }
    }
}
