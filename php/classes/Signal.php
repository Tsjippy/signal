<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;
use GuzzleHttp;
use mikehaertl\shellcommand\Command;

/* Install java apt install openjdk-17-jdk -y
export VERSION=0.11.3 *** Change with latest version ***
wget https://github.com/AsamK/signal-cli/releases/download/v"${VERSION}"/signal-cli-"${VERSION}"-Linux.tar.gz
sudo tar xf signal-cli-"${VERSION}"-Linux.tar.gz -C /opt
sudo ln -sf /opt/signal-cli-"${VERSION}"/bin/signal-cli /usr/local/bin/ */

// data is stored in $HOME/.local/share/signal-cli

class Signal
{
    public string   $attachmentsPath;
    public string   $basePath;
    public string   $command;
    public string   $configPath;
    public bool     $daemon;
    public string   $error;
    public string   $os;
    public string   $osUserId;
    public string   $path;
    public string   $phoneNumber;
    public string   $programPath;
    public string   $queueTableName;
    public string   $receivedTableName;
    public string   $tableName;
    public string   $commandTableName;
    public int|null $totalMessages;
    public bool     $valid;
    public bool|int $rateLimited;   // false if not rate limited, otherwise the timestamp of when the rate limit will be lifted
    public string   $rateLimitString;
    public bool     $processingQueue;
    public array    $groups;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;

        /**
         * Folder containing the signal-cli executable and its data
         */
        $this->basePath         = wp_normalize_path(WP_CONTENT_DIR) . '/signal-cli';
        if (!is_dir($this->basePath)) {
            wp_mkdir_p($this->basePath);
        }

        /**
         * Folder where all received files are stored
         */
        $this->attachmentsPath  = $this->basePath . '/attachments';
        if (!is_dir($this->attachmentsPath)) {
            wp_mkdir_p($this->attachmentsPath);
        }

        /**
         * Folder where the signal-cli config is stored i.e. the linked account
         */
        $this->configPath  = $this->basePath . '/config';
        if (!is_dir($this->configPath)) {
            wp_mkdir_p($this->configPath);
        }

        /**
         * Folder where the signal-cli executable is stored
         */
        $this->programPath      = $this->basePath . '/program';
        if (!is_dir($this->programPath)) {
            wp_mkdir_p($this->programPath);
        }

        $this->command          = '';
        $this->daemon           = false;
        $this->error            = '';
        $this->path             = $this->programPath . '/signal-cli';
        $this->os               = 'macOS';
        $this->groups           = [];

        if (str_contains(php_uname(), 'Windows')) {
            $this->os               = 'Windows';

            $this->basePath         = wp_normalize_path($this->basePath);

            $this->path             = $this->programPath . '/bin/signal-cli.bat';
        } elseif (str_contains(php_uname(), 'Linux')) {
            $this->os               = 'Linux';
        }

        $this->osUserId         = "";

        $this->phoneNumber      = '';
        if (file_exists("$this->configPath/data/accounts.json")) {
            $accountData        = file_get_contents("$this->configPath/data/accounts.json");
            $accountData        = json_decode($accountData);
            $this->phoneNumber  = $accountData->accounts[0]->number;
        }

        $this->queueTableName    = $wpdb->prefix . 'tsjippy_signal_message_queue';

        $this->receivedTableName = $wpdb->prefix . 'tsjippy_received_signal_messages';

        $this->tableName         = $wpdb->prefix . 'tsjippy_signal_messages';

        $this->commandTableName  = $wpdb->prefix . 'tsjippy_signal_command_history';

        $this->totalMessages     = 0;

        $this->valid             = true;

        $this->rateLimited       = get_option('tsjippy-signal-rate-limit');

        $this->setRateLimit($this->rateLimited, false);

        $this->processingQueue  = false;

        // check permissions
        $path   = $this->programPath . '/signal-cli';
        if (file_exists($path) && !is_executable($path)) {
            $wpFileSystem   = TSJIPPY\loadWpFileSystem();
            $wpFileSystem->chmod($path, 0555);
        }

        // .htaccess to prevent access
        if (!file_exists($this->basePath . '/.htaccess')) {
            file_put_contents($this->basePath . '/.htaccess', 'deny from all');
        }
        if (!file_exists($this->basePath . '/index.php')) {
            file_put_contents($this->basePath . '/index.php', '<?php');
        }
        if (!file_exists($this->attachmentsPath . '/.htaccess')) {
            file_put_contents($this->attachmentsPath . '/.htaccess', 'allow from all');
        }

        if (file_exists("$this->path/bin/signal-cli")) {
            $this->path = "$this->path/bin/signal-cli";
        }

        // clean db
        delete_option('tsjippy-signal-messages');
    }

    /**
     * Create the sent messages table if it does not exist
     */
    public function createDbTables()
    {
        global $wpdb;

        if (!function_exists('maybe_create_table')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        //only create db if it does not exist
        $charsetCollate = $wpdb->get_charset_collate();

        // Sent messages log
        $sql = "CREATE TABLE {$this->tableName} (
            id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            time_send bigint(20) NOT NULL,
            recipient longtext NOT NULL,
            message longtext NOT NULL,
            status text NOT NULL
       ) $charsetCollate;";

        maybe_create_table($this->tableName, $sql);

        // Received messages log
        $sql = "CREATE TABLE {$this->receivedTableName} (
            id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            time_send bigint(20) NOT NULL,
            sender longtext NOT NULL,
            message longtext NOT NULL,
            chat longtext,
            attachments longtext,
            status text NOT NULL
       ) $charsetCollate;";

        maybe_create_table($this->receivedTableName, $sql);

        // Command queue
        $sql = "CREATE TABLE {$this->queueTableName} (
            id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            time_added bigint(20) NOT NULL,
            method longtext NOT NULL,
            params longtext,
            priority int,
            result longtext,
            retries int NOT NULL DEFAULT 0,
            waiting boolean NOT NULL DEFAULT false
       ) $charsetCollate;";

        maybe_create_table($this->queueTableName, $sql);

        // Command queue
        $sql = "CREATE TABLE {$this->commandTableName} (
            id mediumint(9) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            time_added bigint(20) NOT NULL,
            method longtext NOT NULL,
            params longtext
       ) $charsetCollate;";

        maybe_create_table($this->queueTableName, $sql);
    }

    /**
     * Adds a send message to the log
     *
     * @param   string  $recipient  The user or group the message was sent to
     * @param   string  $message    The sent message
     * @param   int     $timestamp  The timestamp of the message
     *
     * @return  int                 The db row id
     */
    protected function addToMessageLog($recipient, $message, $timestamp)
    {
        if (empty($recipient) || empty($message)) {
            return;
        }

        return TSJIPPY\insertInDb(
            $this->tableName,
            array(
                'time_send' => $timestamp,
                'recipient' => $recipient,
                'message'   => $message,
            ),
            [
                '%d',
                '%s',
                '%s'
            ],
            'signal'
        );
    }

    /**
     * Adds a received message to the log
     *
     * @param   string  $sender     The sender phonenumber
     * @param   string  $message    The message sent
     * @param   int     $time       The time the message was sent
     * @param   string  $chat       The groupId is sent in a group chat, defaults to $sender for private chats
     */
    public function addToReceivedMessageLog($sender, $message, $time, $chat = '', $attachments = '')
    {
        if (empty($sender) || empty($message)) {
            return;
        }

        if (empty($chat)) {
            $chat   = $sender;
        }

        return TSJIPPY\insertInDb(
            $this->receivedTableName,
            array(
                'time_send'   => $time,
                'sender'      => $sender,
                'message'     => $message,
                'chat'        => $chat,
                'attachments' => maybe_serialize($attachments)
            ),
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ],
            'signal'
        );
    }

    /**
     * Adds a command to the command history table
     *
     * @param   string  $method
     * @param   array   $params 
     */
    protected function addToCommandLog($method, $params)
    {
        TSJIPPY\insertInDb(
            $this->commandTableName,
            array(
                'time_send' => time(),
                'recipient' => $method,
                'message'   => $params,
            ),
            [
                '%d',
                '%s',
                '%s'
            ],
            'signal'
        );
    }

    /**
     * Retrieves the sent messages from the log
     *
     * @param   int     $amount     The amount of rows to get per page, default 100
     * @param   int     $page       Which page to get, default 1
     * @param   int     $minTime    Time after which the message has been sent in EPOCH, default empty
     * @param   int     $maxTime    Time before which the message has been sent in EPOCH, default empty
     */
    public function getSentMessageLog($amount = 100, $page = 1, $minTime = '', $maxTime = '', $receiver = '')
    {
        $startIndex = 0;

        if ($page > 1) {
            $startIndex         = ($page - 1) * $amount;
        }

        $query          = "SELECT * FROM %i where 1";
        $values         = [$this->tableName];

        $cacheKey       = "sent-messages";    

        if (!empty($minTime)) {
            $query      .= " and time_send > %d";
            $values[]      = $minTime . "000";

            $cacheKey .= "-mintime-$minTime";
        }

        if (!empty($maxTime)) {
            $query .= " and time_send < %d";
            $values[]  = $maxTime . "000";

            $cacheKey .= "-maxtime-$maxTime";
        }

        if (!empty($receiver)) {
            $query .= " and recipient = %s";
            $values[] = $receiver;

            $cacheKey .= "-receiver-$receiver";
        }

        $this->totalMessages    = TSJIPPY\getFromDb(
            $cacheKey.'-count', 
            'signal', 
            str_replace('*', 'COUNT(id) as total', $query), 
            $values
        );

        $query      .= " ORDER BY `time_send` DESC LIMIT $startIndex, $amount;";

        if ($this->totalMessages < $startIndex) {
            return [];
        }

        return TSJIPPY\getFromDb(
            $cacheKey, 
            'signal', 
            $query, 
            $values
        );
    }

    /**
     * Retrieves the messages from the log
     *
     * @param   int     $amount     The amount of rows to get per page, default 100
     * @param   int     $page       Which page to get, default 1
     * @param   int     $minTime    Time after which the message has been sent in EPOCH, default empty
     * @param   int     $maxTime    Time before which the message has been sent in EPOCH, default empty
     */
    public function getReceivedMessageLog($amount = 100, $page = 1, $minTime = '', $maxTime = '', $sender = '')
    {
        $startIndex = 0;

        if ($page > 1) {
            $startIndex         = ($page - 1) * $amount;
        }

        $query      = "SELECT * FROM %i where 1";
        $values     = [$this->receivedTableName];

        $cacheKey   = "received-messages";

        if (!empty($minTime)) {
            $query      .= " and time_send > %d";
            $values[]    = $minTime . "000";

            $cacheKey   .= "-mintime-$minTime";
        }

        if (!empty($maxTime)) {
            $query      .= " and time_send < %d";
            $values[]    = $maxTime . "000";

            $cacheKey   .= "-maxtime-$maxTime";
        }

        if (!empty($sender)) {
            $query      .= " and sender = %s";
            $values[]    = $sender;

            $cacheKey   .= "-sender-$sender";
        }

        $this->totalMessages    = TSJIPPY\getFromDb(
            $cacheKey.'-count',
            'signal',
            str_replace('*', 'COUNT(id) as total ', $query), 
            $values
        );

        $query      .= " ORDER BY `chat` ASC, `time_send` DESC LIMIT $startIndex, $amount;";

        if ($this->totalMessages < $startIndex) {
            return [];
        }

        return  TSJIPPY\getFromDb(
            $cacheKey,
            'signal',
            $query, 
            $values
        );
    }

    /**
     * Gets the latest messages from the log for a specific user
     *
     * @param   string  $phoneNumber    The phonenumber or user id
     *
     * @return  array                   The messages
     */
    public function getSendMessagesByUser($phoneNumber)
    {
        if (get_userdata($phoneNumber)) {
            $phoneNumber    = get_user_meta($phoneNumber, 'tsjippy_signalnumber', true);

            if (!$phoneNumber) {
                return;
            }
        }

        return TSJIPPY\getFromDb(
            "get_messages_from_$phoneNumber",
            "signal",
            "SELECT * FROM %i WHERE `recipient` = %s ORDER BY `time_send` DESC LIMIT 5; ",
            $this->tableName,
            $phoneNumber
        );
    }

    /**
     * Gets the latest messages from the log for a specific user
     *
     * @param   int  $timestamp         The timestamp
     *
     * @return  string                   The message
     */
    public function getSendMessageByTimestamp($timestamp)
    {
        return TSJIPPY\getFromDb(
            "get_message_$timestamp",
            "signal",
            "SELECT message FROM %i WHERE `time_send` = %d LIMIT 1",
            $this->tableName,
            $timestamp
        );
    }

    /**
     * Deletes messages from the log
     *
     * @param   string     $maxDate     The date after which messages should be kept Should be in yyyy-mm-dd format
     *
     * @return  string                  The message
     */
    public function clearMessageLog($maxDate)
    {
        $timeSend   = strtotime(get_gmt_from_date($maxDate, 'Y-m-d'));

        // remove sent messages
        TSJIPPY\removeFromDb(
            $this->tableName,
            [
                "DELETE FROM %i WHERE `time_send` < %s",
                $this->tableName,
                "{$timeSend}000"
            ],
            [],
            'signal'
        );

        // remove attachment files
        $results    =  TSJIPPY\getFromDb(
            "received-messages-with-attachment-timeSend-$timeSend",
            'signal',
            "SELECT * FROM %i WHERE `time_send` < %d AND `attachments` is NOT NULL; ", 
            $this->receivedTableName,
            "{$timeSend}000"
        );

        foreach ($results as $result) {
            foreach ($result->attachments as $attachment) {
                if (file_exists($attachment)) {
                    wp_delete_file($attachment);
                }
            }
        }

        // remove received messages
        TSJIPPY\removeFromDb(
            '',
            [
                "DELETE FROM %i WHERE `time_send` < %d",
                $this->receivedTableName,
                "{$timeSend}000"
            ],
            [],
            'signal'
        );

        return true;
    }

    /**
     * Marks a specific message as deleted in the log
     * @param   int     $timeStamp     The timestamp of the message to mark as deleted
     *
     * @return  string                  The message
     */
    public function markAsDeleted($timeStamp)
    {
        TSJIPPY\updateDbValue(
            $this->tableName,
            [
                `status` => 'deleted'
            ],
            [
                'time_send' => $timeStamp
            ],
            [
                '%s'
            ],
            [
                '%d'
            ],
            'signal'
        );
    }

    /**
     * Get Command to further get output, error or more details of the command.
     * @return Command
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Parses signal-cli message layout
     *
     * @param   string  $message    The message with signal-cli layout
     *
     * @return  array               An array containing the message with the layout tags stripped and a style array containing the position and type of the layout to apply in signal styling
     */
    protected function parseMessageLayout($message)
    {
        $replaceTags    = [
            "&nbsp;"        => ' ',
            '&amp;'         => '&',
            '<br>'          => "\n",
            '<br />'        => "\n",
            '<strong>'      => '<b>',
            '</strong>'     => '</b>',
            '<em>'          => '<i>',
            '</em>'         => '</i>',
            '<details>'     => '<spoiler>',
            '</details>'    => '</spoiler>',
            '<s>'           => '<ss>',
            '</s>'          => '</ss>'
        ];

        // Strip unwanted html
        $message    = strip_tags($message, [...array_keys($replaceTags), '<b>', '</b>']);

        // replace html tags with signal styling
        $message    = str_replace(
            array_keys($replaceTags),
            array_values($replaceTags),
            $message
        );

        $style        = [];

        // parse layout
        $result    = preg_match_all('/<(b|i|spoiler|ss|tt)>(.*?)<\/(?:b|i|spoiler|ss|tt)>/s', $message, $matches, PREG_OFFSET_CAPTURE);

        // we found some layout in the text
        if ($result) {
            foreach ($matches[0] as $index => $match) {
                $capture        = $match[0];
                $typeIndicator   = $matches[1][$index][0];
                $strWithoutType  = $matches[2][$index][0];

                switch ($typeIndicator) {
                    case 'b':
                        $type    = 'BOLD';
                        break;
                    case 'i':
                        $type    = 'ITALIC';
                        break;
                    case 'spoiler':
                        $type    = 'SPOILER';
                        break;
                    case 'ss':
                        $type    = 'STRIKETHROUGH';
                        break;
                    case 'tt':
                        $type    = 'MONOSPACE';
                        break;
                    default:
                        $type    = null;
                }

                if (empty($type)) {
                    continue;
                }

                $start          = mb_strpos($message, $capture);

                if ($start === false) {
                    continue;
                }

                $length     = mb_strlen($strWithoutType);

                $style[]    = "$start:$length:$type";

                // replace without layout
                $message    = str_replace($capture, $strWithoutType, $message);
            }
        }

        return [
            'textStyle' => $style,
            'message'   => $message
        ];
    }

    /**
     * Sets the rate limit expiry time
     *
     * @param   string|false      $epoch  epoch when the reate limit will be lifted or false to reset
     *
     */
    public function setRateLimit($epoch, $save = true)
    {
        $this->rateLimitString  = '';

        // Conver to seconds and check if in the past
        if (is_numeric($epoch)) {
            // Convert to seconds
            if ($epoch && strlen((string)$epoch) > 11) {
                $epoch = intval($epoch / 1000);
            }

            if (time() >= $epoch) {
                $epoch  = false;
            } else {
                $this->rateLimitString   = gmdate(TSJIPPY\DATEFORMAT . ' ' . TSJIPPY\TIMEFORMAT, $epoch);
            }
        } else {
            $epoch  = false;
        }

        $this->rateLimited      = $epoch;

        if ($save) {
            update_option('tsjippy-signal-rate-limit', $this->rateLimited);
        }
    }

    /**
     * Get uncached rate limited value
     */
    public function getRateLimited()
    {
        $rateLimited = get_option('tsjippy-signal-rate-limit');

        if ($rateLimited != $this->rateLimited) {
            $this->setRateLimit($rateLimited, false);
        }

        return $rateLimited == null ? false : $rateLimited;
    }

    /**
     * Send Captcha instructions by e-mail
     *
     * @param   string  $token      The token from the error
     *
     *
     */
    function sendRateLimitInstructions($token)
    {
        $adminUrl       = admin_url("admin.php?page=tsjippy-signal&main-tab=functions&challenge=$token");

        $to             = get_option('admin_email');
        $subject        = "Signal captcha required";
        $message        = "Hi admin,<br><br>";
        $message        .= "Signal messages are currently not been send from the website as you need to submit a captcha or wait till {$this->rateLimitString}.<br>";
        $message        .= "Submit the challenge and captcha <a href='$adminUrl'>here</a>";

        wp_mail($to, $subject, $message);
    }

    /**
     * Checks if signal-cli is installed and up to date
     */
    public function checkPrerequisites()
    {
        $this->error   = '';

        /**
         * Find the current JAVA Version
         */
        $curVersion     = shell_exec('javac -version');

        if (!empty($curVersion)) {
            $curVersion = str_replace('javac ', '', $curVersion);
        }

        if (empty($curVersion) && $this->os == 'Windows') {
            // Try to find the path for java in case javac is not in the PATH variable
            $basePath   = is_dir('C:/Program Files/Java') ? 'C:/Program Files/Java' : 'C:/Program Files (x86)/Java';
            $subs       = scandir($basePath);
            rsort($subs);

            // Find latest version of java and set the path to that
            foreach ($subs as $sub) {
                if (str_contains($sub, 'jdk') || str_contains($sub, 'openjdk')) {
                    $javaPath   = "$basePath/$sub/bin";
                    putenv("PATH=$javaPath");
                    $curVersion = trim(str_replace('javac ', '', shell_exec('javac -version')));

                    if (!empty($curVersion)) {
                        break;
                    }
                }
            }

            if (empty($curVersion)) {
                echo "javac did not return any result<br>";
                $this->error    .= "Please install Java JDK and make sure javac is in your PATH variable<br>";
            }
        }

        if (version_compare('25.0.0.0', $curVersion) === 1) {
            $this->error    .= "Please install Java JDK, at least version 25. Current version is $curVersion";
            $this->valid    = false;
        }

        /**
         * Find the current Signal-cli Version
         */
        $github         = new TSJIPPY\GITHUB\Github();
        $release        = $github->getLatestRelease('AsamK', 'signal-cli', true);

        if (is_wp_error($release)) {
            return false;
        }

        if (!file_exists($this->path)) {
            $this->installSignal($release);

            if (!file_exists($this->path)) {
                $this->error    .= "Please install signal-cli<br>";
                $this->valid    = false;
            }
        } else {
            $command         = '"' . $this->path . '" --version';
            $curVersion     = str_replace('signal-cli ', 'v', trim(shell_exec($command)));

            if (empty($curVersion)) {
                var_dump(shell_exec("$command 2>&1"));
                echo esc_html($command) . " did not return any result<br>";
            } else {
                echo "Current Signal version is <b>" . esc_attr($curVersion) . "</b><br>";
            }

            /**
             * Check if an update is available and at least 5 days old
             */
            $publishDate    = strtotime($release['published_at']);

            if ($release['tag_name'] != 'v0.14.4.1' && $curVersion != $release['tag_name'] && $publishDate + (5 * DAY_IN_SECONDS) < time()) {
?>
                <strong>
                    Updating Signal to version "<?php echo esc_attr($release['tag_name']); ?>
                </strong>
                <br>
            <?php

                // Disabled for now
                #$this->installSignal($release);
            }
        }

        if (!empty($this->error)) {
            return false;
        }

        return $curVersion;
    }

    /**
     * Installs the Signal CLI
     *
     * @param   array   $release  The release information
     *
     * @return  bool              Whether the installation was successful
     */
    private function installSignal($release)
    {
        $version    = str_replace('v', '', $release['tag_name']);

        if ($this->os == 'Linux') {
            $pidFile    = __DIR__ . '/installing.signal';
            if (file_exists($pidFile)) {
                echo esc_attr($pidFile) . " exists, another installation might by running already<br>";
                return;
            }
            file_put_contents($pidFile, 'running');
        }

        try {
            echo "Downloading Signal version " . esc_attr($version) . "<br>";
            $url    = "https://github.com/AsamK/signal-cli/releases/download/v$version/signal-cli-$version-$this->os.tar.gz";

            if (!empty($release['assets']) && is_array($release['assets'])) {
                foreach ($release['assets'] as $asset) {
                    if (
                        (
                            (
                                $this->os == 'Linux' &&
                                str_contains($asset['browser_download_url'], $this->os)
                            ) ||
                            !str_contains($asset['browser_download_url'], 'Linux')
                        ) &&
                        isset($asset['size']) && $asset['size'] > 10000000
                    ) {
                        $url    = $asset['browser_download_url'];
                        break;
                    }
                }
            }

            $tempPath   = $this->downloadSignal($url);

            echo "URL: " . esc_url($url) . "<br>";
            echo "Destination: " . esc_attr($tempPath) . "<br>";
            echo "Download finished<br>";

            // Unzip the gz
            $fileName = str_replace('.gz', '', $tempPath);

            if (!file_exists($fileName)) {

                echo "Unzipping .gz archive<br>";

                // Raising this value may increase performance
                $bufferSize = 4096; // read 4kb at a time

                // Open our files (in binary mode)
                $file       = gzopen($tempPath, 'rb');
                $outFile    = fopen($fileName, 'wb');

                // Keep repeating until the end of the input file
                while (!gzeof($file)) {
                    // Read buffer-size bytes
                    // Both fwrite and gzread and binary-safe
                    fwrite($outFile, gzread($file, $bufferSize));
                }

                // Files are done, close files
                fclose($outFile);
                gzclose($file);
            }

            // unzip the tar
            $folder = str_replace('.tar.gz', '', $tempPath);

            // Unzip if needed
            if (
                !file_exists($folder . '/' . basename($folder) . '/bin/signal-cli') && // it does not include this file (on Windows)
                !file_exists($folder . '/signal-cli')                              // / it does not include this file (on Linux)
            ) {
                if (file_exists($folder)) {
                    if ($this->os == 'Windows') {
                        // remove the folder
                        exec("rmdir \"$folder\" /s /q");
                    } else {
                        exec("rm -R $folder");
                    }
                }

                echo "Unzipping .tar archive to " . esc_attr($folder) . "<br>";

                $phar = new \PharData($fileName);
                $phar->extractTo($folder); // extract all files
            }
        } catch (\Exception $e) {
            ?>
            <div class='error'>
                <?php wp_kses_post(wp_kses_post($e->getMessage())); ?>
            </div>
        <?php

            // handle errors
            $this->error    = 'Installation error';
            return $this->error;
        } finally {
            if ($this->os == 'Linux') {
                /** @disregard  */
                wp_delete_file($pidFile);
            }
        }

        // remove the old folder
        if (file_exists($this->programPath)) {
            echo "Removing old version<br>";

            if ($this->os == 'Windows') {
                $path   = wp_normalize_path($this->programPath);
                // kill the process
                exec("taskkill /IM signal-cli /F");

                // remove the folder
                exec("rmdir \"$path\" /s /q");
            } else {
                // stop the deamon
                #exec("kill $(ps -ef | grep -v grep | grep -P 'signal-cli.*daemon'| awk '{print $2}')");

                echo "Removing from " . esc_attr($this->programPath) . "<br>";

                exec("rm -rfd $this->programPath");

                wp_mkdir_p($this->programPath);
                TSJIPPY\printArray("Created $this->programPath");
            }
        }

        // move the folder
        $path   = "$folder/signal-cli";
        $result = false;
        if (file_exists("$folder/signal-cli-$version")) {
            $path   = "$folder/signal-cli-$version";

            if (!is_dir(dirname($this->programPath))) {
                mkdir(dirname($this->programPath), 0777, true);
            }

            if ($this->os == 'Windows') {
                $result = $this->copyfolder($path . '/', $this->programPath);
            } else {
                $wpFileSystem   = TSJIPPY\loadWpFileSystem();
                $result = $wpFileSystem->move($path, $this->programPath);
            }
        } elseif (file_exists("$folder/signal-cli")) {
            $wpFileSystem   = TSJIPPY\loadWpFileSystem();
            $result = $wpFileSystem->move("$folder/signal-cli", "$this->path");
        } else {
            echo esc_attr($path) . " does not exist<br>";
            TSJIPPY\printArray("$folder/signal-cli not found please check", true);
        }

        if ($result) {
        ?>
            <div class='success'>
                Succesfully installed Signal version <?php echo esc_attr($version); ?>!
            </div>
        <?php
        } else {
        ?>
            <div class='error'>
                Failed!<br>
                Could not move <?php echo esc_attr($path); ?> to <?php echo esc_attr($this->programPath); ?>/signal-cli.<br>
                Check the <?php echo esc_attr($folder); ?> folder.
            </div>
<?php
        }
    }

    /**
     * Copies a folder and its contents to another location
     *
     * @param   string  $from   The source folder
     * @param   string  $to     The destination folder
     * @param   string  $ext    The file extension to copy, default is all files (*)
     *
     * @return  bool            Whether the copy was successful
     */
    private function copyfolder($from, $to, $ext = "*")
    {
        // (A1) SOURCE FOLDER CHECK
        if (!is_dir($from)) {
            TSJIPPY\printArray("$from does not exist");
        }

        // (A2) CREATE DESTINATION FOLDER
        if (!is_dir($to)) {
            if (!mkdir($to)) {
                TSJIPPY\printArray("Failed to create $to");
            };
        }

        // (A3) GET ALL FILES + FOLDERS IN SOURCE
        $all = glob("$from$ext", GLOB_MARK);
        print_r($all);

        // (A4) COPY FILES + RECURSIVE INTERNAL FOLDERS
        if (count($all) > 0) {
            foreach ($all as $a) {
                $ff = basename($a); // CURRENT FILE/FOLDER

                if (is_dir($a)) {
                    $this->copyfolder("$a", "$to/$ff/");
                } else {
                    if (!copy($a, "$to$ff")) {
                        TSJIPPY\printArray("Error copying $a to $to$ff");
                    }
                }
            }
        }

        return true;
    }

    /**
     * Downloads the Signal CLI
     *
     * @param   string  $url  The URL of the Signal CLI to download
     *
     * @return  string        The path to the downloaded file
     */
    private function downloadSignal($url)
    {
        $filename   = basename($url);
        $tempPath   = sys_get_temp_dir() . '/' . $filename;

        $tempPath = wp_normalize_path($tempPath);

        if (file_exists($tempPath)) {
            return $tempPath;
        }

        $client     = new GuzzleHttp\Client();
        try {
            $client->request(
                'GET',
                $url,
                array('sink' => $tempPath)
            );

            if (file_exists($tempPath)) {
                return $tempPath;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            wp_delete_file($tempPath);

            if ($e->getResponse()->getStatusCode() == 404) {
                $newUrl = str_replace("-" . $this->os, '', $url);

                if ($newUrl != $url) {
                    return $this->downloadSignal($newUrl);
                }
            }

            if ($e->getResponse()->getReasonPhrase() == 'Gone') {
                return "The link has expired, please get a new one";
            }
            return $e->getResponse()->getReasonPhrase();
        }

        echo "Downloading " . esc_url($url) . " to " . esc_attr($tempPath) . " failed!";
    }

    /**
     * Checks if the signal-cli daemon is running
     */
    protected function daemonIsRunning()
    {
        // check if running
        $command = new Command([
            'command' => "ps -ef | grep -v grep | grep -P 'signal-cli.*daemon'"
        ]);

        $command->execute();

        $result             = $command->getOutput();
        if (empty($result)) {
            $this->daemon   = false;
        } else {
            $this->daemon   =  true;

            // Running daemon but not for this website
            if (!str_contains($result, $this->basePath) && !str_contains($result, 'do find -name signal-daemon.php')) {
                $this->error    = 'The daemon is started but for another website in this user account.<br>';
                $this->error   .= "You can send messages just fine, but not receive any.<br>";
                $this->error   .= "To enable receiving messages add this to your crontab (crontab -e): <br>";
                $this->error   .= '<code>@reboot export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/' . $this->osUserId . '/bus;' . $this->path . ' -o json daemon | while read -r line; do find -name signal-daemon.php 2>/dev/null -exec php "{}" "$line" \; ; done; &</code><br>';
                $this->error   .= "Then reboot your server";
            }
        }
    }

    /**
     * Starts the signal-cli daemon to receive messages
     */
    public function startDaemon()
    {
        return;
        if ($this->os == 'Windows') {
            return;
        }

        if (!$this->daemon) {
            // Messaging deamon to receive messages, needs to be running in the background to receive messages, also needs to be started with the same user that starts the daemon to prevent DB
            $display    = 'export DISPLAY=:0.0;';
            $dbus       = "export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/$this->osUserId/bus;";
            $cli        = "$this->path -o json --trust-new-identities=always daemon";
            $read       = 'while read -r line; do php ' . __DIR__ . '/../../daemon/signal-daemon.php "$line"; done;';

            $command = new Command([
                'command' => "bash -c '$display $dbus $cli | $read' &"
            ]);

            $command->execute();

            // queue processing
            $cmd       = 'do php ' . __DIR__ . '/../../daemon/signal-command-queue-daemon.php';

            $command = new Command([
                'command' => "bash -c '$cmd' &"
            ]);

            $command->execute();
        }

        $this->daemon   = true;
    }

    /**
     * Adds a message to the queue to be send to prevent rate limit issues
     *
     * @param   string  $method    The method to execute, e.g. send or receive
     * @param   array   $params    The parameters for the method
     * @param   int     $priority  The priority of the message, lower numbers are executed first, default 10
     *
     * @return  int                 The db row id
     */
    public function addToQueue($method, $params = [], $priority = 10, $waiting = false)
    {
        return TSJIPPY\insertInDb(
            $this->queueTableName,
            array(
                'time_added'   => time(),
                'method'       => $method,
                'params'       => maybe_serialize($params),
                'priority'     => $priority,
                'waiting'      => $waiting
            ),
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%d',
            ],
            'signal'
        );
    }

    /**
     * Retrieves the message queue
     *
     * @return  object   The oldest command in the queue, or a specific command if id is provided
     */
    public function getQueue($id = -1)
    {
        if ($id == -1) {
            $result     = TSJIPPY\getFromDb(
                "command-oldest-result",
                'signal',
                "SELECT * FROM %i WHERE result IS NULL ORDER BY priority ASC, time_added ASC LIMIT 1;",
                $this->queueTableName
            );
        } else {
            $result     = TSJIPPY\getFromDb(
                "command-$id-result",
                'signal',
                "SELECT * FROM %i WHERE id = %d LIMIT 1",
                $this->queueTableName,
                $id
            );
        }

        return $result;
    }

    /**
     * Check the queue count
     */
    private function getQueueSize()
    {
        // Get the queue count
        $result    =  TSJIPPY\getFromDb(
            'queue-count',
            'signal',
            "SELECT COUNT(*) FROM %i WHERE result IS NULL;",
            $this->queueTableName
        );

        return $result;
    }

    /**
     * Removes a message from the queue
     *
     * @param   int     $id The id of the message to remove
     *
     * @return  bool        Whether the message was removed successfully
     */

    public function removeFromQueue($id)
    {
        TSJIPPY\removeFromDb(
            '',
            [
                "DELETE FROM %i WHERE id = %d",
                $this->queueTableName,
                $id
            ],
            [],
            'signal'
        );
    }

    /**
     * Mark a command as not waiting
     * @param   int  $commandId     The commandId
     *
     * @return  bool                Whether the message was updated successfully
     */
    public function markNotWaiting($commandId)
    {
        // Update the queue
        TSJIPPY\updateDbValue(
            $this->queueTableName,
            [
                'waiting'   => false
            ],
            [
                'id'        => $commandId
            ],
            ['%d'],
            ['%d'],
            'signal'
        );
    }

    /**
     * Updates a message in the queue with the result of the command
     * @param   object  $command    The command to update, should be the result of getQueue
     * @param   mixed  $result     The result of the command
     *
     * @return  bool                Whether the message was updated successfully
     */
    public function updateQueueResult($command, $result)
    {
        if (is_wp_error($result)) {
            TSJIPPY\printArray($result, false, false, true);
            $result = $result->get_error_message();
        }

        // the result should be a string not an object
        if (is_object($result)) {
            TSJIPPY\printArray($command);
            TSJIPPY\printArray($result);
        } else {

            $data   = [
                'retries'   => $command->retries + 1
            ];

            if (!empty($result)) {
                $data['result']    = $result;
            }

            // Update the queue
            $result = TSJIPPY\updateDbValue(
                $this->queueTableName,
                $data,
                [
                    'id'        => $command->id
                ],
                [],
                ['%d'],
                'signal'
            );
        }
    }

    /**
     * Processes the message queue, sending messages and executing commands
     */
    public function processQueue()
    {
        if (wp_get_environment_type() === 'local') {
            //return; // no point in doing this
        }

        $this->processingQueue     = true;

        // Mark the start of this option
        $startTime = time();
        update_option('tsjippy-signal-processing-queue', $startTime);

        $queueSize  = 0;
        $sleepTime  = 30;

        // Loop until a new cronjob has started
        while (true) {
            /**
             * Check if we if should terminate
             */
            $dbStartTime    = get_option('tsjippy-signal-processing-queue');

            if ($dbStartTime != $startTime) {
                break;
            }

            /**
             * Check Rate limit
             */
            if ($this->getRateLimited()) {
                // We are past the rate limit, reset it
                if (time() > $this->rateLimited) {
                    $this->setRateLimit(false);
                } else {
                    // no need to run if there is a rate limit
                    sleep(60);
                    continue;
                }
            }

            // Get the oldest command
            $command    = $this->getQueue();

            if(is_array($command)){
                $command    = $command[0];
            }

            // Nothing in the queue
            if (empty($command)) {
                sleep(1);
                continue;
            }


            if (!is_array($command->params)) {
                $this->removeFromQueue($command->id);
                continue;
            }

            /**
             * Check the remaining items in the queue
             */
            // No need to query the db again if we already know that there are multiple commands awaiting execution
            if ($queueSize > 3) {
                $queueSize--;
            } else {
                $queueSize  = $this->getQueueSize();
                if ($queueSize < 3) {
                    $sleepTime  = 2;
                }
            }

            if (method_exists($this, $command->method)) {
                if ($command->method == 'send') {
                    if (isset($command->params['groupId'])) {
                        $command->params['recipient']    = $command->params['groupId'];

                        unset($command->params['groupId']);
                    }

                    // Originally sent more than 1 hour ago
                    if ($command->time_added < time() - HOUR_IN_SECONDS) {

                        $appendix       = "\n\nThis message was orginally sent " . human_time_diff($command->time_added) . " ago, sorry for the delay";

                        $start          = mb_strlen($command->params['message']);

                        $command->params['message'] .= $appendix;

                        $length         = mb_strlen($appendix);

                        $command->params['textStyle'][]    = "$start:$length:'ITALIC'";
                    }
                }
                $result = call_user_func_array(array($this, $command->method), $command->params);

                $this->addToCommandLog($command->method, $command->params);
            } else {
                TSJIPPY\printArray($command);
            }

            // Mark as timed out if still no result after 10 times
            if ($command->retries >= 9 && empty($result)) {
                TSJIPPY\printArray("Command $command->method has been retried 10 times, skipping", true);
                $result = 'timed out';
            }

            // We got a result
            if (!empty($result)) {
                // Add to the message log
                if ($command->method == 'send' && !empty($result->timestamp)) {
                    $this->addToMessageLog($command->params['recipient'], $command->params['message'], $result->timestamp);
                }

                // Delete a message
                elseif ($command->method == 'remoteDelete' && isset($result->results[0]->type) && $result->results[0]->type == 'SUCCESS') {
                    $this->markAsDeleted($command->param['targetTimestamp']);
                }

                // Remove from the queue as none is waiting for the result or to much time has passed since adding it
                if (!$command->waiting || time() - $command->time_added > 25) {
                    $this->removeFromQueue($command->id);

                    sleep($sleepTime);
                    continue;
                }
            }

            $this->updateQueueResult($command, $result);

            sleep($sleepTime);
        }

        $this->processingQueue     = false;

        //TSJIPPY\printArray('Finished processing queue, as another job has taken over');
    }
}
