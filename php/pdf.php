<?php
namespace TSJIPPY\SIGNAL;
use TSJIPPY;

add_action('tsjippy_after_pdf_text', __NAMESPACE__.'\afterPdf', 10, 5);
function afterPdf($cellText, $pdf, $x, $y, $cellWidth){
    if(is_array($cellText)){
        foreach($cellText as $index=>$phoneNr){
            if($phoneNr[0] == '+'){
                $users = get_users(array(
                    'meta_key'     => 'signal_number',
                    'meta_value'   => $phoneNr ,
                ));
            
                if(!empty($users)){
                    $signalNr   	  = get_user_meta($users[0]->ID, 'signal_number', true);
                    $pdf->addCellPicture(PLUGINPATH.'pictures/signal.png', $x + $cellWidth - 4, $y + ($index * 6), "https://signal.me/#p/$signalNr", 4);
                }
            }
        }
    }

}

