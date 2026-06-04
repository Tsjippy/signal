<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use GuzzleHttp;
use mikehaertl\shellcommand\Command;

/* Install java apt install openjdk-17-jdk -y
export VERSION=0.11.3
wget https://github.com/AsamK/signal-cli/releases/download/v"${VERSION}"/signal-cli-"${VERSION}"-Linux.tar.gz
sudo tar xf signal-cli-"${VERSION}"-Linux.tar.gz -C /opt
sudo ln -sf /opt/signal-cli-"${VERSION}"/bin/signal-cli /usr/local/bin/ */

// data is stored in $HOME/.local/share/signal-cli


class SignalCommandLine extends AbstractSignal
{
    use SendEmailBySignal;

    public object $commandObject;

    /**
     * Constructor
     */

    public function __construct()
    {
        parent::__construct();
    }

    public function baseCommand()
    {
        $this->commandObject = new Command([
            'command' => $this->path,
            // This is required for binary to be able to find libzkgroup.dylib to support Group V2
            'procCwd' => dirname($this->path),
        ]);

        $this->commandObject->addArg('-c', $this->configPath);

        if ($this->os == 'Windows') {
            $this->commandObject->useExec  = true;
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
        //dbus-send --session --dest=org.asamk.Signal --type=method_call --print-reply /org/asamk/Signal org.asamk.Signal.link string:"My secondary client" | tr '\n' '\0' | sed 's/.*string //g' | sed 's/\"//g' | qrencode -s10 -tANSI256

        file_put_contents($this->basePath . '/phone.signal', $phone);

        $captcha    = str_replace('signalcaptcha://', '', $captcha);

        $this->baseCommand();

        $this->commandObject->addArg('-a', $phone);

        $this->commandObject->addArg('register');

        if ($voiceVerification) {
            $this->commandObject->addArg('--voice', null);
        }

        if (!empty($captcha)) {
            $this->commandObject->addArg('--captcha', $captcha);
        }

        $this->commandObject->execute();

        if ($this->commandObject->getExitCode()) {
            wp_delete_file($this->basePath . '/phone.signal');
        }

        return $this->parseResult();
    }

    /**
     * Disable push support for this device, i.e. this device won’t receive any more messages.
     * If this is the master device, other users can’t send messages to this number anymore.
     * Use "updateAccount" to undo this. To remove a linked device, use "removeDevice" from the master device.
     * @return bool|string
     */
    public function unregister()
    {
        $this->baseCommand();

        $this->commandObject->addArg('-a', $this->phoneNumber);

        $this->commandObject->addArg('unregister', null);

        $this->commandObject->execute();

        if (!$this->commandObject->getExitCode()) {
            wp_delete_file($this->basePath . '/phone.signal');
        }

        return $this->parseResult();
    }

    /**
     * Uses a list of phone numbers to determine the statuses of those users.
     * Shows if they are registered on the Signal Servers or not.
     * @param string|array $recipients One or more numbers to check.
     * @return string|string
     */

    public function getUserStatus($recipients)
    {
        if (!is_array($recipients)) {
            $recipients    = [$recipients];
        }

        $this->baseCommand();

        $this->commandObject->addArg('getUserStatus', $recipients);

        $this->commandObject->execute();

        return $this->parseResult();
    }

    /**
     * Verify the number using the code received via SMS or voice.
     * @param string $code The verification code e.g 123-456
     * @return bool|string
     */
    public function verify(string $code)
    {

        $this->baseCommand();

        $this->commandObject->addArg('-a', $this->phoneNumber);

        $this->commandObject->addArg('verify', $code);

        $this->commandObject->execute();

        if ($this->commandObject->getExitCode()) {
            wp_delete_file($this->basePath . '/phone.signal');
        }

        return $this->parseResult();
    }

    /**
     * Send a message to another user or group
     * @param string|array  $recipients     Specify the recipients’ phone number or a group id
     * @param string        $message        Specify the message, if missing, standard input is used
     * @param array         $attachments    Array of Image file paths
     *
     * @return bool|string
     */
    public function send($recipients, string $message, $attachments = null, int $timeStamp = 0, $quoteAuthor = '', $quoteMessage = '')
    {
        $groupId    = null;
        if (!is_array($recipients)) {
            if (strpos($recipients, '+') === 0) {
                $recipients    = [$recipients];
            } else {
                $groupId    = $recipients;
                $recipients = null;
            }
        }

        // parse any styling
        extract($this->parseMessageLayout($message));

        $this->baseCommand();

        $this->commandObject->nonBlockingMode = true;

        $this->commandObject->addArg('-a', $this->phoneNumber);

        $this->commandObject->addArg('send', $recipients);

        $this->commandObject->addArg('-m', $message);

        if ($groupId) {
            $this->commandObject->addArg('-g', $groupId);
        }

        if (!empty($attachments)) {
            if (!is_array($attachments)) {
                $attachments    = [$attachments];
            }
            TSJIPPY\printArray($attachments);
            $this->commandObject->addArg('-a', $attachments);
        }

        $this->commandObject->execute();

        return $this->parseResult();
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
    public function sendGroupMessage($message, $groupId, $attachments = '', $timeStamp = '', $quoteAuthor = '', $quoteMessage = '', $style = '')
    {
        return $this->send($groupId, $message, $attachments, $timeStamp, $quoteAuthor, $quoteMessage);
    }

    public function sendReceipt($recipient, $timestamp)
    {
        // Mark as read
        $this->baseCommand();

        $this->commandObject->addArg('-a', $this->phoneNumber);

        $this->commandObject->addArg('sendReceipt', $recipient);

        $this->commandObject->addArg('-t', $timestamp);

        $this->commandObject->addArg('--type', 'read');

        $this->commandObject->execute();

        return $this->parseResult();
    }

    public function sentTyping($recipient, $timestamp = '', $groupId = '')
    {
        // Mark as read
        $this->sendReceipt($recipient, $timestamp);

        // Send typing
        $this->baseCommand();

        $this->commandObject->addArg('-a', $this->phoneNumber);

        $this->commandObject->addArg('sendTyping', $recipient);

        if (!empty($groupId)) {
            $this->commandObject->addArg('-g', $groupId);
        }

        $this->commandObject->execute();

        return $this->parseResult();
    }

    public function sendGroupTyping($groupId)
    {
        return $this->sentTyping($groupId);
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
    public function updateProfile(string $name, ?string $avatarPath = '', bool $removeAvatar = false)
    {
        $this->baseCommand();

        $this->commandObject->addArg('updateProfile', null);

        $this->commandObject->addArg('--name', $name);

        if ($avatarPath) {
            $this->commandObject->addArg('--avatar', $avatarPath);
        }

        if ($removeAvatar) {
            $this->commandObject->addArg('--removeAvatar', null);
        }

        $this->commandObject->execute();

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
        #| tr '\n' '\0' | sed 's/.*string //g' | sed 's/\"//g' | qrencode -s10 -tANSI256
        if (empty($name)) {
            $name   = get_bloginfo('name');
        }
        $this->baseCommand();

        $this->commandObject->nonBlockingMode = false;

        $this->commandObject->addArg('link', null);

        $this->commandObject->addArg('-n', $name);

        // TODO: Better response handling
        $randFile = sys_get_temp_dir() . '/' . rand() . time() . ' .device';
        $this->commandObject->addArg(" > $randFile 2>&1 &", null, false); // Ugly hack!
        sleep(1); // wait for file to get populated

        $this->commandObject->execute();

        if ($this->commandObject->getExitCode()) {
            $error  = "<div class='error'>";
            $error  .= $this->commandObject->getError() . "<br>";
            $error  .= "Try to do the linking yourself<br><br>";
            $error  .= "Open a command line and run this:<br>";
            $error  .= "<code>$this->path link -n \"$name\"</code><br><br>";
            $error  .= "</div>";
            return $error;
        }

        $link   = '';
        while (empty($link)) {
            $link   = file_get_contents($randFile);
        }
        wp_delete_file($randFile);

        TSJIPPY\clearOutput();
        header("X-Accel-Buffering: no");
        header('Content-Encoding: none');

        // Turn on implicit flushing
        ob_implicit_flush(1);

        echo "Link is <code>$link</code>";

        #https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=$link

        return "<img loading='lazy' src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=$link'/><br>$link";
        if (!extension_loaded('imagick')) {
            return $link;
        }

        $renderer       = new ImageRenderer(
            new RendererStyle(400),
            new ImagickImageBackEnd()
        );
        $writer         = new Writer($renderer);
        $qrcodeImage    = base64_encode($writer->writeString($link));

        return "<img loading='lazy' src='data:image/png;base64, $qrcodeImage'/><br>$link";
    }

    /**
     * Link another device to this device.
     * Only works, if this is the master device
     * @param string $uri Specify the uri contained in the QR code shown by the new device.
     *                    You will need the full uri enclosed in quotation marks, such as "tsdevice:/?uuid=…​.. "
     * @return bool|string
     */
    public function addDevice(string $uri)
    {
        $this->baseCommand();

        $this->commandObject->addArg('--uri', $uri);

        $this->commandObject->execute();

        return $this->parseResult();
    }

    /**
     * Show a list of connected devices
     * @return array|null
     */
    public function listDevices()
    {
        $this->baseCommand();

        $this->commandObject->addArg('-o', 'json');

        $this->commandObject->addArg('listDevices', null);

        $this->commandObject->execute();

        return json_decode($this->parseResult(true));
    }

    /**
     * Remove a connected device. Only works, if this is the master device
     * @param int $deviceId Specify the device you want to remove. Use listDevices to see the deviceIds
     * @return bool|string
     */
    public function removeDevice(int $deviceId)
    {
        $this->baseCommand();

        $this->commandObject->addArg('removeDevice', null);

        $this->commandObject->addArg('-d', $deviceId);

        $this->commandObject->execute();

        return $this->parseResult();
    }

    /**
     * Update the account attributes on the signal server.
     * Can fix problems with receiving messages
     * @return bool
     */
    public function updateAccount()
    {
        $this->baseCommand();

        $this->commandObject->addArg('updateAccount', null);

        $this->commandObject->execute();

        return $this->parseResult();
    }

    /**
     * Private function to create group, update group and add members in the group
     * @param string|null $name Specify the new group name
     * @param array $members Specify one or more members to add to the group
     * @param string|null $avatarPath Specify a new group avatar image file
     * @param string|null $groupId Specify the recipient group ID in base64 encoding.
     *                             If not specified, a new group with a new random ID is generated
     * @return bool|string
     */
    private function _createOrUpdateGroup(string $name = '', array $members = [], string $avatarPath = '', string $groupId = '')
    {
        $this->baseCommand();

        $this->commandObject->addArg('updateGroup', null);

        if (!empty($groupId)) {
            $this->commandObject->addArg('-g', $groupId);
        }

        if ($name) {
            $this->commandObject->addArg('-n', $name);
        }

        if (!empty($members)) {
            $this->commandObject->addArg('-m', $members);
        }

        if (!empty($avatarPath)) {
            $this->commandObject->addArg('-a', $avatarPath);
        }

        $this->commandObject->execute();

        return $this->parseResult();
    }

    /**
     * Create Group
     * @param string $name
     * @param array $members
     * @param string|null $avatarPath
     * @return bool
     */
    public function createGroup(string $name, array $members = [], string $avatarPath = ''): bool
    {
        return $this->_createOrUpdateGroup($name, $members, $avatarPath);
    }

    public function updateGroup(string $groupId, string $name = '', array $members = [], string $avatarPath = ''): bool
    {
        return $this->_createOrUpdateGroup($name, $members, $avatarPath, $groupId);
    }

    public function addMembersToGroup(string $groupId, array $members)
    {
        return $this->_createOrUpdateGroup('', $members, '', $groupId);
    }

    /**
     * List Groups
     * @return array|string
     */
    public function listGroups()
    {
        $this->baseCommand();

        $this->commandObject->addArg('-a', $this->phoneNumber);

        $this->commandObject->addArg('-o', 'json');

        $this->commandObject->addArg('listGroups', null);

        $this->commandObject->execute();

        return json_decode($this->parseResult(true));
    }

    /**
     * Join a group via an invitation link.
     * To be able to join a v2 group the account needs to have a profile (can be created with the updateProfile command)
     * @param string $uri The invitation link URI (starts with https://signal.group/#)
     * @return bool|string
     */
    public function joinGroup(string $uri)
    {
        $this->baseCommand();

        $this->commandObject->addArg('joinGroup', null);

        $this->commandObject->addArg('--uri', $uri);

        $this->commandObject->execute();

        return $this->parseResult();
    }

    /**
     * Send a quit group message to all group members and remove self from member list.
     * If the user is a pending member, this command will decline the group invitation
     * @param string $groupId Specify the recipient group ID in base64 encoding
     * @return bool|string
     */
    public function quitGroup(string $groupId)
    {
        $this->baseCommand();

        $this->commandObject->addArg('quitGroup', null);

        $this->commandObject->addArg('-g', $groupId);

        $this->commandObject->execute();

        return $this->parseResult();
    }

    /**
     * Query the server for new messages.
     * New messages are printed on standard output and attachments are downloaded to the config directory.
     * In json mode this is outputted as one json object per line
     * @param int $timeout Number of seconds to wait for new messages (negative values disable timeout). Default is 5 seconds
     * @return object|string
     */
    public function receive(int $timeout = 5)
    {
        $this->baseCommand();

        $this->commandObject->addArg('-o', 'json');

        $this->commandObject->addArg('receive', null);

        $this->commandObject->addArg('-t', $timeout);

        $this->commandObject->execute();

        return json_decode($this->parseResult(true));
    }

    protected function parseResult($returnJson = false)
    {
        if ($this->commandObject->getExitCode()) {

            $errorMessage  = $this->commandObject->getError();

            //TSJIPPY\printArray($errorMessage);

            // Captcha required
            if (str_contains($errorMessage, 'CAPTCHA proof required')) {
                // Store command
                $failedCommands[]    = $this->commandObject->getCommand();

                $this->sendCaptchaInstructions($errorMessage);
            } elseif (str_contains($errorMessage, '429 Too Many Requests')) {
                // Store command
                $failedCommands[]    = $this->commandObject->getCommand();
            } elseif (str_contains($errorMessage, 'Unregistered user')) {
                // get phonenumber from the message
                preg_match('/"(\+\d*)/m', $errorMessage, $matches);

                if (isset($matches[1])) {
                    // delete the signal meta key
                    $users = get_users(array(
                        'meta_key'     => 'signal_number',
                        'meta_value'   => $matches[1],
                        'meta_compare' => '=',
                    ));

                    foreach ($users as $user) {
                        delete_user_meta($user->ID, 'signal_number');

                        TSJIPPY\printArray("Deleting Signal number {$matches[1]} for user $user->ID as it is not valid anymore");
                    }
                }
            } elseif (str_contains($errorMessage, 'Invalid group id')) {
                TSJIPPY\printArray($errorMessage);
            } elseif (str_contains($errorMessage, 'Did not receive a reply. ')) {
                TSJIPPY\printArray($errorMessage);
            } else {
                TSJIPPY\printArray($errorMessage);
            }

            $this->error    = $errorMessage;
            if ($returnJson) {
                return json_encode($this->error);
            }

            return $this->error;
        }

        $output = $this->commandObject->getOutput();

        $lines  = explode("\n", $output);

        $output = end($lines);

        if ($returnJson && (empty($output) || json_decode($output) == $output)) {
            return json_encode($output);
        }
        return $output;
    }

    /**
     * Deletes a message
     *
     * @param   int             $targetSentTimestamp    The original timestamp
     * @param   string|array    $recipients             The original recipient(s)
     */
    public function remoteDelete($targetSentTimestamp, $recipients)
    {
        // to be implemented
    }

    public function sendReaction($recipient, $timestamp, $groupId = '', $emoji = '')
    {
        // to be implemented
    }

    public function submitRateLimitChallenge($challenge, $captcha)
    {
        // to be implemented
    }

    public function getGroupInvitationLink($groupPath)
    {
        // to be implemented
    }

    public function findGroupName($id)
    {
        // to be implemented
    }

    public function doRequest($command, $params)
    {
        // to be implemented
    }
}
