<?php

namespace UdpRelay;

class RelayMessage{


    public $state = 0;
    private $handlerRef = null;

    private $request_parts      = [];
    private $request_id          = 0;
    private $request_autosend    = 0;

    private $msgSum = null;
    private $receiveCount = 0;
    private $receiveParts = [];
    
    static $relayid = 1;
    private $pass;

    function __construct($transferPassword)
    {
        $this->pass = $transferPassword;
    }

    /**
     * Generate random message ID
     * @return int ID to be used
     */
    public static function getNextId(){
        return random_int(1, Constants::MAX_32_ULONG);
    }

    /**
     * This is message from client
     * @param string $dataToSend Full data to be transmitted as is
     */
    public function init_client_send(string $dataToSend){
        $this->request_id = self::getNextId();
        $this->buildData($dataToSend);
        array_unshift($this->request_parts, 
            'RI'.
            pack('NN', $this->request_id, count($this->request_parts)).
            hash("sha256", $dataToSend, true)
        );
        $this->state = Constants::STATE_SENDING;
    }

    /**
     * This message is being either: received by relay from client, or now a response from server on client side
     * @param string $messagebody Contents of mesage, with signature and MessageTag `RI` already trimmed
     * @param int $messageId Message (request) ID already determined
     */
    public function init_receive($messagebody, int $messageId){
        $this->state = Constants::STATE_RECIEVING;
        $this->request_id = $messageId;
        $this->msgSum = substr($messagebody, -32);
        $tmp = unpack('N', substr($messagebody, 4, 4));
        $this->receiveCount = $tmp[1];
    }

    /**
     * Transform data to individual messages and store them inside objects
     * @param string $dataToSend Data to be transformed and stored
     * @internal
     */
    private function buildData($dataToSend){
        $request_parts = str_split($this->lockData($dataToSend), Constants::getMessageMaxSize()-14);
        $n = 0;
        $rcc = count($request_parts);
        while($n < $rcc){
            $request_parts[$n] = 
                'RS'. // 2
                pack('NN', $this->request_id, $n+1). //4+4=8
                $request_parts[$n].
                substr(hash('adler32',$request_parts[$n], true), -4); //4
            $n++;
        }
        $this->request_parts = $request_parts;
    }

    /**
     * To be triggered from Client when data is read from socket
     * @param string $mbody Contents of mesage, with signature and MessageTag `RS` already trimmed
     */
    public function receivePart($mbody){
        $tmp = unpack('N', substr($mbody, 4, 4));
        $hash = substr($mbody, -4);
        $content = substr($mbody, 8, -4);
        if(hash('adler32',$content, true) == $hash){
            $this->receiveParts[$tmp[1]] = $content;
            if($tmp[1] == $this->receiveCount){
                if(!$this->complete()){
                    echo "Missing parts!!\n\n";
                }
            }
            // Contunie recieving
        }else{
            #TODO hash not ok
            echo "Hash not ok!!\n\n";
        }
    }

    /**
     * Transforms data on entry - crypting and/or compressing
     * @param string $data Content to be affected
     * @todo implement security logic
     * @return string Transformed data
     */
    private function lockData($data){
        $ivlen = openssl_cipher_iv_length("aes-256-gcm");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $data = openssl_encrypt(gzdeflate($data, 9), "aes-256-gcm", hash('sha256', $this->pass, true), OPENSSL_RAW_DATA, $iv, $tag);
        return $iv.$data.$tag;
    }
    
    /**
     * Transforms data on output - decrypting and/or decompressing
     * @param string $data Content to be affected
     * @todo implement security logic
     * @return string Transformed data
     */
    private function unlockData($data){
        $data = openssl_decrypt(substr($data, 12, -16), "aes-256-gcm", hash('sha256', $this->pass, true), OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, -16));
        return gzinflate($data);
    }

    /**
     * Confirms integrity and completeness of data
     * @param bool $returnData Whether to return full (cleaned up) data, or only boolean
     * @todo should also trigger resending of lost / malformed packets
     * @uses RelayMessage:handlerRef If set, and not $return data, triggers callback function
     * @return string|boolean 
     */
    public function complete($returnData = false){
        if($this->receiveCount == count($this->receiveParts)){
            $msg = join('',$this->receiveParts);
            $msg = $this->unlockData($msg);
            if(hash("sha256", $msg, true) == $this->msgSum){
                $this->state = Constants::STATE_DONE;
                if($returnData){
                    return $msg;
                }elseif(!is_null($this->handlerRef)){
                    $relpy = call_user_func($this->handlerRef, $msg);
                    if($relpy){
                        $this->reply($relpy);
                    }
                    return true;
                }
            }else{
                // todo retry whole message
                var_dump($this);
                echo "retry whole message";
            }
        }else{
            // todo retry missing
            var_dump($this);
            echo "retry missing part";
        }
        return false;
    }

    /**
     * Load packet from qeue to be sent
     * @param int|null $order of packet, automaticly returns latest required, if any
     * @return string|false Data to sent or no more data in qeue
     */
    public function getPacket(int $order = null){
        if(is_null($order) && $this->request_autosend < count($this->request_parts)){
            $order = $this->request_autosend;
            $this->request_autosend ++;
        }
        if(is_null($order)) return false;
        return $this->request_parts[$order];
    }

    /**
     * Getter method for message ID
     * @return int|null Assigned id or null if not any
     */
    public function messageId(){
        return $this->request_id;
    }

    /**
     * Used only on Relay side, builds reply to Client's request
     * @param string $data Data to be sent as a response 
     */
    public function reply($data){
        $this->buildData($data);
        array_unshift($this->request_parts, 
            'RI'.
            pack('NN', $this->request_id, count($this->request_parts)).
            hash("sha256", $data, true)
        );
    }

    /**
     * Set callback function for response handling, primary used on Relay side
     * @param callable $processor Function to trigger, clean data will be present as first parameter, returned string will be sent to cient as a response 
     */
    public function setMessageHandler(callable $processor){
        $this->handlerRef = $processor;
    }

    /**
     * Using given connected client, sends data packets which are held in qeue
     * @param Client $client Instance of connected client.
     */
    public function processSend($client){
        while($row = $this->getPacket()){
            $client->send($row);
        }
        return true;
    }

    /**
     * Using given connected client, recieve responnse to (this) sent message
     * @param Client $client Instance of connected client.
     * @param int $timeout time to wait for response
     */
    public function processReceive($client, int $timeout = 20){
        $live = time() + $timeout;
        $timer = new DynamicTimer();
        while($live >= time()){
            $client->ping();
            if($this->state == Constants::STATE_DONE){
                return $this->complete(true);
                # TODO check if done not complete?
            }
            $timer;
        }
        return null;
    }

}
