<?php
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

function generate_random_string ($nLength)
{
    // Generates random string of length $nLength.
    static $aRange = array();
    if (!$aRange) {
        for ($i = ord('0'); $i <= ord('9'); $i++) {
            $aRange[] = $i;
        }
        for ($i = ord('A'); $i <= ord('Z'); $i++) {
            $aRange[] = $i;
        }
        for ($i = ord('a'); $i <= ord('z'); $i++) {
            $aRange[] = $i;
        }
    }

    $s = '';
    $nMax = count($aRange) - 1;
    for ($i = 0; $i < $nLength; $i ++) {
        $s .= chr($aRange[mt_rand(0, $nMax)]);
    }
    return $s;
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
flush();

// Test on change of size VARCHAR(255) en TEXT, allebei leeg. The actual testing can be done in MySQL monitor directly.
print(hour() . ' Creating a VARCHAR and a TEXT table with 50.000 entries each...' . "\n");
$b = @mysql_query('CREATE TABLE IF NOT EXISTS benchmark_data_varchar (value VARCHAR(255)) TYPE=InnoDB');
if (!$b)
    die('Could not create VARCHAR table: ' . mysql_error());

$b = @mysql_query('CREATE TABLE IF NOT EXISTS benchmark_data_text (value TEXT) TYPE=InnoDB');
if (!$b)
    die('Could not create TEXT table: ' . mysql_error());
flush();


$n = 50000;
$nSteps = $n / 5;

//////////////////////////////////////

print(hour() . ' Inserting data...' . "\n");
flush();
$tStart = mtime();
$iSet = 0;
$tSet = $tStart;
for ($i = 1; $i <= $n; $i ++) {
    $b = @mysql_query('INSERT INTO benchmark_data_varchar VALUES ("' . generate_random_string(100) . '")');
    if (!$b)
        die('Could not insert into VARCHAR table: ' . mysql_error());
    if (!($i%$nSteps)) {
        print(hour() . ' Inserted ' . $i . ' ' . ((mtime() - $tSet)/($i - $iSet)) . ' seconds/query' . "\n");
        flush();
        $iSet = $i;
        $tSet = mtime();
    }
}
$t = mtime() - $tStart;
print(hour() . ' Data inserted in ' . $t . ' seconds with an average of ' . ($t/$n) . ' sec/query' . "\n");
flush();

//////////////////////////////////////

print(hour() . ' Inserting data...' . "\n");
flush();
$tStart = mtime();
$iSet = 0;
$tSet = $tStart;
for ($i = 1; $i <= $n; $i ++) {
    $b = @mysql_query('INSERT INTO benchmark_data_text VALUES ("' . generate_random_string(100) . '")');
    if (!$b)
        die('Could not insert into TEXT table: ' . mysql_error());
    if (!($i%$nSteps)) {
        print(hour() . ' Inserted ' . $i . ' ' . ((mtime() - $tSet)/($i - $iSet)) . ' seconds/query' . "\n");
        flush();
        $iSet = $i;
        $tSet = mtime();
    }
}
$t = mtime() - $tStart;
print(hour() . ' Data inserted in ' . $t . ' seconds with an average of ' . ($t/$n) . ' sec/query' . "\n");
flush();

//////////////////////////////////////

print(hour() . ' Done.' . "\n");
?>