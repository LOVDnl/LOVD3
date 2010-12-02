<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2010-10-12
 * For LOVD    : 3.0-pre-09
 *
 * Copyright   : 2004-2010 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 * Last edited : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class SystemSetting extends Object {
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

        // SSL check.
        if (!empty($_POST['use_ssl']) && !SSL) {
            lovd_errorAdd('use_ssl', 'You\'ve selected to force the use of SSL, but SSL is not currently activated for this session. To force SSL, I must be sure it\'s possible to approach LOVD through an SSL connection (use <A href="https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING']? '?' . str_replace('&sent=true', '', $_SERVER['QUERY_STRING']) : '') . '" target="_blank">https://</A> in stead of http://).');
        }

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
                        'hr',
                        array('Institute (optional)', 'The institute which runs this database is displayed in the public area and in emails sent by LOVD. Is commonly set to a laboratory name or a website name.', 'text', 'institute', 45),
                        'hr',
                        array('Database URL (optional)', 'This is the URL with which the database can be accessed by the outside world, including "http://" or "https://". It will also be used in emails sent by LOVD. This field is mandatory if you select the "Include in the global LOVD listing" option.<BR>If you click the "check" link, LOVD will verify or try to predict the value.', 'print', '<INPUT type="text" name="location_url" size="40" id="location_url" value="' . (empty($_POST['location_url'])? '' : htmlspecialchars($_POST['location_url'])) . '"' . (!lovd_errorFindField('location_url')? '' : ' class="err"') . '>&nbsp;<SPAN id="location_url_check">(<A href="#" onclick="javascript:lovd_checkURL(); return false;">check</A>)</SPAN>'),
                        'hr',
                        array('LOVD email address', 'This email address will be used to send emails from LOVD to users. We need this address to make sure that emails from LOVD arrive. Please note that although strictly spoken this email address does not need to exist, we recommend that you use a valid address.', 'text', 'email_address', 40),
                        'hr',
                        array('Forward messages to database admin?', 'This will forward messages to the database administrator about submitter registrations and submissions.', 'checkbox', 'send_admin_submissions', 1),
                        'hr',
                        array('Human Build to map to (UCSC/NCBI)', 'We need to know which version of the Human Build we need to map the variants in this LOVD to.', 'select', 'refseq_build', 1, $aHumanBuilds, false, false, false),
                        'hr',
                        array('List database changes in feed for how long?', 'LOVD includes a "newsfeed" that allows users to get a list of changes recently made in the database. Select here how many months back you want changes to appear on this list. Set to "Not available" to disable the newsfeed.', 'select', 'api_feed_history', 1, $aFeedHistory, false, false, false),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Global LOVD statistics</B>'),
                        array('', '', 'note', 'The following settings apply to the kind of information your LOVD install sends to the development team to gather statistics about global LOVD usage.'),
                        'hr',
                        array('Send statistics?', 'This sends <I>anonymous</I> statistics about the number of submitters, genes, patients and mutations in your installation of LOVD.', 'checkbox', 'send_stats', 1),
                        'hr',
                        array('Include in the global LOVD listing?', 'We keep a public listing of LOVD installations, their genes and their URLs. Deselect this checkbox if you do not want to be included in this public listing.', 'checkbox', 'include_in_listing', 1),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Security settings</B>'),
                        array('', '', 'note', 'Using the following settings you can control some security settings of LOVD.'),
                        'hr',
/* DMD_SPECIFIC: are these names still ok? */
                        array('Lock users after 3rd failed login?', 'Do you want to lock users (submitters, curators and managers) after three failed attempts to log in using their username?<BR>(This does <I>not</I> affect the database administrator account)', 'checkbox', 'lock_users', 1),
                        'hr',
                        array('Allow (locked) users to retrieve a new password?', 'Do you want to enable an "I forgot my password" option that allows users who forgot their password to retrieve a new one?', 'checkbox', 'allow_unlock_accounts', 1),
                        'hr',
                        array('Enable submitters to change data?', 'Enabling this setting allows submitters to make changes to data previously submitted by them or assigned to them.', 'checkbox', 'allow_submitter_mods', 1),
                        'hr',
                        array('Enable getting counts of hidden entries?', 'Enabling this feature allows the public to find the number of entries in the database (including hidden entries) matching one or more search terms on a specified set of columns. This feature will only mention the number of variant entries matched, without showing them.', 'checkbox', 'allow_count_hidden_entries', 1),
                        'hr',
                        array('Force SSL-only access to LOVD?', 'SSL is a secure protocol allowing for encryption of data sent between you and LOVD. When you will record sensitive patient information in LOVD, you <B>should</B> enable this setting, as the patient information can otherwise be \'sniffed\' off the network. If you do not record sensitive information, enabling SSL is <I>recommended</I>.', 'checkbox', 'use_ssl', 1),
                        'hr',
                        array('Use data versioning of biological data?', 'Versioning allows you to see all previous versions of a certain data entry (patients, variants, phenotype information, etc) and allows you to return the entry to a previous state. Please note that this feature requires quite a lot of space in the database. Disabling this feature later will not free any space, just prevent more space from being used.', 'checkbox', 'use_versioning', 1),
                        'hr',
         'uninstall' => array('Disable LOVD uninstall?', 'Select this to disable the "Uninstall LOVD" option in the Setup area. Please note that this uninstall lock can only be removed by directly accessing the MySQL database.', 'checkbox', 'lock_uninstall', 1),
      'uninstall_hr' => 'hr',
                      );
        if (lovd_getProjectFile() != '/install/index.php') {
            unset($this->aFormData['uninstall'], $this->aFormData['uninstall_hr']);
        }

        return parent::getForm();
    }



    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['system_title'] = 'LOVD - Leiden Open Variation Database';
        $_POST['refseq_build'] = 'hg19';
        $_POST['api_feed_history'] = 3;
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