<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class TestBlockSubmitterRegistration extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testTestBlockSubmitterRegistration ()
    {
        // Test that LOVD can block submitter registration.
        // This test assumes that you're logged in as manager or admin, and it
        //  will leave as a manager.

        // First, set it to *NOT* allowed to register. Go to settings...
        $this->driver->get(ROOT_URL . '/src/settings?edit');

        // Find the element that defines the setting.
        $element = $this->driver->findElement(WebDriverBy::name('allow_submitter_registration'));
        if ($element->getAttribute('checked')) {
            // It's currently allowed. Turn it off!
            $element->click();
            $element = $this->driver->findElement(WebDriverBy::xpath('//input[@type="submit"]'));
            $element->click();
            $this->chooseOkOnNextConfirmation();
        }

        // Log out, then check if element is gone indeed.
        $this->driver->get(ROOT_URL . '/src/logout');

        // There should be no link to register yourself.
        // First, I had this findElements(), but Chrome doesn't like that at all, and times out.
        // Firefox anyway took quite some time, because of the timeout that we have set if elements are not found immediately (normally needed if pages load slowly).
        // $this->assertFalse((bool) count($this->driver->findElements(WebDriverBy::xpath('//a/b[text()="Register as submitter"]'))));
        // New attempt to test for absence of register link.
        $this->assertFalse(strpos($this->driver->findElement(WebDriverBy::xpath('//table[@class="logo"]//td[3]'))->getText(), 'Register as submitter'));

        // Not only the link should be gone. Also the form should no longer work.
        $this->driver->get(ROOT_URL . '/src/users?register');
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"]//td[contains(text(), "Submitter registration is not active in this LOVD installation.")]'));

        // Then, log in as a manager again, and enable the feature again. Then test again.
        $this->driver->get(ROOT_URL . '/src/login');
        $this->enterValue(WebDriverBy::name('username'), 'manager');
        $this->enterValue(WebDriverBy::name('password'), 'test1234');
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@value="Log in"]'));
        $element->click();

        // Change the setting back.
        $this->driver->get(ROOT_URL . '/src/settings?edit');
        $this->setCheckBoxValue(WebDriverBy::name('allow_submitter_registration'), true);
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@type="submit"]'));
        $element->click();
        $this->chooseOkOnNextConfirmation();

        // Log out, and check if registration is allowed again.
        $this->driver->get(ROOT_URL . '/src/logout');

        // Find the link to register yourself.
        $this->driver->findElement(WebDriverBy::xpath('//a/b[text()="Register as submitter"]'));

        // Also verify the form still works.
        $this->driver->get(ROOT_URL . '/src/users?register');
        $this->driver->findElement(WebDriverBy::xpath('//input[contains(@value, "I don\'t have an ORCID ID")]'));

        // Log back in, future tests may need it.
        // FIXME: I want to get rid of this need, by implementing a proper solution for it through the base class,
        $this->driver->get(ROOT_URL . '/src/login');
        $this->enterValue(WebDriverBy::name('username'), 'manager');
        $this->enterValue(WebDriverBy::name('password'), 'test1234');
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@value="Log in"]'));
        $element->click();
    }
}
