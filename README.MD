# LightSyncProxy (udp based)

Program for remote comunication, bypassing firewall using UDP protocol.

## How does it work?

Whole program is divided into three parts:
- Client (The one "outside" the network making requests into network) see folder `web`
- Gatweay (Available through internet, listening on public ip in selected UDP port) see folder `gateway`
- ClientRelay (The one "inside" local network, responding to requests) see folder `service`

## Demo:
*What it does?*  
Having a webserver on some hostname (`public.examplehost.local`), on request fro ousitde, transfer and mimic this request in local network, into "local only" host (`private.examplehost.local`), and return apropirate response with headers. In setup section, below, is described 

### Setup - Mandatory  
 - Create a web server (for example apache2)
 - Configure two virtual hosts (pick any names, and directories)
   - ServerName `public.examplehost.local`
   - ServerName `private.examplehost.local`
   - *Please refer to your webserver's configuration giude*
 - set hostnames in hosts file:
    - File is located:
      - On windows: `%windir%\System32\drivers\etc\hosts`
      - On *nix: `/etc/hosts`
    - Add:
      - 127.0.0.1 public.examplehost.local
      - 127.0.0.1 private.examplehost.local
 - create `index.php` in root directory `public.examplehost.local`, and include `web/index.php`
 - set all request to point into `index.php` for host `public.examplehost.local`
    - If you allow `mod_rewrite`, for this host, everything is pre-configured in `web/.htaccess`
 - create some simple response for `private.examplehost.local`
 - *Configure ip addresses and ports in this project*
 - **#TODO: descibe**

### Start

 - Start `php gateway/gateway.php` 
 - Start `php service/service.php` 
 - Visit `public.examplehost.local` with web browser 

### Message tags:

Always a 2-byte sequences

**Client**
 - `L:` Login, folowed by id and response
 - `P:` Ping Service
 - `PR` Ping Service, I am relay, yes?
 - `RR` Relay request - I will be relay now for my id


**Server**
 - `N.` No action, usual reply for ping
 - `A:` Authorize! Followed by random bytes challenge
 - `F:` Auth failed
 - `RY` Relay confirmed

**Both**
 - `RI` Relay init message  (c2s 1x)
 - `RS` Relay send message  (c2s n*)

## Remakrs
 - First version may contain numerous bugs
 - Only IPv4 Tested
