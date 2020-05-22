<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-22
 * Modified    : 2020-05-22
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;

class CheckAuthorizationsTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/genes');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
    }





    /**
     * @depends testSetUp
     */
    public function testAdminRights ()
    {
        // Global'ed because lovd_isAuthorized() needs them!
        global $_AUTH, $_CONF, $_DB;

        // Load the LOVD libraries directly.
        // Settings and constants to prevent notices when including inc-init.php.
        define('FORMAT_ALLOW_TEXTPLAIN', true);
        $_GET['format'] = 'text/plain';
        $_SERVER = array_merge($_SERVER, array(
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => '/' . basename(__FILE__),
            'QUERY_STRING' => '',
            'REQUEST_METHOD' => 'GET',
        ));
        require_once ROOT_PATH . 'inc-init.php';

        // In Travis tests, our users are:
        // 1 - Admin.
        // 2 - Manager.
        // 3 - Curator.
        // 4 - Collaborator.
        // 5 - Owner.
        // 6 - Submitter.
        // 7 - Colleague.

        // Assertions for DATABASE ADMINISTRATOR.
        $_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 1')->fetchAssoc();
        $this->assertNotFalse($_AUTH);
        $this->assertEquals(LEVEL_ADMIN, $_AUTH['level']);
        $this->assertEquals(1, lovd_isAuthorized('user', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('gene', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('disease', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('transcript', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('individual', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('screening', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('does_not_exist', 'does_not_exist'), false);

        $this->assertEquals(1, lovd_isAuthorized('user', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '3'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '4'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '5'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '6'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '7'), false);
        $this->assertEquals(1, lovd_isAuthorized('gene', 'IVD'), false);
        $this->assertEquals(1, lovd_isAuthorized('gene', 'ARSD'), false);
        $this->assertEquals(1, lovd_isAuthorized('disease', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('transcript', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('transcript', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('individual', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('screening', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('screening', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '3'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '4'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '5'), false);

        return [$_CONF, $_DB];
    }





    /**
     * @depends testAdminRights
     */
    public function testManagerRights ($aVariables)
    {
        // Global'ed because lovd_isAuthorized() needs them!
        global $_AUTH, $_CONF, $_DB;
        list($_CONF, $_DB) = $aVariables;

        // In Travis tests, our users are:
        // 1 - Admin.
        // 2 - Manager.
        // 3 - Curator.
        // 4 - Collaborator.
        // 5 - Owner.
        // 6 - Submitter.
        // 7 - Colleague.

        // Assertions for MANAGER.
        $_AUTH = $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 2')->fetchAssoc();
        $this->assertNotFalse($_AUTH);
        $this->assertEquals(LEVEL_MANAGER, $_AUTH['level']);
        $this->assertEquals(1, lovd_isAuthorized('user', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('gene', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('disease', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('transcript', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('individual', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('screening', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', 'does_not_exist'), false);
        $this->assertEquals(1, lovd_isAuthorized('does_not_exist', 'does_not_exist'), false);

        $this->assertEquals(false, lovd_isAuthorized('user', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '3'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '4'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '5'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '6'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '7'), false);
        $this->assertEquals(1, lovd_isAuthorized('gene', 'IVD'), false);
        $this->assertEquals(1, lovd_isAuthorized('gene', 'ARSD'), false);
        $this->assertEquals(1, lovd_isAuthorized('disease', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('transcript', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('transcript', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('individual', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('screening', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('screening', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '3'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '4'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '5'), false);

        return [$_CONF, $_DB];
    }





    /**
     * @depends testManagerRights
     */
    public function testCuratorRights ($aVariables)
    {
        // Global'ed because lovd_isAuthorized() needs them!
        global $_CONF, $_DB;
        list($_CONF, $_DB) = $aVariables;

        // In Travis tests, our users are:
        // 1 - Admin.
        // 2 - Manager.
        // 3 - Curator.
        // 4 - Collaborator.
        // 5 - Owner.
        // 6 - Submitter.
        // 7 - Colleague.

        // Assertions for CURATOR.
        $_SESSION = array(
            'auth' => $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 3')->fetchAssoc(),
        );

        // Let LOVD load the curator, collaborator, and colleagues access.
        // Settings to prevent notices when including inc-auth.php.
        $_SERVER = array_merge($_SERVER, array(
            'REMOTE_ADDR' => '127.0.0.1',
        ));
        require ROOT_PATH . 'inc-auth.php';

        // Requiring inc-auth here, removed access to $_AUTH for lovd_isAuthorized().
        // This makes absolutely no sense whatsoever, I can only conclude that
        //  PHPUnit destroys my data senselessly, just like it's nearly
        //  impossible already to share variables between tests already.
        // $_AUTH is global, which normally works, but not anymore. I have to
        //  *redeclare* it as global, and then *reassign* it. None of this makes
        //  any sense whatsoever, but it's the only way to stop PHPUnit from
        //  destroying the data. Removed the absolete top global declaration.
        global $_AUTH;
        $_AUTH = $_SESSION['auth'];

        $this->assertNotFalse($_AUTH);
        $this->assertEquals(LEVEL_SUBMITTER, $_AUTH['level']);
        $this->assertEquals(false, lovd_isAuthorized('user', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('gene', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('disease', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('transcript', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('individual', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('phenotype', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('screening', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('does_not_exist', 'does_not_exist'), false);

        $this->assertEquals(false, lovd_isAuthorized('user', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '3'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '4'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '5'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '6'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '7'), false);
        $this->assertEquals(1, lovd_isAuthorized('gene', 'IVD'), false);
        $this->assertEquals(false, lovd_isAuthorized('gene', 'ARSD'), false);
        $this->assertEquals(1, lovd_isAuthorized('disease', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('transcript', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('transcript', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('individual', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', '1'), false);
        $this->assertEquals(1, lovd_isAuthorized('phenotype', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('screening', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('screening', '2'), false);
        $this->assertEquals(1, lovd_isAuthorized('variant', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '2'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '3'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '4'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '5'), false);

        return [$_CONF, $_DB];
    }





    /**
     * @depends testCuratorRights
     */
    public function testCollaboratorRights ($aVariables)
    {
        // Global'ed because lovd_isAuthorized() needs them!
        global $_CONF, $_DB;
        list($_CONF, $_DB) = $aVariables;

        // In Travis tests, our users are:
        // 1 - Admin.
        // 2 - Manager.
        // 3 - Curator.
        // 4 - Collaborator.
        // 5 - Owner.
        // 6 - Submitter.
        // 7 - Colleague.

        // Assertions for COLLABORATOR.
        $_SESSION = array(
            'auth' => $_DB->query('SELECT * FROM ' . TABLE_USERS . ' WHERE id = 4')->fetchAssoc(),
        );

        // Let LOVD load the curator, collaborator, and colleagues access.
        // Settings to prevent notices when including inc-auth.php.
        $_SERVER = array_merge($_SERVER, array(
            'REMOTE_ADDR' => '127.0.0.1',
        ));
        require ROOT_PATH . 'inc-auth.php';

        // Requiring inc-auth here, removed access to $_AUTH for lovd_isAuthorized().
        // This makes absolutely no sense whatsoever, I can only conclude that
        //  PHPUnit destroys my data senselessly, just like it's nearly
        //  impossible already to share variables between tests already.
        // $_AUTH is global, which normally works, but not anymore. I have to
        //  *redeclare* it as global, and then *reassign* it. None of this makes
        //  any sense whatsoever, but it's the only way to stop PHPUnit from
        //  destroying the data. Removed the absolete top global declaration.
        global $_AUTH;
        $_AUTH = $_SESSION['auth'];

        $this->assertNotFalse($_AUTH);
        $this->assertEquals(LEVEL_SUBMITTER, $_AUTH['level']);
        $this->assertEquals(false, lovd_isAuthorized('user', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('gene', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('disease', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('transcript', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('individual', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('phenotype', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('screening', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', 'does_not_exist'), false);
        $this->assertEquals(false, lovd_isAuthorized('does_not_exist', 'does_not_exist'), false);

        $this->assertEquals(false, lovd_isAuthorized('user', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '2'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '3'), false);
        $this->assertEquals(1, lovd_isAuthorized('user', '4'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '5'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '6'), false);
        $this->assertEquals(false, lovd_isAuthorized('user', '7'), false);
        $this->assertEquals(0, lovd_isAuthorized('gene', 'IVD'), false);
        $this->assertEquals(false, lovd_isAuthorized('gene', 'ARSD'), false);
        $this->assertEquals(0, lovd_isAuthorized('disease', '1'), false);
        $this->assertEquals(0, lovd_isAuthorized('transcript', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('transcript', '2'), false);
        $this->assertEquals(0, lovd_isAuthorized('individual', '1'), false);
        $this->assertEquals(0, lovd_isAuthorized('phenotype', '1'), false);
        $this->assertEquals(0, lovd_isAuthorized('phenotype', '2'), false);
        $this->assertEquals(0, lovd_isAuthorized('screening', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('screening', '2'), false);
        $this->assertEquals(0, lovd_isAuthorized('variant', '1'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '2'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '3'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '4'), false);
        $this->assertEquals(false, lovd_isAuthorized('variant', '5'), false);
    }
}
?>
