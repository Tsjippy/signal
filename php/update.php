<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim_plugin_update', function($oldVersion){
    global $wpdb;
    require_once ABSPATH . 'wp-admin/install-helper.php';

    $signal 	= new Signal();

    if($oldVersion < '2.35.7'){
            

        maybe_add_column($signal->receivedTableName, 'chat', "ALTER TABLE $signal->receivedTableName ADD COLUMN `chat` longtext");

        if(maybe_drop_column( $signal->receivedTableName, 'groupid', "ALTER TABLE $signal->receivedTableName  DROP `groupid`;" )){
            SIM\printArray("Dropped column groupid");
        }else{
            SIM\printArray("Failed to drop column groupid");
        }
    }

    if($oldVersion < '2.36.2'){

        $wpdb->query("ALTER TABLE `$signal->tableName` CHANGE `timesend` `timesend` INT(20) NOT NULL;");
        $wpdb->query("ALTER TABLE `$signal->receivedTableName` CHANGE `timesend` `timesend` INT(20) NOT NULL;");
    }

    if($oldVersion < '2.36.4'){
        maybe_add_column($signal->receivedTableName, 'attachments', "ALTER TABLE $signal->receivedTableName ADD COLUMN `attachments` longtext");
    }
});