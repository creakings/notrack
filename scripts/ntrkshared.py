#NoTrack Shared Functions

import os


""" Delete File
    1. Check file exists
    2. Attempt to delete file
Args:
    File to delete
Returns:
    True on success
    False on failure or not needed
"""
def delete_file(filename):
    if os.path.isfile(filename):
        print('Deleting old file %s' % filename)
        try:
            os.remove(filename)
            print('%s deleted successfully' % filename)
            return True
        except OSError as e:
            print(e)

    return False


""" Move File
    1. Check source exists
    2. Move file
Args:
    source
    destination
Returns:
    True on success
    False on failure
"""
def move_file(source, destination):
    import shutil

    if not os.path.isfile(source):
        print('Move_file: Error %s is missing' % source)
        return False

    #Copy specified file
    shutil.move(source, destination)

    if not os.path.isfile(destination):                    #Check move has been successful
        print('Move_file: Error %s does not exist. Copy failed')
        return False

    return True
