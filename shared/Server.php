<?php

namespace UdpRelay;

class Server{

    private $socket;
    private $clients = [];

    public $relays  = [];
    public $clientManager = null;

    // For reporting stats
    private $t0; 
    private $td = null;
    private $stats = [
        'pkts' => [0,0],
        'data' => [0,0],
    ];

    function __construct($ip = '0.0.0.0', $port = 65511, callable $clientManager = null){
        $this->clientManager = $clientManager;
        $this->t0 = time();
        if(!($sock = socket_create(AF_INET, SOCK_DGRAM, 0)))
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            
            die("Couldn't create socket: [$errorcode] $errormsg \n");
        }
        if( !socket_bind($sock, $ip , $port) )
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            die("Could not bind socket : [$errorcode] $errormsg \n");
        }
        $this->socket = $sock;
        $this->procView();
        $this->masterLoop();
    }

    function masterLoop() {
        while(1)
        {
            $ok = socket_recvfrom($this->socket, $buf, Constants::PKT_SIZE, 0, $ip, $port);
            $this->stats['data'][0] += $ok;
            $this->stats['pkts'][0] ++;
            if($ok && substr($buf, 0, strlen(Constants::CLI_SIG)) == Constants::CLI_SIG){

                $client = null;
                if(isset($this->clients[$ip.':'.$port])){
                    $client = $this->clients[$ip.':'.$port];
                }else{
                    $client = new ClientReference($ip, $port, $this);
                    $this->clients[$ip.':'.$port] = $client;
                }

                //echo "$ip:$port => ";
                $reply = $client->onMessage(substr($buf, strlen(Constants::CLI_SIG)));
                $sent = socket_sendto($this->socket, $reply , strlen($reply) , 0 , $ip , $port);
                if($sent){
                    $this->stats['data'][1] += $sent;
                    $this->stats['pkts'][1] ++;    
                }
            }
            usleep(1000);
            $this->timeoutConnections();
            $this->procView();
        }
    }

    function timeoutConnections(){
        $aks = array_keys($this->clients);
        foreach ($aks as $key) {
            if(!$this->clients[$key]->getTTL()){
                unset($this->clients[$key]);
            }
        }
    }

    function procView(){
        if($this->td+5 < time()){
            echo "\033[2J\033[u";
            $spc = str_repeat(' ', 18)."\n";
            echo "Memory/Php:  ".memory_get_usage().$spc;
            echo "Memory/Real: ".memory_get_usage(true).$spc;
            echo "Time:        ".(time()-$this->t0).$spc;
            echo "Clients:  ".count($this->clients).'/'.count($this->relays).$spc;
            echo "Data:     IN:".$this->stats['data'][0].'   OUT:'.$this->stats['data'][1].'   SUM: '.array_sum($this->stats['data']).$spc;
            echo "Packets:  IN:".$this->stats['pkts'][0].'   OUT:'.$this->stats['pkts'][1].'   SUM: '.array_sum($this->stats['pkts']).$spc;
            $this->td = time();
        }
    }

    function setClientManager(callable $fn = null){
        $this->clientManager = $fn;
    }

    function __destruct() {
        socket_close($this->socket);
    }

}
