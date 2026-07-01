<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

use function TSJIPPY\addRawHtml;
use function TSJIPPY\addElement;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu extends \TSJIPPY\ADMIN\SubAdminMenu
{

    /**
     * AdminMenu constructor.
     *
     * @param array $settings The settings for the plugin
     * @param string $name The name of the plugin
     */
    public function __construct($settings, $name)
    {
        parent::__construct($settings, $name);
    }

    public function settings($parent)
    {
        $local    = false;
        if (isset($this->settings['local']) && $this->settings['local']) {
            $local    = true;
        }

        addElement('strong', $parent, [], 'Server type');
        addElement('br', $parent);

        $parent->append('Indicate if you can install signal-cli on this server or not');
        addElement('br', $parent);
        $parent->append('I have root access on this server');

        $label  = addElement('label', $parent, ['class' => 'switch']);

        $attributes = [
            'type' => "checkbox",
            'name' => "local",
            'value' => 1
        ];

        if ($local) {
            $attributes['checked']  = 'checked';
        }

        addElement('input', $label, $attributes);

        addElement('span', $label, ['class' => "slider round"]);

        addElement('br', $parent);

        addElement('br', $parent);

        ob_start();

        if ($local) {
            $signal = getSignalInstance();

            if (str_contains(php_uname(), 'Linux')) {
                $signal->createDbTables();
            }

            $passed     = $signal->checkPrerequisites();
            addRawHtml(ob_get_clean(), $parent);

            ob_start();

            if (!$passed) {
?>
                <div class='error'>
                    Signal-cli is not working properly, please check the error log for more details.<br>
                    <?php echo esc_html($signal->error); ?>
                </div>
        <?php
            } elseif ($signal->phoneNumber && $signal->getUserStatus($signal->phoneNumber)) {
                $this->connectedOptions($signal, $parent);
            } else {
                $this->notConnectedOptions();
            }
        } else {
            $this->notLocalOptions();
        }

        addRawHtml(ob_get_clean(), $parent);

        return true;
    }

    public function emails($parent)
    {
        if (!SETTINGS['local'] ?? false) {
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

        <h4>
            E-mail to remind people to add their Signal phonenumber
        </h4>
    <?php

        $email->printInputs();

        addRawHtml(ob_get_clean(), $parent);

        return true;
    }

    public function data($parent)
    {
        if (!isset($this->settings['local']) || !$this->settings['local']) {
            return false;
        }

        $amount    = 100;
        if (isset($_REQUEST['amount'])) {
            $amount    = (int) $_REQUEST['amount'];
        }

        $startDate     = gmdate('Y-m-d', strtotime('-3 month'));
        if (isset($_REQUEST['start-date'])) {
            $startDate = TSJIPPY\sanitize($_REQUEST['start-date'] ?? '');
        }

        $endDate    = gmdate('Y-m-d', strtotime('+1 day'));
        if (isset($_REQUEST['end-date'])) {
            $endDate   = TSJIPPY\sanitize($_REQUEST['end-date'] ?? '');
        }

        $this->messagesHeader($startDate, $endDate, $amount, $parent);

        $this->processActions($parent);

        $tablinkWrapper = addElement('div', $parent, ['class' => 'tablink-wrapper']);

        $buttons    = [
            'sent'            => 'Sent Messages',
            'received'        => 'Received Messages'
        ];

        $tab      = 'sent';
        if (isset($_GET['second-tab'])) {
            $tab  = TSJIPPY\sanitize($_GET['second-tab'], 'key');
        }

        foreach ($buttons as $id => $text) {
            $attributes = [
                'class'       => 'tablink' . ($tab == $id ? ' active' : ''),
                'id'          => "show-$id",
                'data-target' => $id,
                'type'        => 'button'
            ];
            addElement('button', $tablinkWrapper, $attributes, $text);
        }

        $sentTable  = $this->sentMessagesTable($startDate, $endDate, $amount, $parent);

        $hidden    = 'hidden';
        if (empty($sentTable)) {
            $hidden    = '';
        }

        $this->receivedMessagesTable($startDate, $endDate, $amount, $parent, $hidden);

        return true;
    }

    private function rateChallenge($parent)
    {
        // no challenge var set
        if (empty($_REQUEST['challenge'])) {
            return false;
        }

        // captcha token submitted, run action and show functions form
        if (!empty($_REQUEST['captchastring'])) {
            $signal    = getSignalInstance();

            $result    = $signal->submitRateLimitChallenge($_REQUEST['challenge'], $_REQUEST['captchastring']);

            if (!$result) {
                TSJIPPY\addElement('div', $parent, ['class' => 'error'], 'Rate challenge could not be submitted');
            } else {
                TSJIPPY\addElement('div', $parent, ['class' => 'success'], 'Rate challenge succesfully submitted');
            }

            return false;
        }

        /**
         * Show rate challenge form
         */
        else {
            $form   = TSJIPPY\addElement('form', $parent, ['method' => 'get']);

            TSJIPPY\addElement('input', $form, ['type' => "hidden", 'class' => "no-reset", 'name' => "page", 'value' => "tsjippy-signal"]);
            TSJIPPY\addElement('input', $form, ['type' => "hidden", 'class' => "no-reset", 'name' => "main-tab", 'value' => "functions"]);

            $label  = TSJIPPY\addElement('label', $form);
            TSJIPPY\addElement('h4', $label, [], 'Challenge string');

            TSJIPPY\addElement('input', $label, ['type' => "text", 'name' => "challenge", 'value' => TSJIPPY\sanitize($_REQUEST['challenge']), 'style' => "width:100%", 'required' => "required"]);

            $label  = TSJIPPY\addElement('label', $form, [], 'Get the captcha from ');
            TSJIPPY\addElement('h4', $label, [], 'Captcha string', 'afterBegin');

            TSJIPPY\addElement('a', $label, ['href' => "https://signalcaptchas.org/challenge/generate.html", 'target' => "_blank"], 'here');

            /** @disregard P1013 */
            $label->insertAdjacentText('beforeEnd', ' then copy the link below');

            TSJIPPY\addElement('textarea', $form, ['name' => "captchastring", 'style' => "width:100%;", 'required' => "required"]);

            TSJIPPY\addElement('br', $form);

            TSJIPPY\addElement('button', $form, ['type' => "submit"], 'Submit');

            return true;
        }
    }

    public function functions($parent)
    {
        if ($this->rateChallenge($parent)) {
            return true;
        }

        // check if we need to send a message
        if (!empty($_REQUEST['message']) && !empty($_REQUEST['recipient'])) {
            $message    = stripslashes($_REQUEST['message']);

            // reply to previous message
            if (!empty($_REQUEST['timesent']) && !empty($_REQUEST['replymessage']) && !empty($_REQUEST['author'])) {
                $result    = sendSignalMessage($message, stripslashes($_REQUEST['recipient']), '', intval($_REQUEST['timesent']), $_REQUEST['author'], $_REQUEST['replymessage']);
            } else {
                $result    = sendSignalMessage($message, stripslashes($_REQUEST['recipient']));
            }

            if (is_wp_error($result)) {
                TSJIPPY\printArray($result);

                TSJIPPY\addElement('div', $parent, ['class' => 'error'], 'Message could not be send' . esc_html($result->get_error_message()));
            } elseif (empty($result)) {
                TSJIPPY\addElement('div', $parent, ['class' => 'error'], 'Message sending timed out');
            } else {
                TSJIPPY\addElement('div', $parent, ['class' => 'success'], 'Message succesfully send' . esc_html($result));
            }
        };
        $timeStamp        = '';

        if (!empty($_GET['timesent'])) {
            $timeStamp    = (int) $_GET['timesent'];
        }

        $prevMessage    = TSJIPPY\sanitize($_GET['replymessage'] ?? '');

        $author         = TSJIPPY\sanitize($_GET['author'] ?? '');

        $chat           = TSJIPPY\sanitize($_GET['recipient'] ?? '');

        $form   = TSJIPPY\addElement('form', $parent, ['method' => 'post']);

        TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'timestamp', 'value' => $timeStamp]);

        TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'author', 'value' => $author]);

        TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'prevmessage', 'value' => $prevMessage]);

        $label  = TSJIPPY\addElement('label', $form);

        TSJIPPY\addElement('h4', $label, [], 'Message to be send');

        /** @disregard P1013 */
        $label->insertAdjacentText('beforeEnd', 'You can do basic formatting as listed below:');

        TSJIPPY\addElement('br', $label);

        ob_start();
    ?>
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

        <?php

        TSJIPPY\addRawHtml(ob_get_clean(), $label);

        TSJIPPY\addElement('textarea', $label, ['name' => 'message', 'style' => 'width: calc(100% - 50px);', 'required' => 'required']);

        TSJIPPY\addElement('button', $label, ['type' => 'button', 'class' => 'trigger', 'data-target' => '[name="message"]'], 'Emoji');

        TSJIPPY\addElement('br', $form);

        $label  = TSJIPPY\addElement('label', $form);

        TSJIPPY\addElement('h4', $label, [], 'Recipient');

        TSJIPPY\addElement(
            'input',
            $label,
            [
                'type'          => 'text',
                'name'          => 'recipient',
                'list'          => 'groups',
                'style'         => 'width: calc(100% - 50px);',
                'required'      => 'required',
                'placeholder'   => "Type a name or groupname to select",
                'value'         => $chat
            ]
        );

        $users            = get_users([
            'meta_query' => array(
                array(
                    'key'     => 'tsjippy_phonenumbers',
                    'compare' => 'EXISTS'
                )
            ),
            'orderby'    => 'meta_value',
            'order'     => 'ASC'
        ]);

        $dataList   = TSJIPPY\addElement('datalist', $label, ['id' => "groups"]);
        foreach ($users as $user) {
            $phones    = get_user_meta($user->ID, 'tsjippy_phonenumbers');

            foreach ($phones as $phone) {
                TSJIPPY\addElement('option', $dataList, ['value' => $phone], "{$user->display_name} ({$phone})");
            }
        }

        if (isset($this->settings['local']) && $this->settings['local']) {
            $signal    = getSignalInstance();

            $groups    = $signal->listGroups();

            foreach ((array)$groups as $group) {
                if (empty($group->name)) {
                    continue;
                }

                TSJIPPY\addElement('option', $dataList, ['value' => $group->id], $group->name);
            }
        } else {
            if (empty($this->settings['groups'])) {
                $groups    = [''];
            } else {
                $groups    = $this->settings['groups'];
            }
            foreach ((array)$groups as $group) {
                TSJIPPY\addElement('option', $dataList, ['value' => $group], $group);
            }
        }

        TSJIPPY\addElement('button', $label, [], 'Send message');

        TSJIPPY\addElement('br', $form);
        TSJIPPY\addElement('br', $form);

        return true;
    }

    /**
     * Function to do extra actions from $request data. Overwrite if needed
     */
    public function postActions($request)
    {
        $local    = SETTINGS['local'] ?? false;

        if (!$local) {
            return "<div class='error'>You need to have root access to change these settings</div>";
        }

        $signal    = getSignalInstance();

        // Change account details
        if (isset($request['display-name']) || isset($request['avatar'])) {

            $message    = '';

            if (isset($request['display-name'])) {
                $displayName    = $request['display-name'];

                if ($displayName != $this->settings['display-name']) {
                    $result    = $signal->updateProfile($displayName);

                    if (is_wp_error($result)) {
                        // @disregard
                        $message    .= "<div class='error'>" . $result->get_error_message() . "</div>";
                    } else {
                        $message    .= "<div class='success'>Display name changed succesfully to $displayName</div>";
                    }
                }
            }


            if (isset($request['picture-ids']['avatar'])) {
                $avatarAttachmentId    = $request['picture-ids']['avatar'];

                if ($avatarAttachmentId != $this->settings['picture-ids']['avatar']) {
                    if (empty($avatarAttachmentId)) {
                        $result    = $signal->updateProfile('', null, true);
                    } else {
                        $path    = get_attached_file($avatarAttachmentId);

                        if (empty($path) || !file_exists($path)) {
                            return $message . "<div class='error'>Something went wrong with the avatar, please try again</div>";
                        }
                        $result    = $signal->updateProfile('', $path);
                    }

                    if (is_wp_error($result)) {
                        $message    .= "<div class='error'>" . $result->get_error_message() . "</div>";
                    } else {
                        $message    .= "<div class='success'>Avatar changed succesfully</div>";
                    }
                }
            }

            return $message;
        }

        /**
         * Show the registration form if needed
         */
        if (isset($_GET['register'])) {
            return $this->registerForm();
        } elseif (isset($_GET['unregister'])) {
            $signal->unregister();
        } elseif (!empty($request['captcha'])) {
            $result = $signal->register(request['phone'], $request['captcha'], isset($request['voice']));

            if (is_wp_error($result)) {
                return "<div class='error'>" . $result->get_error_message() . "</div>";
            } elseif (empty($signal->error)) {
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
        } elseif (!empty($request['verification-code'])) {
            $result    = $signal->verify($request['verification-code']);

            if (is_wp_error($result)) {
                return "<div class='error'>" . $result->get_error_message() . "</div>" . $this->registerForm();
            } elseif (!empty($signal->error)) {
                return "<div class='error'>$signal->error</div>" . $this->registerForm();
            } else {
                unset($request['verification-code']);

                return "<div class='success'>Succesfully registered with Signal!</div>";
            }
        } elseif (isset($_GET['link'])) {
            return $signal->link();
        }
    }

    public function registerForm()
    {
        ob_start();
        ?>
        <form method='post' action='<?php echo esc_url(admin_url("admin.php?page=" . TSJIPPY\sanitize($_GET['page']) . "&main-tab=" . TSJIPPY\sanitize($_GET['main-tab']))); ?>'>
            <h4>
                Register with Signal
            </h4>
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
     * @param    object            $signal        The signal object
     * @param   \DOMElement|null $parent     Parent node element
     */
    public function connectedOptions($signal, $parent)
    {
        $tab        = '';
        if (!empty($_GET['main-tab'])) {
            $tab    = "&main-tab=" . TSJIPPY\sanitize($_GET['main-tab']);
        }
        $url        = admin_url("admin.php?page=" . TSJIPPY\sanitize($_GET['page']) . $tab);

        if (isset($_GET['force'])) {
            $signalGroups    = $signal->listGroups(false, false, true);
        } else {
            $signalGroups    = $signal->listGroups();
        }

        ob_start();

        if (!empty($signal->error)) {
            if (str_contains($signal->error, 'Specified account does not exist')) {
        ?>
                <div class='warning'>
                    <?php echo esc_attr($signal->phoneNumber); ?> is connected to on this machine but not registered on the Signal Servers, please register the number again<br>
                </div>

        <?php

                return $this->notConnectedOptions();
            }

            echo wp_kses_post($signal->error);
        }
        ?>
        <h4>
            Connection details
        </h4>
        <p>
            Currently connected to <?php echo esc_attr($signal->phoneNumber); ?>
            <a href='<?php echo esc_url($url); ?>&unregister=true' class='button'>Unregister</a><br>
        </p>

        <label>
            Signal Messenger Display name<br>
            <input type='text' name='display-name' value='<?php echo esc_attr($this->settings['display-name']); ?>' style='width:310px'>
        </label>
        <br>
        <label>
            Signal Messenger Avatar (328pxx328px)<br>
        </label>
        <?php
        addRawHtml(ob_get_clean(), $parent);

        $this->pictureSelector('avatar', 'avatar', $parent);

        $this->recurrenceSelector('reminder-freq', $this->settings['reminder-freq'] ?? '', 'How often should people be reminded to add a signal phonenumber  to the website', $parent);

        if (!empty($signalGroups)) {
            $wrapper = addElement('div', $parent, ['class' => 'signal-group-wrapper']);
            addElement('h4', $wrapper, [], 'Select Signal group(s) to send new content messages to by default');

            foreach ($signalGroups as $group) {
                if (empty($group->name)) continue;

                $label  = addElement('label', $wrapper, [], $group->name);
                $attr   = [
                    'type' => 'checkbox',
                    'name' => 'groups[]',
                    'value' => $group->id
                ];

                if (in_array($group->id, $this->settings['groups'] ?? [])) $attr['checked'] = 'checked';

                addElement('input', $label, $attr, '', 'afterBegin');
                addElement('br', $wrapper);
            }

            $invWrapper     = addElement('div', $parent);
            addElement('h4', $invWrapper, [], 'Select optional Signal group(s) to invite new users to:');

            foreach ($signalGroups as $group) {
                if (empty($group->name)) continue;

                $label  = addElement('label', $invWrapper, [], $group->name);
                $attr   = [
                    'type' => 'checkbox',
                    'name' => 'invgroups[]',
                    'value' => $group->id
                ];

                if (in_array($group->id, $this->settings['invgroups'] ?? [])) $attr['checked'] = 'checked';

                addElement('input', $label, $attr, '', 'afterBegin');
                addElement('br', $invWrapper);
            }
        }
    }

    /**
     * Shows the options when not connected to Signal
     */
    public function notConnectedOptions()
    {
        $url        = admin_url("admin.php?page=" . TSJIPPY\sanitize($_GET['page']));
        if (!empty($_GET['tab'])) {
            $url    .= "&main-tab=" . TSJIPPY\sanitize($_GET['main-tab']);
        }

        ?>
        <h4>
            Connection details
        </h4>
        <p>
            Currently not connected to Signal
            <br>
            <a href='<?php echo esc_url($url); ?>&register=true' class='button'>Register a new number with Signal</a>

            <a href='<?php echo esc_url($url); ?>&link=true' class='button'>Link to an existing Signal number</a>
        </p>
    <?php
    }

    /**
     * Shows the options when Java JDK is not installed
     */
    public function notLocalOptions()
    {
        if (empty($this->settings['groups'])) {
            $groups    = [''];
        } else {
            $groups    = $this->settings['groups'];
        }

    ?>
        <label>
            Link to join the Signal group
            <input type='url' name='group-link' value='<?php echo esc_attr($this->settings["group-link"]); ?>' style='width:100%'>
        </label>

        <div class="">
            <h4>
                Give optional Signal group name(s) to send new content messages to:
            </h4>
            <div class="clone-divs-wrapper">
                <?php
                foreach ($groups as $index => $group) {
                ?>
                    <div class="clone-div" data-div-id="<?php echo esc_attr($index); ?>">
                        <label>
                            <h4 style='margin: 0px;'>Signal groupname <?php echo esc_attr($index + 1); ?></h4>
                            <input type='text' name="groups[<?php echo esc_attr($index); ?>]" value='<?php echo esc_attr($group); ?>'>
                        </label>
                        <span class='button-wrapper' style='margin:auto;'>
                            <button type="button" class="add button" style="flex: 1;">+</button>
                            <?php
                            if (count($groups) > 1) {
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

    public function processActions($parent)
    {
        /**
         * Download a backup of the configuration
         */
        if (isset($_REQUEST['backup'])) {
            $signal    = getSignalInstance();

            if (!empty($signal->configPath)) {
                $zip = new \ZipArchive();

                $zipFileName    = 'Signal-cli-Backup.zip';

                $zipFilePath     = get_temp_dir() . $zipFileName; // Use a temporary directory

                if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== TRUE) {
                    exit(esc_attr("Cannot open <$zipFilePath>"));
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

                $wpFileSystem   = TSJIPPY\loadWpFileSystem();
                echo ($wpFileSystem->get_contents($zipFilePath)); // Output the file content

                wp_delete_file($zipFilePath);
                exit;
            }
        }

        if (!isset($_REQUEST['action'])) {
            return;
        }

        if ($_REQUEST['action'] == 'Delete') {
            $signal    = getSignalInstance();

            if (isset($_REQUEST['time_send'])) {
                $result        = $signal->remoteDelete($_REQUEST['time_send'], $_REQUEST['recipients']);

                if (
                    $result !== true ||
                    (
                        is_string($result) && !is_numeric(str_replace('int64 ', '', $result))
                    )
                ) {
                    TSJIPPY\addElement('div', $parent, ['class' => 'error'], $result);
                } else {
                    TSJIPPY\addElement('div', $parent, ['class' => 'success'], 'Succesfully removed the message');
                }
            } else {
                $result        = $signal->clearMessageLog($_REQUEST['delete-date']);

                if ($result === false) {
                    TSJIPPY\addElement('div', $parent, ['class' => 'error'], 'Something went wrong');
                } else {
                    TSJIPPY\addElement('div', $parent, ['class' => 'success'], "Succesfully removed $result messages");
                }
            }
        } elseif ($_REQUEST['action'] == 'Save') {
            $this->settings['clean-period']    = TSJIPPY\sanitize($_REQUEST['clean-period'] ?? '');
            $this->settings['clean-amount']    = TSJIPPY\sanitize($_REQUEST['clean-amount'] ?? '');

            update_option('tsjippy_signal_settings', $this->settings);
        } elseif ($_REQUEST['action'] == 'Reply') {
            $signal    = getSignalInstance();

            $groupId    = '';
            if ($_REQUEST['sender'] != $_REQUEST['chat']) {
                $groupId    = TSJIPPY\sanitize($_REQUEST['chat'] ?? '');
            }

            $result    = $signal->sendReaction($_REQUEST['sender'], $_REQUEST['timesent'], $groupId, $_REQUEST['emoji']);

            if (is_numeric(str_replace('int64 ', '', $result))) {
                TSJIPPY\addElement('div', $parent, ['class' => 'success'], 'Reaction sent succesfully');
            } else {
                TSJIPPY\addElement('div', $parent, ['class' => 'error'], 'Reaction sent not succesfull');
            }
        }
    }

    /**
     * Display the header for the messages table
     * @param string $startDate The start date for the message log
     * @param string $endDate The end date for the message log
     * @param int $amount The number of messages to display
     */
    public function messagesHeader($startDate, $endDate, $amount, $parent)
    {
        if (!isset($this->settings['clean-period'])) {
            $this->settings['clean-period']    = '';
        }
        if (!isset($this->settings['clean-amount'])) {
            $this->settings['clean-amount']    = '';
        }

        ob_start();
    ?>
        <div class='flex-container'>
            <div class='flex'>
                <h2>Show Message History</h2>

                <form method='get' id='message-overview-settings'>
                    <input type="hidden" class="no-reset" name="page" value="tsjippy_signal" />
                    <input type="hidden" class="no-reset" name="tab" value="data" />

                    <label>
                        Show Messages send between <input type='date' name='start-date' value='<?php echo esc_attr($startDate); ?>' max='<?php echo esc_attr(gmdate('Y-m-d')); ?>'> and <input type='date' name='end-date' value='<?php echo esc_attr($endDate); ?>' max='<?php echo esc_attr(gmdate('Y-m-d', strtotime('+1 day'))); ?>'>
                    </label>
                    <br>
                    <label>
                        Amount of messages to show <input type='number' name='amount' value='<?php echo esc_attr($amount); ?>' style='max-width: 60px;'>
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
                        Delete Messages send before <input type='date' name='delete-date' value='<?php echo esc_attr(gmdate('Y-m-d', strtotime('-1 month'))); ?>' max='<?php echo esc_attr(gmdate('Y-m-d')); ?>'>
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
                        Automatically remove messages older then <input type='number' name='clean-amount' value='<?php echo esc_attr($this->settings['clean-amount']); ?>' style='width:60px;'>
                        <select name='clean-period' class='inline'>
                            <option value='days' <?php if ($this->settings['clean-period'] == 'days') echo 'selected="selected"'; ?>>days</option>
                            <option value='weeks' <?php if ($this->settings['clean-period'] == 'weeks') echo 'selected="selected"'; ?>>weeks</option>
                            <option value='months' <?php if ($this->settings['clean-period'] == 'months') echo 'selected="selected"'; ?>>months</option>
                        </select>
                    </label>
                    <br>
                    <input type='submit' name='action' value='Save'>
                </form>
            </div>
        </div>
<?php

        addRawHtml(ob_get_clean(), $parent);
    }

    /**
     * Adds the navigator for the messages tables
     */
    private function addNavigator($startDate, $endDate, $amount, $parent, $signal, $page)
    {
        if ($signal->totalMessages < $amount) {
            return;
        }

        $url        = admin_url("admin.php?page=tsjippy_signal&main-tab=data&amount=$amount&start-date=$startDate&end-date=$endDate&nr=");
        $totalPages    = ceil($signal->totalMessages / $amount);

        if ($page != 1) {
            $prev    = $page - 1;
            TSJIPPY\addElement('a', $parent, ['href' => esc_url($url . $prev)], '< Previous');
        }

        for ($x = 1; $x <= $totalPages; $x++) {
            $wrapEl = $parent;
            if ($page == $x) {
                $wrapEl = TSJIPPY\addElement('strong', $parent);
            }

            TSJIPPY\addElement('a', $wrapEl, ['href' => esc_url($url . $x)], " $x ");
        }

        if ($page != $totalPages) {
            $next    = $page + 1;

            TSJIPPY\addElement('a', $parent, ['href' => esc_url($url . $next)], "Next >");
        }
    }

    /**
     * Display a table of sent messages
     * @param string $startDate The start date for the message log
     * @param string $endDate The end date for the message log
     * @param int $amount The number of messages to display
     * @return bool True if the table is displayed, false otherwise
     */
    public function sentMessagesTable($startDate, $endDate, $amount, $parent)
    {
        global $wpdb;

        $page    = 1;
        if (isset($_REQUEST['nr'])) {
            $page    = (int) $_REQUEST['nr'];
        }

        $signal        = getSignalInstance();
        $messages    = $signal->getSentMessageLog($amount, $page, strtotime($startDate), strtotime($endDate));

        if (empty($messages)) {
            return false;
        }

        wp_enqueue_style('tsjippy_signal_admin', TSJIPPY\pathToUrl(TSJIPPY\PLUGINPATH . 'css/admin.min.css'), array(), PLUGINVERSION);

        $attributes    = [
            'class' => 'send-signal-messages tabcontent',
            'id'    => 'sent'
        ];

        if (($_GET['second-tab'] ?? '') == 'received') {
            $attributes['class']    .= ' hidden';
        }

        $div    = TSJIPPY\addElement('div', $parent, $attributes);

        /**
         * Page navigator
         */
        $this->addNavigator($startDate, $endDate, $amount, $div, $signal, $page);

        $table      = TSJIPPY\addElement('table', $div, ['class' => 'signal-table tsjippy table']);
        $thead      = TSJIPPY\addElement('thead', $table);
        $tbody      = TSJIPPY\addElement('tbody', $table);

        foreach (['Date', 'Time', 'Recipient', 'Message', 'Actions'] as $header) {
            TSJIPPY\addElement('th', $thead, [], $header);
        }

        foreach ($messages as $message) {
            $isoDate    = gmdate('Y-m-d H:i:s', intval($message->time_send / 1000));
            $date        = get_date_from_gmt($isoDate, TSJIPPY\DATEFORMAT);
            $time        = get_date_from_gmt($isoDate, TSJIPPY\TIMEFORMAT);

            $recipient    = '';
            if ($message->recipient[0] === '+') {
                $recipient    = TSJIPPY\getFromDb(
                    "get_display_name_for_" . $message->recipient,
                    "signal",
                    "SELECT display_name FROM %i WHERE ID in (SELECT user_id FROM %i WHERE `meta_value` LIKE %s) LIMIT 1",
                    $wpdb->users,
                    $wpdb->usermeta,
                    "%" . $wpdb->esc_like($message->recipient) . "%"
                );
            } else {
                $signal->listGroups();
                if (gettype($signal->groups) == 'array') {
                    foreach ($signal->groups as $group) {
                        if ($group->id == $message->recipient) {
                            $recipient    = $group->name;
                            break;
                        }
                    }
                }
            }

            $tr = TSJIPPY\addElement('tr', $tbody);

            TSJIPPY\addElement('td', $tr, ['class' => 'date'], $date);
            TSJIPPY\addElement('td', $tr, ['class' => 'time'], $time);
            TSJIPPY\addElement('td', $tr, ['class' => 'recipient'], $recipient);
            $td = TSJIPPY\addElement('td', $tr, ['class' => 'message', 'style' => 'text-align:left;']);
            TSJIPPY\addRawHtml(str_replace("\n", "<br>", $message->message), $td);


            $delete = TSJIPPY\addElement('td', $tr);
            if ($message->status == 'deleted') {
                /**
                 * @disregard
                 */
                $delete->append("Already Deleted");
            } else {
                $form   = TSJIPPY\addElement('form', $delete, ['method' => 'post']);

                TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'time_send', 'value' => $message->time_send]);

                TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'id', 'value' => $message->id]);

                TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'recipients', 'value' => $message->recipient]);

                TSJIPPY\addElement('input', $form, ['type' => 'submit', 'name' => 'action', 'value' => 'Delete']);
            }
        }

        return true;
    }

    /**
     * Display a table of received messages
     * @param string $startDate The start date for the message log
     * @param string $endDate The end date for the message log
     * @param int $amount The number of messages to display
     * @param string $hidden The CSS class for hiding the table
     * @return bool True if the table is displayed, false otherwise
     */
    public function receivedMessagesTable($startDate, $endDate, $amount, $parent, $hidden = 'hidden')
    {
        global $wpdb;

        if (($_GET['second-tab'] ?? '') == 'received') {
            $hidden    = '';
        }

        $page    = 1;
        if (isset($_REQUEST['nr'])) {
            $page    = (int) $_REQUEST['nr'];
        }

        $signal      = getSignalInstance();
        $messages    = $signal->getReceivedMessageLog($amount, $page, strtotime($startDate), strtotime($endDate));

        if (empty($messages)) {
            return false;
        }

        $groupedMessages    = [];

        // group the messages by chat
        foreach ($messages as $message) {
            if (!isset($groupedMessages[$message->chat])) {
                $groupedMessages[$message->chat]    = [];
            }

            $groupedMessages[$message->chat][]    = [
                'id'            => $message->id,
                'timesent'        => $message->time_send,    // timestamp is in milis
                'message'        => $message->message,
                'status'        => $message->status,
                'sender'        => $message->sender,
                'attachments'    => $message->attachments
            ];
        }

        $div    = TSJIPPY\addElement('div', $parent, ['class' => "send-signal-messages tabcontent $hidden", 'id' => 'received']);

        /**
         * Page navigator
         */
        $this->addNavigator($startDate, $endDate, $amount, $div, $signal, $page);

        $table  = TSJIPPY\addElement('table', $div, ['class' => 'signal-table tsjippy table']);
        $thead  = TSJIPPY\addElement('thead', $table);
        $tbody  = TSJIPPY\addElement('tbody', $table);

        foreach (['Chat', 'Date', 'Time', 'Recipient', 'Sender', 'Message', 'Attachments', 'Actions'] as $header) {
            TSJIPPY\addElement('th', $thead, [], $header);
        }

        if (empty($groupedMessages)) {
            TSJIPPY\addElement('p', $tbody, [], 'No messages found. ');
        }

        foreach ($groupedMessages as $chat => $group) {
            if (empty($group)) {
                continue;
            }

            if (!str_contains($chat, '+')) {
                $chatName    = $signal->findGroupName($chat);
                if (empty($chatName)) {
                    $chatName    = 'Unknow group';
                }
            } else {
                $chatName    = $chat;
            }

            foreach ($group as $index => $message) {
                $isoDate    = gmdate('Y-m-d H:i:s', intval($message['timesent'] / 1000));
                $date        = get_date_from_gmt($isoDate, TSJIPPY\DATEFORMAT);
                $time        = get_date_from_gmt($isoDate, TSJIPPY\TIMEFORMAT);

                $sender    = TSJIPPY\getFromDb(
                    "get_display_name_for_" . $message['sender'],
                    "signal",
                    "SELECT display_name FROM %i WHERE ID in (SELECT user_id FROM %i WHERE `meta_value` LIKE %s)",
                    $wpdb->users,
                    $wpdb->usermeta,
                    '%' . $wpdb->esc_like($message['sender'])
                );

                if (empty($sender)) {
                    $sender    = $message['sender'];
                } else {
                    $sender    = $sender[0];
                    $sender    = apply_filters('tsjippy-tsjippy-signal-admin-display-name', $sender->display_name, $sender);
                }

                // in case of private message replace the phonenumber in the chat for the name as well
                if ($message['sender'] == $chat) {
                    $chatName = $sender;
                }

                $attributes     = [];

                if (!empty($hidden)) {
                    $attributes['class']    = 'hidden';
                }

                $tr     = TSJIPPY\addElement('tr', $tbody, $attributes);

                if ($index === 0) {
                    $attributes = ['class' => 'chat'];

                    if (count($group) > 1) {
                        $attributes['data-rowspan'] = count($group);
                    }

                    $td     = TSJIPPY\addElement('td', $tr, $attributes, $chatName);
                    if (count($group) > 1) {
                        TSJIPPY\addElement('span', $td, ['class' => 'expand', 'style' => 'color:#b22222; cursor: pointer; font-size: x-large;float: right;'], "+");
                    }

                    $hidden    = 'hidden';
                }

                TSJIPPY\addElement('td', $tr, ['class' => 'date'], $date);
                TSJIPPY\addElement('td', $tr, ['class' => 'time'], $time);
                TSJIPPY\addElement('td', $tr, ['class' => 'sender'], $sender);
                TSJIPPY\addElement('td', $tr, ['class' => 'message'], $message['message']);

                $td = TSJIPPY\addElement('td', $tr, ['class' => 'attachments']);
                $attachments    = (array)maybe_unserialize($message['attachments']);
                foreach ($attachments as $attachment) {
                    if (!file_exists($attachment)) {
                        continue;
                    }

                    $url    = TSJIPPY\pathToUrl($attachment);
                    $size    = getimagesize($attachment);
                    if ($size && is_array($size)) {
                        $a  = TSJIPPY\addElement('a', $td, ['href' => $url]);
                        TSJIPPY\addElement('img', $a, ['src' => $url, 'alt' => 'picture', 'loading' => 'lazy', 'style' => 'height:150px;']);
                    } else {
                        TSJIPPY\addElement('a', $td, ['href' => $url], basename($attachment));
                    }
                }

                $td = TSJIPPY\addElement('td', $tr, ['class' => 'reply']);

                if ($message['status'] == 'replied') {
                    /** @disregard */
                    $td->append("Already Replied");
                } else {
                    $msg    = urlencode($message['message']);
                    $author = urlencode($message['sender']);
                    $chat   = urlencode($chat);

                    TSJIPPY\addElement(
                        'button',
                        $td,
                        [
                            'type'          => "button",
                            'class'         => "trigger",
                            'data-target'   => "[name='emoji']",
                            'data-replace'  => 1,
                            'title'         => 'Send an emoji reaction'
                        ],
                        'emoji'
                    );

                    $form    = TSJIPPY\addElement('form', $td, ['method' => 'post', 'class' => 'hidden']);

                    TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'timesent', 'value' => $message['timesent'] ?? '']);
                    TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'id', 'value' => $message['id'] ?? 0]);
                    TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'sender', 'value' => $message['sender'] ?? '']);
                    TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'chat', 'value' => urlencode($chat)]);
                    TSJIPPY\addElement('input', $form, ['type' => 'hidden', 'class' => 'no-reset', 'name' => 'emoji']);
                    TSJIPPY\addElement('input', $form, ['type' => 'submit', 'name' => 'action', 'value' => 'Reply']);

                    TSJIPPY\addElement('a', $td, ['href' => admin_url("admin.php?page=" . TSJIPPY\sanitize($_GET['page']) . "&main-tab=functions&recipient=$chat&timesent={$message['timesent']}&replymessage=$msg&author=$author"), 'class' => 'button small'], 'Reply');
                }
            }
        }

        return true;
    }
}
