#NoTrack Shared Functions

#Standard imports
import os
import shutil
import sys
import time

#Local imports
import errorlogger

#Create logger
logger = errorlogger.logging.getLogger(__name__)

#Constants
VERSION = '21.03'

def check_root():
    """
    Check script is being run as root
    """
    if os.geteuid() != 0:
        logger.error('This script must be run as root :-(')
        sys.exit(2)


def delete(source):
    """
    Delete a File or Folder

    Parameters:
        source (str): File or Folder to delete
    Returns:
        True on success, False on failure or not needed
    """
    if os.path.isdir(source):                              #Check if source is a folder
        logger.info(f'Deleting folder: {source}')
        try:                                               #Attempt to delete folder
            shutil.rmtree(source, ignore_errors=True)
        except:
            logger.error(f'Error unable to delete folder {source}')
            logger.error(e)
            return False

    elif os.path.isfile(source):                           #Check if source is a file
        logger.info(f'Deleting file: {source}')
        try:                                               #Attempt to delete file
            os.remove(source)
        except OSError as e:
            logger.error(f'Error unable to delete file {source}')
            logger.error(e)
            return False

    else:                                                  #Nothing found
        return False

    return True                                            #Success


def copy(src, dest):
    """
    Copy a File or Folder from source to destination

    Parameters:
        source (str): Source file
        destination (str): Destination file
    Returns:
        True on success, False on failure
    """

    if os.path.isdir(src):                                 #Check if source is a folder
        print(f'Copying folder: {src} to {dest}')
        try:                                               #Attempt to copy folder
            shutil.copytree(src, dest, dirs_exist_ok=True)
        except:
            logger.error(f'Error unable to copy folder {src} to {dest}')
            return False

    elif os.path.isfile(src):                              #Check if source is a file
        print(f'Copying file: {src} to {dest}')
        try:                                               #Attempt to copy the file
            shutil.copy(src, dest)
        except OSError as e:
            logger.error(f'Error unable to copy file {src} to {dest}')
            logger.error(e)
            return False

    else:                                                  #Nothing found
        logger.error(f'Error unable to copy {src}, source is missing')
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
        logger.error(f'Unable to move {source}, file is missing')
        return False

    try:
        shutil.move(source, destination)                   #Attempt to move the file
    except IOError as e:
        logger.error(f'Unable to move {source} to {destination}')
        logger.error(e)
        return False
    else:
        if permissions != 0:
            os.chmod(destination, permissions)

    return True


def get_owner(src):
    """
    Get file / folder ownership details

    Parameters:
        src (str): Source File or Folder
    Returns:
        statinfo class, use st_uid and st_gid for user / group ownership
    """
    if not os.path.isfile(src) and not os.path.isdir(src):
        logger.error(f'Get_Owner: Error {src} is missing')
        return None

    return os.stat(src)


def set_owner(src, uid, gid):
    """
    Set file / folder ownership details

    Parameters:
        src (str): Source File or Folder
        uid (int): User ID
        gid (int): Group ID
    Returns:
        True on success, False on failure
    """
    if os.path.isfile(src):                                #Single file
        try:                                               #Attempt to change ownership
            os.chown(src, uid, gid)
        except OSError as e:
            logger.error(f'Error unable to change ownership of {src}')
            logger.error(e)
            return False

    elif os.path.isdir(src):                               #Multiple files / folders
        for root, dirs, files in os.walk(src):             #Use os.walk to navigate struct
            os.chown(root, uid, gid)
            for item in dirs:
                os.chown(os.path.join(root, item), uid, gid)
            for item in files:
                os.chown(os.path.join(root, item), uid, gid)
    else:
        logger.error(f'Set_Owner: Error {src} is missing')
        return False

    return True


def load_file(filename):
    """
    Load contents of file and return as a list
    1. Check file exists
    2. Read all lines of file

    Returns:
        List of all lines in file
        Empty list if file doesn't exist or error occured
    """
    logger.info(f'Loading {filename}')
    if not os.path.isfile(filename):
        logger.error(f'Unable to load {filename}, file is missing')
        return []

    try:
        f = open(filename, 'r')                            #Open file for reading
    except IOError as e:
        logger.error(f'Unable to read to {filename}')
        logger.error(e)
        return []
    except OSError as e:
        logger.error(f'Unable to read to {filename}')
        logger.error(e)
        return []
    else:
        filelines = f.readlines()
    finally:
        f.close()

    return filelines


def save_file(lines, filename):
    """
    Save a list into a file

    Parameters:
        lines (list): lines of ascii data to save to file
        filename (str): File to save to
    Returns:
        True on success
        False on error
    """
    try:
        f = open(filename, 'w')                            #Open file for ascii writing
    except IOError as e:
        logger.error(f'Unable to write to {filename}')
        logger.error(e)
        return False
    except OSError as e:
        logger.error(f'Unable to write to {filename}')
        logger.error(e)
        return False
    else:
        f.writelines(lines)
        f.close()

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
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:84.0) Gecko/20100101 Firefox/84.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/87.0.4280.88 Chrome/87.0.4280.88 Safari/537.36'
    ]

    logger.info(f'Downloading {url}')

    for i in range(1, 4):
        req = Request(url, headers={'User-Agent': user_agents[i]})
        try:
            response = urlopen(req)
        except HTTPError as e:
            logger.error(f'Unable to download {url}')
            if e.code >= 500 and e.code < 600:
                #Take another attempt up to max of for loop
                logger.error(f'HTTP Error {e.code}: Server side error')
            elif e.code == 400:
                logger.error('HTTP Error 400: Bad request')
                return False
            elif e.code == 403:
                logger.error('HTTP Error 403: Unauthorised Access')
                #return False
            elif e.code == 404:
                logger.error('HTTP Error 404: Not Found')
                return False
            elif e.code == 429:
                logger.error('HTTP Error 429: Too many requests')
        except URLError as e:
            logger.error(f'Unable to download {url}')
            if hasattr(e, 'reason'):
                logger.error(f'Reason: {e.reason}')
                return False
            elif hasattr(e, 'code'):
                logger.error('Server was unable to fulfill the request')
                logger.error(f'HTTP Error: {e.code}')
                return False
        else:
            res_code = response.getcode()
            if res_code == 200:                            #200 - Success
                break
            elif res_code == 204:                          #204 - Success but nothing
                logger.warning(f'HTTP Response 204: No data found from {url}')
                return False
            else:
                logger.warning(f'HTTP Response {res_code} unable to download {url}')

        time.sleep(i * 2)                                  #Throttle repeat attemps

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
        logger.error(f'Error writing to {filename}')
        logger.error(e)
        return False
    except OSError as e:
        logger.error(f'Error writing to {filename}')
        logger.error(e)
        return False
    else:
        f.write(data)
        f.close()
