<?php

function binaryString($str){
    $out = '';
    $s2a = str_split($str, 1);
    $is_hex = false;
    foreach ($s2a as $char) {
        if(ord($char) < 32 || ord($char) > 126){
            if(!$is_hex){
                $out .= "«";
            }
            $out .= strtoupper(dechex(ord($char)));
            $is_hex = true;
        }else{
            if($is_hex){
                $out .= "»";
            }
            $out .= $char;
            $is_hex = false;
        }
    }
    return $out;
}
