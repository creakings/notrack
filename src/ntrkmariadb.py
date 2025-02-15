#!/usr/bin/env python3
#Title       : NoTrack MariaDB Wrapper
#Description : MariaDB Wrapper provides a functions for interacting with the SQL tables that NoTrack uses.
#Author      : QuidsUp
#Version     : 20.12
#TODO load unique password out of php file

#Standard Imports

#Additional standard import
import mysql.connector as mariadb

#Local imports
import errorlogger
from ntrkregex import *

#Create logger
logger = errorlogger.logging.getLogger(__name__)

class DBWrapper:
    """
    TODO load unique password out of php file
    """
    def __init__(self):
        """
        Create static value for DBWrapper and open connection to MariaDB
        """
        ntrkuser = 'ntrk'
        ntrkpassword = 'ntrkpass'
        ntrkdb = 'ntrkdb'

        DBWrapper.__db = mariadb.connect(user=ntrkuser, password=ntrkpassword, database=ntrkdb)


    #def __del__(self):
        """
        Close DB connector
        """
        #DBWrapper.__db.close()


    def __execute(self, cmd):
        """
        Execute a SQL command

        Parameters:
            cmd (str): Command to execute
        Returns:
            Success: Row Count
            Failure: False
        """
        cursor = DBWrapper.__db.cursor()                   #Create a cursor
        rowcount = 0                                       #Variable to hold rowcount

        try:
            logger.debug(f'Executing SQL command: {cmd}')
            cursor.execute(cmd)
        except mariadb.Error as e:                         #Catch any errors
            logger.warning(f'Unable to execute {cmd}')
            logger.warning(e)                              #Log the error message
            return False
        else:                                              #Successful execution
            DBWrapper.__db.commit()
            rowcount = cursor.rowcount                     #Get the rowcount
        finally:
            cursor.close()                                 #Close the cursor

        return rowcount


    def __search(self, search):
        """
        Table searcher

        Parameters:
            search (str): Search to perform
        """
        rowcount = 0
        tabledata = []                                     #Results from table
        cursor = DBWrapper.__db.cursor()

        try:
            cursor.execute(search);
        except mariadb.Error as e:
            logger.warning('Search failed :-(')
            logger.warning(e)                              #Log the error message
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
        cmd = 'CREATE TABLE IF NOT EXISTS analytics (id SERIAL, log_time DATETIME, sys TINYTEXT, dns_request TINYTEXT, severity CHAR(1), issue VARCHAR(50), ack BOOLEAN)';

        logger.info('Checking SQL Table analytics exists')
        return self.__execute(cmd)


    def analytics_insertrecord(self, log_time, system, dns_request, severity, issue):
        """
        Add a new record to analytics table

        Parameters:
            recordnum (int): row id
            log_time (str)
            system (str)
            dns_request
            severity (char): '1', '2', '3'
            issue (str)
        Returns:
            True: Successful update
            False: Invalid parameter or error occurred
        """
        cmd = ''
        cursor = DBWrapper.__db.cursor()

        if severity not in ('1', '2', '3'):
            logger.warning(f'Invalid severity {severity}')
            return False

        cmd = f"INSERT INTO analytics (id,log_time,sys,dns_request,severity,issue,ack) VALUES (NULL,'{log_time}','{system}','{dns_request}','{severity}','{issue}',FALSE)"

        return self.__execute(cmd)


    def analytics_trim(self, days):
        """
        Trim rows older than a specified number of days from analytics table
        Parameters:
            days (int): Interval of days to keep
                        When days is set to zero nothing will be deleted
        Returns:
            Success: Number of rows deleted
            Failure: False
        """
        if not isinstance(days, int):                      #Check Days is an integer value
            logger.warning('Invalid number of days specified for analytics_trim')
            return False

        if days == 0:
            logger.info('Days set to zero, keeping logs forever')
            return True

        res = self.__execute(f"DELETE FROM analytics WHERE log_time < NOW() - INTERVAL '{days}' DAY")

        if res != False:
            logger.info(f'Trimmed {res} rows from analytics table')

        return res


    def blocklist_createtable(self):
        """
        Create SQL table for blocklist, in case it has been deleted
        """
        cmd = 'CREATE TABLE IF NOT EXISTS blocklist (id SERIAL, bl_source TINYTEXT, site TINYTEXT, site_status BOOLEAN, comment TEXT)';

        logger.info('Checking SQL Table for blocklist exists')
        return self.__execute(cmd)


    def blocklist_cleartable(self):
        """
        Clear blocklist table and reset serial increment
        """

        self.__execute('DELETE FROM blocklist')
        self.__execute('ALTER TABLE blocklist AUTO_INCREMENT = 1')


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
            logger.info('No blocklists active')
            return []

        logger.info(f'{tabledatalen} blocklists active')

        return self.__single_column(tabledata, 0)


    def blocklist_getdomains_listsource(self):
        """
        Get Domains and List source
        """
        cmd = ''
        tabledata = []

        cmd = 'SELECT site,bl_source FROM blocklist'
        tabledata = self.__search(cmd)

        return tabledata


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
            logger.info('No whitelisted domains')
            return []

        logger.info(f'{tabledatalen} domains whitelisted')

        return self.__single_column(tabledata, 0)



    def blocklist_insertdata(self, sqldata):
        """
        Bulk insert a list into MariaDB
        Large data blocks are sliced up to avoid exceeding memory limitations of
         data uploading into MariaDB

        Parameters:
            sqldata (list): List of data
        """
        cmd = ''                                           #Bulk Insert command
        i = 0
        sqldatalen = 0                                     #Size of sqldata parameter
        totalrowcount = 0                                  #Total of rows added
        cursor = DBWrapper.__db.cursor()                   #Create a cursor
        dataslice = list()                                 #Temporary slice of sqldata

        cmd = 'INSERT INTO blocklist (id, bl_source, site, site_status, comment) VALUES (NULL, %s, %s, %s, %s)'

        sqldatalen = len(sqldata)
        logger.info(f'Adding {sqldatalen} domains into blocklist table')

        if sqldatalen < 100000:                            #Small data blocks can be added
            cursor.executemany(cmd, sqldata)               #Insert small block as is
            DBWrapper.__db.commit()                        #Commit insert
            totalrowcount = cursor.rowcount                #Row count is literal

        else:                                              #Larger blocks must be split
            for i in range(0, sqldatalen, 100000):         #Split into 100K blocks
                dataslice = sqldata[i:i+99999]             #Slice list
                cursor.executemany(cmd, dataslice)         #Insert slice into MariaDB
                DBWrapper.__db.commit()                    #Commit insert
                totalrowcount += cursor.rowcount           #Tally count

        cursor.close()                                     #Done with cursor
        logger.info(f'Completed adding {totalrowcount} rows to blocklist table')


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


    def blockliststats_createtable(self):
        """
        Create SQL table for blockliststats, in case it has been deleted
        bl_source (KEY), filelines, linesused
        """
        cmd = 'CREATE TABLE IF NOT EXISTS blockliststats (bl_source VARCHAR(50), filelines BIGINT UNSIGNED, linesused BIGINT UNSIGNED, PRIMARY KEY (bl_source))';

        logger.info('Checking SQL Table for blockliststats exists')
        return self.__execute(cmd);



    def blockliststats_insert(self, listname, filelines, linesused):
        """
        Insert / Replace data in blockliststats

        Parameters:
            listname(str)
            filelines(int)
            linesused(int)
        Returns:
            None
        """
        cmd = '';

        #Force line to be updated on a duplicate entry of the blocklist name
        cmd = f"INSERT INTO blockliststats (bl_source, filelines, linesused) VALUES ('{listname}','{filelines}','{linesused}') ON DUPLICATE KEY UPDATE bl_source='{listname}'"

        self.__execute(cmd)


    #DNS Log Table
    def dnslog_createtable(self):
        """
        Create SQL table for dnslog, in case it has been deleted
        """
        cmd = ''
        cmd = 'CREATE TABLE IF NOT EXISTS dnslog (id SERIAL, log_time DATETIME, sys TINYTEXT, dns_request TINYTEXT, severity CHAR(1), bl_source VARCHAR(50))';

        logger.info('Checking SQL Table dnslog exists')
        return self.__execute(cmd)


    def dnslog_insertdata(self, sqldata):
        """
        Bulk insert a list into dnslog
        NOTE Single quotes aren't needed around %s as they're added by executemany function

        Parameters:
            sqldata (list): List of data
        """
        cmd = ''
        cursor = DBWrapper.__db.cursor()

        cmd = 'INSERT INTO dnslog (id, log_time, sys, dns_request, severity, bl_source) VALUES (NULL, %s, %s, %s, %s, %s)'

        cursor.executemany(cmd, sqldata)
        DBWrapper.__db.commit()
        logger.info(f'Added {cursor.rowcount} rows to dnslog table')
        cursor.close()


    def dnslog_searchmalware(self, bl):
        """
        Get past hour of results from dnslog looking for results from a blocklist

        Parameters:
        bl (str): Enabled blocklist to search from
        """
        cmd = ''
        tabledata = []

        cmd = f"SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND dns_request IN (SELECT site FROM blocklist WHERE bl_source = '{bl}') GROUP BY(dns_request) AND severity = 1 AND bl_source IN ('allowed', 'cname') ORDER BY id asc"

        tabledata = self.__search(cmd)

        return(tabledata)


    def dnslog_searchregex(self, pattern):
        """
        Get past hour of results from dnslog based on a regex pattern

        Parameters:
            pattern (str): Regex pattern to search
        """
        cmd = ''
        tabledata = []

        cmd = f"SELECT * FROM dnslog WHERE log_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND dns_request REGEXP '{pattern}' AND severity = '1' AND bl_source IN ('allowed', 'cname') ORDER BY id asc"

        tabledata = self.__search(cmd)

        return(tabledata)


    def dnslog_trim(self, days):
        """
        Trim rows older than a specified number of days from dnslog table
        Parameters:
            days (int): Interval of days to keep
                        When days is set to zero nothing will be deleted
        Returns:
            Success: Number of rows deleted
            Failure: False
        """
        if not isinstance(days, int):                      #Check Days is an integer value
            logger.warning('Invalid number of days specified for dnslog_trim')
            return False

        if days == 0:
            logger.info('Days set to zero, keeping logs forever')
            return True

        res = self.__execute(f"DELETE FROM dnslog WHERE log_time < NOW() - INTERVAL '{days}' DAY")

        if res != False:
            logger.info(f'Trimmed {res} rows from dnslog table')

        return res


    def dnslog_updaterecord(self, recordnum, severity, bl_source):
        """
        Update the dns_result value in dnslog table

        Parameters:
            recordnum (int): row id
            dns_result: New value for dns_result (M, T)
        Returns:
            True: Successful update
            False: Invalid parameter or error occurred
        """
        cmd = ''
        cursor = DBWrapper.__db.cursor()

        if not isinstance(recordnum, int):                #Check record is an integer value
            logger.warning(f'Invalid record number {recordnum}')
            return False

        if severity not in ('1', '2', '3'):
            logger.warning(f'Invalid Severity {severity}')
            return False

        cmd = f"UPDATE dnslog SET severity='{severity}', bl_source = '{bl_source}' WHERE id={recordnum}"

        return self.__execute(cmd)

