<?php

use UdpRelay\Client;

require_once __DIR__.'/../shared/loader.php';


$replyFn = function($message){
    echo "\n";
    $m = json_decode($message);

    $ha = [];
    $m->headers->Connection = 'close';
    foreach ($m->headers as $key => $value) {
        if($key == 'Accept-Encoding') continue;
        //if($key == 'Referer') $value = 'http://192.168.1.1/';
        $ha[] = $key.': '.$value;
    }

    $host = '127.0.0.1';
    $scoptions = [
        "http"=>[
            'ignore_errors' => true,
            'header' => $ha
        ]
    ];
    if($m->rm != "GET"){
        $scoptions['http']['method'] = $m->rm;
        if(strlen($m->body))
            $scoptions['http']['content'] = $m->body;
        echo  $m->rm.": ".$m->body."\n";
    }
    $url = 'http://'.$host.$m->uri;

    echo "$url\n";
    $tr = microtime(true);

    if (!$fp = fopen($url, 'r', false, stream_context_create($scoptions))) {
        trigger_error("Unable to open URL ($url)", E_USER_ERROR);
    }

    $meta = stream_get_meta_data($fp);
    $out = stream_get_contents($fp);
    fclose($fp);

    echo $meta['wrapper_data'][0]."   ~ ".round(microtime(1) - $tr, 2)."s\n";
    echo "Size: ".strlen($out)." Headers: ".count($meta['wrapper_data'])."\n";

    $out = json_encode($meta['wrapper_data'])."\n".$out;
    //echo '-----------------------------------------------------------'."\n$out\n";
    return $out;
};


$Client = new Client('127.0.0.1');
$Client->setRelayCallback($replyFn);
$Client->becameRelay(true);

