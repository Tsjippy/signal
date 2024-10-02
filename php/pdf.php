<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim_after_pdf_text', function($cellText, $pdf, $x, $y, $cellWidth){
    if(is_array($cellText)){
        foreach($cellText as $index=>$phoneNr){
            if($phoneNr[0] == '+'){
                $users = get_users(array(
                    'meta_key'     => 'signal_number',
                    'meta_value'   => $phoneNr ,
                ));
            
                if(!empty($users)){
                    $signalNr   	  = get_user_meta($users[0]->ID, 'signal_number', true);
                    $pdf->addCellPicture(MODULE_PATH.'pictures/signal.png', $x + $cellWidth - 4, $y + ($index * 6), "https://signal.me/#p/$signalNr", 4);
                }
            }
        }
    }

}, 10, 5);

