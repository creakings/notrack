#NoTrack Blocklists
#Abbreviated Name : [List enabled by default, URL / File path, Type]
#Values are loaded out of config based on the abbreviated name
#Do not move the position of bl_tld! It is important Top Level Domains are processed first
#Positioning of later lists doesn't matter, although they have been laid out as:
#NoTrack, then A-Z, and finally A-Z of country specific easy lists

TYPE_PLAIN = 1
TYPE_UNIXLIST = 2
TYPE_EASYLIST = 4
TYPE_CSV = 8
TYPE_SPECIAL = 64

blocklistconf = {
    'bl_tld' : [True, '', TYPE_SPECIAL],
    'bl_blacklist' : [True, '', TYPE_PLAIN],
    'bl_notrack' : [True, 'https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-blocklist.txt', TYPE_PLAIN],
    'bl_notrack_malware' : [True, 'https://gitlab.com/quidsup/notrack-blocklists/raw/master/notrack-malware.txt', TYPE_PLAIN],
    'bl_cbl_all' : [False, 'https://zerodot1.gitlab.io/CoinBlockerLists/list.txt', TYPE_PLAIN],
    'bl_cbl_browser' : [False, 'https://zerodot1.gitlab.io/CoinBlockerLists/list_browser.txt', TYPE_PLAIN],
    'bl_cbl_opt' : [False, 'https://zerodot1.gitlab.io/CoinBlockerLists/list_optional.txt', TYPE_PLAIN],
    'bl_cedia' : [False, 'http://mirror.cedia.org.ec/malwaredomains/domains.zip', TYPE_CSV],
    'bl_cedia_immortal' : [True, 'http://mirror.cedia.org.ec/malwaredomains/immortal_domains.zip', TYPE_PLAIN],
    'bl_ddg_confirmed' : [False, 'https://gitlab.com/quidsup/ntrk-tracker-radar/-/raw/master/ddg_tracker_radar_confirmed.txt', TYPE_PLAIN],
    'bl_ddg_high' : [False, 'https://gitlab.com/quidsup/ntrk-tracker-radar/-/raw/master/ddg_tracker_radar_high.txt', TYPE_PLAIN],
    'bl_ddg_medium' : [False, 'https://gitlab.com/quidsup/ntrk-tracker-radar/-/raw/master/ddg_tracker_radar_med.txt', TYPE_PLAIN],
    'bl_ddg_low' : [False, 'https://gitlab.com/quidsup/ntrk-tracker-radar/-/raw/master/ddg_tracker_radar_low.txt', TYPE_PLAIN],
    'bl_ddg_unknown' : [False, 'https://gitlab.com/quidsup/ntrk-tracker-radar/-/raw/master/ddg_tracker_radar_unknown.txt', TYPE_PLAIN],
    'bl_hexxium' : [False, 'https://hexxiumcreations.github.io/threat-list/hexxiumthreatlist.txt', TYPE_EASYLIST],
    'bl_disconnectmalvertising' : [False, 'https://s3.amazonaws.com/lists.disconnect.me/simple_malvertising.txt', TYPE_PLAIN],
    'bl_easylist' : [False, 'https://easylist-downloads.adblockplus.org/easylist_noelemhide.txt', TYPE_EASYLIST],
    'bl_easyprivacy' : [False, 'https://easylist-downloads.adblockplus.org/easyprivacy.txt', TYPE_EASYLIST],
    'bl_fbannoyance' : [False, 'https://easylist-downloads.adblockplus.org/fanboy-annoyance.txt', TYPE_EASYLIST],
    'bl_fbenhanced' : [False, 'https://www.fanboy.co.nz/enhancedstats.txt', TYPE_EASYLIST],
    'bl_fbsocial' : [False, 'https://secure.fanboy.co.nz/fanboy-social.txt', TYPE_EASYLIST],
    'bl_hphosts' : [False, 'http://hosts-file.net/ad_servers.txt', TYPE_UNIXLIST],
    'bl_malwaredomainlist' : [False, 'http://www.malwaredomainlist.com/hostslist/hosts.txt', TYPE_UNIXLIST],
    'bl_malwaredomains' : [False, 'http://mirror1.malwaredomains.com/files/justdomains', TYPE_PLAIN],
    'bl_pglyoyo' : [False, 'http://pgl.yoyo.org/adservers/serverlist.php?hostformat=;mimetype=plaintext', TYPE_PLAIN],
    'bl_someonewhocares' : [False, 'http://someonewhocares.org/hosts/hosts', TYPE_UNIXLIST],
    'bl_spam404' : [False, 'https://raw.githubusercontent.com/Dawsey21/Lists/master/adblock-list.txt', TYPE_EASYLIST],
    'bl_swissransom' : [False, 'https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt', TYPE_PLAIN],
    'bl_winhelp2002' : [False, 'http://winhelp2002.mvps.org/hosts.txt', TYPE_UNIXLIST],
    'bl_windowsspyblocker' : [False, 'https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/update.txt', TYPE_UNIXLIST],
    'bl_areasy' : [False, 'https://easylist-downloads.adblockplus.org/Liste_AR.txt', TYPE_EASYLIST],                      #Arab
    'bl_chneasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistchina.txt', TYPE_EASYLIST],                #China
    'bl_deueasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistgermany.txt', TYPE_EASYLIST],              #Germany
    'bl_dnkeasy' : [False, 'https://adblock.dk/block.csv', TYPE_EASYLIST],                                                #Denmark
    'bl_fblatin' : [False, 'https://www.fanboy.co.nz/fanboy-espanol.txt', TYPE_EASYLIST],                                 #Portugal/Spain (Latin Countries)
    'bl_fineasy' : [False, 'https://raw.githubusercontent.com/finnish-easylist-addition/finnish-easylist-addition/master/Finland_adb_uBO_extras.txt', TYPE_EASYLIST],                                     #Finland
    'bl_fraeasy' : [False, 'https://easylist-downloads.adblockplus.org/liste_fr.txt', TYPE_EASYLIST],                     #France
    'bl_grceasy' : [False, 'https://www.void.gr/kargig/void-gr-filters.txt', TYPE_EASYLIST],                              #Greece
    'bl_huneasy' : [False, 'https://raw.githubusercontent.com/szpeter80/hufilter/master/hufilter.txt', TYPE_EASYLIST],    #Hungary
    'bl_idneasy' : [False, 'https://raw.githubusercontent.com/ABPindo/indonesianadblockrules/master/subscriptions/abpindo.txt',TYPE_EASYLIST ],#Indonesia
    'bl_isleasy' : [False, 'http://adblock.gardar.net/is.abp.txt', TYPE_EASYLIST],                                        #Iceland
    'bl_itaeasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistitaly.txt', TYPE_EASYLIST],                #Italy
    'bl_jpneasy' : [False, 'https://raw.githubusercontent.com/k2jp/abp-japanese-filters/master/abpjf.txt', TYPE_EASYLIST],#Japan
    'bl_koreasy' : [False, 'https://raw.githubusercontent.com/gfmaster/adblock-korea-contrib/master/filter.txt', TYPE_EASYLIST],#Korea Easy List
    'bl_korfb' : [False, 'https://www.fanboy.co.nz/fanboy-korean.txt', TYPE_EASYLIST],                                    #Korea Fanboy
    'bl_koryous' : [False, 'https://raw.githubusercontent.com/yous/YousList/master/youslist.txt', TYPE_EASYLIST],         #Korea Yous
    'bl_ltueasy' : [False, 'http://margevicius.lt/easylistlithuania.txt', TYPE_EASYLIST],                                 #Lithuania
    'bl_lvaeasy' : [False, 'https://notabug.org/latvian-list/adblock-latvian/raw/master/lists/latvian-list.txt', TYPE_EASYLIST],#Latvia
    'bl_norfiltre' : [False, 'https://raw.githubusercontent.com/DandelionSprout/adfilt/master/NorwegianList.txt', TYPE_EASYLIST],   #Norway
    'bl_nldeasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistdutch.txt', TYPE_EASYLIST],                #Netherlands
    'bl_poleasy' : [False, 'https://raw.githubusercontent.com/MajkiIT/polish-ads-filter/master/polish-adblock-filters/adblock.txt', TYPE_EASYLIST],#Polish
    'bl_ruseasy' : [False, 'https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt', TYPE_EASYLIST],            #Russia
    'bl_spaeasy' : [False, 'https://easylist-downloads.adblockplus.org/easylistspanish.txt', TYPE_EASYLIST],              #Spain
    'bl_svneasy' : [False, 'https://raw.githubusercontent.com/betterwebleon/slovenian-list/master/filters.txt', TYPE_EASYLIST],#Slovenian
    'bl_sweeasy' : [False, 'https://www.fanboy.co.nz/fanboy-swedish.txt', TYPE_EASYLIST],                                 #Sweden
    'bl_viefb' : [False, 'https://www.fanboy.co.nz/fanboy-vietnam.txt', TYPE_EASYLIST],                                   #Vietnam Fanboy
    'bl_yhosts' : [False, 'https://raw.githubusercontent.com/vokins/yhosts/master/hosts', TYPE_UNIXLIST],                 #China yhosts
}
