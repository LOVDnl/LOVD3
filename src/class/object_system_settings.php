<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2012-04-13
 * For LOVD    : 3.0-beta-04
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require ROOT_PATH . 'class/objects.php';





class LOVD_SystemSetting extends LOVD_Object {
    // This class, handling the System Settings, extends the basic Object class.
    var $sObject = 'Settings';
    var $sTable  = 'TABLE_CONFIG';





    function checkFields ($aData)
    {
        // Checks fields before submission of data.
        global $_SETT;

        $this->aCheckMandatory =
                 array(
                        'system_title',
                        'email_address',
                        'refseq_build',
                      );
        parent::checkFields($aData);

        // Database URL is mandatory, if the option "Include in the global LOVD listing" is selected.
        if (!empty($aData['include_in_listing']) && empty($aData['location_url'])) {
            lovd_errorAdd('location_url', 'Please fill in an URL in the \'Database URL\' field, if you want this LOVD installation to be included in the global LOVD listing.');
        }

        // Database URL should be an URL.
        if (!empty($aData['location_url']) && !lovd_matchURL($aData['location_url'])) {
            lovd_errorAdd('location_url', 'Please fill in a correct URL in the \'Database URL\' field.');
        }

        // Email address.
        if (!empty($aData['email_address']) && !lovd_matchEmail($aData['email_address'])) {
            lovd_errorAdd('email_address', 'Please fill in a correct email address.');
        }

        // Refseq build should match the available builds.
        if (!empty($aData['refseq_build']) && !array_key_exists($aData['refseq_build'], $_SETT['human_builds'])) {
            lovd_errorAdd('refseq_build', 'Please select one of the available Human Builds.');
        }

        // Proxy server checks (valid hostname, valid port number, try connecting.
        if (!empty($aData['proxy_host'])) {
            // Pattern taken from lovd_matchURL().
            if (!preg_match('/^([0-9]{1,3}(\.[0-9]{1,3}){3}|(([0-9a-z][-0-9a-z]*[0-9a-z]|[0-9a-z])\.?)+[a-z]{2,6})$/i', $aData['proxy_host'])) {
                lovd_errorAdd('proxy_host', 'Please fill in a correct host name of the proxy server, if you wish to use one.');
            } elseif (empty($aData['proxy_port'])) {
                lovd_errorAdd('proxy_port', 'Please fill in a correct, numeric, port number of the proxy server, if you wish to use a proxy server.');
            } else {
                // Alright, let's try and connect.
                // First: normal connect, direct, no outside connection requested.
                $f = @fsockopen($aData['proxy_host'], $aData['proxy_port'], $nError, $sError, 5);
                if ($f === false) {
                    lovd_errorAdd('proxy_host', 'Could not connect to given proxy server. Please check if the fields are correctly filled in.');
                } else {
                    $sRequest = 'GET ' . $_SETT['check_location_URL'] . ' HTTP/1.0' . "\r\n" .
                                'User-Agent: LOVDv.' . $_SETT['system']['version'] . " Proxy Check\r\n" . // Will be passed on to LOVD.nl.
                                'Connection: Close' . "\r\n\r\n";
                    fputs($f, $sRequest);
                    $s = rtrim(fgets($f));
                    if (!preg_match('/^HTTP\/1\.. [23]/', $s, $aRegs)) { // Allowing HTTP 2XX and 3XX.
                        lovd_errorAdd('proxy_host', 'Unexpected answer from proxy when trying to connect upstream: ' . $s);
                    }
                }
            }

        } elseif (!empty($aData['proxy_port'])) {
            // We have a port number, but no host name.
            lovd_errorAdd('proxy_host', 'Please also fill in a host name of the proxy server.');
        }

        // Custom logo must exist.
        if (!empty($aData['logo_uri'])) {
            // Determine if file can be read and is an image or not.
            if (!is_readable(ROOT_PATH . $aData['logo_uri'])) {
                lovd_errorAdd('logo_uri', 'Cannot read the custom logo file. Please make sure the path is correct and that the file can be read.');
            } else {
                $a = @getimagesize(ROOT_PATH . $aData['logo_uri']);
                if (!is_array($a)) {
                    lovd_errorAdd('logo_uri', 'The custom logo file that you selected does not seem to be a picture.');
                }
            }
        } else {
            // FIXME; this is probably not the best way of doing this...
            $_POST['logo_uri'] = 'gfx/LOVD_logo130x50.jpg';
        }
        
        // FIXME; Like above, not the best solution, but gets the job done for now.
        if (empty($aData['mutalyzer_soap_url'])) {
            $_POST['mutalyzer_soap_url'] = 'http://www.mutalyzer.nl/2.0/services';
        }
        
        // SSL check.
        if (!empty($aData['use_ssl']) && !SSL) {
            lovd_errorAdd('use_ssl', 'You\'ve selected to force the use of SSL, but SSL is not currently activated for this session. To force SSL, I must be sure it\'s possible to approach LOVD through an SSL connection (use <A href="https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING']? '?' . str_replace('&sent=true', '', $_SERVER['QUERY_STRING']) : '') . '" target="_blank">https://</A> instead of http://).');
        }
        
        $_POST['api_feed_history'] = 0;
        $_POST['allow_count_hidden_entries'] = 0;
        $_POST['use_versioning'] = 0;

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS();
    }



    function getForm ()
    {
        // Build the form.
        global $_SETT;

        $aHumanBuilds = array();
        foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
            $aHumanBuilds[$sCode] = $sCode . ' / ' . $aBuild['ncbi_name'];
        }

        $aFeedHistory = array('Not available');
        for ($i = 1; $i <= 12; $i ++) {
            $aFeedHistory[$i] = $i . ' month' . ($i == 1? '' : 's');
        }

        $this->aFormData =
                 array(
                        array('POST', '', '', '', '335', '14', ''),
                        array('', '', 'print', '<B>General system settings</B>'),
                        'hr',
                        array('Title of this LOVD installation', 'This will be shown on the top of every page.', 'text', 'system_title', 45),
                        array('Institute (optional)', 'The institute which runs this database is displayed in the public area and in emails sent by LOVD. Is commonly set to a laboratory name or a website name.', 'text', 'institute', 45),
                        array('Database URL (optional)', 'This is the URL with which the database can be accessed by the outside world, including "http://" or "https://". It will also be used in emails sent by LOVD. This field is mandatory if you select the "Include in the global LOVD listing" option.<BR>If you click the "check" link, LOVD will verify or try to predict the value.', 'print', '<INPUT type="text" name="location_url" size="40" id="location_url" value="' . (empty($_POST['location_url'])? '' : htmlspecialchars($_POST['location_url'])) . '"' . (!lovd_errorFindField('location_url')? '' : ' class="err"') . '>&nbsp;<SPAN id="location_url_check">(<A href="#" onclick="javascript:lovd_checkURL(); return false;">check</A>)</SPAN>'),
                        array('LOVD email address', 'This email address will be used to send emails from LOVD to users. We need this address to make sure that emails from LOVD arrive. Please note that although strictly spoken this email address does not need to exist, we recommend that you use a valid address.', 'text', 'email_address', 40),
                        array('Forward messages to database admin?', 'This will forward messages to the database administrator about submitter registrations and submissions.', 'checkbox', 'send_admin_submissions'),
      'refseq_build' => array('Human Build to map to (UCSC/NCBI)', 'We need to know which version of the Human Build we need to map the variants in this LOVD to.', 'select', 'refseq_build', 1, $aHumanBuilds, false, false, false),
                        //array('List database changes in feed for how long?', 'LOVD includes a "newsfeed" that allows users to get a list of changes recently made in the database. Select here how many months back you want changes to appear on this list. Set to "Not available" to disable the newsfeed.', 'select', 'api_feed_history', 1, $aFeedHistory, false, false, false),
                        array('List database changes in feed for how long?', 'LOVD includes a "newsfeed" that allows users to get a list of changes recently made in the database. Select here how many months back you want changes to appear on this list. Set to "Not available" to disable the newsfeed.', 'print', '&nbsp;<I style="color : #666666;">Not yet implemented</I>'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Connection settings (optional)</B>'),
                        array('', '', 'note', 'Some networks have no access to the outside world except through a proxy. If this applies to the network this server is installed on, please fill in the proxy server information here.'),
                        'hr',
                        array('Proxy server host name', 'The host name of the proxy server, such as www-cache.institution.edu.', 'text', 'proxy_host', 20),
                        array('Proxy server port number', 'The port number of the proxy server, such as 3128.', 'text', 'proxy_port', 4),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Customize LOVD</B>'),
                        array('', '', 'note', 'Here you can customize the way LOVD looks. We will add new options here later.'),
                        'hr',
                        array('System logo', 'If you wish to have your custom logo on the top left of every page instead of the default LOVD logo, enter the path to the image here, relative to the LOVD installation path.', 'text', 'logo_uri', 40),
                        array('', '', 'note', 'Currently, only images already uploaded to the LOVD server are allowed here.'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Global LOVD statistics</B>'),
                        array('', '', 'note', 'The following settings apply to the kind of information your LOVD install sends to the development team to gather statistics about global LOVD usage.'),
                        'hr',
                        array('Send statistics?', 'This sends <I>anonymous</I> statistics about the number of submitters, genes, individuals and mutations in your installation of LOVD.', 'checkbox', 'send_stats'),
                        array('Include in the global LOVD listing?', 'We keep a public listing of LOVD installations, their genes and their URLs. Deselect this checkbox if you do not want to be included in this public listing.', 'checkbox', 'include_in_listing'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Security settings</B>'),
                        array('', '', 'note', 'Using the following settings you can control some security settings of LOVD.'),
                        'hr',
                        array('Lock users after 3rd failed login?', 'Do you want to lock users (submitters, curators and managers) after three failed attempts to log in using their username?<BR>(This does <I>not</I> affect the database administrator account)', 'checkbox', 'lock_users'),
                        array('Allow (locked) users to retrieve a new password?', 'Do you want to enable an "I forgot my password" option that allows users who forgot their password to retrieve a new one?', 'checkbox', 'allow_unlock_accounts'),
                        array('Enable submitters to change data?', 'Enabling this setting allows submitters to make changes to data previously submitted by them or assigned to them.', 'checkbox', 'allow_submitter_mods'),
                        //array('Enable getting counts of hidden entries?', 'Enabling this feature allows the public to find the number of entries in the database (including hidden entries) matching one or more search terms on a specified set of columns. This feature will only mention the number of variant entries matched, without showing them.', 'checkbox', 'allow_count_hidden_entries'),
                        array('Enable getting counts of hidden entries?', 'Enabling this feature allows the public to find the number of entries in the database (including hidden entries) matching one or more search terms on a specified set of columns. This feature will only mention the number of variant entries matched, without showing them.', 'print', '&nbsp;<I style="color : #666666;">Not yet implemented</I>'),
                        array('Force SSL-only access to LOVD?', 'SSL is a secure protocol allowing for encryption of data sent between you and LOVD. When you will record sensitive individual information in LOVD, you <B>should</B> enable this setting, as the individual information can otherwise be \'sniffed\' off the network. If you do not record sensitive information, enabling SSL is <I>recommended</I>.', 'checkbox', 'use_ssl'),
                        //array('Use data versioning of biological data?', 'Versioning allows you to see all previous versions of a certain data entry (individuals, variants, phenotype information, etc) and allows you to return the entry to a previous state. Please note that this feature requires quite a lot of space in the database. Disabling this feature later will not free any space, just prevent more space from being used.', 'checkbox', 'use_versioning'),
                        array('Use data versioning of biological data?', 'Versioning allows you to see all previous versions of a certain data entry (individuals, variants, phenotype information, etc) and allows you to return the entry to a previous state. Please note that this feature requires quite a lot of space in the database. Disabling this feature later will not free any space, just prevent more space from being used.', 'print', '&nbsp;<I style="color : #666666;">Not yet implemented</I>'),
         'uninstall' => array('Disable LOVD uninstall?', 'Select this to disable the "Uninstall LOVD" option in the Setup area. Please note that this uninstall lock can only be removed by directly accessing the MySQL database.', 'checkbox', 'lock_uninstall'),
      'uninstall_hr' => 'hr',
                      );
        if (lovd_getProjectFile() != '/install/index.php') {
            unset($this->aFormData['uninstall'], $this->aFormData['uninstall_hr']);
            global $_CONF;
            $this->aFormData['refseq_build'] = array('Human Build to map to (UCSC/NCBI)', '', 'print', '&nbsp;' . $_CONF['refseq_build']);
        }

        return parent::getForm();
    }



    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['system_title'] = 'LOVD - Leiden Open Variation Database';
        $_POST['location_url'] = ($_SERVER['HTTP_HOST'] == 'localhost' || lovd_matchIPRange($_SERVER['HTTP_HOST'])? '' : lovd_getInstallURL());
        $_POST['refseq_build'] = 'hg19';
        $_POST['api_feed_history'] = 3;
        $_POST['logo_uri'] = 'gfx/LOVD_logo130x50.jpg';
        $_POST['mutalyzer_soap_url'] = 'http://www.mutalyzer.nl/2.0/services';
        $_POST['send_stats'] = 1;
        $_POST['include_in_listing'] = 1;
        $_POST['allow_submitter_mods'] = 1;
        if (!SSL) {
            $_POST['use_ssl'] = 0;
        } else {
            $_POST['use_ssl'] = 1;
        }
        $_POST['lock_users'] = 1;
        $_POST['allow_unlock_accounts'] = 1;
        $_POST['lock_uninstall'] = 1;
        return true;
    }
}
?>
