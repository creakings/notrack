#NoTrack Shared Functions

import os
import shutil
import time


def check_module(mod):
    """ Check Module Exists
    Checks specified module exists

    Parameters:
        mod (str): Module to check
    Returns:
        True - Module exists
        False - Module not installed
    """
    import importlib.util

    spec = importlib.util.find_spec(mod)

    if spec is None:
        print('Check_module: Error - %s not found' % mod)
        return False
    else:
        #print('Check_module: %s can be imported' % mod)
        return True


def check_root():
    """
    Check script is being run as root
    """
    if os.geteuid() != 0:
        print('Error - This script must be run as root')
        print('NoTrack must be run as root', file=sys.stderr)
        sys.exit(2)


def delete_file(source):
    """
    Delete a File

    Parameters:
        source (str): File to delete
    Returns:
        True on success, False on failure or not needed
    """
    if not os.path.isfile(source):                         #Check file exists
        return False

    print('Deleting old file', source)
    try:                                                   #Attempt to delete file
        os.remove(source)
    except OSError as e:
        print('Delete_file: Error unable to delete', source)
        print(e)
        return False
    else:
        print('%s deleted successfully' % source)

    return True


def delete_folder(source):
    """
    Delete a Folder

    Parameters:
        source (str): Folder to delete
    Returns:
        True on success, False on failure or not needed
    """
    if not os.path.isdir(source):                          #Check folder exists
        return False

    print('Deleting old folder', source)
    try:                                                   #Attempt to delete file
        shutil.rmtree(source, ignore_errors=True)
    except:
        print('Delete_folder: Error unable to delete', source)
        #print(e)
        return False
    else:
        print('%s deleted successfully' % source)

    return True


def copy_file(source, destination):
    """
    Copy a File or Folder from source to destination

    Parameters:
        source (str): Source file
        destination (str): Destination file
    Returns:
        True on success, False on failure
    """

    #Check source file/folder exists
    if not os.path.isfile(source) and not os.path.isdir(source):
        print('Copy_file: Error %s is missing' % source)
        return False

    try:
        shutil.copy(source, destination)                   #Attempt to copy the file
    except IOError as e:
        print('Copy_file: Unable to move %s to %s' % (source, destination))
        print(e)
        return False

    return True



def move_file(source, destination, permissions=0):
    """
    Move a File or Folder from source to destination
    Optional: Set file permissions

    Parameters:
        source (str): Source file
        destination (str): Destination file
        permissions (octal): Optional file permissions
    Returns:
        True on success, False on failure
    """

    #Check source file/folder exists
    if not os.path.isfile(source) and not os.path.isdir(source):
        print('Move_file: Error %s is missing' % source)
        return False

    try:
        shutil.move(source, destination)                   #Attempt to move the file
    except IOError as e:
        print('Move_file: Unable to move %s to %s' % (source, destination))
        print(e)
        return False
    else:
        if permissions != 0:
            os.chmod(destination, permissions)

    return True


def download_file(url, destination):
    """
    Download File
    1. Make 3 attempts at downloading a file using a different user-agent each time
    2. Some sites reject the default python/urllib agent, so we try wget first
    followed by Chrome on Windows 10, Firefox on Linux x64, then Chromium on Linux x64
    3. Save File to destination

    Parameters:
        URL, List Name, File Destination
    Returns:
        True - Success
        False - Failed download
    """
    from urllib.request import Request, urlopen
    from urllib.error import HTTPError, URLError

    user_agents = [                                        #Selection of user-agents to try
        '',                                                #Padding
        'wget/1.20.3',                                     #Start with wget
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:74.0) Gecko/20100101 Firefox/74.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/80.0.3987.149 Chrome/80.0.3987.149 Safari/537.36'
    ]



    print('\tDownloading %s' % url)

    for i in range(1, 4):
        req = Request(url, headers={'User-Agent': user_agents[i]})
        try:
            response = urlopen(req)
        except HTTPError as e:
            if e.code >= 500 and e.code < 600:
                #Take another attempt up to max of for loop
                print('\tHTTP Error %d: Server side error' % e.code)
            elif e.code == 400:
                print('\tHTTP Error 400: Bad request')
                return False
            elif e.code == 403:
                print('\tHTTP Error 403: Unauthorised Access')
                #return False
            elif e.code == 404:
                print('\tHTTP Error 404: Not Found')
                return False
            elif e.code == 429:
                print('\tHTTP Error 429: Too many requests')
            print('\t%s' % url)
        except URLError as e:
            if hasattr(e, 'reason'):
                print('\tError downloading %s' % url)
                print('\tReason: %s' % e.reason)
                return False
            elif hasattr(e, 'code'):
                print('\t%s' % url)
                print('Server was unable to fulfill the request')
                print('\tHTTP Error: %d' % e.code)
                return False
        else:
            res_code = response.getcode()
            if res_code == 200:                            #200 - Success
                break
            elif res_code == 204:                          #204 - Success but nothing
                print('\tHTTP Response 204: No data found')
                return False
            else:
                print('\t%s' % url)
                print('\tHTTP Response %d' % res_code)

        time.sleep(i * 2)                                       #Throttle repeat attemps

    save_blob(response.read(), destination)                #Write file to destination

    return True


def unzip_multiple_files(sourcezip, destination):
    from zipfile import ZipFile

    with ZipFile(sourcezip) as zipobj:
        zipobj.extractall(path=destination)
        zipobj.close()



def save_blob(data, filename):
    """
    Save Blob
    Save a binary blob to a file

    Parameters:
        data to save to disk
        filename
    Returns:
        None
    """
    try:
        f = open(filename, 'wb')                           #Open file for binary writing
    except IOError as e:
        print('Error writing to %s' % filename)
        print(e)
        return False
    except OSError as e:
        print('Error writing to %s' % filename)
        print(e)
        return False
    else:
        f.write(data)
        f.close()
