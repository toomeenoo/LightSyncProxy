<?php

namespace UdpRelay;

class DynamicTimer {

    const INITIAL_WAIT_MICRO    = 2000000;

    const MINIMAL_WAIT_MICRO    = 8000;

    const ADJUST_RATIO          = 1.2;

    const STANDBY_WAIT_MICRO    = 800000;
    const STANDBY_DURATION_SEC  = 90;

    const MAXIMAL_WAIT_MICRO    = 4900000;

    private $timeout;

    function __construct()
    {
        $this->timeout = self::INITIAL_WAIT_MICRO;
    }

    function wait($lastResponseUtime){        
        $relayDelay = round(1000000 * (microtime(true) - $lastResponseUtime));
        if($relayDelay < $this->timeout){
            // Set minimal timeout
            $this->timeout = min(self::MAXIMAL_WAIT_MICRO, max(self::MINIMAL_WAIT_MICRO, $relayDelay));
        }else if($this->timeout < self::MAXIMAL_WAIT_MICRO){
            // Wait for standbytime
            if(time() - round($lastResponseUtime) > self::STANDBY_DURATION_SEC){
                $this->timeout = self::MAXIMAL_WAIT_MICRO;
            // Adjust to max standby
            }else if($this->timeout < self::STANDBY_WAIT_MICRO){
                $this->timeout = min(self::STANDBY_WAIT_MICRO, round($this->timeout*self::ADJUST_RATIO));
            }
        }
        usleep($this->timeout);        
    }
}