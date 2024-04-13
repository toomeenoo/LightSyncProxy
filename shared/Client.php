<?php

namespace UdpRelay;

class Client{

    private $socket;
    private $server;
    private $port;

    private $id;
    private $pwd;
    private $e2epwd;
    private $relay = false;
    private $lastRelayMessage = null;
    private $relayMessageQeue = null;
    private $relayCallback = null;

    function __construct($serverIp = '127.0.0.1', $serverPort = 65511, $myId = 'default', $mySecret = '0000', $e2ePassword = null){

        $this->server = $serverIp;
        $this->port = $serverPort;
        $this->relay = Constants::RELAY_NO;

        $this->id = str_pad(substr($myId, 0, Constants::ID_MAXLEN), Constants::ID_MAXLEN, "\0");
        $this->pwd = $mySecret;
        if(is_null($e2ePassword)){
            $this->e2epwd = $mySecret;
        }else{
            $this->e2epwd = $e2ePassword;
        }

        if(!($sock = socket_create(AF_INET, SOCK_DGRAM, 0)))
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new \Exception("Can not create socket: ".$errormsg, $errorcode);
        }
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec'=>Constants::READ_TIMEOUT,'usec'=>0]);
        $this->socket = $sock;
    }

    public function ping(){
        $mtag = 'P.';
        if($this->relay == Constants::RELAY_IS) $mtag = 'PR';
        $this->send($mtag);
    }

    public function becameRelay(bool $startRelayService = false){
        $this->relay = Constants::RELAY_QEUE;
        $this->relayMessageQeue = [];
        $this->send("RR");
        if($this->relay == Constants::RELAY_IS && $startRelayService){
            $this->startRelayService();
        }
    }

    private function startRelayService(){
        $timer = new DynamicTimer();
        while(1){
            $this->ping();
            $this->procMessages();
            $timer->wait($this->lastRelayMessage);
        }
    }

    public function relaySend($data, $timeout = 20){
        $pm = new RelayMessage($this->pwd);
        $pm->init_client_send($data);
        $pm->processSend($this);
        $this->relayMessageQeue = $pm;
        $pm->processReceive($this, $timeout);
        return false;
    }

    public function setRelayCallback(callable $fn){
        $this->relayCallback = $fn;
    }

    private function procMessages(){
        $msgids = array_keys($this->relayMessageQeue);
        foreach($msgids as $mi){
            if(!$this->relayMessageQeue[$mi]->processSend($this)){
                unset($this->relayMessageQeue[$mi]);
            }
        }
    }

    public function send($msg = null){
        if(!is_null($msg)){
            $input = Constants::CLI_SIG.$msg;
            if( ! socket_sendto($this->socket, $input , strlen($input) , 0 , $this->server , $this->port))
            {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                $this->relay = ($this->relay == Constants::RELAY_NO)? Constants::RELAY_NO : Constants::RELAY_WANT;
                throw new \Exception("Could not send data: $errormsg", $errorcode);
            }
        }
        if(@socket_recv($this->socket , $reply , Constants::PKT_SIZE, 0) === FALSE)
        {
            $this->relay = ($this->relay == Constants::RELAY_NO)? Constants::RELAY_NO : Constants::RELAY_WANT;
        }else{
            $this->onReply($reply);
        }

        if($this->relay == Constants::RELAY_WANT){
            $this->becameRelay();
        }
    }

    private function onReply($msg){
        $mtag = substr($msg, 0, 2);
        $mbody = substr($msg, 2);
        if($mtag == 'A:'){
            $this->send("L:".$this->id.hash('sha256', $this->pwd.$mbody, true));
        }elseif($mtag == "N."){
            //No action
        }elseif($mtag == "RY"){
            //Relay server yes
            $this->relay = Constants::RELAY_IS;
            $this->lastRelayMessage = microtime(true) - .5;
        }elseif($this->relay == Constants::RELAY_IS){
            if($mtag == 'RI' || $mtag == 'RS'){
                $msgid = $this->getRequestId($mbody);
                $this->lastRelayMessage = microtime(true);
                if($mtag == 'RI'){
                    $pm = new RelayMessage($this->e2epwd);
                    if(!is_null($this->relayCallback))
                        $pm->setMessageHandler($this->relayCallback);
                    $pm->init_receive($mbody, $msgid);
                    $this->relayMessageQeue['#'.$msgid] = $pm;
                }else{
                    $this->relayMessageQeue['#'.$msgid]->receivePart($mbody);
                }
            }
        }elseif($mtag == 'RI' || $mtag == 'RS'){
            $this->lastRelayMessage = microtime(true);
            $msgid = $this->getRequestId($mbody);
            if($msgid == $this->relayMessageQeue->messageId()){
                if($mtag == 'RI'){
                    $this->relayMessageQeue->init_receive($mbody, $msgid);
                }else{
                    $this->relayMessageQeue->receivePart($mbody);
                }
            }
        }
    }

    function getRequestId($mbody){
        $v = unpack('N', substr($mbody, 0, 4));
        return @$v[1];
    }

}



