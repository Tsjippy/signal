<?php
namespace TSJIPPY\SIGNAL;

use function TSJIPPY\addRawHtml;
use function TSJIPPY\addElement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu extends \TSJIPPY\ADMIN\SubAdminMenu{

    public function __construct($settings, $name){
        parent::__construct($settings, $name);
    }

    public function settings($parent){
        $local	= false;
        if(isset($this->settings['local']) && $this->settings['local']){
            $local	= true;
        }

        ob_start();

        ?>
        <strong>Server type</strong><br>
        Indicate if you can install signal-cli on this server or not<br>
        I have root access on this server
        <label class="switch">
            <input type="checkbox" name="local" value=1 <?php if($local){echo 'checked';}?>>
            <span class="slider round"></span>
        </label>
        <br>
        <br>
        <?php

        addRawHtml(ob_get_clean(), $parent);

        ob_start();
        
        if($local){
            $signal = getSignalInstance();

            if(str_contains(php_uname(), 'Linux')){
                $signal->createDbTable();
            }

            if(!$signal->checkPrerequisites()){
                echo "<div class='error'>";
                    echo "Signal-cli is not working properly, please check the error log for more details.<br>$signal->error";
                echo "</div>";
            }elseif($signal->phoneNumber && $signal->isRegistered($signal->phoneNumber)){
                $this->connectedOptions($signal, $parent);
            }else{
                $this->notConnectedOptions();
            }
        }else{
            $this->notLocalOptions();
        }

        addRawHtml(ob_get_clean(), $parent);

        return true;
    }

    public function emails($parent){
        if(!SETTINGS['local'] ?? false){
            return false;
        }

        ob_start();

        ?>
        <label>
            Define the e-mail people get when they should submit a Signal phonenumber
        </label>
        <br>

        <?php
        $email    = new SignalEmail(wp_get_current_user());
        $email->printPlaceholders();
        ?>

        <h4>E-mail to remind people to add their Signal phonenumber</h4>
        <?php

        $email->printInputs();

        addRawHtml(ob_get_clean(), $parent);

        return true;
    }

    public function data($parent=''){
        $local	= false;
        if(isset($this->settings['local']) && $this->settings['local']){
            $local	= true;
        }

        if(!$local){
            return '';
        }

        $amount	= 100;
        if(isset($_REQUEST['amount'])){
            $amount	= $_REQUEST['amount'];
        }

        $startDate	= date('Y-m-d', strtotime('-3 month'));
        if(isset($_REQUEST['start-date'])){
            $startDate	= $_REQUEST['start-date'];
        }

        $endDate	= date('Y-m-d', strtotime('+1 day'));
        if(isset($_REQUEST['end-date'])){
            $endDate	= $_REQUEST['end-date'];
        }

        $this->messagesHeader($startDate, $endDate, $amount);
        
        $this->processActions();

        $tablinkWrapper = addElement('div', $parent, ['class' => 'tablink-wrapper']);

        $buttons    = [
            'sent'            => 'Sent Messages',
            'received'        => 'Received Messages'
        ];

        $tab      = 'sent';
        if(isset($_GET['second-tab'])){
            $tab  = sanitize_key($_GET['second-tab']);
        }

        foreach($buttons as $id => $text){
            $attributes = [
                'class'       => 'tablink' . ($tab == $id ? ' active' : ''),
                'id'          => "show-$id",
                'data-target' => $id,
                'type'        => 'button'
            ];
            addElement('button', $tablinkWrapper, $attributes, $text);
        }

        $sentTable  = $this->sentMessagesTable($startDate, $endDate, $amount);

        $hidden	= 'hidden';
        if(empty($sentTable)){
            $hidden	= '';
        }

        $receivedTable	= $this->receivedMessagesTable($startDate, $endDate, $amount, $hidden);

        return true;
    }

    public function functions($parent){
        wp_enqueue_script('smiley');
	
        ob_start();

        // check if we need to send a message
        if(!empty($_REQUEST['challenge']) && !empty($_REQUEST['captchastring'])){
            $signal	= getSignalInstance();

            $result	= $signal->submitRateLimitChallenge($_REQUEST['challenge'], $_REQUEST['captchastring']);

            echo "<div class='success'>Rate challenge succesfully submitted <br>$result</div>";
        }

        // check if we need to send a message
        if(!empty($_REQUEST['message']) && !empty($_REQUEST['recipient'])){
            $message	= stripslashes($_REQUEST['message']);

            // reply to previous message
            if(!empty($_REQUEST['timesent']) && !empty($_REQUEST['replymessage']) && !empty($_REQUEST['author'])){
                $result	= sendSignalMessage($message, stripslashes($_REQUEST['recipient']), '', intval($_REQUEST['timesent']), $_REQUEST['author'], $_REQUEST['replymessage']);
            }else{
                $result	= sendSignalMessage($message, stripslashes($_REQUEST['recipient']));
            }

            if(is_wp_error($result)){
                echo "<div class='error'>Message could not be send<br>".$result->get_error_message()."</div>";
            }else{
                echo "<div class='success'>Message succesfully send: $result</div>";
            }
        }

        $phonenumbers	= '';
        foreach (get_users() as $user) {
            $phones	= (array)get_user_meta($user->ID, 'phonenumbers', true);
            foreach($phones as $phone){
                $phonenumbers	.= "<option value='$phone'>$user->display_name ($phone)</option>";
            }
        }

        if(isset($_REQUEST['challenge']) && !isset($_REQUEST['captchastring'])){
            ?>
            <form method='get'>
                <input type="hidden" class="no-reset" name="page" value="tsjippy_signal">
                <input type="hidden" class="no-reset" name="tab" value="functions">

                <label>
                    <h4>Challenge string</h4>
                    <input type='text' name='challenge' value='<?php echo $_REQUEST['challenge'];?>' style='width:100%;' required>
                </label>

                <h4>Captcha string</h4>
                <textarea name='captchastring' style='width:100%;' required rows=10></textarea>

                <br>

                <button>Submit</button>
            </form>

            <?php
            return ob_get_clean();
        }

        $author			= '';
        $prevMessage	= '';
        $timeStamp		= '';
        $chat			= '';

        if(!empty($_GET['timesent'])){
            $timeStamp	= $_GET['timesent'];
        }

        if(!empty($_GET['replymessage'])){
            $prevMessage	= $_GET['replymessage'];
        }

        if(!empty($_GET['author'])){
            $author	= $_GET['author'];
        }

        if(!empty($_GET['recipient'])){
            $chat	= $_GET['recipient'];
        }

        ?>
        <form method='post'>
            <input type='hidden' class='no-reset' name='timestamp' 	value='<?php echo $timeStamp;?>'>
            <input type='hidden' class='no-reset' name='author' 		value='<?php echo $author;?>'>
            <input type='hidden' class='no-reset' name='prevmessage' value='<?php echo $prevMessage;?>'>

            <label>
                <h4>Message to be send</h4>
                You can do basic formatting as listed below:<br>
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>BOLD</td>
                            <td>&lt;b&gt;Some <b>bold</b> text&lt;/b&gt;</td>
                        </tr>
                        <tr>
                            <td>ITALIC</td>
                            <td>&lt;i&gt;Some <i>italic</i> text&lt;/i&gt;</td>
                        </tr>
                        <tr>
                            <td>SPOILER</td>
                            <td>&lt;spoiler&gt;Some spoiler text&lt;/spoiler&gt;</td>
                        </tr>
                        <tr>
                            <td>STRIKETHROUGH</td>
                            <td>&lt;ss&gt;Some <s>striketrhough</s> text&lt;/ss&gt;</td>
                        </tr>
                        <tr>
                            <td>MONOSPACE</td>
                            <td>&lt;tt&gt;Some <tt>monospace</tt> text&lt;/tt&gt;</td>
                        </tr>
                    </tbody>
                </table>
                <textarea name='message' style='width: calc(100% - 50px);' required></textarea>
                <button type='button' class='trigger' data-target='[name="message"]'>emoji</button>
            </label>
            <label>
                <h4>Recipient</h4>
                <input type='text' name='recipient' list='groups' style='width: calc(100% - 50px);' placeholder="Type a name or groupname to select" required value='<?php echo $chat;?>'>

                <datalist id='groups'>
                    <?php
                    echo $phonenumbers;
                    if(isset($this->settings['local']) && $this->settings['local']){
                        $signal	= getSignalInstance();

                        $groups	= $signal->listGroups();

                        foreach((array)$groups as $group){
                            if(empty($group->name)){
                                continue;
                            }
                            echo "<option value='$group->id'>$group->name</option>";
                        }
                    }else{
                        if(empty($this->settings['groups'])){
                            $groups	= [''];
                        }else{
                            $groups	= $this->settings['groups'];
                        }
                        foreach((array)$groups as $group){
                            echo "<option value='$group'>$group</option>";
                        }
                    }
                    ?>
                </datalist>
            </label>
            <button>Send message</button>
        </form>
        <br>
        <br>

        <?php

        addRawHtml(ob_get_clean(), $parent);

        return true;
    }

    /**
     * Function to do extra actions from $_POST data. Overwrite if needed
     */
    public function postActions(){
        $local	= SETTINGS['local'] ?? false;

        if(!$local){
            return "<div class='error'>You need to have root access to change these settings</div>";
        }

        $signal	= getSignalInstance();

        // Change account details
        if(isset($_POST['display-name']) || isset($_POST['avatar'])){

            $message	= '';

            if(isset($_POST['display-name'])){
                $displayName	= sanitize_text_field($_POST['display-name']);

                if($displayName != $this->settings['display-name']){
                    $result	= $signal->updateProfile($displayName);

                    if(is_wp_error($result)){
                        // @disregard 
                        $message	.= "<div class='error'>".$result->get_error_message()."</div>";
                    }else{
                        $message	.= "<div class='success'>Display name changed succesfully to $displayName</div>";
                    }
                }
            }


            if(isset($_POST['picture-ids']['avatar'])){
                $avatarAttachmentId	= sanitize_text_field($_POST['picture-ids']['avatar']);

                if($avatarAttachmentId != $this->settings['picture-ids']['avatar']){
                    if(empty($avatarAttachmentId)){
                        $result	= $signal->updateProfile('', null, true);
                    }else{
                        $path	= get_attached_file($avatarAttachmentId);

                        if(empty($path) || !file_exists($path)){
                            return $message."<div class='error'>Something went wrong with the avatar, please try again</div>";
                        }
                        $result	= $signal->updateProfile('', $path);
                    }

                    if(is_wp_error($result)){
                        $message	.= "<div class='error'>".$result->get_error_message()."</div>";
                    }else{
                        $message	.= "<div class='success'>Avatar changed succesfully</div>";
                    }
                }
            }

            return $message;
        }

        /**
         * Show the registration form if needed
         */
        if( isset($_GET['register']) ){
            return $this->registerForm();
        }elseif(isset($_GET['unregister'])){
            $signal->unregister();
        }elseif(!empty($_POST['captcha'])){
            $result= $signal->register($_POST['phone'], $_POST['captcha'], isset($_POST['voice']));

            if(is_wp_error($result)){
                return "<div class='error'>".$result->get_error_message()."</div>";
            }elseif(empty($signal->error)){
                ob_start();
                // show the verification form after the registration form if there is no error
                ?>
                <form method='post'>
                    You should have received a verification code.<br>
                    Please insert the code below.
                    <br>
                    <label>
                        Verification code
                        <input type='number' name='verification-code' required>
                    </label>

                    <br>
                    <br>
                    <button>Verify</button>
                </form>
                <?php

                return ob_get_clean();
            }
        }elseif(!empty($_POST['verification-code'])){
            $result	= $signal->verify($_POST['verification-code']);

            if(is_wp_error($result)){
                return "<div class='error'>".$result->get_error_message()."</div>".$this->registerForm();
            }elseif(!empty($signal->error)){
                return "<div class='error'>$signal->error</div>".$this->registerForm();
            }else{
                unset($_POST['verification-code']);

                return "<div class='success'>Succesfully registered with Signal!</div>";
            }
        }elseif(isset($_GET['link'])){
            return $signal->link();
        }
    }

    /**
     * Schedules the tasks for this plugin
     *
    */
    public function postSettingsSave(){
        scheduleTasks();
    }

    public function registerForm(){
        ob_start();
        ?>
        <form method='post' action='<?php echo admin_url( "admin.php?page={$_GET['page']}&tab={$_GET['tab']}" );?>'>
            <h4>Register with Signal</h4>
            <br>
            <label>
                Phone number you want to register
                <input type="tel" name="phone" pattern="\+[0-9]{9,}" title="Phonenumber starting with a +. Only numbers. Example: +2349041234567" style='width:100%'>
            </label>
            <br>
            <label>
                Captcha:
                <br>
                <input type='text' name='captcha' style='width:100%' required>
            </label>
            Get a captcha from <a href='https://signalcaptchas.org/registration/generate.html' target=_blank>here</a>, for an explanation see <a href='https://github.com/AsamK/signal-cli/wiki/Registration-with-captcha' target=_blank>the manual.</a>
            <br>
            <br>
            <label>
                <input type='checkbox' name='voice' value=1>
                Register with a voice call in stead of sms
            </label>
            <br>
            <br>
            <button>Register</button>
        </form>
        <?php

        return ob_get_clean();
    }

     /**
     * Shows the options when connected to Signal
     *
     * @param	object	        $signal		The signal object
     * @param   DOMElement|null $parent     Parent node element
     */
    public function connectedOptions($signal, $parent){
        $url		= admin_url( "admin.php?page={$_GET['page']}&tab={$_GET['tab']}" );

        if(isset($_GET['force'])){
            $signalGroups	= $signal->listGroups(false, false, true);
        }else{
            $signalGroups	= $signal->listGroups();
        }

        if(!empty($signal->error)){
            if(str_contains($signal->error, 'Specified account does not exist')){
                ?>
                <div class='warning'>
                    <?php echo $signal->phoneNumber;?> is connected to on this machine but not registered on the Signal Servers, please register the number again<br>
                </div>

                <?php

                return $this->notConnectedOptions();
            }
            
            echo $signal->error;
        }

        ob_start();
        ?>
        <h4>Connection details</h4>
        <p>
            Currently connected to <?php echo $signal->phoneNumber; ?>
            <a href='<?php echo $url;?>&unregister=true' class='button'>Unregister</a><br>
        </p>

        <label>
            Signal Messenger Display name<br>
            <input type='text' name='display-name' value='<?php echo $this->settings['display-name'];?>' style='width:310px'>
        </label>
        <br>
        <br>
        <label>
            Signal Messenger Avatar (328pxx328px)<br>
        </label>
        <?php 
        addRawHtml(ob_get_clean(), $parent);
        $this->pictureSelector('avatar', 'avatar', $parent);

        addElement('br', $parent);
        addElement('br', $parent);	

        $this->recurrenceSelector('reminder-freq', $this->settings['reminder-freq'], 'How often should people be reminded to add a signal phonenumber  to the website', $parent);

        if(!empty($signalGroups)){
            $wrapper = addElement('div', $parent, ['class' => 'signal-group-wrapper']);
            addElement('h4', $wrapper, [], 'Select Signal group(s) to send new content messages to by default');

            foreach($signalGroups as $group){
                if(empty($group->name)) continue;

                $label  = addElement('label', $wrapper);
                $attr   = [
                    'type' => 'checkbox', 
                    'name' => 'groups[]', 
                    'value' => $group->id
                ];

                if(in_array($group->id, $this->settings['groups'] ?? [])) $attr['checked'] = 'checked';

                addElement('input', $label, $attr);
                addRawHtml(' ' . $group->name, $label);
                addElement('br', $wrapper);
            }

            $invWrapper     = addElement('div', $parent);
            addElement('h4', $invWrapper, [], 'Select optional Signal group(s) to invite new users to:');

            foreach($signalGroups as $group){
                if(empty($group->name)) continue;

                $label  = addElement('label', $invWrapper, [], $group->name);
                $attr   = [
                    'type' => 'checkbox', 
                    'name' => 'invgroups[]', 
                    'value' => $group->id
                ];

                if(in_array($group->id, $this->settings['invgroups'] ?? [])) $attr['checked'] = 'checked';

                addElement('input', $label, $attr);
                addElement('br', $invWrapper);
            }
        }
    }

    /**
     * Shows the options when not connected to Signal
     */
    public function notConnectedOptions(){
        $url		= admin_url( "admin.php?page={$_GET['page']}" );
        if(!empty($_GET['tab'])){
            $url	.= "&tab={$_GET['tab']}";
        }

        ?>
        <h4>Connection details</h4>
        <p>
            Currently not connected to Signal
            <br>
            <a href='<?php echo $url;?>&register=true' class='button'>Register a new number with Signal</a>
            
            <a href='<?php echo $url;?>&link=true' class='button'>Link to an existing Signal number</a>
        </p>
        <?php
    }

    /**
     * Shows the options when Java JDK is not installed
     */
    public function notLocalOptions(){
        if(empty($this->settings['groups'])){
            $groups	= [''];
        }else{
            $groups	= $this->settings['groups'];
        }

        ?>
        <label>
            Link to join the Signal group
            <input type='url' name='group-link' value='<?php echo $this->settings["group-link"]; ?>' style='width:100%'>
        </label>

        <div class="">
            <h4>Give optional Signal group name(s) to send new content messages to:</h4>
            <div class="clone-divs-wrapper">
                <?php
                foreach($groups as $index=>$group){
                    ?>
                    <div class="clone-div" data-div-id="<?php echo $index;?>">
                        <label>
                            <h4 style='margin: 0px;'>Signal groupname <?php echo $index+1;?></h4>
                            <input type='text' name="groups[<?php echo $index;?>]" value='<?php echo $group;?>'>
                        </label>
                        <span class='button-wrapper' style='margin:auto;'>
                            <button type="button" class="add button" style="flex: 1;">+</button>
                            <?php
                            if(count($groups)> 1){
                                ?>
                                <button type="button" class="remove button" style="flex: 1;">-</button>
                                <?php
                            }
                            ?>
                        </span>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function processActions(){
        /**
         * Download a backup of the configuration
         */
        if(isset($_REQUEST['backup'])){
            $signal	= getSignalInstance();

            if(!empty($signal->configPath)){
                $zip = new \ZipArchive();

                $zipFileName	= 'Signal-cli-Backup.zip';

                $zipFilePath 	= get_temp_dir() . $zipFileName; // Use a temporary directory

                if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== TRUE) {
                    exit("Cannot open <$zipFilePath>");
                }

                foreach (scandir("$signal->configPath/data") as $file) {
                    if (file_exists($file)) {
                        $zip->addFile($file, basename($file)); // Add with its original filename
                    }
                }

                $zip->close();

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                header('Content-Length: ' . filesize($zipFilePath));
                readfile($zipFilePath); // Output the file content

                unlink($zipFilePath);
                exit;
            }
        }

        if(!isset($_REQUEST['action'])){
            return;
        }

        if($_REQUEST['action'] == 'Delete'){
            $signal	= getSignalInstance();

            if(isset($_REQUEST['time_send'])){
                $result		= $signal->deleteMessage($_REQUEST['time_send'], $_REQUEST['recipients']);

                if(	
                    $result !== true ||
                    (
                        is_string($result) && !is_numeric(str_replace('int64 ', '', $result))
                    )
                ){
                    echo "<div class='error'>$result</div>";
                }else{
                    ?><div class='success'>
                        Succesfully removed the message
                    </div>
                    <?php
                }
            }else{
                $result		= $signal->clearMessageLog($_REQUEST['delete-date']);

                if($result === false){
                    ?>
                    <div class='error'>
                        Something went wrong
                    </div>
                    <?php
                }else{
                    ?>
                    <div class='success'>
                        Succesfully removed $result messages
                    </div>
                    <?php
                }
            }
        }elseif($_REQUEST['action'] == 'Save'){
            $this->settings['clean-period']	= $_REQUEST['clean-period'];
            $this->settings['clean-amount']	= $_REQUEST['clean-amount'];

            global $Modules;

            $Modules[PLUGINSLUG]	= $this->settings;

            update_option('tsjippy_modules', $Modules);
        }elseif($_REQUEST['action'] == 'Reply'){
            $signal	= getSignalInstance();

            $groupId	= '';
            if($_REQUEST['sender'] != $_REQUEST['chat'] ){
                $groupId	= $_REQUEST['chat'];
            }

            $result	= $signal->sendMessageReaction($_REQUEST['sender'] , $_REQUEST['timesent'], $groupId, $_REQUEST['emoji']  );

            if(is_numeric(str_replace('int64 ', '', $result))){
                ?>
                <div class='success'>Reaction sent succesfully</div>
                <?php
            }else{
                ?><div class='error'>Reaction sent not succesfull</div><?php
            }
        }
    }

    public function messagesHeader($startDate, $endDate, $amount){
        if(!isset($this->settings['clean-period'])){
            $this->settings['clean-period']	= '';
        }
        if(!isset($this->settings['clean-amount'])){
            $this->settings['clean-amount']	= '';
        }

        ?>
        <div class='flex-container'>
            <div class='flex'>
                <h2>Show Message History</h2>

                <form method='get' id='message-overview-settings'>
                    <input type="hidden" class="no-reset" name="page" value="tsjippy_signal" />
                    <input type="hidden" class="no-reset" name="tab" value="data" />

                    <label>
                        Show Messages send between <input type='date' name='start-date' value='<?php echo $startDate;?>' max='<?php echo date('Y-m-d'); ?>'> and <input type='date' name='end-date' value='<?php echo $endDate;?>' max='<?php echo date('Y-m-d', strtotime('+1 day')); ?>'>
                    </label>
                    <br>
                    <label>
                        Amount of messages to show <input type='number' name='amount' value='<?php echo $amount; ?>' style='max-width: 60px;'>
                    </label>
                    <br>
                    <input type='submit' value='Apply'>
                </form>
            </div>

            <div class='flex'>
                <h2>Clear Message History</h2>

                <form method='post'>
                    <input type="hidden" class="no-reset" name="page" value="tsjippy_signal" />
                    <input type="hidden" class="no-reset" name="tab" value="data" />

                    <label>
                        Delete Messages send before <input type='date' name='delete-date' value='<?php echo date('Y-m-d', strtotime('-1 month'));?>' max='<?php echo date('Y-m-d'); ?>'>
                    </label>
                    <br>
                    <input type='submit' name='action' value='Delete'>
                </form>
            </div>

            <div class='flex'>
                <h2>Auto clean Message History</h2>

                <form method='get' id='message-overview-settings'>
                    <input type="hidden" class="no-reset" name="page" value="tsjippy_signal" />
                    <input type="hidden" class="no-reset" name="tab" value="data" />

                    <label>
                        Automatically remove messages older then <input type='number' name='clean-amount' value='<?php echo $this->settings['clean-amount'];?>' style='width:60px;'>
                        <select name='clean-period' class='inline'>
                            <option value='days' <?php if($this->settings['clean-period'] == 'days'){echo 'selected="selected"';}?>>days</option>
                            <option value='weeks' <?php if($this->settings['clean-period'] == 'weeks'){echo 'selected="selected"';}?>>weeks</option>
                            <option value='months' <?php if($this->settings['clean-period'] == 'months'){echo 'selected="selected"';}?>>months</option>
                        </select>
                    </label>
                    <br>
                    <input type='submit' name='action' value='Save'>
                </form>
            </div>
        </div>
        <?php
    }

    public function sentMessagesTable($startDate, $endDate, $amount){
        global $wpdb;

        $page	= 1;
        if(isset($_REQUEST['nr'])){
            $page	= $_REQUEST['nr'];
        }

        $signal	= getSignalInstance();
        $messages	= $signal->getSentMessageLog($amount, $page, strtotime($startDate), strtotime($endDate));

        if(empty($messages)){
            return false;
        }

        ?>
        <style>
            .flex-container{
                display: flex;
            }

            .flex{
                padding: 20px;
            }

            .signal-table td.message{
                max-width: 500px;
                word-break: break-word;
                white-space: break-spaces;
            }
        </style>
        <div class='send-signal-messages tabcontent <?php if(!empty($_GET['second-tab']) && $_GET['second-tab']=='received'){echo ' hidden';}?>' id='sent'>
            <?php

            if($signal->totalMessages > $amount){
                $url		= admin_url("admin.php?page=tsjippy_signal&tab=data&amount=$amount&start-date=$startDate&end-date=$endDate&nr=");
                $totalPages	= ceil($signal->totalMessages/$amount);
                
                if($page != 1){
                    $prev	= $page-1;
                    echo "<a href='$url$prev'>< Previous</a>   ";
                }

                for ($x = 1; $x <= $totalPages; $x++) {
                    if($page == $x){
                        echo "<strong>";
                    }
                    echo "   <a href='$url$x'>$x</a>   ";
                    if($page == $x){
                        echo "</strong>";
                    }
                }

                if($page != $totalPages){
                    $next	= $page + 1;
                    echo "   <a href='$url$next'>Next ></a>";
                }
            }

            ?>

            <table class='signal-table tsjippy table'>
                <thead>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Recipient</th>
                    <th>Message</th>
                    <th>Actions</th>
                </thead>
                <tbody>
                    <?php
                        foreach($messages as $message){
                            $isoDate	= date( 'Y-m-d H:i:s', intval($message->time_send/1000) );
                            $date		= get_date_from_gmt( $isoDate, DATEFORMAT);
                            $time		= get_date_from_gmt( $isoDate, TIMEFORMAT);

                            $recipient	= '';
                            if($message->recipient[0] === '+'){
                                $recipient	= $wpdb->get_var("SELECT display_name FROM $wpdb->users WHERE ID in (SELECT user_id FROM `{$wpdb->prefix}usermeta` WHERE `meta_value` LIKE '%$message->recipient%')");
                            }else{
                                $signal->listGroups();
                                if(gettype($signal->groups) == 'array'){
                                    foreach($signal->groups as $group){
                                        if($group->id == $message->recipient){
                                            $recipient	= $group->name;
                                            break;
                                        }
                                    }
                                }
                            }

                            ?>
                            <tr>
                                <td class='date'><?php echo $date;?></td>
                                <td class='time'><?php echo $time?></td>
                                <td class='recipient'><?php echo $recipient;?></td>
                                <td class='message'><?php echo $message->message;?></td>
                                <td class='delete'>
                                    <?php
                                    if($message->status == 'deleted'){
                                        echo "Already Deleted";
                                    }else{
                                        ?>
                                        <form method='post'>
                                            <input type="hidden" class="no-reset" name="time_send" value="<?php echo $message->time_send;?>" />
                                            <input type="hidden" class="no-reset" name="id" value="<?php echo $message->id;?>" />
                                            <input type="hidden" class="no-reset" name="recipients" value="<?php echo $message->recipient;?>" />
                                            <input type='submit' name='action' value='Delete'>
                                        </form>
                                        <?php
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                    ?>
                </tbody>
            </table>
        </div>

        <?php
        return true;
    }

    public function receivedMessagesTable($startDate, $endDate, $amount, $hidden='hidden'){
        global $wpdb;

        if(!empty($_GET['second-tab']) && $_GET['second-tab'] == 'received'){
            $hidden	= '';
        }

        $page	= 1;
        if(isset($_REQUEST['nr'])){
            $page	= $_REQUEST['nr'];
        }

        $signal	= getSignalInstance();
        $messages	= $signal->getReceivedMessageLog($amount, $page, strtotime($startDate), strtotime($endDate));

        if(empty($messages)){
            return false;
        }

        $groupedMessages	= [];

        // group the messages by chat
        foreach($messages as $message){
            if(!isset($groupedMessages[$message->chat])){
                $groupedMessages[$message->chat]	= [];
            }

            $groupedMessages[$message->chat][]	= [
                'id'			=> $message->id,
                'timesent'		=> $message->time_send,	// timestamp is in milis
                'message'		=> $message->message,
                'status'		=> $message->status,
                'sender'		=> $message->sender,
                'attachments'	=> $message->attachments
            ];
        }

        ?>
        <style>
            .flex-container{
                display: flex;
            }

            .flex{
                padding: 20px;
            }
        </style>
        <script>
            document.addEventListener("click", function(ev) {
                let target	= ev.target;
                
                if(target.matches('.expand')){

                    let rowspan = target.closest('td').dataset.rowspan;

                    target.closest('td').rowSpan	= rowspan;

                    let row	= target.closest('tr').nextElementSibling;

                    while(row.matches('.hidden')) {
                        row.classList.remove('hidden');
                        row	= row.nextElementSibling;

                        if(row == null){
                            break;
                        }
                    }

                    target.textContent	= '-';
                    target.classList.replace('expand', 'condense');
                }else if(target.matches('.condense')){

                    let rowspan = target.closest('td').rowSpan	= 1;

                    let row	= target.closest('tr').nextElementSibling;

                    while(row.querySelector('td.chat') == null) {
                        console.log(row);
                        row.classList.add('hidden');
                        row	= row.nextElementSibling;

                        if(row == null){
                            break;
                        }
                    }

                    target.textContent	= '+';
                    target.classList.replace('condense','expand');
                }else{
                    return;
                }

                ev.stopImmediatePropagation();
            });

            document.addEventListener("emoji_selected", function(ev) {
                ev.target.closest('form').submit();
            });
        </script>
        <div class='send-signal-messages tabcontent <?php echo $hidden;?>' id='received'>
            <?php

            if($signal->totalMessages > $amount){
                $url		= admin_url("admin.php?page=tsjippy_signal&tab=data&amount=$amount&start-date=$startDate&end-date=$endDate&nr=");
                $totalPages	= ceil($signal->totalMessages/$amount);
                
                if($page != 1){
                    $prev	= $page-1;
                    echo "<a href='$url$prev'>< Previous</a>   ";
                }

                for ($x = 1; $x <= $totalPages; $x++) {
                    if($page == $x){
                        echo "<strong>";
                    }
                    echo "   <a href='$url$x'>$x</a>   ";
                    if($page == $x){
                        echo "</strong>";
                    }
                }

                if($page != $totalPages){
                    $next	= $page + 1;
                    echo "   <a href='$url$next'>Next ></a>";
                }
            }

            ?>

            <table class='signal-table tsjippy table'>
                <thead>
                    <th>Chat</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Sender</th>
                    <th>Message</th>
                    <th>Attachments</th>
                    <th>Actions</th>
                </thead>
                <tbody>
                    <?php
                    foreach($groupedMessages as $chat=>$group){
                        if(empty($group)){
                            continue;
                        }

                        if(!str_contains($chat, '+')){
                            $chatName	= $signal->findGroupName($chat );
                            if(empty($chatName)){
                                $chatName	= 'Unknow group';
                            }
                        }else{
                            $chatName	= $chat;
                        }
                        
                        $hidden	= '';

                        foreach($group as $index=>$message){
                            $isoDate	= date( 'Y-m-d H:i:s', intval($message['timesent']/1000) );
                            $date		= get_date_from_gmt( $isoDate, DATEFORMAT);
                            $time		= get_date_from_gmt( $isoDate, TIMEFORMAT);

                            $sender	= $wpdb->get_results("SELECT * FROM $wpdb->users WHERE ID in (SELECT user_id FROM `{$wpdb->prefix}usermeta` WHERE `meta_value` LIKE '%{$message['sender']}')");

                            if(empty($sender)){
                                $sender	= $message['sender'];
                            }else{
                                $sender	= $sender[0];
                                $sender	= TSJIPPY\USERPAGES\getUserPageLink($sender->ID);
                            }

                            // in case of private message replace the phonenumber in the chat for the name as well
                            if($message['sender'] == $chat){
                                $chatName = $sender;
                            }

                            ?>
                            <tr class=<?php echo $hidden;?>>
                                <?php
                                if($index === 0){
                                    if(count($group) > 1 ){
                                        $rowSpan	= "data-rowspan='".count($group)."'";
                                        $span		= "<span class='expand' style='color:#b22222;cursor: pointer;font-size: x-large;float: right;'>+</span>";
                                    }else{
                                        $rowSpan	= '';
                                        $span		= '';
                                    }
                                    
                                    ?>
                                    <td class='chat' <?php echo $rowSpan;?>><?php echo $chatName.'   '.$span;?></td>
                                    <?php

                                    $hidden	= 'hidden';
                                }
                                ?>
                                <td class='date'><?php echo $date;?></td>
                                <td class='time'><?php echo $time?></td>
                                <td class='sender'><?php echo $sender;?></td>
                                <td class='message'><?php echo $message['message'];?></td>
                                <td class='attachments'>
                                    <?php
                                    $attachments	= (array)maybe_unserialize($message['attachments']);
                                    foreach($attachments as $attachment){
                                        if(!file_exists($attachment)){
                                            continue;
                                        }

                                        $url	= TSJIPPY\pathToUrl($attachment);
                                        if(@is_array(getimagesize($attachment))){
                                            echo "<a href='$url'><img src='$url' alt='picture' loading='lazy' style='height:150px;'></a>";
                                        } else {
                                            echo "<a href='$url'>".basename($attachment)."</a>";
                                        }
                                    }
                                    ?>
                                </td>
                                <td class='reply'>
                                    <?php
                                    if($message['status'] == 'replied'){
                                        echo "Already Replied";
                                    }else{
                                        $msg	= urlencode($message['message']);
                                        $author	= urlencode($message['sender']);
                                        $chat	= urlencode($chat);
                                        ?>
                                        <button type="button" class="trigger" data-target="[name='emoji']" data-replace=1 title='Send an emoji reaction'>emoji</button>
                                        <form method='post' class='hidden'>
                                            <input type="hidden" class="no-reset" name="timesent" value="<?php echo $message['timesent'];?>" />
                                            <input type="hidden" class="no-reset" name="id" value="<?php echo $message['id'];?>" />
                                            <input type="hidden" class="no-reset" name="sender" value="<?php echo $message['sender'];?>" />
                                            <input type="hidden" class="no-reset" name="chat" value="<?php echo $chat;?>" />
                                            <input type='hidden' class='no-reset' name='emoji'>
                                            <input type='submit' name='action' value='Reply'>
                                        </form>
                                        <a class='button small' href='<?php echo admin_url( "admin.php?page={$_GET['page']}&tab=functions&recipient=$chat&timesent={$message['timesent']}&replymessage=$msg&author=$author" );?>'>Reply</a>
                                        <?php
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php
        return true;
    }
}