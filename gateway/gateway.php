<?php

use UdpRelay\Server;

require_once __DIR__.'/../shared/loader.php';

new Server('0.0.0.0', 65511, function($clientId){
    echo "$clientId Connected\n";
    return true;
});

