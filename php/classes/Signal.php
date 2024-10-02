<?php

namespace SIM\SIGNAL;
use SIM;
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


class Signal{
    public $valid;
    public $os;
    public $basePath;
    public $programPath;
    public $phoneNumber;
    public $path;
    public $daemon;
    public $osUserId;
    public $command;
    public $error;
    public $attachmentsPath;
    public $tableName;
    public $receivedTableName;
    public $totalMessages;

    public function __construct(){
        global $wpdb;

        require_once( MODULE_PATH  . 'lib/vendor/autoload.php');

        $this->valid            = true;
        $this->tableName        = $wpdb->prefix.'sim_signal_messages';

        $this->receivedTableName= $wpdb->prefix.'sim_received_signal_messages';

        $this->os               = 'macOS';
        $this->basePath = WP_CONTENT_DIR.'/signal-cli';
        if (!is_dir($this->basePath )) {
            mkdir($this->basePath , 0777, true);
        }

        $this->attachmentsPath  = $this->basePath.'/attachments';
        if (!is_dir($this->attachmentsPath )) {
            mkdir($this->attachmentsPath , 0777, true);
        }

        // .htaccess to prevent access
        if(!file_exists($this->basePath.'/.htaccess')){
            file_put_contents($this->basePath.'/.htaccess', 'deny from all');
        }
        if(!file_exists($this->basePath.'/index.php')){
            file_put_contents($this->basePath.'/index.php', '<?php');
        }
        if(!file_exists($this->attachmentsPath.'/.htaccess')){
            file_put_contents($this->attachmentsPath.'/.htaccess', 'allow from all');
        }

        if(str_contains(php_uname(), 'Windows')){
            $this->os               = 'Windows';
            $this->basePath         = str_replace('\\', '/', $this->basePath);
        }elseif(str_contains(php_uname(), 'Linux')){
            $this->os               = 'Linux';
        }
        
        $this->programPath      = $this->basePath.'/program/';
        if (!is_dir($this->programPath )) {
            mkdir($this->programPath , 0777, true);
            SIM\printArray("Created $this->programPath");
        }

        // check permissions
        $path   = $this->programPath.'/signal-cli';
        if(!is_executable($path)){
            chmod($path, 0555);
        }

        $this->phoneNumber      = '';
        if(file_exists($this->basePath.'/phone.signal')){
            $this->phoneNumber      = trim(file_get_contents($this->basePath.'/phone.signal'));
        }

        $this->path             = $this->programPath.'bin/signal-cli';

        if(!file_exists($this->path) && file_exists($this->programPath.'signal-cli')){
            $this->path = $this->programPath.'signal-cli';
        }

        $this->daemon           = false;

        $this->osUserId         = "";
    }

    /**
     * Create the sent messages table if it does not exist
     */
    public function createDbTable(){
		global $wpdb;

		if ( !function_exists( 'maybe_create_table' ) ) {
			require_once ABSPATH . '/wp-admin/install-helper.php';
		}
		
		//only create db if it does not exist
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->tableName} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timesend bigint(20) NOT NULL,
            recipient longtext NOT NULL,
            message longtext NOT NULL,
            status text NOT NULL,
            PRIMARY KEY  (id)
		) $charsetCollate;";

		maybe_create_table($this->tableName, $sql );

        $sql = "CREATE TABLE {$this->receivedTableName} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timesend bigint(20) NOT NULL,
            sender longtext NOT NULL,
            message longtext NOT NULL,
            chat longtext,
            attachments longtext,
            status text NOT NULL,
            PRIMARY KEY  (id)
		) $charsetCollate;";

		maybe_create_table($this->receivedTableName, $sql );
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
    protected function addToMessageLog($recipient, $message, $timestamp){
        if(empty($recipient) || empty($message)){
            return;
        }
        
        global $wpdb;

        $wpdb->insert(
            $this->tableName,
            array(
                'timesend'      => $timestamp,
                'recipient'     => $recipient,
                'message'		=> $message,
            )
        );

        return $wpdb->insert_id;
    }

    /**
     * Adds a received message to the log
     *
     * @param   string  $sender     The sender phonenumber
     * @param   string  $message    The message sent
     * @param   int     $time       The time the message was sent
     * @param   string  $chat       The groupId is sent in a group chat, defaults to $sender for private chats
     */
    public function addToReceivedMessageLog($sender, $message, $time, $chat='', $attachments=''){
        if(empty($sender) || empty($message)){
            return;
        }

        if(empty($chat) ){
            $chat   = $sender;
        }
        
        global $wpdb;

        $wpdb->insert(
            $this->receivedTableName,
            array(
                'timesend'      => $time,
                'sender'        => $sender,
                'message'	    => $message,
                'chat'          => $chat,
                'attachments'   => maybe_serialize($attachments)
            )
        );

        return $wpdb->insert_id;
    }

    /**
     * Retrieves the messages from the log
     *
     * @param   int     $amount     The amount of rows to get per page, default 100
     * @param   int     $page       Which page to get, default 1
     * @param   int     $minTime    Time after which the message has been send, default empty
     * @param   int     $maxTime    Time before which the message has been send, default empty
     */
    public function getMessageLog($amount=100, $page=1, $minTime='', $maxTime=''){
        global $wpdb;

        $startIndex = 0;

        if($page > 1){
            $startIndex         = ($page - 1) * $amount;
        }

        $totalQuery = "SELECT COUNT(id) as total FROM $this->tableName";
        $query      = "SELECT * FROM $this->tableName";

        if(!empty($minTime)){
            $totalQuery .= " WHERE timesend > {$minTime}000";
            $query      .= " WHERE timesend > {$minTime}000";
        }

        if(!empty($maxTime)){
            $combinator = 'AND';
            if(empty($minTime)){
                $combinator     = 'WHERE';
            }

            $totalQuery .= " $combinator timesend < {$maxTime}000";
            $query      .= " $combinator timesend < {$maxTime}000";
        }

        $query      .= " ORDER BY `timesend` DESC LIMIT $startIndex,$amount;";

        $this->totalMessages    = $wpdb->get_var($totalQuery);

        if($this->totalMessages < $startIndex){
            return [];
        }
        
        return $wpdb->get_results( $query );
    }

    /**
     * Retrieves the messages from the log
     *
     * @param   int     $amount     The amount of rows to get per page, default 100
     * @param   int     $page       Which page to get, default 1
     * @param   int     $minTime    Time after which the message has been send, default empty
     * @param   int     $maxTime    Time before which the message has been send, default empty
     */
    public function getReceivedMessageLog($amount=100, $page=1, $minTime='', $maxTime=''){
        global $wpdb;

        $startIndex = 0;

        if($page > 1){
            $startIndex         = ($page - 1) * $amount;
        }

        $totalQuery = "SELECT COUNT(id) as total FROM $this->receivedTableName";
        $query      = "SELECT * FROM $this->receivedTableName";

        if(!empty($minTime)){
            $totalQuery .= " WHERE timesend > {$minTime}000";
            $query      .= " WHERE timesend > {$minTime}000";
        }

        if(!empty($maxTime)){
            $combinator = 'AND';
            if(empty($minTime)){
                $combinator     = 'WHERE';
            }

            $totalQuery .= " $combinator timesend < {$maxTime}000";
            $query      .= " $combinator timesend < {$maxTime}000";
        }

        $query      .= "  ORDER BY `chat` ASC, `timesend` DESC LIMIT $startIndex,$amount;";

        $this->totalMessages    = $wpdb->get_var($totalQuery);

        if($this->totalMessages < $startIndex){
            return [];
        }
        
        return $wpdb->get_results( $query );
    }

    /**
     * Gets the latest messages from the log for a specific user
     *
     * @param   string  $phoneNumber    The phonenumber or user id
     *
     * @return  array                   The messages
     */
    public function getSendMessagesByUser($phoneNumber){
        if(get_userdata($phoneNumber)){
            $phoneNumber    = get_user_meta($phoneNumber, 'signalnumber', true);

            if(!$phoneNumber){
                return;
            }
        }

        global $wpdb;

        $query      = "SELECT * FROM $this->tableName WHERE `recipient` = '$phoneNumber' ORDER BY `timesend` DESC LIMIT 5; ";

        return $wpdb->get_results( $query );
    }

    /**
     * Gets the latest messages from the log for a specific user
     *
     * @param   int  $timestamp         The timestamp
     *
     * @return  string                   The message
     */
    public function getSendMessageByTimestamp($timestamp){
        global $wpdb;

        $query      = "SELECT message FROM $this->tableName WHERE `timesend` = '$timestamp'";

        return $wpdb->get_var( $query );
    }

    /**
     * Deletes messages from the log
     *
     * @param   string     $maxDate     The date after which messages should be kept Should be in yyyy-mm-dd format
     *
     * @return  string                  The message
     */
    public function clearMessageLog($maxDate){
        global $wpdb;

        $timeSend   = strtotime(get_gmt_from_date($maxDate, 'Y-m-d'));

        // remove sent messages
        $query      = "DELETE FROM $this->tableName WHERE `timesend` < {$timeSend}000";

        $result1    = $wpdb->query( $query );

        // remove attachment files
        $query      = "SELECT * FROM $this->receivedTableName WHERE `timesend` < {$timeSend}000 AND `attachments` is NOT NULL; ";
        foreach($wpdb->get_results($query) as $result){
            $attachments    = unserialize($result->attachments);

            foreach($attachments as $attachment){
                if(file_exists($attachment)){
                    unlink($attachment);
                }
            }
        }

        // remove received messages
        $query      = "DELETE FROM $this->receivedTableName WHERE `timesend` < {$timeSend}000";

        $result2    = $wpdb->query( $query );

        return $result1 && $result2;
    }

    /**
     * Marks a specific message as deleted in the log
     *
     * @param   int     $timesend     The date after which messages should be kept Should be in yyyy-mm-dd format
     *
     * @return  string                  The message
     */
    public function markAsDeleted($timeStamp){
        global $wpdb;

        $query      = "UPDATE $this->tableName SET `status` = 'deleted' WHERE timesend = $timeStamp";

        return $wpdb->query( $query );
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
     */
    protected function parseMessageLayout($message){
        $style		= [];

        // parse layout
		$result	= preg_match_all('/<(b|i|spoiler|ss|tt)>(.*?)<\/(?:b|i|spoiler|ss|tt)>/', $message, $matches, PREG_OFFSET_CAPTURE);

		// we found some layout in the text
		if($result){
			$shortened	= 0;
			foreach($matches[0] as $index=>$match){
				$capture		= $match[0];
				$baseStart		= $match[1];
				$typeIndicator	= $matches[1][$index][0];
				$strWithoutType	= $matches[2][$index][0];

				$start	= $baseStart - $shortened; // start postion without previous removed chars
				if($start < 0){
					$start = 0;
				}

				$length	= strlen($strWithoutType);

				switch($typeIndicator){
					case 'b':
						$type	= 'BOLD';
						break;
					case 'i':
						$type	= 'ITALIC';
						break;
					case 'spoiler':
						$type	= 'SPOILER';
						break;
					case 'ss':
						$type	= 'STRIKETHROUGH';
						break;
					case 'tt':
						$type	= 'MONOSPACE';
						break;
					default:
						$type	= null;
				}

				if(empty($type)){
					continue;
				}
				
				$style[]	= "$start:$length:$type";
				
				// replace without layout
				$message	= substr_replace($message, $strWithoutType, $baseStart - $shortened, strlen($capture));

				$shortened	= $shortened + 5 + (2 * strlen($typeIndicator)); // we remove at least 5 characters (<> and </> plus 2 times the indicater chars)
			}
		}

        return [
            'style'     => $style,
            'message'   => $message
        ];
    }

    /**
     * Send Captcha instructions by e-mail
     *
     * @param   string  $error  The error returned from a signal actions
     */
    function sendCaptchaInstructions($error){
        $username       = [];
        exec("bash -c 'whoami'", $username);
        $instructions   = $error;
        $instructions   = str_replace('signal-cli', "$this->path --config /home/{$username[0]}/.local/share/signal-cli" , $instructions);
        $adminUrl       = admin_url("admin.php?page=sim_signal&tab=functions&challenge=");

        $to             = get_option('admin_email');
        $subject        = "Signal captcha required";
        $message        = "Hi admin,<br><br>";
        $message        .= "Signal messages are currently not been send from the website as you need to submit a captcha.<br>";
        $message        .= "Use the following instructions to submit the captacha:<br><br>";
        $message        .= "<code>$instructions</code><br>";
        $message        .= "Submit the challenge and captcha <a href='$adminUrl'>here</a>";

        wp_mail($to, $subject, $message);
    }

    public function checkPrerequisites(){
        $this->error   = '';

        $curVersion = str_replace('javac ', '', shell_exec('javac -version'));
        if(version_compare('17.0.0.0', $curVersion) > 0){
            $this->error    .= "Please install Java JDK, at least version 17<br>install dbus-x11";
            $this->valid    = false;
        }

        $github         = new SIM\GITHUB\Github();
        $release        = $github->getLatestRelease('AsamK', 'signal-cli');

        if(is_wp_error($release)){
            return false;
        }

        $curVersion     = str_replace('signal-cli ', 'v', trim(shell_exec($this->path.' --version')));

        if(empty($curVersion)){
            SIM\printArray($this->path.' --version did not return any result', true);
            echo $this->path.' --version did not return any result';
        }

        #echo "Current version is '$curVersion'<br>";

        if(!file_exists($this->path) || empty($curVersion)){
            $this->installSignal($release);

            if(!file_exists($this->path)){
                $this->error    .= "Please install signal-cli<br>";
                $this->valid    = false;
            }
        }elseif($curVersion  != $release['tag_name']){
            echo "<strong>Updating Signal to version ".$release['tag_name']."</strong> <br>";

            $this->installSignal($release);
        }

        if(empty($this->error)){
            return true;
        }
    }

    private function installSignal($release){
        $version    = str_replace('v', '', $release['tag_name']);

        $pidFile    = __DIR__.'/installing.signal';
        if(file_exists($pidFile)){
            echo "$pidFile exists, another installation might by running already<br>";
            return;
        }
        file_put_contents($pidFile, 'running');

        try {
            echo "Downloading Signal version $version<br>";
            $url    = "https://github.com/AsamK/signal-cli/releases/download/v$version/signal-cli-$version-$this->os.tar.gz";

            if(!empty($release['assets']) && is_array($release['assets'])){
                foreach($release['assets'] as $asset){
                    if(str_contains($asset['browser_download_url'], $this->os)){
                        $url    = $asset['browser_download_url'];
                    }
                }
            }

            $tempPath   = $this->downloadSignal($url);

            echo "URL: $url<br>";
            echo "Destination: $tempPath<br>";
            echo "Download finished<br>";

            // Unzip the gz
            $fileName = str_replace('.gz', '', $tempPath);

            if(!file_exists($fileName)){

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

            if(file_exists($folder)){
                if($this->os == 'Windows'){
                    // remove the folder
                    exec("rmdir $folder /s /q");
                }else{
                    exec("rm -R $folder");
                }
            }

            echo "Unzipping .tar archive to $folder<br>";

            $phar = new \PharData($fileName);
            $phar->extractTo($folder); // extract all files
        } catch (\Exception $e) {
            echo "<div class='error'>".$e->getMessage().'</div>';

            // handle errors
            $this->error    = 'Installation error';
            return $this->error;
        } finally {
            unlink($pidFile);
        }

        // remove the old folder
        if(file_exists($this->programPath)){
            echo "Removing old version<br>";

            if($this->os == 'Windows'){
                $path   = str_replace('/', '\\', $this->programPath);
                // kill the process
                exec("taskkill /IM signal-cli /F");

                // remove the folder
                exec("rmdir $path /s /q");
            }else{
                // stop the deamon
                #exec("kill $(ps -ef | grep -v grep | grep -P 'signal-cli.*daemon'| awk '{print $2}')");

                echo "Removing from $this->programPath<br>";

                exec("rm -rfd $this->programPath");

                mkdir($this->programPath , 0777, true);
                SIM\printArray("Created $this->programPath");
            }
        }

        // move the folder
        $path   = "$folder/signal-cli";
        if(file_exists("$folder/signal-cli-$version")){
            $path   = "$folder/signal-cli-$version";
            #echo "$path exists<br>";
            $result = rename("$folder/signal-cli-$version", "$this->programPath/signal-cli" );
        }elseif(file_exists("$folder/signal-cli")){
            #echo "$path exists<br>";
            $result = rename("$folder/signal-cli", "$this->programPath/signal-cli" );
        }else{
            echo "$path does not exist<br>";
            SIM\printArray("$folder/signal-cli not found please check", true);
        }

        if($result){
            echo "<div class='success'>Succesfully installed Signal version $version!</div>";
        }else{
            echo "<div class='error'>Failed!<br>Could not move $path to $this->programPath/signal-cli.<br>Check the $folder folder.</div>";
        }

        unlink($pidFile);
    }

    private function downloadSignal($url){
        $filename   = basename($url);
        $tempPath       = sys_get_temp_dir().'/'.$filename;

        $tempPath = str_replace('\\', '/', $tempPath);

        if(file_exists($tempPath)){
            return $tempPath;
        }

        $client     = new GuzzleHttp\Client();
        try{
            $client->request(
                'GET',
                $url,
                array('sink' => $tempPath)
            );

            if(file_exists($tempPath)){
                return $tempPath;
            }
        }catch (\GuzzleHttp\Exception\ClientException $e) {
            unlink($tempPath);

            if($e->getResponse()->getReasonPhrase() == 'Gone'){
                return "The link has expired, please get a new one";
            }
            return $e->getResponse()->getReasonPhrase();
        }

        echo "Downloading $url to $tempPath failed!";
    }

    protected function daemonIsRunning(){
        // check if running
        $command = new Command([
            'command' => "ps -ef | grep -v grep | grep -P 'signal-cli.*daemon'"
        ]);

        $command->execute();

        $result = $command->getOutput();
        if(empty($result)){
            $this->daemon   = false;
        }else{
            $this->daemon   =  true;

            // Running daemon but not for this website
            if(!str_contains($result, $this->basePath) && !str_contains($result, 'do find -name signal-daemon.php')){
                $this->error    = 'The daemon is started but for another website in this user account.<br>';
                $this->error   .= "You can send messages just fine, but not receive any.<br>";
                $this->error   .= "To enable receiving messages add this to your crontab (crontab -e): <br>";
                $this->error   .= '<code>@reboot export DISPLAY=:0.0; export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/'.$this->osUserId.'/bus;'.$this->path.' -o json daemon | while read -r line; do find -name signal-daemon.php 2>/dev/null -exec php "{}" "$line" \; ; done; &</code><br>';
                $this->error   .= "Then reboot your server";
            }
        }
    }

    public function startDaemon(){
        if($this->os == 'Windows'){
            return;
        }

        if(!$this->daemon){
            $display    = 'export DISPLAY=:0.0;';
            $dbus       = "export DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/$this->osUserId/bus;";
            $cli        = "$this->path -o json --trust-new-identities=always daemon";
            $read       = 'while read -r line; do php '.__DIR__.'/../../daemon/signal-daemon.php "$line"; done;';
            
            $command = new Command([
                'command' => "bash -c '$display $dbus $cli | $read' &"
            ]);

            $command->execute();
        }
 
        $this->daemon   = true;
    }
}
