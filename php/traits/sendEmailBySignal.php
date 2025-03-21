<?php

namespace SIM\SIGNAL;

trait SendEmailBySignal{
    function sendEmailBySignal($args){
        $numbers    = [];
        if(!empty($args['formresults'])){
            $forms      = new \SIM\FORMS\SimForms();

            $numbers    = (array) $args['formresults'][$forms->findPhoneNumberElementName()];
        }
        
        if(empty($numbers)){
            // try to find phone based on e-mail
            $emails     = $args['to'];
            if(!is_array($args['to'])){
                $emails = explode(',', $args['to']);
            }

            foreach($emails as $email){
                $user       = get_user_by('email', $email);

                if(!$user){
                    continue;
                }

                $nrs    = get_user_meta($user->ID, 'phonenumbers', true);

                if(empty($nrs)){
                    continue;
                }

                $numbers    = array_merge($numbers, $nrs);
            }
        }

        $message        = $args['message']; 

        // Find any hyperlinks in the text
        preg_match_all('/<a\s+href=(?:"|\')(.*?)(?:"|\')>(.*?)<\/a>/i', $message, $matches);

        //replace the hyperlinks with plain links
        foreach($matches[0] as $index=>$match){
            $message    = str_replace($match, $matches[2][$index].': '.str_replace('https://', '', $matches[1][$index]), $message);
        }

        $message        = html_entity_decode(strip_tags(str_replace(['<br>', '</br>', '<br />', '</p>'], "\n", $message)));

        //Send Signal message
        foreach($numbers as $number){
            $this->send($number, $message);
        }
    }
}
