<?php

// open this directory
$reportDirectory = "../test_results/reports";
$usedDirectory = opendir($reportDirectory);

// get each entry
while($entryName = readdir($usedDirectory)) {
	$dirArray[] = $entryName;
}

// close directory
closedir($usedDirectory);

//	count elements in array
$indexCount	= count($dirArray);

// sort array
rsort($dirArray);

?><TABLE border=1 cellpadding=5 cellspacing=0 class=whitelinks>
	<TR>
		<th>Filename</th>
		<th>Filetype</th>
		<th>Filesize</th>
		<th>ChangeTime</th>
		<th>ModifyTime</th>
		<th>Number of tests</th>
		<th>Assertions</th>
		<th>Failures</th>
		<th>Errors</th>
		<th>Time</th></TR><?php
	// loop through the array of files and print them all
	for($index=0; $index < $indexCount; $index++)
	{
		if (substr("$dirArray[$index]", 0, 1) != "."){ // don't list hidden files
			?><TR> <?php
				print("<TR><TD><a href=\"test_results.php?file=$reportDirectory/$dirArray[$index]\">$dirArray[$index]</a></td>"); ?>
				<td><?php echo filetype("$reportDirectory/$dirArray[$index]"); ?></td>
				<td><?php echo filesize("$reportDirectory/$dirArray[$index]"); ?></td>
				<td><?php echo date("d-m-Y H:i:s", filectime("$reportDirectory/$dirArray[$index]")); ?></td>
				<td><?php echo date("d-m-Y H:i:s", filemtime("$reportDirectory/$dirArray[$index]")); ?></td>
				<td><?php echo getNumberOfTests("$reportDirectory/$dirArray[$index]"); ?></td>
				<td><?php echo getNumberOfAssertions("$reportDirectory/$dirArray[$index]"); ?></td>
				<td><?php echo getNumberOfFailures("$reportDirectory/$dirArray[$index]"); ?></td>
				<td><?php echo getNumberOfErrors("$reportDirectory/$dirArray[$index]"); ?></td>
				<td><?php echo getRunningTime("$reportDirectory/$dirArray[$index]"); ?></td>
			</TR><?php
		}
	}
?></TABLE>


<?php

function getNumberOfTests($file)
{
	$xml = file_get_contents($file);
	if (trim($xml) != '') {
		$xml=simplexml_load_file($file);
		if ($xml) {
			return $xml->testsuite[0]->attributes()->tests;
		}
	}
	return "ND";
}

function getNumberOfAssertions($file)
{
	$xml = file_get_contents($file);
	if (trim($xml) != '') {
		$xml=simplexml_load_file($file);
		if ($xml) {
			return $xml->testsuite[0]->attributes()->assertions;
		}
	}
	return "ND";
}

function getNumberOfFailures($file)
{
	$xml = file_get_contents($file);
	if (trim($xml) != '') {
		$xml=simplexml_load_file($file);
		if ($xml) {
			return $xml->testsuite[0]->attributes()->failures;
		}
	}
	return "ND";
}

function getNumberOfErrors($file)
{
	$xml = file_get_contents($file);
	if (trim($xml) != '') {
	$xml=simplexml_load_file($file);
		if ($xml) {
			return $xml->testsuite[0]->attributes()->errors;
		}
	}
	return "ND";
}

function getRunningTime($file)
{
	$xml = file_get_contents($file);
	if (trim($xml) != '') {
		$xml=simplexml_load_file($file);
		if ($xml) {
			$minutes = floor($xml->testsuite[0]->attributes()->time/60);
			$seconds = $xml->testsuite[0]->attributes()->time % 60;
			$seconds = sprintf( '%02d', $seconds );
			return $minutes.":".$seconds."  (min:sec)";
		}
	}
	return "ND";
}
