<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2020-05-12
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2016-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

class LOVDScreenshotListener implements PHPUnit\Framework\TestListener
{
    // Takes a screenshot on failing tests.
    // Based on PHPUnit's Selenium2TestCase/ScreenshotListener.php

    protected $directory;





    public function __construct ($directory = null)
    {
        if (is_null($directory)) {
            $relDir = dirname(__FILE__) . '/../test_results/error_screenshots';
            $directory = realpath($relDir);
        }
        $this->directory = $directory;
    }





    public function addError (PHPUnit\Framework\Test $test, Exception $e, $time)
    {
        $this->storeAScreenshot($test, $e);
    }





    public function addFailure (PHPUnit\Framework\Test $test, PHPUnit\Framework\AssertionFailedError $e, $time)
    {
        $this->storeAScreenshot($test, $e);
    }





    private function storeAScreenshot (PHPUnit\Framework\Test $test, $e)
    {
        // Store screenshot. Also try to print some information on the error.
        // Unfortunately, we don't seem to have access to the precise location
        //  in our code where the failure occured. Only PHP Unit knows this, and
        //  they show it at the end of the test suite.

        if ($test instanceof LOVDSeleniumWebdriverBaseTestCase &&
            $test->driver instanceof \Facebook\WebDriver\Remote\RemoteWebDriver) {

            fwrite(STDERR, PHP_EOL .
                $e->getMessage() . PHP_EOL .
                get_class($test) . '::' . $test->getName() . PHP_EOL);

            try {
                $file = $this->directory . '/' . date('Y-m-d\TH-i-s') . '__' . get_class($test) . '__' . $test->getName() . '.png';
                $test->driver->takeScreenshot($file);
            } catch (Exception $e) {
                $file = $this->directory . '/' . date('Y-m-d\TH-i-s') . '__' . get_class($test) . '__' . $test->getName() . '.txt';
                file_put_contents($file, "Screenshot generation doesn't work." . PHP_EOL
                    . $e->getMessage() . PHP_EOL
                    . $e->getTraceAsString());
            }
        }
    }





    public function endTestSuite (PHPUnit\Framework\TestSuite $suite)
    {
        // Normally run at the end of the test suite, but PHPUnit decides that
        //  every file in our suite is a suite in itself. So, this is run after
        //  every file is complete, but it saves us from having to call it from
        //  .travis.yml which makes it look like yet another test.
        // Upload all the screenshots, if we have any.
        system(dirname(__FILE__) . '/../upload_test_results.sh');
    }





    // Really dumb, but since PHPUnit\Framework\TestListener doesn't
    //  implement these, but does define them, we should implement them.
    // Yes, it makes no sense.
    public function addIncompleteTest (PHPUnit\Framework\Test $test, Exception $e, $time) {}
    public function addSkippedTest (PHPUnit\Framework\Test $test, Exception $e, $time) {}
    public function addRiskyTest (PHPUnit\Framework\Test $test, Exception $e, $time) {}
    public function startTest (PHPUnit\Framework\Test $test) {}
    public function endTest (PHPUnit\Framework\Test $test, $time) {}
    public function startTestSuite (PHPUnit\Framework\TestSuite $suite) {}
}
