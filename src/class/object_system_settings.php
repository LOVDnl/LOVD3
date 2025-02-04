<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-23
 * Modified    : 2024-09-04
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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





class LOVD_SystemSetting extends LOVD_Object
{
    // This class extends the basic Object class and it handles the System Settings.
    var $sObject = 'Settings';
    var $sTable  = 'TABLE_CONFIG';





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.
        global $_SETT, $_STAT;

        $this->aCheckMandatory =
                 array(
                        'system_title',
                        'email_address',
                        'refseq_build',
                      );
        parent::checkFields($aData, $zData, $aOptions);

        // Database URL is mandatory, if the option "Include in the global LOVD listing" is selected.
        // If mandatory or given, we'll check the URL (if not given, we'll auto-generate it).
        if (!empty($aData['include_in_listing']) || !empty($aData['location_url'])) {
            // Database URL should be an URL.
            if (!empty($aData['location_url']) && !lovd_matchURL($aData['location_url'])) {
                lovd_errorAdd('location_url', 'Please fill in a correct URL in the \'Database URL\' field, if you want this LOVD installation to be included in the global LOVD listing; otherwise empty the field and disable the \'Include in the global LOVD listing\' setting below.');
            } else {
                // Validate the URL. Failures are fatal since 3.0-28; before, this was an ajax script.
                $sResponse = implode(
                    lovd_php_file(
                        $_SETT['check_location_URL'] . '?url=' . rawurlencode(rtrim(
                                (empty($aData['location_url'])? lovd_getInstallURL() : $aData['location_url']), '/') . '/') .
                        '&signature=' . rawurlencode($_STAT['signature'])) ?: []);
                if (strpos($sResponse, 'http') === 0) {
                    // I don't have a nice feedback system, $aData is not passed as reference.
                    // So we'll just overwrite $_POST in this case.
                    // It's OK, the value will be escaped before being fed back to the form.
                    $aData['location_url'] = $_POST['location_url'] = $sResponse;
                } elseif (empty($aData['location_url'])) {
                    // Couldn't automatically determine the URL, so we'll have to ask.
                    lovd_errorAdd('location_url', 'Please fill in an URL in the \'Database URL\' field, if you want this LOVD installation to be included in the global LOVD listing; otherwise disable the \'Include in the global LOVD listing\' setting below.');
                } else {
                    // Some other error occurred.
                    lovd_errorAdd('location_url', 'Error while trying to validate your database URL: ' . htmlspecialchars($sResponse));
                }
            }
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
                    lovd_errorAdd('proxy_port', '');
                } else {
                    $sRequest = 'GET ' . $_SETT['check_location_URL'] . ' HTTP/1.0' . "\r\n" .
                                'User-Agent: LOVDv.' . $_SETT['system']['version'] . " Proxy Check\r\n" . // Will be passed on to LOVD.nl.
                        (empty($_POST['proxy_username']) || empty($_POST['proxy_password'])? '' :
                            'Proxy-Authorization: Basic ' . base64_encode($_POST['proxy_username'] . ':' . $_POST['proxy_password']) . "\r\n") .
                        'Connection: Close' . "\r\n\r\n";
                    fputs($f, $sRequest);
                    $s = rtrim(fgets($f));
                    if (!preg_match('/^HTTP\/1\.. [23]/', $s, $aRegs)) { // Allowing HTTP 2XX and 3XX.
                        if (preg_match('/^HTTP\/1\.. 407/', $s, $aRegs)) { // Proxy needs username and password.
                            if (!empty($_POST['proxy_username']) && !empty($_POST['proxy_password'])) {
                                lovd_errorAdd('proxy_username', 'Invalid username/password combination for this proxy server. Please try again.');
                                lovd_errorAdd('proxy_password', '');
                            } else {
                                lovd_errorAdd('proxy_username', 'This proxy server requires a valid username and password. Please make sure you provide them both.');
                                lovd_errorAdd('proxy_password', '');
                            }
                        } else {
                            lovd_errorAdd('proxy_host', 'Unexpected answer from proxy when trying to connect upstream: ' . $s);
                        }
                    }
                }
            }

        } elseif (!empty($aData['proxy_port'])) {
            // We have a port number, but no host name.
            lovd_errorAdd('proxy_host', 'Please also fill in a correct host name of the proxy server, if you wish to use one.');
        }

        // MD key must work.
        if (!empty($aData['md_apikey'])) {
            $aResponse = lovd_php_file(
                'https://mobidetails.iurc.montp.inserm.fr/MD/api/service/check_api_key',
                false,
                'api_key=' . $aData['md_apikey'],
                array(
                    'Accept: application/json',
                ));
            if ($aResponse) {
                $aResponse = json_decode(implode($aResponse), true);
            }
            if (!$aResponse || !isset($aResponse['api_key_pass_check']) || !isset($aResponse['api_key_status'])) {
                lovd_errorAdd('md_apikey', 'While testing the given MobiDetails API key, got an error from MobiDetails.');
            } else {
                if ($aResponse['api_key_pass_check'] === false) {
                    // API key error.
                    lovd_errorAdd('md_apikey', 'MobiDetails indicates that this API key is invalid.');
                } elseif ($aResponse['api_key_status'] != 'active') {
                    // That's weird, key expired maybe?
                    lovd_errorAdd('md_apikey', 'MobiDetails indicates the status of this API key is &quot;' . $aResponse['api_key_status'] . '&quot;.');
                }
            }
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
            $_POST['logo_uri'] = 'gfx/LOVD' . (LOVD_plus? '_plus' : '3') . '_logo145x50.jpg';
        }

        // FIXME; Like above, not the best solution, but gets the job done for now.
        if (empty($aData['mutalyzer_soap_url'])) {
            $_POST['mutalyzer_soap_url'] = 'https://v2.mutalyzer.nl/services';
        }

        // SSL check.
        if (!empty($aData['use_ssl']) && !SSL) {
            lovd_errorAdd('use_ssl', 'You\'ve selected to force the use of SSL, but SSL is not currently activated for this session. To force SSL, I must be sure it\'s possible to approach LOVD through an SSL connection (use <A href="https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING']? '?' . str_replace('&sent=true', '', $_SERVER['QUERY_STRING']) : '') . '" target="_blank">https://</A> instead of http://).');
        }

        // Prevent notices.
        if (LOVD_plus) {
            $_POST['logo_uri'] = 'LOVD_plus_logo145x50';
            $_POST['donate_dialog_allow'] = 0;
            $_POST['donate_dialog_months_hidden'] = 0;
            $_POST['send_stats'] = 0;
            $_POST['include_in_listing'] = 0;
            $_POST['allow_submitter_registration'] = 0;
            $_POST['allow_submitter_mods'] = 0;
        }

        // XSS attack prevention. Deny input of HTML.
        lovd_checkXSS($aData);
    }



    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        global $_SETT;

        $aHumanBuilds = array();
        foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
            $aHumanBuilds[$sCode] = $sCode . ' / ' . $aBuild['ncbi_name'];
        }
        // No more hg18! We'll support LOVDs having this setting, but we won't allow new installations to pick this setting.
        unset($aHumanBuilds['hg18']);

        $aFeedHistory = array('Not available');
        $aDonationDialogMonths = array();
        foreach (array(1, 2, 3, 6, 9, 12) as $i) {
            $aFeedHistory[$i] = $i . ' month' . ($i == 1? '' : 's');
            $aDonationDialogMonths[$i] = $i . ' month' . ($i == 1? '' : 's');
        }

        $this->aFormData =
                 array(
                        array('POST', '', '', '', '335', '14', ''),
                        array('', '', 'print', '<B>General system settings</B>'),
                        'hr',
                        array('Title of this LOVD installation', 'This will be shown on the top of every page.', 'text', 'system_title', 45),
                        array('Institute (optional)', 'The institute which runs this database is displayed in the public area and in emails sent by LOVD. It\'s commonly set to a laboratory name or a website name.', 'text', 'institute', 45),
                        array('Database URL (optional)', 'This is the URL with which the database can be accessed by the outside world, including "http://" or "https://". It will also be used in emails sent by LOVD. This field is mandatory if you select the "Include in the global LOVD listing" option.<BR>If you click the "check" link, LOVD will verify or try to predict the value.', 'text', 'location_url', 40),
                        array('LOVD email address', 'This email address will be used to send emails from LOVD to users. We need this address to make sure that emails from LOVD arrive. Please note that although strictly speaking this email address does not need to exist, we recommend that you use a valid address.', 'text', 'email_address', 40),
                        array('Forward messages to database admin?', 'This will forward messages to the database administrator about submitter registrations and submissions.', 'checkbox', 'send_admin_submissions'),
      'refseq_build' => array('Human Build to map to (UCSC/NCBI)', 'We need to know which version of the Human Build we need to map the variants in this LOVD to.', 'select', 'refseq_build', 1, $aHumanBuilds, false, false, false),
                        //array('List database changes in feed for how long?', 'LOVD includes a "newsfeed" that allows users to get a list of changes recently made in the database. Select here how many months back you want changes to appear on this list. Set to "Not available" to disable the newsfeed.', 'select', 'api_feed_history', 1, $aFeedHistory, false, false, false),
      'feed_history' => array('List database changes in feed for how long?', 'LOVD includes a "newsfeed" that allows users to get a list of changes recently made in the database. Select here how many months back you want changes to appear on this list. Set to "Not available" to disable the newsfeed.', 'print', '&nbsp;<I style="color : #666666;">Not yet implemented</I>'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Connection settings (optional)</B>'),
                        array('', '', 'note', 'The following settings apply to how LOVD connects to other resources.<BR>Some networks have no access to the outside world except through a proxy. If this applies to the network this server is installed on, please fill in the proxy server information here.'),
                        'hr',
//                     array('OMIM API key', 'LOVD can connect to OMIM.org to retrieve information about diseases, genes and phenotypes. Connecting to OMIM requires an API key. OMIM unfortunately does not allow us to use one key for all LOVDs, so you\'ll have to register at OMIM.org to request your own.', 'text', 'proxy_host', 20),
                        array('Proxy server host name', 'The host name of the proxy server, such as www-cache.institution.edu.', 'text', 'proxy_host', 20),
                        array('Proxy server port number', 'The port number of the proxy server, such as 3128.', 'text', 'proxy_port', 4),
                        'skip',
                        array('', '', 'note', 'The following two fields only apply if the proxy server requires authentication.'),
                        array('Proxy server username', 'In case the proxy server requires authentication, please enter the required username here.', 'text', 'proxy_username', 20),
                        array('Proxy server password', 'In case the proxy server requires authentication, please enter the required password here.', 'password', 'proxy_password', 20, true),
                        'skip',
                        array('', '', 'print', 'API keys'),
                        array('MobiDetails API key', '', 'text', 'md_apikey', 40),
                        array('', '', 'note', 'LOVD allows looking up variants in <A href="https://mobidetails.iurc.montp.inserm.fr/MD" target="_blank">MobiDetails</A>, an online DNA variant annotation and interpretation platform. To submit variants to MobiDetails, you need an API key. You can register for one <A href="https://mobidetails.iurc.montp.inserm.fr/MD/auth/register" target="_blank">here</A>.'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Customize LOVD</B>'), // Don't edit, we're parsing for this.
                        array('', '', 'note', 'Here you can customize the way LOVD looks.'),
                        'hr',
                        array('System logo', 'If you wish to have your custom logo on the top left of every page instead of the default LOVD logo, enter the path to the image here, relative to the LOVD installation path.', 'text', 'logo_uri', 40),
                        array('', '', 'note', 'Currently, only images already uploaded to the LOVD server are allowed here.'),
                        array('Ask visitors of this database to donate to LOVD?', 'We rely on donations to keep updating and developing the LOVD software. Please consider allowing LOVD to regularly ask visitors to donate to the LOVD project using a popup dialog.', 'checkbox', 'donate_dialog_allow'),
                        array('Once shown, don\'t show the dialog for this long', 'When enabled, the dialog will always be shown to new users. Once seen, select for how long the dialog should then remain hidden for this user.', 'select', 'donate_dialog_months_hidden', 1, $aDonationDialogMonths, false, false, false),
                        array('', '', 'note', 'We rely on donations to keep updating and developing the LOVD software. Please consider allowing LOVD to regularly ask visitors to donate to the LOVD project using a popup dialog. If enabled, new visitors will always see this dialog. You can configure for how long this dialog should then be hidden from them.'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Global LOVD statistics</B>'), // Don't edit, we're parsing for this.
                        array('', '', 'note', 'The following settings apply to the kind of information your LOVD install sends to the development team to gather statistics about global LOVD usage.'),
                        'hr',
                        array('Send statistics?', 'This sends <I>anonymous</I> statistics about the number of submitters, genes, individuals and variants in your installation of LOVD.', 'checkbox', 'send_stats'),
                        array('Include in the global LOVD listing?', 'We keep a public listing of LOVD installations, their genes and their URLs. Deselect this checkbox if you do not want to be included in this public listing.', 'checkbox', 'include_in_listing'),
                        'hr',
                        'skip',
                        'skip',
                        array('', '', 'print', '<B>Security settings</B>'),
                        array('', '', 'note', 'Using the following settings you can control some security settings of LOVD.'),
                        'hr',
                        array('Allow submitter registration?', 'Enabling this setting allows submitters to create their own accounts in your LOVD installation. Having it enabled is the default setting, and this could not be disabled prior to LOVD 3.0-17.<BR>Note, that submitters can never register when your LOVD installation is set to read-only because of an active announcement with the read-only setting enabled. See the Announcements section in the Setup area for more information.', 'checkbox', 'allow_submitter_registration'),
                        array('Lock users after 3rd failed login?', 'Do you want to lock users (submitters, curators and managers) after three failed attempts to log in using their username?<BR>(This does <I>not</I> affect the database administrator account)', 'checkbox', 'lock_users'),
                        array('Allow (locked) users to retrieve a new password?', 'Do you want to enable an "I forgot my password" option that allows users who forgot their password to retrieve a new one?', 'checkbox', 'allow_unlock_accounts'),
                        array('Enable submitters to change data?', 'Enabling this setting allows submitters to make changes to data previously submitted by them or assigned to them.', 'checkbox', 'allow_submitter_mods'),
                        //array('Enable getting counts of hidden entries?', 'Enabling this feature allows the public to find the number of entries in the database (including hidden entries) matching one or more search terms on a specified set of columns. This feature will only mention the number of variant entries matched, without showing them.', 'checkbox', 'allow_count_hidden_entries'),
 'count_hidden_data' => array('Enable getting counts of hidden entries?', 'Enabling this feature allows the public to find the number of entries in the database (including hidden entries) matching one or more search terms on a specified set of columns. This feature will only mention the number of variant entries matched, without showing them.', 'print', '&nbsp;<I style="color : #666666;">Not yet implemented</I>'),
                        array('Force SSL-only access to LOVD?', 'SSL is a secure protocol allowing for encryption of data sent between you and LOVD. When you will record sensitive individual information in LOVD, you <B>should</B> enable this setting, as the individual information can otherwise be \'sniffed\' off the network. If you do not record sensitive information, enabling SSL is <I>recommended</I>.', 'checkbox', 'use_ssl'),
                        array('Enable rate limiting?', 'Rate limiting allows you to reduce traffic to this LOVD instance from certain IP addresses or from unknown IPs using a certain user agent (like search engine bots). You can configure rate limits in the Setup area, but you have to enable this setting for any configured rate limits to work.', 'checkbox', 'use_rate_limiting'),
                        //array('Use data versioning of biological data?', 'Versioning allows you to see all previous versions of a certain data entry (individuals, variants, phenotype information, etc) and allows you to return the entry to a previous state. Please note that this feature requires quite a lot of space in the database. Disabling this feature later will not free any space, just prevent more space from being used.', 'checkbox', 'use_versioning'),
    'use_versioning' => array('Use data versioning of biological data?', 'Versioning allows you to see all previous versions of a certain data entry (individuals, variants, phenotype information, etc) and allows you to return the entry to a previous state. Please note that this feature requires quite a lot of space in the database. Disabling this feature later will not free any space, just prevent more space from being used.', 'print', '&nbsp;<I style="color : #666666;">Not yet implemented</I>'),
         'uninstall' => array('Disable LOVD uninstall?', 'Select this to disable the "Uninstall LOVD" option in the Setup area. Please note that this uninstall lock can only be removed by directly accessing the MySQL database.', 'checkbox', 'lock_uninstall'),
      'uninstall_hr' => 'hr',
                      );
        if (lovd_getProjectFile() != '/install/index.php') {
            unset($this->aFormData['uninstall'], $this->aFormData['uninstall_hr']);
            global $_CONF;
            $this->aFormData['refseq_build'] = array('Human Build to map to (UCSC/NCBI)', '', 'print', '&nbsp;' . $_CONF['refseq_build']);
        }

        // Remove features that are anyway currently not developed yet. They can confuse users.
        unset($this->aFormData['feed_history'], $this->aFormData['count_hidden_data'], $this->aFormData['use_versioning']);

        if (LOVD_plus) {
            // Remove features currently unavailable for LOVD+ (or that we choose not to support).
            foreach ($this->aFormData as $nKey => $aFormEntry) {
                // Unset whole ranges of options, easier to do like this than to name all of the options.
                if (isset($this->aFormData[$nKey]) && is_array($aFormEntry)
                    && strpos($aFormEntry[3], '<B>Customize LOVD</B>') !== false) {
                    unset($this->aFormData[$nKey], $this->aFormData[$nKey + 1], $this->aFormData[$nKey + 2],
                        $this->aFormData[$nKey + 3], $this->aFormData[$nKey + 4], $this->aFormData[$nKey + 5],
                        $this->aFormData[$nKey + 6], $this->aFormData[$nKey + 7], $this->aFormData[$nKey + 8],
                        $this->aFormData[$nKey + 9], $this->aFormData[$nKey + 10]);
                    continue;

                } elseif (isset($this->aFormData[$nKey]) && is_array($aFormEntry)
                    && strpos($aFormEntry[3], '<B>Global LOVD statistics</B>') !== false) {
                    unset($this->aFormData[$nKey], $this->aFormData[$nKey + 1], $this->aFormData[$nKey + 2],
                        $this->aFormData[$nKey + 3], $this->aFormData[$nKey + 4], $this->aFormData[$nKey + 5],
                        $this->aFormData[$nKey + 6], $this->aFormData[$nKey + 7]);
                    continue;

                } elseif (isset($this->aFormData[$nKey]) && is_array($aFormEntry)
                    && in_array($aFormEntry[3], array('allow_submitter_registration', 'allow_submitter_mods'))) {
                    unset($this->aFormData[$nKey]);
                }
            }
        }

        return parent::getForm();
    }



    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['system_title'] = (LOVD_plus? 'Leiden Open Variation Database for diagnostics' : 'LOVD - Leiden Open Variation Database');
        $_POST['location_url'] = ($_SERVER['HTTP_HOST'] == 'localhost' || lovd_matchIPRange($_SERVER['HTTP_HOST'])? '' : lovd_getInstallURL());
        $_POST['refseq_build'] = 'hg38';
        $_POST['api_feed_history'] = 3;
        $_POST['logo_uri'] = 'gfx/LOVD' . (LOVD_plus? '_plus' : '3') . '_logo145x50.jpg';
        $_POST['mutalyzer_soap_url'] = 'https://v2.mutalyzer.nl/services';
        $_POST['send_stats'] = (int) (!LOVD_plus);
        $_POST['include_in_listing'] = (int) (!LOVD_plus);
        $_POST['allow_submitter_registration'] = (int) (!LOVD_plus);
        $_POST['allow_submitter_mods'] = (int) (!LOVD_plus);
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
