<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

add_action('tsjippy_signal_module_update', __NAMESPACE__.'\pluginUpdate');
function pluginUpdate($oldVersion){
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $signal 	= new Signal();

    if($oldVersion < '2.36.4'){
        maybe_add_column($signal->receivedTableName, 'attachments', "ALTER TABLE $signal->receivedTableName ADD COLUMN `attachments` longtext");
    }
}