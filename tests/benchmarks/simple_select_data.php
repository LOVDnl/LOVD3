<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-09-09
 * Modified    : 2010-09-09
 * For LOVD    : 3.0-pre-09
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

define('TABLE_USERS', 'lovd_v3_users');
define('TABLE_COLS', 'lovd_v3_columns');
define('TABLE_ACTIVE_COLS', 'lovd_v3_active_columns');

// Function from the microtime manual page, just renamed and reformatted a bit.
function mtime ()
{
    // Return current time(), including microseconds.
    list($usec, $sec) = explode(' ', microtime());
    return ((float) $usec + (float) $sec);
}

function hour ()
{
    // Returns the current time, for printing purposes.
    return date('H:i:s');
}



header('Content-type: text/plain; charset=UTF-8');
ini_set('default_charset','UTF-8');

print('BENCHMARKING...' . "\n" . hour() . ' Starting...' . "\n");

@mysql_connect('localhost', 'lovd', 'lovd_pw');
@mysql_query('SET AUTOCOMMIT=1');
$db = @mysql_select_db('lovd3');

if (!$db)
    die('Cannot connect to database');
print(hour() . ' Database OK' . "\n");


////////////////////////////////////////////////////////////////////////////////


// Now, test selecting data to see what is fastest.
print(hour() . ' Selecting all columns with JOINs to TABLE_USERS and TABLE_ACTIVE_COLS, using ORDER BY without WHERE...' . "\n");
flush();
$sSQL = 'SELECT SQL_NO_CACHE c.*, SUBSTRING_INDEX(c.id, "/", 1) AS category, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid, (a.created_by > 0) AS active, u.name AS created_by_ FROM ' . TABLE_COLS . ' AS c LEFT JOIN ' . TABLE_ACTIVE_COLS . ' AS a ON (c.id = a.colid) LEFT JOIN ' . TABLE_USERS . ' AS u ON (c.created_by = u.id) ORDER BY category, colid';
$tStart = mtime();
$nLoop = 1000;
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();

//////////////////////////////////////

print(hour() . ' Idem, maar dan met subqueries' . "\n");
flush();
$tStart = mtime();
$sSQL = 'SELECT SQL_NO_CACHE c.*, SUBSTRING_INDEX(c.id, "/", 1) AS category, SUBSTRING(c.id, LOCATE("/", c.id)+1) AS colid, (SELECT a.created_by > 0 FROM ' . TABLE_ACTIVE_COLS . ' AS a WHERE c.id = a.colid) AS active, (SELECT u.name FROM ' . TABLE_USERS . ' AS u WHERE c.created_by = u.id) AS created_by_ FROM ' . TABLE_COLS . ' AS c ORDER BY category, colid';
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();

//////////////////////////////////////




exit; // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<



?>