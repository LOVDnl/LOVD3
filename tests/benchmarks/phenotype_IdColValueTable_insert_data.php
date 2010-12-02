<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-08-16
 * Modified    : 2010-08-16
 * For LOVD    : 3.0-pre-08
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 * The intention of this test is to check the feasibility of creating one big
 * phenotype table, in which all phenotype data is stored, no matter the
 * disease. It has been calculated (2010-08-11) that if we would combine the 21
 * most "normal" (out of 27) LOVD installations, we would end up with a
 * phenotype table of around 84 columns, of which on average 25% will be used.
 * Possibly, we would get away with this, especially if there are not that many
 * patients in the database. But benchmarking would be best.
 *
 * Testing this with a phenotype table with 100 custom columns, of which 25%
 * will be in use and all LOVD specific columns are in use always.
 *
 * Checking both INSERT time over time and SELECT time.
 *
 *************/

define('TABLE_USERS', 'benchmark_lovd_v3_users');
define('TABLE_DISEASES', 'benchmark_lovd_v3_diseases');
define('TABLE_PATIENTS', 'benchmark_lovd_v3_patients');
define('TABLE_PHENOTYPES', 'benchmark_lovd_v3_phenotypes');
define('TABLE_PHENOTYPE_DATA', 'benchmark_lovd_v3_phenotype_data');
define('COUNT_PATIENTS', 125000);
define('COUNT_PHEN2PAT', 4); // # phenotypes per patient.
define('COUNT_PHENOTYPES', COUNT_PATIENTS * COUNT_PHEN2PAT);
define('COUNT_PHENOTYPE_DATA', 15); // # "columns" of phenotype data filled.

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
$db = @mysql_select_db('test');

if (!$db)
    die('Cannot connect to database');
print(hour() . ' Database OK' . "\n");


////////////////////////////////////////////////////////////////////////////////


/*
// We need the users table to test the foreign key checks.
@mysql_query('SET foreign_key_checks = 0');
@mysql_query('DROP TABLE IF EXISTS ' . TABLE_USERS);
@mysql_query('CREATE TABLE ' . TABLE_USERS . ' (
    id SMALLINT(5) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    name VARCHAR(75) NOT NULL,
    institute VARCHAR(75) NOT NULL,
    department VARCHAR(75) NOT NULL,
    telephone VARCHAR(50) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    email TEXT NOT NULL,
    username VARCHAR(20) NOT NULL,
    password CHAR(32) NOT NULL,
    level TINYINT(1) UNSIGNED NOT NULL,
    login_attempts TINYINT(1) UNSIGNED NOT NULL,
    created_by SMALLINT(5) UNSIGNED,
    created_date DATETIME NOT NULL,
    edited_by SMALLINT(5) UNSIGNED,
    edited_date DATETIME,
    PRIMARY KEY (id),
    UNIQUE (username),
    INDEX (created_by),
    INDEX (edited_by),
    FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL)
    TYPE=InnoDB,
    DEFAULT CHARACTER SET utf8');
$db = @mysql_query('INSERT INTO ' . TABLE_USERS . ' VALUES (NULL, "Ivo Fokkema", "LUMC", "HG", "+31 70 536 9438", "asdfasdf\r\nasdfasdf", "Leiden", "I.F.A.C.Fokkema@LUMC.nl", "ifokkema", md5("test"), 9, 0, 1, NOW(), NULL, NULL)');

if (!$db)
    die('Cannot fill TABLE_USERS: ' . mysql_error());
print(hour() . ' TABLE_USERS OK' . "\n");
@mysql_query('SET foreign_key_checks = 1');


////////////////////////////////////////////////////////////////////////////////


// We need the diseases table to test the foreign key checks.
@mysql_query('SET foreign_key_checks = 0');
@mysql_query('DROP TABLE IF EXISTS ' . TABLE_DISEASES);
@mysql_query('CREATE TABLE ' . TABLE_DISEASES . ' (
    id SMALLINT(5) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    symbol VARCHAR(10) NOT NULL,
    name VARCHAR(75) NOT NULL,
    id_omim INT(10) UNSIGNED NOT NULL,
    created_by SMALLINT(5) UNSIGNED,
    created_date DATETIME NOT NULL,
    edited_by SMALLINT(5) UNSIGNED,
    edited_date DATETIME,
    PRIMARY KEY (id),
    INDEX (created_by),
    INDEX (edited_by),
    FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL)
    TYPE=InnoDB,
    DEFAULT CHARACTER SET utf8');
$db = @mysql_query('INSERT INTO ' . TABLE_DISEASES . ' VALUES (NULL, "A", "Disease A", 0, 1, NOW(), NULL, NULL), (NULL, "B", "Disease B", 0, 1, NOW(), NULL, NULL), (NULL, "C", "Disease C", 0, 1, NOW(), NULL, NULL), (NULL, "D", "Disease D", 0, 1, NOW(), NULL, NULL)');
if (!$db)
    die('Cannot fill TABLE_DISEASE: ' . mysql_error());
print(hour() . ' TABLE_DISEASE OK' . "\n");
@mysql_query('SET foreign_key_checks = 1');


////////////////////////////////////////////////////////////////////////////////


// We need the patients table to test the foreign key checks.
@mysql_query('SET foreign_key_checks = 0');
@mysql_query('DROP TABLE IF EXISTS ' . TABLE_PATIENTS);
$b = @mysql_query('CREATE TABLE ' . TABLE_PATIENTS . ' (
    id MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
    ownerid SMALLINT(5) UNSIGNED ZEROFILL,
    created_by SMALLINT(5) UNSIGNED,
    created_date DATETIME NOT NULL,
    edited_by SMALLINT(5) UNSIGNED,
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NOT NULL DEFAULT "9999-12-31",
    deleted BOOLEAN NOT NULL,
    deleted_by SMALLINT(5) UNSIGNED,
    PRIMARY KEY (id, valid_from),
    INDEX (valid_to),
    INDEX (ownerid),
    INDEX (created_by),
    INDEX (edited_by),
    INDEX (deleted_by),
    FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL)
    TYPE=InnoDB,
    DEFAULT CHARACTER SET utf8');
if (!$b)
    die('Could not create TABLE_PATIENTS: ' . mysql_error());
@mysql_query('SET foreign_key_checks = 1');

//////////////////////////////////////

print(hour() . ' Inserting patients...' . "\n");
flush();

$tStart = mtime();
$iSet = 0;
$tSet = $tStart;
for ($i = 1; $i <= COUNT_PATIENTS; $i ++) {
    $b = @mysql_query('INSERT INTO ' . TABLE_PATIENTS . ' VALUES (' . $i . ', 1, 1, NOW(), NULL, NOW(), "9999-12-31", 0, NULL)');
    if (!$b)
        die('Could not insert into TABLE_PATIENTS: ' . mysql_error());
    if (!($i%(COUNT_PATIENTS/10))) {
        print(hour() . ' Inserted ' . $i . ' ' . ((mtime() - $tSet)/($i - $iSet)) . ' seconds/query' . "\n");
        flush();
        $iSet = $i;
        $tSet = mtime();
    }
}
$t = mtime() - $tStart;
print(hour() . ' Patients inserted in ' . $t . ' seconds with an average of ' . ($t/COUNT_PATIENTS) . ' sec/query' . "\n");
exit; // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


////////////////////////////////////////////////////////////////////////////////


// Creating the tables and filling, please skip in case you want to speed up the benchmark.
@mysql_query('DROP TABLE IF EXISTS ' . TABLE_PHENOTYPES);
$b = @mysql_query('CREATE TABLE ' . TABLE_PHENOTYPES . ' (
    id INT(10) UNSIGNED ZEROFILL NOT NULL,
    diseaseid SMALLINT(5) UNSIGNED ZEROFILL NOT NULL,
    patientid MEDIUMINT(8) UNSIGNED ZEROFILL NOT NULL,
    ownerid SMALLINT(5) UNSIGNED ZEROFILL,
    created_by SMALLINT(5) UNSIGNED,
    created_date DATETIME NOT NULL,
    edited_by SMALLINT(5) UNSIGNED,
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NOT NULL DEFAULT "9999-12-31",
    deleted BOOLEAN NOT NULL,
    deleted_by SMALLINT(5) UNSIGNED,
    PRIMARY KEY (id, valid_from),
    INDEX (valid_to),
    INDEX (diseaseid),
    INDEX (patientid),
    INDEX (ownerid),
    INDEX (created_by),
    INDEX (edited_by),
    INDEX (deleted_by),
    FOREIGN KEY (diseaseid) REFERENCES ' . TABLE_DISEASES . ' (id) ON DELETE CASCADE,
    FOREIGN KEY (patientid) REFERENCES ' . TABLE_PATIENTS . ' (id) ON DELETE CASCADE,
    FOREIGN KEY (ownerid) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (edited_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES ' . TABLE_USERS . ' (id) ON DELETE SET NULL)
    TYPE=InnoDB,
    DEFAULT CHARACTER SET utf8');
if (!$b)
    die('Could not create TABLE_PHENOTYPES: ' . mysql_error());

print(hour() . ' TABLE_PHENOTYPES created' . "\n");
flush();

//////////////////////////////////////

print(hour() . ' Inserting phenotype IDs...' . "\n");
flush();
set_time_limit(300); // 10 minutes PHP stuff, so MySQL excluded. With 30 seconds it already almost got halfway, so no problems expected.
@mysql_query('TRUNCATE ' . TABLE_PHENOTYPES);
$tStart = mtime();
$iSet = 0;
$tSet = $tStart;
for ($i = 1; $i <= COUNT_PHENOTYPES; $i ++) {
    $nPatientID = ceil($i / COUNT_PHEN2PAT);
    $nDiseaseID = $i%4;
    if (!$nDiseaseID)
        $nDiseaseID = 4;
    $sSQL = 'INSERT INTO ' . TABLE_PHENOTYPES . ' (id, diseaseid, patientid, ownerid, created_by, created_date, valid_from, deleted) VALUES (' . $i . ', ' . $nDiseaseID . ', ' . $nPatientID . ', 1, 1, NOW(), NOW(), 0)';
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not insert into TABLE_PHENOTYPES: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    if (!($i%(COUNT_PHENOTYPES/20))) {
        print(hour() . ' Inserted ' . $i . ' ' . ((mtime() - $tSet)/($i - $iSet)) . ' seconds/query' . "\n");
        flush();
        $iSet = $i;
        $tSet = mtime();
    }
}
$t = mtime() - $tStart;
print(hour() . ' Phenotype IDs inserted in ' . $t . ' seconds with an average of ' . ($t/COUNT_PHENOTYPES) . ' sec/query' . "\n");
exit; // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


////////////////////////////////////////////////////////////////////////////////


// Creating the tables and filling, please skip in case you want to speed up the benchmark.
@mysql_query('DROP TABLE IF EXISTS ' . TABLE_PHENOTYPE_DATA);
$b = @mysql_query('CREATE TABLE ' . TABLE_PHENOTYPE_DATA . ' (
    phenotypeid INT(10) UNSIGNED ZEROFILL NOT NULL,
    colid VARCHAR(255) NOT NULL,
    val_char VARCHAR(255),
    val_int BIGINT,
    val_date DATETIME,
    PRIMARY KEY (phenotypeid, colid),
    FOREIGN KEY (phenotypeid) REFERENCES ' . TABLE_PHENOTYPES . ' (id) ON DELETE CASCADE)
    TYPE=InnoDB,
    DEFAULT CHARACTER SET utf8');
if (!$b)
    die('Could not create TABLE_PHENOTYPE_DATA: ' . mysql_error());

print(hour() . ' TABLE_PHENOTYPE_DATA created' . "\n");
flush();

//////////////////////////////////////

print(hour() . ' Inserting phenotype data...' . "\n");
flush();
set_time_limit(300); // 10 minutes PHP stuff, so MySQL excluded. With 30 seconds it already almost got halfway, so no problems expected.
@mysql_query('TRUNCATE ' . TABLE_PHENOTYPE_DATA);
$tStart = mtime();
$iSet = 0;
$tSet = $tStart;
for ($i = 1; $i <= COUNT_PHENOTYPES; $i ++) {
    $nPatientID = ceil($i / COUNT_PHEN2PAT);
    $nDiseaseID = $i%4;
    if (!$nDiseaseID)
        $nDiseaseID = 4;
    $jTo   = $nDiseaseID*COUNT_PHENOTYPE_DATA;
    $jFrom = $jTo - (COUNT_PHENOTYPE_DATA - 1);
    for ($j = $jFrom; $j <= $jTo; $j ++) {
        $sSQL = 'INSERT INTO ' . TABLE_PHENOTYPE_DATA . ' (phenotypeid, colid, val_char) VALUES (' . $i . ', "DiseaseCol' . str_pad($j, 3, '0', STR_PAD_LEFT) . '", "Random text, does not really matter what gets inserted. Just random stuff. Probably means something.")';
        $b = @mysql_query($sSQL);
        if (!$b)
            die('Could not insert into TABLE_PHENOTYPE_DATA: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    }
    if (!($i%(COUNT_PHENOTYPES/50))) {
        print(hour() . ' Inserted ' . $i . ' ' . ((mtime() - $tSet)/($i - $iSet)) . ' seconds/' . COUNT_PHENOTYPE_DATA . ' queries' . "\n");
        flush();
        $iSet = $i;
        $tSet = mtime();
    }
}
$t = mtime() - $tStart;
print(hour() . ' Phenotype data inserted in ' . $t . ' seconds with an average of ' . ($t/COUNT_PHENOTYPES) . ' sec/' . COUNT_PHENOTYPE_DATA . ' queries' . "\n");
exit; // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
*/


////////////////////////////////////////////////////////////////////////////////


// Now, test selecting data as it may be sloooooooowwwwwwwwwww...
print(hour() . ' Selecting 1000 patients with ' . COUNT_PHENOTYPE_DATA . ' phenotype columns, simple query without ORDER BY...' . "\n");
flush();
$sSQLStart = 'SELECT SQL_NO_CACHE p.*, ph.id AS phenotypeid';
for ($i = 1; $i <= COUNT_PHENOTYPE_DATA; $i++) {
    $sCol = 'DiseaseCol' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $sSQLStart .= ', (SELECT val_char FROM ' . TABLE_PHENOTYPE_DATA . ' AS d WHERE d.phenotypeid = ph.id AND d.colid = "' . $sCol . '") AS `' . $sCol . '`';
}
$sSQLStart .= ' FROM ' . TABLE_PATIENTS . ' AS p LEFT JOIN ' . TABLE_PHENOTYPES . ' AS ph ON (p.id = ph.patientid) WHERE p.valid_to = "9999-12-31" AND ph.valid_to = "9999-12-31" AND ph.diseaseid = 1';
$tStart = mtime();
$nLoop = 25;
$sSQL = $sSQLStart . ' LIMIT 1000';
/*
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' Data SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();
*/

//////////////////////////////////////

/*
print(hour() . ' (...) ORDER BY on indexed patient column' . "\n");
flush();
$tStart = mtime();
$sSQL = $sSQLStart . ' ORDER BY p.ownerid LIMIT 1000';
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' Data SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();
*/

//////////////////////////////////////

print(hour() . ' (...) ORDER BY on non-indexed phenotype data column' . "\n");
flush();
$nLoop = 5;
$tStart = mtime();
$sSQL = $sSQLStart . ' ORDER BY DiseaseCol001 LIMIT 1000';
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' Data SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();

//////////////////////////////////////

/*
print(hour() . ' (...) WHERE on non-indexed phenotype data column (no hits)' . "\n");
flush();
$tStart = mtime();
$sSQL = $sSQLStart . ' AND EXISTS (SELECT * FROM ' . TABLE_PHENOTYPE_DATA . ' AS d WHERE d.phenotypeid = ph.id AND d.colid = "DiseaseCol001" AND d.val_char LIKE "%asdf%") LIMIT 1000';
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    $n = mysql_num_rows($b);
    if (!$b)
        die('Could not SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' Data SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();
*/

//////////////////////////////////////

/*
print(hour() . ' (...) WHERE on non-indexed phenotype data column (all hits, no LIMIT)' . "\n");
flush();
$tStart = mtime();
$sSQL = $sSQLStart . ' AND EXISTS (SELECT * FROM ' . TABLE_PHENOTYPE_DATA . ' AS d WHERE d.phenotypeid = ph.id AND d.colid = "DiseaseCol001" AND d.val_char LIKE "%inserted%")';
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' Data SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();
*/

//////////////////////////////////////

print(hour() . ' (...) WHERE on non-indexed phenotype data column (all hits, no LIMIT) + ORDER BY on non-indexed patient column' . "\n");
flush();
$tStart = mtime();
$sSQL = $sSQLStart . ' AND EXISTS (SELECT * FROM ' . TABLE_PHENOTYPE_DATA . ' AS d WHERE d.phenotypeid = ph.id AND d.colid = "DiseaseCol001" AND d.val_char LIKE "%inserted%") ORDER BY p.valid_from';
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' Data SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();

//////////////////////////////////////

print(hour() . ' (...) WHERE on non-indexed phenotype data column (all hits, no LIMIT) + ORDER BY on non-indexed phenotype column' . "\n");
flush();
$tStart = mtime();
$sSQL = $sSQLStart . ' AND EXISTS (SELECT * FROM ' . TABLE_PHENOTYPE_DATA . ' AS d WHERE d.phenotypeid = ph.id AND d.colid = "DiseaseCol001" AND d.val_char LIKE "%inserted%") ORDER BY DiseaseCol001';
for ($i = 1; $i <= $nLoop; $i ++) {
    $b = @mysql_query($sSQL);
    if (!$b)
        die('Could not SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
    $n = mysql_num_rows($b);
    if (!$n)
        die('No results returned in SELECT phenotype data: ' . mysql_error() . "\n" . 'Query was: ' . $sSQL);
}
$t = mtime() - $tStart;
print(hour() . ' Data SELECT (' . $n . ' rows) complete in ' . $t . ' seconds with an average of ' . ($t/$nLoop) . ' sec/query' . "\n");
flush();




exit; // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<








// daarna, test of latin1 en utf-8 veel scheelt qua opslag
// Check TEXT vs. VARCHAR data opslag en tijd.


?>