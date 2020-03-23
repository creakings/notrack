#NoTrack MariaDB Wrapper
#Author: QuidsUp
#MariaDB Wrapper provides a functions for interacting with the SQL tables that NoTrack uses.

#Additional standard import
import mysql.connector as mariadb

class DBWrapper:
    """ Class Init
        1. TODO load unique password out of php file
        2. Create DB connector
    Args:
        None
    Returns:
        None
    """
    def __init__(self):
        ntrkuser = 'ntrk'
        ntrkpassword = 'ntrkpass'
        ntrkdb = 'ntrkdb'

        print('Opening connection to MariaDB')
        self.__db = mariadb.connect(user=ntrkuser, password=ntrkpassword, database=ntrkdb)

    """ Class Destructor
        Close DB connector
    """
    def __del__(self):
        print('Closing connection to MariaDB')
        self.__db.close()


    """ Create Blocklist Table
        Create SQL table for blocklist, in case it has been deleted
    Args:
        None
    Returns:
        None
    """
    def blocklist_createtable(self):
        cursor = self.__db.cursor()

        cmd = 'CREATE TABLE IF NOT EXISTS blocklist (id SERIAL, bl_source TINYTEXT, site TINYTEXT, site_status BOOLEAN, comment TEXT)';

        print('Checking SQL Table blocklist exists')
        cursor.execute(cmd);
        cursor.close()


    """ Clear Table
        Clear blocklist table and reset serial increment
    Args:
        None
    Returns:
        None
    """
    def blocklist_cleartable(self):
        cursor = self.__db.cursor()

        cursor.execute('DELETE FROM blocklist')
        cursor.execute('ALTER TABLE blocklist AUTO_INCREMENT = 1')
        cursor.close()


    """ Insert data into SQL table blocklist
        Bulk insert a list into MariaDB
        NOTE Single quotes aren't needed around %s as they're added by executemany function
    Args:
        List of data
    Returns:
        None
    """
    def blocklist_insertdata(self, sqldata):
        cmd = ''
        cursor = self.__db.cursor()

        cmd = 'INSERT INTO blocklist (id, bl_source, site, site_status, comment) VALUES (NULL, %s, %s, %s, %s)'

        cursor.executemany(cmd, sqldata)
        self.__db.commit()
        cursor.close()
