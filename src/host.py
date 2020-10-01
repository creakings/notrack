import socket

class Host:
    """
    Host gets the Name and IP address of this system
    If an IP has been supplied then use that instead of trying to find the system IP
    Usecase is for when NoTrack is used on a VPN
    Parameters:
    conf_ip (str): config.ipaddress
    """
    def __init__(self, conf_ip):
        self.name = ''
        self.ip = ''
        self.name = socket.gethostname()                   #Host Name is easy to get
        #Has a non-loopback config - ipaddress been supplied?
        if conf_ip == '127.0.0.1' or conf_ip == '::1' or conf_ip == '':
            #Make a network connection to unroutable 169.x IP in order to get system IP
            #Connect to an unroutable 169.254.0.0/16 address on port 1
            try:
                s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
                s.connect(("169.254.0.255", 1))
            except OSError as e:
                print('Host Init: Error - Unable to open network connection')
                sys.exit(1)
            else:
                self.ip = s.getsockname()[0]
            finally:
                s.close()
        else:
            self.ip = conf_ip
