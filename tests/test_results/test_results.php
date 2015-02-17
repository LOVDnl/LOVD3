<?php
$file = $_GET['file'];
$front = strpos($file, 'testlog') + 8;
$back = strpos($file, '.xml');
?>
<html>
	<body>
		<h1> PHPUnit - Selenium test results </h1>
		<h4>
			<?php echo "Date test run: " . substr($file, $front, ($back - $front)); ?>
		</h4>
		<?php $xml=simplexml_load_file($file);
		
		foreach($xml->children() as $classe)
		{
			?><b><?php echo "Number of tests: "; ?></b><?php
			echo $classe->attributes()->tests;
			?><b><?php echo "  Assertions: "; ?></b><?php
			echo $classe->attributes()->assertions;
			?><b><?php echo "  Failures: "; ?></b><?php
			echo $classe->attributes()->failures;
			?><b><?php echo "  Errors: "; ?></b><?php
			echo $classe->attributes()->errors;
			?><b><?php echo "  Time: "; ?></b><?php
			$minutes = floor($classe->attributes()->time/60);
			$seconds = $classe->attributes()->time % 60;
			$seconds = sprintf( '%02d', $seconds );
			echo $minutes.":".$seconds."  (min:sec) <br>";
			//echo $classe->attributes()->time . "<br>";
	
			// There are small differences in the xml files for one test class and for
			// multiple test classes. These require diferent layouts.
			$classname = $classe->attributes()->file;
			if(file_exists($classname))
			{			
				?><b><?php echo "Class: "; ?></b><?php
				echo $classe->attributes()->name . "<br>";
				printLayoutSingleClass($classe);
			}
			else
			{
				?><b><?php echo "Folder: "; ?></b><?php
				echo $classe->attributes()->name . "<br>";
				printLayoutMultipleleClasses($classe);
			}
			
		}
		?>
	</body>
</html>

<?php

function printLayoutSingleClass($classe)
{
	?><ol type="1"><?php
								
	foreach($classe->children() as $testcase)
	{
		?><li><b><?php echo "Method name: " ?></b>
		<i><?php echo $testcase->attributes()->name . "<br>";
		?></i></li><?php

		printTestCases($testcase);
	}
	?></ol>	<?php	
}

function printLayoutMultipleleClasses($classe)
{	 
	foreach($classe->children() as $testsuite)
	{
		?><b><?php echo "Class name: "; ?></b><?php
		echo $testsuite->attributes()->name . "<br>";
		?><ol type="1"><?php
								
		foreach($testsuite->children() as $testcase)
		{
			?><li><b><?php echo "Method name: " ?></b>
			<i><?php echo $testcase->attributes()->name . "<br>";
					?></i></li><?php

			printTestCases($testcase);
		}
		?></ol>	<?php	
	}
}

function printTestCases($testcase)
{
	foreach($testcase->children() as $errors)
	{
		echo $errors->getName() . ": " . $errors . "<br>";
		echo $errors->attributes() . ": " . $errors . "<br>";
		echo "<br>";

		$breakdownerrors = explode(": ", $errors);
		// Extract the URL on failure from the error.
		$urlonerror = strpos($breakdownerrors[1], 'Screenshot') - 1;
		echo "URL on failure: " . substr($breakdownerrors[1], 0, $urlonerror) . "<br>";
		
		// Extract the path for screenshots on failure from the error.
		$screenshotpath = strpos($breakdownerrors[2], '.png') + 4;
		$localhost = substr($breakdownerrors[1], 0, $urlonerror);
		$localhost = explode('/trunk/src', $localhost);
		$filename = substr($breakdownerrors[2], 0 ,$screenshotpath);
		$imageLocation = $localhost[0]."/".$filename;
		
		echo "Image path: " . $imageLocation . "<br>";
		print('<IMG src="'.$imageLocation.'" alt="Screenshot on Failure" style="width:40%;height:40%;border:5px outset black"><BR><BR>');
	}
	
	
}