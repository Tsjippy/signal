<?php

namespace TSJIPPY\SIGNAL;
use TSJIPPY;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use mikehaertl\shellcommand\Command;
use stdClass;
use WP_Error;

/*
    To
    Test unix socket from command line:
    printf  '{"jsonrpc":"2.0","method":"getUserStatus","params":{"recipient":["+SOMENUMBER"]},"id":SOMEID}\n' | socat STDIN UNIX-CONNECT:SOCKETPATH -YOURPATH/public_html/wp-content/signal-cli/program/signal-cli/socket

    Test json RPC
    echo '{"jsonrpc":"2.0","method":"getUserStatus","params":{"recipient":["+SOMENUMBER"]},"id":"my special mark"}' | YOURPATH/public_html/wp-content/signal-cli/program/signal-cli/config --config YOURPATH/wp-content/signal-cli jsonRpc
*/


class SignalJsonRpc extends AbstractSignal{
    use SendEmailBySignal;
    public bool $getResult;
    public array $groups;
    public bool $invalidNumber;
    public int $lastRequestTime;
    public string $lastResponse;
    public int $listenTime;
    private string $postUrl;
    public bool $shouldCloseSocket;
    public mixed $socket;
    public string $socketPath;

    public function __construct($shouldCloseSocket=true, $getResult=true){
        parent::__construct();

        $this->getResult            = $getResult;
        $this->groups               = [];
        $this->invalidNumber        = false;
        $this->lastRequestTime      = time();
        $this->lastResponse         = '';
        $this->listenTime           = 60;

        $this->shouldCloseSocket    = $shouldCloseSocket;

        // Check daemon
        $this->daemonIsRunning();
        $this->startDaemon();

        $this->socketPath     = "$this->basePath/socket";

        clearstatcache();

        if (!is_writable($this->socketPath )) {
            //TSJIPPY\printArray( "Please chick the file permisions to $this->socketPath");
        }

        $this->socket   = stream_socket_client("unix:///$this->socketPath", $errno, $this->error);
        
        if($errno == 111){
            // remove the old socket file
            unlink($this->socketPath);

            // try again
            $this->socket   = stream_socket_client("unix:///$this->socketPath", $errno, $this->error);
        }

        if($errno == 2){
            echo "Could not start, is the signal-cli jsonrpc daemon running?";

        }elseif(!$this->socket){
            echo "Unable to create socket on $this->socketPath";

            //TSJIPPY\printArray("$errno: $this->error");
        }
    }

    /**
     * Performs the json RPC request
     *
     * @param   string      $method     The command to perform
     * @param   array       $params     The parameters for the command
     *
     * @return  mixed                   The result or false in case of trouble or nothing if $getResult is false
     */
    public function doRequest($method, $params=[]){
        $this->lastRequestTime = time();

        if($this->shouldCloseSocket){
            $this->socket   = stream_socket_client("unix:////$this->socketPath" , $errno, $this->error);
        }

        if(!$this->socket){
            //TSJIPPY\printArray("$errno: $this->error", true);
            return false;
        }

        // this commands needs a higher timeout than usual
        try{
            stream_set_timeout($this->socket, 1);
        }catch (\Error $e) {
            TSJIPPY\printArray($e);
        
            TSJIPPY\printArray($this->socket); 
        }

        $params["account"]  = $this->phoneNumber;

        $id     = time(); 

        $data   = [
            "jsonrpc"       => "2.0",
            "method"        => $method,
            "params"        => $params,
            "id"            => $id
        ];

        $json   = json_encode($data)."\n";

        fwrite($this->socket, $json);         

        flush();

        $response   = '';
        if($this->getResult){
            //stream_socket_recvfrom
            $response = $this->getRequestResponse($id);
        }

        if($this->shouldCloseSocket){
            fclose($this->socket);
        }

        if($this->getResult){
            $this->checkForErrors($response, $method, $params, $id);

            if(!empty($this->error)){
                return new \WP_Error('tsjippy-signal', $this->error);
            }

            if(!is_object($response) || empty($response->result)){
                if(!$this->invalidNumber){
                    TSJIPPY\printArray("Got faulty result");
                    TSJIPPY\printArray($response);
                }

                return false;
            }

            return $response->result;
        }
    }

    /**
     * Get the response to a request
     *
     * @param   int     $id         the request id should epoch of the request
     */
    public function getRequestResponse(int $id){
        if(empty($id)){
            TSJIPPY\printArray("Got an empty Id");
            return false;
        }

        // maximum of listentime seconds
        if(time() - $id > $this->listenTime){
            TSJIPPY\printArray('Cancelling as this has been running for '.time() - $id.' seconds');
            return false;
        }

        $json   = $this->getResultFromSocket($id);

        if(!$json){
            $json   = $this->getResultFromDb($id);
        }        

        return $json; 
    }

    /**
     * Get response from db
     *
     * @param   int     $id         the request id should epoch of the request
     *
     * @return  mixed               the result or empty if no result
     */
    public function getResultFromDb($id){
        $signalResults  = get_option('tsjippy-signal-results', []);

        // the id is not found in the db
        if(!!isset($signalResults[$id])){
            return false;
        }

        $result = $signalResults[$id];

        unset($signalResults[$id]);

        // remove the result from the array
        update_option('tsjippy-signal-results', $signalResults);

        return $result;
    }
    
    /**
     * Get response from socket
     *
     * @param   int     $id         the request id should epoch of the request
     *
     * @return  string              a json result string
     */
    public function getResultFromSocket($id){
        // maybe the daemon is not running, lets read from the socket ourselves
        $response   = '';
        $x          = 0;
        $base       = '{"jsonrpc":';

        while (!feof($this->socket)) {
            $response       .= fgets($this->socket, 4096);

            // response is a valid json response
            if(!empty(json_decode($response))){
                break;
            }

            // break if not broken yet and there is no more data
            $streamMetaData  = stream_get_meta_data($this->socket);

            if($streamMetaData['unread_bytes'] <= 0){
                $x++;

                if( $x > 10 ){
                    break;
                }
            }
        }
        flush();

        $streamMetaData         = stream_get_meta_data($this->socket);
        if ($streamMetaData['timed_out']) {
            TSJIPPY\printArray("Signal Socket Timed Out");

            return false;
        }

        $this->lastResponse     = trim($response);
        $this->invalidNumber    = false;

        if(empty($this->lastResponse)){
            return $this->getRequestResponse($id);
        }

        // somehow we have red multiple responses
        if(substr_count($this->lastResponse, $base) > 1){
            TSJIPPY\printArray($this->lastResponse);

            $results    = [];

            // loop over each jsonrpc response to find the ones with a result property
            foreach(explode($base, $this->lastResponse) as $jsonString){
                $decoded    = json_decode($base.$jsonString);

                if(!empty($decoded) && isset($decoded->result)){
                    // this is the one we are after
                    if($decoded->id == $id){
                        $this->lastResponse   = json_encode($decoded);
                    }else{
                        // not this one
                        $results[$decoded->id]  = $decoded;
                    }
                }
            }

            // add the results we are not interested in to the db
            if(!empty($results)){
                $signalResults              = get_option('tsjippy-signal-results', []);

                array_merge($signalResults, $results);

                update_option('tsjippy-signal-results', $signalResults);
            }
        }

        $json       = json_decode($this->lastResponse);

        if(empty($json)){
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    TSJIPPY\printArray(' - No errors'.$this->lastResponse, true);
                    break;
                case JSON_ERROR_DEPTH:
                    TSJIPPY\printArray(' - Maximum stack depth exceeded'.$this->lastResponse, true);
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    TSJIPPY\printArray(' - Underflow or the modes mismatch'.$this->lastResponse, true);
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    TSJIPPY\printArray(' - Unexpected control character found'.$this->lastResponse, true);
                    break;
                case JSON_ERROR_SYNTAX:
                    TSJIPPY\printArray(' - Syntax error, malformed JSON: '.$this->lastResponse, true);
                    break;
                case JSON_ERROR_UTF8:
                    TSJIPPY\printArray(' - Malformed UTF-8 characters, possibly incorrectly encoded'.$this->lastResponse, true);
                    break;
                default:
                    break;
            }
        }

        if(isset($json->error)){
            return $json;
        }elseif(!isset($json->result)){
            $json2   = $this->getRequestResponse($id);

            if(!isset($json->method) || $json->method != 'receive'){
                TSJIPPY\printArray("Trying again:");
                TSJIPPY\printArray($json);

                TSJIPPY\printArray($json2);
            }

            $json   = $json2;
        }elseif(!isset($json->id)){
            TSJIPPY\printArray("Response has no id");
            TSJIPPY\printArray($this->lastResponse);
            TSJIPPY\printArray($json);
            $json   = $this->getRequestResponse($id);
            TSJIPPY\printArray($json);
        }elseif($json->id != $id){
            TSJIPPY\printArray("Id '$json->id' is not the right id '$id', trying again");
            TSJIPPY\printArray($response);
            TSJIPPY\printArray($json);
            $json   = $this->getRequestResponse($id);
            TSJIPPY\printArray($json);
        }

        return $json;
    }

    /**
     * Check if the response contains an error and handle it
     *
     * @param   object      $json       The json response from the daemon
     * @param   string      $method     The command that was performed
     * @param   array       $params     The parameters for the command
     * @param   int         $id         The id of the request
     *
     * @return  void
     */
    protected function checkForErrors($json, $method, $params, $id){
        $this->error    = "";

        if(!$json){
            TSJIPPY\printArray("Getting response for command $method timed out");
            TSJIPPY\printArray($params);

            $signalResults              = get_option('tsjippy-signal-results', []);
            if(isset($signalResults[$id])){
                TSJIPPY\printArray($signalResults[$id]);
            }

            return false;
        }elseif(empty($json->error)){
            // Everything went well, nothing to do
            return;
        }

        $errorMessage  = $json->error->message;

        // unregistered number or user
        if(
            isset($json->error->data->response->results[0]->type) && 
            $json->error->data->response->results[0]->type == 'UNREGISTERED_FAILURE'
        ){
            $this->invalidNumber = true;

            // Remove the indicator that the invalid number is an valid number
            if(isset($json->error->data->response->results[0]->recipientAddress->number)){
                //TSJIPPY\printArray("Deleting Signal number: ".$json->error->data->response->results[0]->recipientAddress->number);
                //TSJIPPY\printArray($json);

                // delete the signal meta key
                $users = get_users(array(
                    'meta_key'     => 'signal_number',
                    'meta_value'   => $json->error->data->response->results[0]->recipientAddress->number,
                    'meta_compare' => '=',
                ));
        
                foreach($users as $user){
                    delete_user_meta($user->ID, 'signal_number');

                    TSJIPPY\printArray("Deleting Signal number {$json->error->data->response->results[0]->recipientAddress->number} for $user->display_name with id $user->ID as it is not valid anymore");
                }
            }else{
                TSJIPPY\printArray($json->error->data->response->results);
            }
        }

        // The connected number is not registered on the Signal Servers
        elseif(str_contains($errorMessage, 'Specified account does not exist')){
            $this->invalidNumber = true;

            TSJIPPY\printArray("The connected number is not registered on the Signal Servers, please register the number first");
        }

        // Captcha required
        elseif(str_contains($errorMessage, 'CAPTCHA proof required')){

            $this->sendCaptchaInstructions($errorMessage);
        }
        
        // Rate Limit
        elseif(
            str_contains($errorMessage, '429 Too Many Requests') || 
            (
                !empty($json->error->data->response->results[0]->type)  &&
                $json->error->data->response->results[0]->type == 'RATE_LIMIT_FAILURE'
            ) ||
            (
                !empty($json->error->code)  &&
                $json->error->code == -5
            ) 
        ){
            TSJIPPY\printArray($json);

            $matches            = [];
            $rateLimitedTill    = 0;
            preg_match('/\d{10,}/', $errorMessage, $matches);
            if(!empty($matches[0])){
                $rateLimitedTill    = intval($matches[0]);
            }elseif(isset($json->error->data->response->results[0]->retryAfterSeconds)){
                $rateLimitedTill    = time() + intval($json->error->data->response->results[0]->retryAfterSeconds);
            }

            // Only update if this is higher than the current value
            if($rateLimitedTill > $this->getRateLimited()){
                // Send rate limit instruction if this is the first time we encouter the issue
                if(!$this->rateLimited){
                    $this->sendRateLimitInstructions($json->error->data->response->results[0]->token);
                }

                $this->setRateLimit($rateLimitedTill);
                TSJIPPY\printArray("Rate limited till $this->rateLimitString");
            }
        }
        
        // Group ID
        elseif(str_contains($errorMessage, 'Invalid group id')){
            TSJIPPY\printArray($errorMessage);
        }
        
        // Timed Out
        elseif(str_contains($errorMessage, 'Did not receive a reply.')){
            TSJIPPY\printArray($errorMessage); 
        }
        
        // Unknown
        else{
            TSJIPPY\printArray("Got error '$errorMessage' while running the '$method' command.");
            TSJIPPY\printArray($params);   
            TSJIPPY\printArray($json);            
            TSJIPPY\printArray($errorMessage);
            TSJIPPY\printArray($this);
        }
        
        $this->error    = $errorMessage;
    }

    /**
     * Add a command to the queue of commannds
     * if the queue is empty, do the command straight away, otherwise add it to the queue and wait till it is processed and a result is added to the db
     * @param   string      $method     The command to perform
     * @param   array       $params     The parameters for the command
     * 
     * @return  mixed                   The result of the command if $waitForResult is true, otherwise true if the command is added to the queue successfully, false if there was an error
     */
    protected function addToCommandQueue($method, $params=[]){
        if($this->getRateLimited()){
            TSJIPPY\printArray("Rate limited till $this->rateLimitString");
        }

        // only add to queue if needed
        if( $this->processingQueue ){
            // do this straight away
            return $this->doRequest($method, $params);
        }

        $priority       = 10;
        $waitForResult  = false;
        if($this->getResult){
            $priority       = 1;
            $waitForResult  = true;
        }

        // Store command
        $commandId      = $this->addToQueue($method, $params, $priority, $waitForResult);

        // Do not wait for the result
        if(!$this->getResult){
            return $commandId;
        }

        if($this->rateLimited){
            return 'rate limited';
        }

        // Wait till the params are replaced by the result
        $result         = '';

        // Loop till we get an result or an timeout
        $i = 0;
        while(empty($result) && $i < 5){            
            $result = $this->getQueue($commandId)->result;

            sleep(5);
            TSJIPPY\printArray("Command result:");
            TSJIPPY\printArray($result);

            $i++;
        }

        // Make sure we do not wait for the result anymore but keep in queue
        if(in_array($method, ['send', 'remoteDelete', 'sendReceipt', 'sendReaction', 'updateProfile'])){
            if($waitForResult){
                global $wpdb;

                $wpdb->update(
                    $this->queueTableName,
                    [
                        'waiting' => false
                    ],
                    array(
                        'id'		=> $commandId
                    ),
                );
            }
        }
        
        // Remove from queue, no point in keeping it
        else{
            $this->removeFromQueue($commandId);
        }

        return $result;
    }

    /**
     * Disable push support for this device, i.e. this device won’t receive any more messages.
     * If this is the master device, other users can’t send messages to this number anymore.
     * Use "updateAccount" to undo this. To remove a linked device, use "removeDevice" from the master device.
     *
     * @return bool|string
     */
    public function unregister(){
        $result = $this->doRequest('unregister');

        if($result){
            unlink($this->basePath.'/phone.signal');
        }

        return $result;
    }

    /**
     * Register a phone number with SMS or voice verification. Use the verify command to complete the verification.
     * Default verify with SMS
     * @param bool $voiceVerification The verification should be done over voice, not SMS.
     * @param string $captcha - from https://signalcaptchas.org/registration/generate.html
     * 
     * @return bool|string|WP_Error
     */
    public function register(string $phone, string $captcha, bool $voiceVerification = false){
        $voice  = false;
        if($voiceVerification){
            $voice  = 'false';
        }

        file_put_contents($this->basePath.'/phone.signal', $phone);

        $this->phoneNumber = $phone;

        $captcha    = str_replace('signalcaptcha://', '', $captcha);

        $params     = [
            "voice"     => $voice,
            "captcha"   => $captcha
        ];

        return $this->doRequest('register', $params);
    }

    /**
     * Verify the number using the code received via SMS or voice.
     * @param string $code The verification code e.g 123-456
     * @return bool|string|WP_Error
     */
    public function verify(string $code){
        $phone              = trim(file_get_contents($this->basePath.'/phone.signal'));

        $this->phoneNumber  = $phone;

        $params     = [
            "verificationCode"     => $code
        ];

        return $this->doRequest('verify', $params);
    }
    
    /**
     * Link to an existing device, instead of registering a new number.
     * This shows a "tsdevice:/…" URI.
     * If you want to connect to another signal-cli instance, you can just use this URI.
     * If you want to link to an Android/iOS device, create a QR code with the URI (e.g. with qrencode) and scan that in the Signal app.
     * @param string|null $name Optionally specify a name to describe this new device. By default "cli" will be used
     * 
     * @return string           QR code
     */
    public function link(string $name = ''): string{
        $result = $this->doRequest('startLink');

        if(!$result){
            return false;
        }

        $uri    = $result['deviceLinkUri'];
        $id     = $result['id'];

        if(empty($name)){
            $name   = get_bloginfo('name');
        }

        $client     = new \GuzzleHttp\Client();
        $data    = [
            "jsonrpc"       => "2.0",
            "method"        => 'finishLink',
            "params"        => [
                "deviceLinkUri"     => $uri,
                "deviceName"        => $name
            ]
        ];

        // TO DO define postUrl

        $promise  = $client->requestAsync(
            "POST",
            $this->postUrl,
            ["json"  => $data]        
        );
        $promise->then(
            function ($res){
                if($res->getStatusCode() != 200){
                    TSJIPPY\printArray("Got ".$res->getStatusCode()." from $this->postUrl");
                    return false;
                }
        
                $result = $res->getBody()->getContents();
                $json   = json_decode($result);
            }
        );
        

        $link   = str_replace(['\n', '"'], ['\0', ''], $uri);

        if (!extension_loaded('imagick')){
            return $uri;
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
     * @param   string|array          $recipient or array of recipients number to check.
     *
     * @return  array|bool              If more than one recipient returns an array of results, if only one returns a boolean true or false
     */
    public function isRegistered($recipient){       
        if(!is_array($recipient)){
            $recipient  = [$recipient];
        }

        $params     = [
            "recipient"     => $recipient
        ];

        $result = $this->addToCommandQueue('getUserStatus', $params);

        if(!$result || is_wp_error($result)){
            if(is_wp_error($result)){
                TSJIPPY\printArray($result);
            }
            return true;
        }

        TSJIPPY\printArray($result);

        if(is_array($result) && count($result) == 1){
            return $result[0]->isRegistered;
        }

        return $result;
    }

    /**
     * Send a message to another user or group
     * @param string|array  $recipient     Specify the recipients’ phone number or a group id
     * @param string        $message        Specify the message, if missing, standard input is used
     * @param string|array  $attachments    Image file path or array of file paths
     * @param int           $timeStamp      The timestamp of a message to reply to
     *
     * @return bool|string
     */
    public function send($recipient, string $message, $attachments = [], int $timeStamp=0, $quoteAuthor='', $quoteMessage='', $textStyle = ''){
        if(empty($recipient)){
            return new WP_Error('Signal', 'You should submit at least one recipient');
        }

        $params = [];

        if(is_array($recipient)){
            $result = '';

            foreach($recipient as $r){
                $result = $this->send($r, $message, $attachments, $timeStamp);
            }

            return $result;
        }else{
            // first character is a +
            if(strpos( $recipient , '+' ) === 0){
                $params['recipient']    = $recipient;
            // invalid formatted phone number
            }elseif(strlen($recipient) < 15){
                TSJIPPY\printArray("Invalid phonenumber '$recipient'");

                return new WP_Error('Phonenumber invalid', "Invalid phonenumber '$recipient'");
            }else{
                $params['groupId']      = $recipient;
            }
        }

        if(empty($textStyle)){
            // parse any styling
            $parsed = $this->parseMessageLayout($message);
            extract($parsed);
        }

        $params["message"]  = $message;

        if(!empty($attachments)){
            if(!is_array($attachments)){
                $attachments    = [$attachments];
            }

            foreach($attachments as $index => $attachment){
                // Check if the attachment is a file
                if(!file_exists($attachment)){
                    // Not a file, check if it is a base64 encoded string
                    if (strpos($attachment, 'data:image/') === 0) {
                        list($type, $base64String) = explode(';base64,', $attachment, 2);
                    }
                    $binaryData = base64_decode($base64String);

                    $image = imagecreatefromstring($binaryData);
                    if ($image === false) {
                        unset($attachments[$index]);
                    }else{
                        imagedestroy($image);
                    }
                }
            }
        
            if(!empty($attachments)){
                $params["attachments"]  = array_values($attachments);
            }
        }

        if(!empty($timeStamp) && !empty($quoteAuthor) && !empty($quoteMessage)){
            $params['quoteTimestamp']   = $timeStamp;
       
            $params['quoteAuthor']      = $quoteAuthor;
        
            $params['quoteMessage']     = $quoteMessage;
        }

        if(!empty($textStyle)){
            $params['textStyle']   = $textStyle;
        }

        return $this->addToCommandQueue('send', $params);
    }

    /**
     * Compliancy function
     */
    public function sendGroupMessage($message, $groupId, $attachments=[], int $timeStamp=0, $quoteAuthor='', $quoteMessage=''){
        return $this->send($groupId, $message, $attachments, $timeStamp, $quoteAuthor, $quoteMessage);
    }

    /**
     * Mark a message as read
     *
     * @param   string  $recipient  The phonenumber
     * @param   int     $timestamp  The timestamp of the message to mark as read
     *
     * @return  bool                 Whether the operation was successful
     */
    public function markAsRead($recipient, $timestamp){
        $params = [
            "recipient"         => $recipient,
            "targetTimestamp"   => $timestamp,
            "type"              => "read"
        ];
        
        return $this->addToCommandQueue('sendReceipt', $params);
    }

    /**
     * List Groups
     *
     * @param   bool    $detailed   wheter to get details
     * @param   string  $groupId    The group id of a specif group you want details of
     *
     * @return array|string
     */
    public function listGroups($detailed = false, $groupId = false, $force=false){
        if(!$force){
            if(!empty($this->groups)){
                return $this->groups;
            }

            $transientGroups    = get_transient('tsjippy-signal-groups');

            if($transientGroups && is_array($transientGroups)){
                $this->groups   = $transientGroups;
                
                return $transientGroups;
            }
        }

        $params = [];

        if($detailed){
            $params['detailed'] = 1; 
        }

        if($groupId){
            $params['groupId'] = $groupId; 
        }
        
        $result = $this->doRequest('listGroups', $params);

        if(empty($this->error) && !empty($result)){
            $this->groups   = $result;
            set_transient('tsjippy-signal-groups', $result, WEEK_IN_SECONDS);
        }

        return $this->groups;
    }

    /**
     * Deletes a message
     *
     * @param   int             $timestamp    The original timestamp
     * @param   string|array    $recipients   The original recipient(s)
     */
    public function deleteMessage($timestamp, $recipients){

        if(is_array($recipients)){
            foreach($recipients as $recipient){
                $this->deleteMessage($timestamp, $recipient);
            }
        }

        $param = [
            "targetTimestamp"   => intval($timestamp)
        ];

        $firstCharacter = mb_substr($recipients, 0, 1);
        if($firstCharacter == '+'){
            $param['recipient'] = $recipients;
        }else{
            $param['groupId']   = $recipients;
        }

        //TSJIPPY\printArray($param, true);
        
        $result = $this->addToCommandQueue('remoteDelete', $param);

        if(isset($result->results[0]->type) && $result->results[0]->type == 'SUCCESS'){
            return true;
        }else{
            TSJIPPY\printArray($result, true);
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

        $params = [
            "recipient" => $recipient
        ];

        if(!empty($groupId)){
            $params["groupId"] = $groupId;
        }

        return $this->addToCommandQueue('sendTyping', $params);
    }

    /**
     * Dummy function to be compliancy with the sendGroupTyping function, as there is no difference between sending a typing indicator to a group or an individual
     * 
     * @param   string  $recipient  The phonenumber or group id
     * @param   int     $timestamp  Optional timestamp of a message to mark as read
     * @return string               The result
     * 
     * 
     */
    public function sendGroupTyping($recipient, $timestamp='', $groupId=''){
        $this->sentTyping($recipient, $timestamp, $groupId);
    }

    /**
     * Send a reaction to a message
     *
     * @param   string  $recipient          The phonenumber or group id
     * @param   int     $targetTimestamp    The timestamp of the message to react to
     * @param   string  $groupId            The group id
     * @param   string  $emoji              The emoji to send
     *
     * @return  mixed                       The result
     */
    public function sendReaction($recipient, $targetTimestamp, $groupId='', $emoji='', $targetAuthor=''){
        if(empty($emoji)){
            $emoji  = "🦘";
        }

        if(empty($targetAuthor)){
            $targetAuthor   = $recipient;
        }

        $params = [
            "recipient"         => $recipient,
            "emoji"             => $emoji,
            "targetAuthor"      => $targetAuthor,
            "targetTimestamp"   => $targetTimestamp
        ];

        if(!empty($groupId)){
            $params['groupId']  = $groupId;
        }

        return $this->addToCommandQueue('sendReaction', $params);
    }

    /**
     * Update the name and avatar image visible by message recipients for the current users.
     * The profile is stored encrypted on the Signal servers.
     * The decryption key is sent with every outgoing messages to contacts.
     *
     * @param string    $name           New name visible by message recipients
     * @param string    $avatarPath     Path to the new avatar visible by message recipients
     * @param bool      $removeAvatar   Remove the avatar visible by message recipients
     * 
     * @return bool|string|WP_Error     Tehe result or an WP Error object
     */
    public function updateProfile(string $name = '', ?string $avatarPath = '', bool $removeAvatar = false){

        $params = [];

        if(!empty($name)){
            $params['given-name'] = $name;
        }

        if(!empty($avatarPath) && file_exists($avatarPath)){
            $params['avatar'] = $avatarPath;
        }

        if($removeAvatar){
            $params['removeAvatar'] = true;
        }

        $result = $this->addToCommandQueue('updateProfile', $params);

        return $result;
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
        $params = [
            "challenge" => $challenge,
            "captcha"   => $captcha
        ];

        $result = $this->doRequest('submitRateLimitChallenge', $params);

        if(!$result){
            return false;
        }

        TSJIPPY\printArray($result);

        $this->setRateLimit(false);
    }

    /**
     * gets the invitation link of a specific group
     * 
     * @param   string  $groupPath The group id or group name of the group you want the invitation link for
     * 
     * @return  string              The invitation link of the group
     */
    public function getGroupInvitationLink($groupPath){
        $result = $this->listGroups(true, $groupPath);

        if(empty($result[0]->groupInviteLink)){
            TSJIPPY\printArray($result, true);
        }

        return $result[0]->groupInviteLink;
    }

    /**
     * Find the group name based on the group id
     * 
     * @param   string  $id The group id of the group you want the name of
     * 
     * @return  string      The name of the group, or an empty string if not found
     */
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
}
