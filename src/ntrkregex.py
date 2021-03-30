#NoTrack Regular Expressions
#Author: QuidsUp

#Standard Imports
import re

Regex_Defanged = re.compile('^(?:f[txX]p|h[txX][txX]ps?)\[?:\]?\/\/([\w\.\-_\[\]]{1,250}\[?\.\]?[\w\-]{2,63})')

#Regex to extract domain.co.uk from subdomain.domain.co.uk
Regex_Domain = re.compile('([\w\-]{1,63})(\.(?:co\.|com\.|org\.|edu\.|gov\.)?[\w\-]{1,63}$)')

#
Regex_TLD = re.compile(r'(\.(?:co\.|com\.|org\.|edu\.|gov\.)?[\w\-]{1,63})$')

#Regex to validate a domain entry
Regex_ValidDomain = re.compile('^[\w\.\-]{1,250}\.[\w\-]{2,63}$')

#Regex to validate an input for SQL search
Regex_ValidInput = re.compile('^[\w\.\-]{1,253}$')

#Regex CSV Line:
#Any number of spaces
#Group 1: domain.com
#Tab or Comma seperator
#Group 2: Comment
Regex_CSV = re.compile('^\s*([\w\.\-]{1,253})[\t,]([\w]{1,255})')

#Regex EasyList Line:
#|| Marks active domain entry
#Group 1: domain.com (Need to eliminate IP addresses, so assume TLD begins with [a-z]
#Non-capturing group: Domain ending
#Non-capturing group: Against document type: Acceptable - ^, $3p, $third-party, $all, $doc, $document, $popup, $popup,third-party
Regex_EasyLine = re.compile('^\|\|([\w\.\-]{1,250}\.[a-zA-Z][\w\-]{1,62})(?:\^|\.)(?:\^|\$3p|\$third\-party|\^\$?all|\$doc|\$document|\$popup|\$popup,third\-party)?\n$')

#Regex Plain Line
#Group 1: domain.com
#Group 2: optional comment.
#Utilise negative lookahead to make sure that two hashes aren't next to each other,
# as this could be an EasyList element hider
Regex_PlainLine = re.compile(r'^([\w\-\.]{1,253})( #(?!#).*)?\n$')

#Regex TLD Line:
Regex_TLDLine = re.compile('^(\.\w{1,63})(?:\s#.*)?\n$')

#Regex Unix Line
Regex_UnixLine = re.compile('^(?:0|127)\.0\.0\.[01]\s+([\w\.\-]{1,250}\.[\w\-]{2,63})\s*#?(.*)\n$')

#Version from bl_notrack DEPRECATED
Regex_Version = re.compile('^# ?LatestVersion (\d{1,2}\.\d{1,2}\.?\d?\d?)\n$')

Regex_BlockListStatus = re.compile('^\$this\->set_blocklist_status\(\'(bl_\w{2,25})\', (true|false)\);\n$')

Regex_BlockListCustom = re.compile('^\$this\->set_blocklist_custom\(\'(.{0,2000})\'\);\n$')
