<?php

namespace UdpRelay;

class ClientReference{

    private $ip;
    private $port;
    private $server;

    private $seen;
    private $id;
    private $state;
    private $challenge = null;
    private $relayMyQeue = null;
    private $authQeue;

    function __construct($ip, $port, $server){
        $this->ip = $ip;
        $this->port = $port;
        $this->seen = time();
        $this->state = Constants::STATE_ANON;
        $this->server = $server;
    }


    function onMessage($message){
        $this->seen = time();
        $mtag = substr($message, 0, 2);
        if($this->state == Constants::STATE_ANON){
            if($mtag == 'L:' && !is_null($this->challenge)){
                return $this->auth_check(substr($message, 2));
            }else{
                $this->authQeue = $message;
                return $this->auth_init();
            }
        }else if($this->state == Constants::STATE_READY){ // from client 
            if($mtag == 'RR' || $mtag == 'PR'){
                $this->becomeRelay();
                return "RY";
            }elseif($mtag == 'RI' || $mtag == 'RS'){
                if($mtag == 'RI'){
                    $this->relayMyQeue = (object) [
                        'client' => $this,
                        'livetill' => time()+Constants::TTL, # TODO update and check!
                        'qeue' => []
                    ];
                    $rid = $this->getRequestId($message);
                    $this->server->relays[$this->id]->requests[$rid] = $this->relayMyQeue;
                }
                $this->server->relays[$this->id]->in[] = $message;
                $this->server->relays[$this->id]->livetill = time()+Constants::TTL;
                /*if($mtag == 'RI'){
                    var_dump($this->server->relays);
                }*/
            }
            return $this->fwdToClient();
        }else if($this->state == Constants::STATE_RELAY_SERVER){  // from relay server
            if($mtag == 'RI' || $mtag == 'RS'){
                $rid = $this->getRequestId($message);
                $this->server->relays[$this->id]->requests[$rid]->qeue[] = $message;
            }
            return $this->fwdToHost();
        }
        return "N.";
    }

    function fwdToClient() {
        if(is_null($this->relayMyQeue) || !count($this->relayMyQeue->qeue)){
            return 'N.';
        }else{
            return array_shift($this->relayMyQeue->qeue);
        }
    }

    function fwdToHost() {
        if(rand(0,1)){
            $rqids = array_keys($this->server->relays[$this->id]->requests);
            foreach($rqids as $rqid) {
                if($this->server->relays[$this->id]->requests[$rqid]->livetill < time()){
                    unset($this->server->relays[$this->id]->requests[$rqid]);
                }
            }
        }
        if(count($this->server->relays[$this->id]->in)){
            return array_shift($this->server->relays[$this->id]->in);
        }
        return 'N.';
    }

    function getRequestId($message){
        $ignore_left = 5;// strlen(LightSyncServer::CLI_SIG)+'MESSAGE_TAG_LEN'
        $v = unpack('N', substr($message, $ignore_left,4));
        return '#'.@$v[1];
    }

    private function becomeRelay(){
        $this->state =  Constants::STATE_RELAY_SERVER;
        if(isset($this->server->relays[$this->id])){
            $this->server->relays[$this->id]->host = $this;
            $this->server->relays[$this->id]->cref = $this->ip.":".$this->port;
        }else{
            $this->server->relays[$this->id] = (object) [
                'in'      => [],
                'requests'=> [],
                'seen'    => time(),
                'host'    => $this,
                'cref'    => $this->ip.":".$this->port
            ];
        }
    }

    function auth_init(){
        $this->challenge = random_bytes(32);
        return "A:".$this->challenge;
    }

    function auth_check($reply){
        $clientId = substr($reply, 0, Constants::ID_MAXLEN);
        $response = substr($reply, Constants::ID_MAXLEN);

        $clientPass = $this->loadClientById($clientId);
        if($clientPass && (($clientPass === true) || ($response == hash('sha256', $clientPass.$this->challenge, true)))){
            $this->id = $clientId;
            $this->state = Constants::STATE_READY;
            $this->challenge = null;
            return $this->onMessage($this->authQeue);
        }else{
            return "F.";
        }
    }

    function getTTL(){
        return max(0, $this->seen + Constants::TTL - time());
    }

    function getID(){
        return $this->id;
    }

    function isRelay(){
        return $this->state == Constants::STATE_RELAY_SERVER;
    }
    

    /**
     * Override this function
     */
    private function loadClientById($id){
        if(is_null($this->server->clientManager)){
            return true;
        }else{
            return call_user_func($this->server->clientManager, $id);
        }
    }


}