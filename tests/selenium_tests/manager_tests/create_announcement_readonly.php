<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateAnnouncementReadOnly extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateAnnouncementReadOnly ()
    {
        // Create an announcement, that switches LOVD into the read-only state.
        // This test assumes you're logged in as manager or admin.
        $sAnnouncement = 'This is a test announcement. LOVD will be closed for registrations, and lower level users can not log in.';

        $this->driver->get(ROOT_URL . '/src/announcements?create');

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/announcements[\s\S]create$/', $this->driver->getCurrentURL()));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="type"]/option[text()="Warning"]'));
        $option->click();
        $this->enterValue(WebDriverBy::name('announcement'), $sAnnouncement);
        $this->enterValue(WebDriverBy::name('start_date'), ''); // No value, means active from now().
        $this->enterValue(WebDriverBy::name('end_date'), ''); // No value, means active until '9999-12-31 23:59:59'.
        $this->check(WebDriverBy::name('lovd_read_only'));
        $this->enterValue(WebDriverBy::name('password'), 'test1234');
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@value="Create announcement"]'));
        $element->click();

        $this->driver->findElement(WebDriverBy::xpath('//td[text()="Successfully created the announcement!"]')); // Finds any TD with this text.

        // Also check if announcement is actually visible.
        $this->assertEquals($sAnnouncement, $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());

        // Wait for redirect...
        $this->waitUntil(WebDriverExpectedCondition::titleContains('Announcement'));
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/announcements\/\d{5}$/', $this->driver->getCurrentURL()));
    }
}
