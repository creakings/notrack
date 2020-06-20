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


    def __search(self, search):
        """
        Table searcher

        Parameters:
            search (str): Search to perform
        """
        rowcount = 0
        tabledata = []                                     #Results from table
        cursor = self.__db.cursor()

        try:
            cursor.execute(search);
        except:
            print('Search failed :-( {}'.format(error))
        else:
            tabledata = cursor.fetchall()
            rowcount = cursor.rowcount
        finally:
            cursor.close()

        if rowcount == 0:                                  #Nothing found, return empty
            return []

        return tabledata


    def __single_column(self, tabledata, col):
        """
        Extract single column of tabledata

        Parameters:
            tabledata (list): List of tupples from cursor.fetchall
            col (int): Column number of data to extract
        Returns
            list of strings
        """
        coldata = []                                       #Data to return

        for row in tabledata:                              #Read each row from table data
            coldata.append(row[col])                       #Add data from appropriate col

        return coldata


    def analytics_createtable(self):
        """
        Create SQL table for analytics, in case it has been deleted
        """
        cursor = self.__db.cursor()

        cmd = 'CREATE TABLE IF NOT EXISTS analytics (id SERIAL, log_time DATETIME, sys TINYTEXT, dns_request TINYTEXT, dns_result CHAR(1), issue TINYTEXT, ack BOOLEAN)';

        print('Checking SQL Table for analytics exists')
        cursor.execute(cmd);
        cursor.close()


    def analytics_searchmalware(self, bl):
        """

        """
        cmd = ''
        tabledata = []

        cmd = "SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND dns_request IN (SELECT site FROM blocklist WHERE bl_source = '%s') GROUP BY(dns_request) ORDER BY id asc" % bl

        tabledata = self.__search(cmd)

        return(tabledata)


    def analytics_searchtracker(self, pattern):
        """

        """
        cmd = ''
        tabledata = []

        cmd = "SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND dns_request REGEXP '%s' AND dns_result='A' GROUP BY(dns_request) ORDER BY id asc" % pattern

        tabledata = self.__search(cmd)

        return(tabledata)


    def blocklist_createtable(self):
        """
        Create SQL table for blocklist, in case it has been deleted
        """
        cursor = self.__db.cursor()

        cmd = 'CREATE TABLE IF NOT EXISTS blocklist (id SERIAL, bl_source TINYTEXT, site TINYTEXT, site_status BOOLEAN, comment TEXT)';

        print('Checking SQL Table for blocklist exists')
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


    def blocklist_getactive(self):
        """
        Get list of blocklists in use
        """
        cmd = ''
        tabledata = []
        tabledatalen = 0

        cmd = 'SELECT DISTINCT bl_source FROM blocklist'

        tabledata = self.__search(cmd)
        tabledatalen = len(tabledata)

        if tabledatalen == 0:
            print('No blocklists active')
            return []

        print('%d blocklists active' % tabledatalen)

        return self.__single_column(tabledata, 0)


    def blocklist_getwhitelist(self):
        """
        Get list of whitelisted domains
        """
        cmd = ''
        tabledata = []
        tabledatalen = 0

        cmd = "SELECT site from blocklist WHERE bl_source = 'whitelist'"

        tabledata = self.__search(cmd)
        tabledatalen = len(tabledata)

        if tabledatalen == 0:
            print('No whitelisted domains')
            return []

        print('%d domains whitelisted' % tabledatalen)

        return self.__single_column(tabledata, 0)



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
        cmd = ''                                           #SQL Search string
        results = []                                       #Table data
        resultslen = 0

        print('Blocklist searcher')

        if not Regex_ValidInput.findall(s):                #Valid input specified?
            print('Invalid search input')
            return

        cmd = "SELECT * FROM blocklist WHERE site REGEXP '%s' OR comment REGEXP '%s' ORDER BY id ASC" % (s, s)

        results = self.__search(cmd)
        resultslen = len(results)

        if resultslen == 0:                                #Any results found?
            print('No domains or comments found named %s' % s)
            return

        print('%d domains found named %s' % (resultslen, s))
        print()

        if resultslen < 5:                                 #Do a detailed view for a small list
            for row in results:
                print('Domain    : %s' % row[2])
                print('Blocklist : %s' % row[1])
                print('Comment   : %s' % row[4])
                print()
        else:
            #Column headers
            print('#      Block List          Domain                                   Comment')
            print('-      ----------          ------                                   -------')
            for row in results:                            #Large list, do a table view
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
