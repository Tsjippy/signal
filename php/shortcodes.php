<?php
namespace SIM\SIGNAL;
use SIM;


//Add Signal messages overview shortcode
add_shortcode('signal_messages',function(){
	$signalMessages = get_option('signal_bot_messages');
	
	$html 			= '';
	
	//Perform remove action
	if(isset($_POST['recipient_number']) && isset($_POST['key'])){
		if($_SERVER['HTTP_HOST'] == 'localhost'){
			$html .= '<div class="success">Succesfully removed all the messages</div>';
			delete_option('signal_bot_messages');
		}else{
			$html .= '<div class="success">Succesfully removed the message</div>';

			unset($signalMessages[$_POST['recipient_number']][$_POST['key']]);
			
			if(count($signalMessages[$_POST['recipient_number']]) == 0) unset($signalMessages[$_POST['recipient_number']]);
			
			update_option('signal_bot_messages', $signalMessages);
		}
	}
	
	if(is_array($signalMessages) && !empty($signalMessages)){
		foreach($signalMessages as $recipient_number=>$recipient){
			$html .= "<strong>Messages to $recipient_number</strong><br>";
			foreach($recipient as $key=>$signal_message){
				$html .= 'Message '.($key+1).":<br>";
				$html .= $signal_message[0].'<br>';
				$html .= '<form action="" method="post">
					<input type="hidden" id="recipient_number" name="recipient_number" value="'.$recipient_number.'">
					<input type="hidden" id="key" name="key" value="'.$key.'">
					<button class="button remove signal_message sim" type="submit" style="margin-top:10px;">Remove this message</button>
				</form>';
			}
		}
	}else{
		$html .= "No Signal messages found";
	}
	return $html;
});