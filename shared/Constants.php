<?php

namespace UdpRelay;

class Constants{

    const ID_MAXLEN  = 16;     // Max Bytes (characters) of client id

    const PKT_SIZE   = 1344;   // Best to be under max avail MTU

    const SRV_SIG = "L\05S";   // Packet identification - sent by server
    const CLI_SIG = "L\05C";   // Packet identification - sent by client

    const TTL          = 30;   // Time to keep "references" alive
    const READ_TIMEOUT = 5;    // Time to wait for any response

    const MAX_32_ULONG = 4294967295; // Message id's are randomly generated from 1 to (this range)

    /* Client relay-status options */
    const RELAY_NO   = 0;
    const RELAY_IS   = 1;
    const RELAY_WANT = 2;
    const RELAY_QEUE = 3;

    /* ClientReference status options */
    const STATE_ANON  = 0;
    const STATE_READY = 1;
    const STATE_RELAY_SERVER = 2;

    /* RelayMessage status options */
    const STATE_INIT = 0;
    const STATE_SENDING = 1;
    const STATE_RECIEVING = 2;
    const STATE_DONE = 8;

    public static function getMessageMaxSize(){
        return self::PKT_SIZE - max(strlen(self::SRV_SIG), strlen(self::CLI_SIG));
    }

}

