<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2016-06-10
 * For LOVD    : 3.0-16
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
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


class LOVDScreenshotListener implements PHPUnit_Framework_TestListener {
    // Based on "../../vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/Selenium2TestCase/ScreenshotListener.php";

    protected $directory;

    public function __construct($directory=null)
    {
        if (is_null($directory)) {
            $relDir = dirname(__FILE__) . '/../test_results/error_screenshots';
            $directory = realpath($relDir);
        }
        $this->directory = $directory;
    }

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        $this->storeAScreenshot($test);
    }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        $this->storeAScreenshot($test);
    }

    private function storeAScreenshot(PHPUnit_Framework_Test $test)
    {
        if ($test instanceof LOVDSeleniumWebdriverBaseTestCase &&
            $test->driver instanceof \Facebook\WebDriver\Remote\RemoteWebDriver) {
            try {
                $file = $this->directory . '/' . get_class($test) . '__' . $test->getName() . '__' . date('Y-m-d\TH-i-s') . '.png';
                $test->driver->takeScreenshot($file);
                $this->log('Screenshot captured.', $file);
            } catch (Exception $e) {
                $file = $this->directory . '/' . get_class($test) . '__' . $test->getName() . '__' . date('Y-m-d\TH-i-s') . '.txt';
                file_put_contents($file, "Screenshot generation doesn't work." . "\n"
                    . $e->getMessage() . "\n"
                    . $e->getTraceAsString());
                $this->log('Failed to capture screenshot.', $file);
            }
        }
    }

    private function log($message, $path) {
        print($message . "\nSee file: " . $path);
    }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time) {}
    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time) {}
    public function addRiskyTest(PHPUnit_Framework_Test $test, Exception $e, $time) {}
    public function startTest(PHPUnit_Framework_Test $test) {}
    public function endTest(PHPUnit_Framework_Test $test, $time) {}
    public function startTestSuite(PHPUnit_Framework_TestSuite $suite) {}
    public function endTestSuite(PHPUnit_Framework_TestSuite $suite) {}
}
