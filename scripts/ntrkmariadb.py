#NoTrack MariaDB Wrapper
#Author: QuidsUp
#MariaDB Wrapper provides a functions for interacting with the SQL tables that NoTrack uses.

#Additional standard import
import mysql.connector as mariadb

#Local imports
from ntrkregex import *

class DBWrapper:
    """
    TODO load unique password out of php file
    Create DB connector
    """
    def __init__(self):
        ntrkuser = 'ntrk'
        ntrkpassword = 'ntrkpass'
        ntrkdb = 'ntrkdb'

        #print('Opening connection to MariaDB')
        self.__db = mariadb.connect(user=ntrkuser, password=ntrkpassword, database=ntrkdb)


    def __del__(self):
        """
        Close DB connector
        """
        #print('Closing connection to MariaDB')
        self.__db.close()


    def __search(self, table, search=''):
        """
        Table searcher

        Parameters:
            table (str): Table to search
            search (str): Search to perform
        """
        cmd = '';
        rowcount = 0
        tabledata = []                                         #Results from table
        cursor = self.__db.cursor()

        if search == '':
            cmd = 'SELECT * FROM %s ORDER BY id ASC' % table
        else:
            cmd = 'SELECT * FROM %s WHERE %s ORDER BY id ASC' % (table, search)

        try:
            cursor.execute(cmd);
        except:
            return False
        else:
            tabledata = cursor.fetchall()
            rowcount = cursor.rowcount
        finally:
            cursor.close()


        if rowcount == 0:
            return False

        return tabledata


    def blocklist_createtable(self):
        """
        Create SQL table for blocklist, in case it has been deleted
        """
        cursor = self.__db.cursor()

        cmd = 'CREATE TABLE IF NOT EXISTS blocklist (id SERIAL, bl_source TINYTEXT, site TINYTEXT, site_status BOOLEAN, comment TEXT)';

        print('Checking SQL Table blocklist exists')
        cursor.execute(cmd);
        cursor.close()


    def blocklist_cleartable(self):
        """
        Clear blocklist table and reset serial increment
        """
        cursor = self.__db.cursor()

        cursor.execute('DELETE FROM blocklist')
        cursor.execute('ALTER TABLE blocklist AUTO_INCREMENT = 1')
        cursor.close()


    def blocklist_insertdata(self, sqldata):
        """
        Bulk insert a list into MariaDB
        NOTE Single quotes aren't needed around %s as they're added by executemany function

        Parameters:
            sqldata (list): List of data
        """
        cmd = ''
        cursor = self.__db.cursor()

        cmd = 'INSERT INTO blocklist (id, bl_source, site, site_status, comment) VALUES (NULL, %s, %s, %s, %s)'

        cursor.executemany(cmd, sqldata)
        self.__db.commit()
        cursor.close()


    def blocklist_search(self, s):
        """
        Find and display results from blocklist table
        1. Check user input is valid
        2. Search against domain or comment using regular expression
        3. Display data
        3a. Small number of results is displayed in detail form
        3b. Large lists are displayed in table form

        Parameters:
            s (str): Search string
        """
        i = 1                                              #Table position
        search = ''                                        #SQL Search string

        if not Regex_ValidInput.findall(s):                #Valid input specified?
            print('Invalid search input')
            return

        search = "site REGEXP '%s' OR comment REGEXP '%s'" % (s, s)

        results = self.__search('blocklist', search)

        if results == False:                               #Any results found?
            print('Nothing found')
            return

        if len(results) < 5:                               #Small list detailed view
            for row in results:
                print('Domain    : %s' % row[2])
                print('Blocklist : %s' % row[1])
                print('Comment   : %s' % row[4])
                print()
        else:
            print('#      Block List          Domain                                   Comment')
            print('-      ----------          ------                                   -------')
            for row in results:                            #Large list table view
                #Specify column widths
                #Blocklist name | Domain | Comment
                print('%-6d %-19s %-40s %s' % (i, row[1], row[2], row[4]))
                i += 1
        print()


    def delete_history(self):
        """
        Delete all rows from dnslog and weblog
        NOTE weblog will be deprecated soon
        """
        cursor = self.__db.cursor()

        print('Deleting contents of dnslog and weblog tables')

        cursor.execute('DELETE LOW_PRIORITY FROM dnslog');
        print('Deleting %d rows from dnslog ' % cursor.rowcount)
        cursor.execute('ALTER TABLE dnslog AUTO_INCREMENT = 1');

        cursor.execute('DELETE LOW_PRIORITY FROM weblog');
        print('Deleting %d rows from weblog ' % cursor.rowcount)
        cursor.execute('ALTER TABLE weblog AUTO_INCREMENT = 1');
        self.__db.commit()
