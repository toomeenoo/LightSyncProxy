<?php

use UdpRelay\Client;

require_once __DIR__.'/../shared/loader.php';

$cacheAble = [
    "/frontend/",
    "/ajax/icon/"
];

$uri = $_SERVER['REQUEST_URI'];

$cacheThis = false;
foreach ($cacheAble as $cpath) {
    if(substr($uri, 0, strlen($cpath)) == $cpath){
        $fix = str_replace('..', '', $uri);
        if($fix == $uri){
            $cacheThis = true;
        }
        break;
    }
}

if($cacheThis && file_exists(__DIR__.'/cache'.$uri)){
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $n = pathinfo($uri, PATHINFO_FILENAME);
    $mime = null;
    if($ext == 'php' || $n == ''){
        // Not this :)
    }else if($ext == 'js'){
        $mime = 'text/javascript';
    }else if($ext == 'css'){
        $mime = 'text/css';
    }else if($ext == 'png'){
        $mime = 'image/png';
    }else if($ext == 'jpg'){
        $mime = 'image/jpeg';
    }else if($ext == 'json'){
        $mime = 'application/json';
    }else if($ext == 'svg' || substr($uri, 0, 11) == '/ajax/icon/'){
        $mime = 'image/svg+xml';
    }
    if(!is_null($mime))
        header("Content-Type: ".$mime);
    echo file_get_contents(__DIR__.'/cache'.$uri);
    die();
}

$Client = new Client('127.0.0.1');
$t0 = microtime(1);
$o = $Client->relaySend(json_encode(
    [
        "headers" => getallheaders(),
        "rm"      => $_SERVER['REQUEST_METHOD'],
        'uri'     => $uri,
        "body"    => file_get_contents("php://input")
    ]
));
header("X-Relay-Time: ".round(microtime(1) - $t0, 4)."s");

$headers = [];
if(preg_match('/^([^\n]+)\n/', $o, $headers)){
    $o = substr($o, strlen($headers[0]));
    $headers = json_decode($headers[1]);
}

$skip_headers = [
    'date',
    'server',
    'connection',
    'vary',
    'content-encoding'
];
foreach ($headers as $index => $rawheader) {
    if($index == 0){
        $http = explode(' ',$rawheader);
        if($http[1] !== 200){
            http_response_code(intval($http[1]));
        }
        continue;
    }else{
        $parts = explode(": ",$rawheader);
        $key = $parts[0];
        $value = $parts[1];
        if(in_array(strtolower($key), $skip_headers)){
            //header("X-Original-".$rawheader);
        }else{
            header($rawheader);
        }
    }
}

if($cacheThis && strlen($o)){
    $tfname = __DIR__.'/cache'.$uri;
    $dir = pathinfo($tfname, PATHINFO_DIRNAME);
    if(!file_exists($dir)){
        @shell_exec('mkdir -p '.$dir);
        @shell_exec('chmod a+rw -R '.escapeshellarg($dir));
    }
    @file_put_contents($tfname, $o);
    @shell_exec('chmod a+rw-x '.escapeshellarg($tfname));
}

echo $o;
