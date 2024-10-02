<?php

namespace SIM\SIGNAL;
use SIM;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use GuzzleHttp;
use mikehaertl\shellcommand\Command;
use stdClass;
use WP_Error;

/* Install java apt install openjdk-17-jdk -y
export VERSION=0.11.3
wget https://github.com/AsamK/signal-cli/releases/download/v"${VERSION}"/signal-cli-"${VERSION}"-Linux.tar.gz
sudo tar xf signal-cli-"${VERSION}"-Linux.tar.gz -C /opt
sudo ln -sf /opt/signal-cli-"${VERSION}"/bin/signal-cli /usr/local/bin/ */

// data is stored in $HOME/.local/share/signal-cli


class SignalBus extends AbstractSignal {
    public $dbusType;
    public $tableName;
    public $prefix;
    public $totalMessages;
    public $groups;
    public $receivedTableName;
    public $homeFolder;

    public function __construct($dbusType='session'){
        global $wpdb;

        parent::__construct();

        $this->osUserId         = "";

        $this->dbusType         = $dbusType;

        $phone                  = str_replace('+', '', $this->phoneNumber);
        $this->prefix           = "dbus-send --$this->dbusType --dest=org.asamk.Signal --type=method_call --print-reply=literal /org/asamk/Signal/_$phone org.asamk.Signal.";
        if($this->dbusType == 'session'){
            // find dbus address
            $id     = [];
            exec("bash -c 'echo \$UID'", $id);

            if(!empty($id)){
                $this->osUserId = $id[0];
            }

            $this->prefix   = "export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/$this->osUserId/bus; $this->prefix";
        }

        $homefolder   = [];
        exec("bash -c 'echo \$HOME'", $homefolder);
        if(!empty($homefolder)){
            $this->homeFolder   = $homefolder[0];
        }

        // Check daemon
        $this->daemonIsRunning();
        $this->startDaemon();
    }

    public function baseCommand(){
        $prefix = "export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/$this->osUserId/bus; ";
        
        $this->command = new Command([
            'command' => $prefix.$this->path,
            // This is required for binary to be able to find libzkgroup.dylib to support Group V2
            'procCwd' => dirname($this->path),
        ]);

        $this->command->addArg('--dbus');

        if($this->os == 'Windows'){
            $this->command->useExec  = true;
        }
    }

    /**
     * Register a phone number with SMS or voice verification. Use the verify command to complete the verification.
     * Default verify with SMS
     * @param bool $voiceVerification The verification should be done over voice, not SMS.
     * @param string $captcha - from https://signalcaptchas.org/registration/generate.html
     * @return bool|string
     */
    public function register(string $phone, string $captcha, bool $voiceVerification = false)
    {
        $voice  = false;
        if($voiceVerification){
            $voice  = 'false';
        }

        file_put_contents($this->basePath.'/phone.signal', $phone);

        $captcha    = str_replace('signalcaptcha://', '', $captcha);

        $this->command = new Command([
            'command' => "dbus-send --$this->dbusType --dest=org.asamk.Signal --type=method_call --print-reply=literal /org/asamk/Signal org.asamk.Signal.registerWithCaptcha string:'$phone' boolean:$voiceVerification string:'$captcha'"
        ]);

        $this->command->execute();

        if($this->command->getExitCode()){
            unlink($this->basePath.'/phone.signal');
        }

        return $this->parseResult();
    }

    /**
     * Verify the number using the code received via SMS or voice.
     * @param string $code The verification code e.g 123-456
     * @return bool|string
     */
    public function verify(string $code)
    {

        $this->command = new Command([
            'command' => "dbus-send --$this->dbusType --dest=org.asamk.Signal --type=method_call --print-reply=literal /org/asamk/Signal org.asamk.Signal.verify string:'$this->phoneNumber' string:'$code'"
        ]);

        $this->command->execute();

        if($this->command->getExitCode()){
            unlink($this->basePath.'/phone.signal');
        }

        return $this->parseResult();
    }
    
    /**
     * Link to an existing device, instead of registering a new number.
     * This shows a "tsdevice:/…" URI.
     * If you want to connect to another signal-cli instance, you can just use this URI.
     * If you want to link to an Android/iOS device, create a QR code with the URI (e.g. with qrencode) and scan that in the Signal app.
     * @param string|null $name Optionally specify a name to describe this new device. By default "cli" will be used
     * @return string
     */
    public function link(string $name = ''): string
    {
        if(empty($name)){
            $name   = get_bloginfo('name');
        }

        $this->command = new Command([
            'command' => "dbus-send --$this->dbusType --dest=org.asamk.Signal --type=method_call --print-reply=literal /org/asamk/Signal org.asamk.Signal.link string:'$name'"
        ]);

        $this->command->execute();

        if($this->command->getExitCode()){
            $error  = "<div class='error'>";
                $error  .= $this->command->getError()."<br>";
                $error  .= "Try to do the linking yourself<br><br>";
                $error  .= "Open a command line and run this:<br>";
                $error  .= "<code>$this->path link -n \"$name\"</code><br><br>";
            $error  .= "</div>";
            return $error;
        }

        $link   = $this->command->getOutput();

        $link   = str_replace(['\n', '"'], ['\0', ''], $link);

        if (!extension_loaded('imagick')){
            return $link;
        }

        $renderer       = new ImageRenderer(
            new RendererStyle(400),
            new ImagickImageBackEnd()
        );
        $writer         = new Writer($renderer);
        $qrcodeImage    = base64_encode($writer->writeString($link));

        return "<img src='data:image/png;base64, $qrcodeImage'/><br>$link";
    }
     
    /**
     * Shows if a number is registered on the Signal Servers or not.
     * @param   string          $recipient Number to check.
     * @return  string
     */
    public function isRegistered($recipient){

        $this->command = new Command([
            'command' => "{$this->prefix}isRegistered string:'$recipient'"
        ]);

        $this->command->execute();

        $result = $this->parseResult();

        if(empty($this->error)){
            $result = (str_replace('boolean ', '', $result) === 'true');
        }
        return $result;
    }

    /**
     * Fix messages before sending them
     */
    function escapeMessage(&$message){
        $message    = str_replace(['$1','$2','$3','$4','$5','$6','$7','$8','$9','$0'], ['$ 1','$ 2','$ 3','$ 4','$ 5','$ 6','$ 7','$ 8','$ 9','$ 0'], $message);

        $message    = str_replace(['`', '"'], "'", $message);


    }

    /**
     * Send a message to a group
     * @param string        $message        Specify the message, if missing, standard input is used
     * @param string        $groupId        Specify the group id
     * @param string|array  $attachments    Image file path or array of file paths
     * @param int           $timeStamp      The timestamp of a message to reply to
     *
     * @return bool|string
     */
    public function sendGroupMessage($message, $groupId, $attachments='', int $timeStamp=0, $quoteAuthor='', $quoteMessage='', $style=''){

        if(empty($message) || empty($groupId)){
            return false;
        }

        /*if(!empty($timeStamp)){
            $this->sendGroupMessageReaction($recipient, $timeStamp, $groupId);
        } */

        $this->escapeMessage($message);
        
        if(is_array($attachments)){
            foreach($attachments as $index => $attachment){
                if(!file_exists($attachment)){
                    unset($attachments[$index]);
                }
            }
            $attachments    = implode(',', $attachments);
        }

        $this->command = new Command([
            'command' => "{$this->prefix}sendGroupMessage string:\"$message\" array:string:\"$attachments\" array:byte:$groupId"
        ]);

        $this->command->execute();

        $ownTimeStamp = str_replace('int64 ', '', $this->parseResult());

        if(is_numeric($ownTimeStamp)){
            $this->addToMessageLog($groupId, $message, $ownTimeStamp);
        }else{
            SIM\printArray($ownTimeStamp);
        }

        if(!empty($this->error) && str_contains($this->error, 'Invalid group id')){
            SIM\printArray("Invalid GroupId: $groupId", false, true);
        }

        return $ownTimeStamp;
    }

    /**
     * List Groups
     * @return array|string
     */
    public function listGroups(){
        if(!empty($this->groups)){
            return $this->groups;
        }

        $this->command = new Command([
            'command' => "{$this->prefix}listGroups"
        ]);

        $this->command->execute();

        $result = $this->parseResult();

        if(empty($this->error)){
            preg_match_all('/struct {(.*?)array of bytes \[(.*?)\](.*?)}/s', $result, $matches);

            $result = [];
            foreach($matches[2] as $key=>$match){
                $group = new stdClass();
                $id = trim(str_replace("\n", '', $match));

                $idString   = [];
                foreach(explode(' ', $id) as $digid){
                    if(empty($digid)){
                        continue;
                    }

                    $idString[] = hexdec($digid);
                }

                $idString   = implode(',', $idString);

                $group->{"id"}      = $idString;
                $group->{"name"}    = trim(str_replace("\n", '', $matches[3][$key]));
                $group->{"path"}    = trim(str_replace("\n", '', $matches[1][$key]));

                $result[]           = $group;
            }
        }

        $this->groups   = $result;

        return $this->groups;
    }

    /**
     * Send a message to another user or group
     * @param string|array  $recipients     Specify the recipients’ phone number or a group id
     * @param string        $message        Specify the message, if missing, standard input is used
     * @param string|array  $attachments    Image file path or array of file paths
     * @param int           $timeStamp      The timestamp of a message to reply to
     *
     * @return bool|string
     */
    public function send($recipients, string $message, $attachments = '', int $timeStamp=0, $quoteAuthor='', $quoteMessage='', $style=''){
        if(empty($recipients)){
            return new WP_Error('Signal', 'You should submit at least one recipient');
        }

        $this->escapeMessage($message);

        if(is_array($attachments)){
            foreach($attachments as $index => $attachment){
                if(!file_exists($attachment)){
                    unset($attachments[$index]);
                }
            }
            $attachments    = implode(',', $attachments);
        }

        if(!is_array($recipients)){
            if(strpos( $recipients , '+' ) === 0){
                $recipient  = "string:'$recipients'";
            }else{
                return $this->sendGroupMessage($message, $recipients, $attachments);
            }
        }else{
            $recipient    = "array:string:".implode(',', $recipients);
        }

        $command    = "{$this->prefix}sendMessage string:\"$message\" array:string:\"$attachments\" $recipient";

        if(!empty($timeStamp)){
            $this->sendMessageReaction($recipient, $timeStamp);
        }

        //$command    .= "int64:$timestamp";

        $this->command = new Command([
            'command' => $command
        ]);

        $this->command->execute();

        $ownTimeStamp = str_replace('int64 ', '', $this->parseResult());

        if(is_numeric($ownTimeStamp)){
            if(!is_array($recipients)){
                if(strpos( $recipients , '+' ) === 0){
                    $this->addToMessageLog($recipients, $message, $ownTimeStamp);
                }
            }else{
                foreach($recipients as $rec){
                    $this->addToMessageLog($rec, $message, $ownTimeStamp);
                }
            }
        }else{
            SIM\printArray($ownTimeStamp);
        }

        return $ownTimeStamp;
    }

    public function markAsRead($recipient, $timestamp){
        // Mark as read
        $this->command = new Command([
            'command' => "{$this->prefix}sendReadReceipt string:'$recipient' array:int64:$timestamp"
        ]);

        $this->command->execute();

        return $this->parseResult();
    }

    /**
     * Deletes a message
     *
     * @param   int             $targetSentTimestamp    The original timestamp
     * @param   string|array    $recipients             The original recipient(s)
     */
    public function deleteMessage($targetSentTimestamp, $recipients){

        if(is_array($recipients)){
            $recipients    = implode(',', $recipients);
            $this->command = new Command([
                'command' => "{$this->prefix}sendRemoteDeleteMessage int64:'$targetSentTimestamp' array:string:'$recipients'"
            ]);
        }else{
            if(str_contains($recipients, '+')){
                $this->command = new Command([
                    'command' => "{$this->prefix}sendRemoteDeleteMessage int64:'$targetSentTimestamp' string:'$recipients'"
                ]);
            }else{
                $this->command = new Command([
                    'command' => "{$this->prefix}sendGroupRemoteDeleteMessage int64:'$targetSentTimestamp' array:byte:'$recipients'"
                ]);
            }
        }

        $this->command->execute();

        $result = str_replace('int64 ', '', $this->parseResult());

        if(is_numeric($result)){
            $this->markAsDeleted($targetSentTimestamp);
        }else{
            SIM\printArray($result);
        }

        return $result;
    }

    /**
     * Sends a typing indicator to number
     *
     * @param   string  $recipient  The phonenumber
     * @param   int     $timestamp  Optional timestamp of a message to mark as read
     *
     * @return string               The result
     */
    public function sentTyping($recipient, $timestamp='', $groupId=''){
        if(!empty($timestamp)){
            // Mark as read
            $this->markAsRead($recipient, $timestamp);
        }

        // Send typing
        $this->command = new Command([
            'command' => "{$this->prefix}sendTyping string:'$recipient' boolean:false"
        ]);

        $this->command->execute();

        return $this->parseResult();
    }

    public function sendGroupTyping($groupId){
        // Send typing
        $this->command = new Command([
            'command' => "{$this->prefix}sendGroupTyping array:byte:'$groupId' boolean:false"
        ]);

        $this->command->execute();

        return $this->parseResult();
    }

    public function sendMessageReaction($recipient, $timestamp, $groupId='', $emoji=''){
        if(empty($emoji)){
            $emoji  = "🦘";
        }

        if(empty($groupId)){
            // Send emoji
            $this->command = new Command([
                'command' => "{$this->prefix}sendMessageReaction string:$emoji boolean:false string:$recipient int64:$timestamp array:string:$recipient"
            ]);
        }else{
            $this->command = new Command([
                'command' => "{$this->prefix}sendGroupMessageReaction string:$emoji boolean:false string:$recipient int64:$timestamp array:byte:'$groupId'"
            ]);
        }

        $this->command->execute();

        return $this->parseResult();
    }

    /**
     * Update the name and avatar image visible by message recipients for the current users.
     * The profile is stored encrypted on the Signal servers.
     * The decryption key is sent with every outgoing messages to contacts.
     * @param string $name New name visible by message recipients
     * @param string $avatarPath Path to the new avatar visible by message recipients
     * @param bool $removeAvatar Remove the avatar visible by message recipients
     * @return bool|string
     */
    public function updateProfile(string $name, string $avatarPath = null, bool $removeAvatar = false){
        if($removeAvatar){
            $removeAvatar   = 'true';
        }else{
            $removeAvatar   = 'false';
        }
        $this->command = new Command([
            'command' => "{$this->prefix}updateProfile string:'$name' string:'' string:'' string:'$avatarPath' boolean:$removeAvatar"
        ]);

        $this->command->execute();

        return $this->parseResult();
    }

    /**
     * Submit a challenge
     *
     * @param   string  $challenge  The challenge string
     * @param   string  $captcha    The captcha as found on https://signalcaptchas.org/challenge/generate.html
     *
     * @return string               The result
     */
    public function submitRateLimitChallenge($challenge, $captcha){
        $this->command = new Command([
            'command' => "{$this->prefix}submitRateLimitChallenge string:'$challenge' string:'$captcha'"
        ]);

        $this->command->execute();

        SIM\printArray($this->command);

        return $this->parseResult();
    }

    public function getGroupInvitationLink($groupPath){
        $this->command = new Command([
            'command' => "export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/$this->osUserId/bus; dbus-send --$this->dbusType --dest=org.asamk.Signal --print-reply $groupPath org.freedesktop.DBus.Properties.Get string:org.asamk.Signal.Group string:GroupInviteLink"
        ]);

        $this->command->execute();

        $result = $this->parseResult();

        return trim(explode('string "', $result)[1],'"');
    }

    /**
     * Converts a base64 group id to an array of bytes
     *
     * @param   string  $id       the base64encode group id
     *
     * @return  string            A comma seperated list of bytes
     */
    public function groupIdToByteArray($id){
        $id = str_replace('_', '/', $id);

        $id = base64_decode($id);

        $id = unpack('C*',$id);

        $id = implode(',', $id);
        return $id;
    }

    public function findGroupName($id){
        $groups = (array)$this->listGroups();

        foreach($groups as $group){
            if(gettype($group) == 'string'){
                return $group;
            }
            if($group->id == $id){
                return $group->name;
            }
        }

        return '';
    }

    protected function parseResult($returnJson=false){
        if($this->command->getExitCode()){
            $failedCommands      = get_option('sim-signal-failed-messages', []);

            $errorMessage  = $this->command->getError();

            //SIM\printArray($errorMessage);

            // Captcha required
            if(str_contains($errorMessage, 'CAPTCHA proof required')){
                // Store command
                $failedCommands[]    = $this->command->getCommand();
                update_option('sim-signal-failed-messages', $failedCommands);

                $this->sendCaptchaInstructions($errorMessage);
            }elseif(str_contains($errorMessage, '429 Too Many Requests')){
                // Store command
                $failedCommands[]    = $this->command->getCommand();
                update_option('sim-signal-failed-messages', $failedCommands);
            }elseif(str_contains($errorMessage, 'Unregistered user')){
                // get phonenumber from the message
                preg_match('/"(\+\d*)/m', $errorMessage, $matches);

                if(isset($matches[1])){
                    // delete the signal meta key
                    $users = get_users(array(
                        'meta_key'     => 'signal_number',
                        'meta_value'   => $matches[1],
		                'meta_compare' => '=',
                    ));
            
                    foreach($users as $user){
                        delete_user_meta($user->ID, 'signal_number');

                        SIM\printArray("Deleting Signal number {$matches[1]} for user $user->ID as it is not valid anymore");
                    }
                }
            }elseif(str_contains($errorMessage, 'Invalid group id')){
                SIM\printArray($errorMessage);
            }elseif(str_contains($errorMessage, 'Did not receive a reply.')){
                SIM\printArray($errorMessage); 
            }else{
                SIM\printArray($this->command);
            }
            
            $this->error    = "<div class='error'>$errorMessage</div>";
            if($returnJson){
                return json_encode($this->error);
            }

            return $this->error;
        }

        $output = $this->command->getOutput();

        if($returnJson && (empty($output) || json_decode($output) == $output)){
            return json_encode($output);
        }
        return $output;
    }

    /**
     * Retry sending previous failed Signal messages
     */
    public function retryFailedMessages(){
        // get failed commands from db
        $failedCommands      = get_option('sim-signal-failed-messages', []);
        
        // clean db
        delete_option('sim-signal-failed-messages');

        if(empty($failedCommands)){
            return;
        }

        foreach($failedCommands as $command){
            //SIM\printArray($command);

            $this->command = new Command([
                'command' => $command,
                // This is required for binary to be able to find libzkgroup.dylib to support Group V2
                'procCwd' => dirname($this->path),
            ]);

            $this->command->execute();

            $this->parseResult();

            sleep(10);
        }
    }
}
