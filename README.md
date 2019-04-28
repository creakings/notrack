# NoTrack Has Moved To GitLab
New Project Page: https://gitlab.com/quidsup/notrack
   
Blocklists:   
NoTrack-Blocklist: https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-blocklist.txt  
NoTrack-Malware: https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-malware.txt  
   
## New Changes
There is quite a significant change in how logs are stored between NoTrack v0.8.x and NoTrack v0.9, so unfortunately there is no simple upgrade path. It will also mean that you’ll be unable to view any of your historic DNS log records.
   
An uninstall and reinstall of NoTrack is required in order to use version 0.9.
   
Instructions:
* Uninstall NoTrack v0.8.11
* `sudo bash /opt/notrack/uninstall.sh`
* or `sudo bash ~/notrack/uninstall.sh`
   
Install NoTrack v0.9
```bash
wget https://gitlab.com/quidsup/notrack/raw/master/install.sh
bash install.sh
```
