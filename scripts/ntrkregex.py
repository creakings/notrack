#NoTrack Regular Expressions
#Author: QuidsUp

#Standard Imports
import re

Regex_Defanged = re.compile('^(?:f[txX]p|h[txX][txX]ps?)\[?:\]?\/\/([\w\.\-_\[\]]{1,250}\[?\.\]?[\w\-]{2,63})')

#Regex to extract domain.co.uk from subdomain.domain.co.uk
Regex_Domain = re.compile('([\w\-_]{1,63})(\.(?:co\.|com\.|org\.|edu\.|gov\.)?[\w\-_]{1,63}$)')

#Regex to validate a domain entry
Regex_ValidDomain = re.compile('^[\w\.\-_]{1,250}\.[\w\-]{2,63}$')

#Regex to validate an input for SQL search
Regex_ValidInput = re.compile('^[\w\.\-_]{1,253}$')

#Regex EasyList Line:
#|| Marks active domain entry
#Group 1: domain.com (Need to eliminate IP addresses, so assume TLD begins with [a-z]
#Non-capturing group: Domain ending
#Non-capturing group: Against document type: Acceptable - third-party, doc, popup
Regex_EasyLine = re.compile('^\|\|([\w\.\-_]{1,250}\.[a-zA-Z][\w\-]{1,62})(?:\^|\.)(?:\$third\-party|\$doc|\$popup|\$popup\,third\-party)?\n$')

#Regex Plain Line
#Group 1: domain.com
#Group 2: optional comment.
#Utilise negative lookahead to make sure that two hashes aren't next to each other,
# as this could be an EasyList element hider
Regex_PlainLine = re.compile('^([\w\.\-_]{1,250}\.[\w\-]{2,63})( #(?!#).*)?\n$')

#Regex TLD Line:
Regex_TLDLine = re.compile('^(\.\w{1,63})(?:\s#.*)?\n$')

#Regex Unix Line
Regex_UnixLine = re.compile('^(?:0|127)\.0\.0\.[01]\s+([\w\.\-_]{1,250}\.[\w\-]{2,63})\s*#?(.*)\n$')
