# NoTrack  

**NoTrack v0.9.0 is ready for use**

NoTrack is a [DNS-Sinkhole](https://en.wikipedia.org/wiki/DNS_sinkhole) which protects all devices on your home network from visiting Tracking, Advertising, and Malicious websites.   

## Automated Install
NoTrack is best used on a Linux server, such as a lightweight Raspberry Pi with [Raspbian Lite](https://www.raspberrypi.org/downloads/raspbian/)
```bash
wget https://gitlab.com/quidsup/notrack/raw/master/install.sh
bash install.sh
```
   
## Tracking  
Tracking is absolutely rife on the Internet, on average 17 cookies are dropped by each website. Although you can block third party cookies, there are also more complex methods of tracking, such as:
* Tracking Pixels
* HTML5 Canvas Fingerprinting
* AudioContext Fingerprinting
* WebRTC Local IP Discovery

99 of the top 100 websites employ one or more of these forms of tracking.   
[NoTrack-Blocklist](https://gitlab.com/quidsup/notrack-blocklists) is one of the largest DNS blocklists dedicated to blocking access to Tracking sites.
  
## Features    
### Web Interface Dashboard   
At a glance see how many sites are in your blocklist, number of DNS Queries, number of Systems on your Netowork, and volume of traffic over the past 24 hours.  
As well as links to all the other admin features in the custom built interface.
![notrackmain](https://gitlab.com/quidsup/notrack/wikis/uploads/57be0de25f7bd55dd4a59d1cc3106885/notrackmain.png)
   
### Analytics
NoTrack analytics will monitor your traffic and provide an Alert when any of your devices attempt to access known malware sites, as well as for sites suspected of being related to tracking, but have not yet been blocked.   
You have the option of Investigating the traffic further, Whitelisting, or Blacklisting the site. Once completed you can Resolve or Delete the alert.   
  
Analytics runs on your system locally. None of your data is ever transmitted.   
![notrackanalytics](https://gitlab.com/quidsup/notrack/wikis/uploads/c1b4372a5619e8dca800176482ee9276/notrackanalytics.png)

More features in the [NoTrack Wiki](https://gitlab.com/quidsup/notrack/wikis/Features)