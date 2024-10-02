<?php

namespace SIM\SIGNAL;

/* Install java apt install openjdk-17-jdk -y
export VERSION=0.11.3
wget https://github.com/AsamK/signal-cli/releases/download/v"${VERSION}"/signal-cli-"${VERSION}"-Linux.tar.gz
sudo tar xf signal-cli-"${VERSION}"-Linux.tar.gz -C /opt
sudo ln -sf /opt/signal-cli-"${VERSION}"/bin/signal-cli /usr/local/bin/ */

// data is stored in $HOME/.local/share/signal-cli


abstract class AbstractSignal extends Signal {
    /**
     * Register a phone number with SMS or voice verification. Use the verify command to complete the verification.
     * Default verify with SMS
     * @param bool $voiceVerification The verification should be done over voice, not SMS.
     * @param string $captcha - from https://signalcaptchas.org/registration/generate.html
     * @return bool|string
     */
    abstract public function register(string $phone, string $captcha, bool $voiceVerification = false);

    /**
     * Verify the number using the code received via SMS or voice.
     * @param string $code The verification code e.g 123-456
     * @return bool|string
     */
    abstract public function verify(string $code);
    
    /**
     * Link to an existing device, instead of registering a new number.
     * This shows a "tsdevice:/…" URI.
     * If you want to connect to another signal-cli instance, you can just use this URI.
     * If you want to link to an Android/iOS device, create a QR code with the URI (e.g. with qrencode) and scan that in the Signal app.
     * @param string|null $name Optionally specify a name to describe this new device. By default "cli" will be used
     * @return string
     */
    abstract public function link(string $name = ''): string;
     
    /**
     * Shows if a number is registered on the Signal Servers or not.
     * @param   string          $recipient Number to check.
     * @return  string
     */
    abstract public function isRegistered($recipient);

    /**
     * List Groups
     * @return array|string
     */
    abstract public function listGroups();

    /**
     * Send a message to another user or group
     * @param string|array  $recipients     Specify the recipients’ phone number or a group id
     * @param string        $message        Specify the message, if missing, standard input is used
     * @param string|array  $attachments    Image file path or array of file paths
     * @param int           $timeStamp      The timestamp of a message to reply to
     *
     * @return bool|string
     */
    abstract public function send($recipients, string $message, $attachments = [], int $timeStamp=0, $quoteAuthor='', $quoteMessage='', $style='');

    /**
     * Send a message to a group
     * @param string        $message        Specify the message, if missing, standard input is used
     * @param string        $groupId        Specify the group id
     * @param string|array  $attachments    Image file path or array of file paths
     * @param int           $timeStamp      The timestamp of a message to reply to
     *
     * @return bool|string
     */
    abstract public function sendGroupMessage($message, $groupId, $attachments = [], int $timeStamp=0, $quoteAuthor='', $quoteMessage='', $style='');

    /**
     * Mark a message as read
     * @param string  $recipient     Specify the recipients’ phone number
     * @param int     $timeStamp     The timestamp of a message to mark as read
     *
     * @return bool|string
     */
    abstract public function markAsRead($recipient, $timestamp);

    /**
     * Deletes a message
     *
     * @param   int             $targetSentTimestamp    The original timestamp
     * @param   string|array    $recipients             The original recipient(s)
     */
    abstract public function deleteMessage($targetSentTimestamp, $recipients);

    /**
     * Sends a typing indicator to number
     *
     * @param   string  $recipient  The phonenumber
     * @param   int     $timestamp  Optional timestamp of a message to mark as read
     *
     * @return string               The result
     */
    abstract public function sentTyping($recipient, $timestamp='', $groupId='');

    abstract public function sendGroupTyping($groupId);

    abstract public function sendMessageReaction($recipient, $timestamp, $groupId='', $emoji='');

    /**
     * Update the name and avatar image visible by message recipients for the current users.
     * The profile is stored encrypted on the Signal servers.
     * The decryption key is sent with every outgoing messages to contacts.
     * @param string $name New name visible by message recipients
     * @param string $avatarPath Path to the new avatar visible by message recipients
     * @param bool $removeAvatar Remove the avatar visible by message recipients
     * @return bool|string
     */
    abstract public function updateProfile(string $name, string $avatarPath = null, bool $removeAvatar = false);

    /**
     * Submit a challenge
     *
     * @param   string  $challenge  The challenge string
     * @param   string  $captcha    The captcha as found on https://signalcaptchas.org/challenge/generate.html
     *
     * @return string               The result
     */
    abstract public function submitRateLimitChallenge($challenge, $captcha);

    abstract public function getGroupInvitationLink($groupPath);

    abstract public function findGroupName($id);


    /**
    * Retry sending previous failed Signal messages
    */
   abstract public function retryFailedMessages();
}
