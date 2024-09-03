<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2024-09-03
 * Modified    : 2024-09-03
 * For LOVD    : 3.0-31
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/objects.php';





class LOVD_RateLimit extends LOVD_Object
{
    // This class extends the basic Object class, and it handles the Rate Limits.
    var $sObject = 'Rate_Limit';





    function __construct ()
    {
        // Default constructor.

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
            array(
                'id' => array(
                    'view' => array('ID', 45),
                    'db'   => array('id', 'ASC', true)),
                'active_' => array(
                    'view' => array('Active', 60, 'style="text-align : center;"'),
                    'db'   => array('active', 'DESC', true)),
                'name' => array(
                    'view' => array('Name', 250),
                    'db'   => array('name', 'ASC', true)),
                'ip_pattern_' => array(
                    'view' => array('IPs', 150),
                    'db'   => array('ip_pattern', 'ASC', true)),
                'user_agent_pattern_' => array(
                    'view' => array('User agents', 250),
                    'db'   => array('user_agent_pattern', 'ASC', true)),
                'max_hits_per_min' => array(
                    'view' => array('Hits/min', 70),
                    'db'   => array('max_hits_per_min', 'ASC', true)),
                'delay' => array(
                    'view' => array('Delay', 50),
                    'db'   => array('delay', 'ASC', true)),
            );

        parent::__construct();
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        // Checks fields before submission of data.

        // Mandatory fields.
        $this->aCheckMandatory =
            array(
                'name',
                'ip_pattern',
                'max_hits_per_min',
            );
        parent::checkFields($aData, $zData, $aOptions);

        // Check given security IP range.
        if (!empty($aData['ip_pattern'])) {
            // This function will throw an error itself (second argument).
            $bIP = lovd_matchIPRange($aData['ip_pattern'], 'ip_pattern');
            // Make sure this range doesn't include the current user.
            if ($bIP && lovd_validateIP($aData['ip_pattern'], $_SERVER['REMOTE_ADDR'])) {
                // This IP range matches the current IP. This ain't right.
                if (empty($aData['user_agent_pattern'])) {
                    lovd_errorAdd('allowed_ip', 'Your current IP address is matched by the given IP range, and you have not provided a user agent pattern. This would mean you\'re limiting your own access to LOVD with this IP range.');
                } elseif (lovd_matchString($aData['user_agent_pattern'], $_SERVER['HTTP_USER_AGENT'])) {
                    lovd_errorAdd('allowed_ip', 'Your current IP address and user agent match the given settings. This would mean you\'re limiting your own access to LOVD with these settings.');
                }
            }
        }

        if (!empty($aData['user_agent_pattern']) && $aData['user_agent_pattern'][0] == '/' && @preg_match($aData['user_agent_pattern'], '') === false) {
            lovd_errorAdd('user_agent_pattern', 'You seem to have used a regular expression in the \'User agent pattern\' field, but it does not seem to contain valid PHP Perl compatible regexp syntax.');
        }

        if (!empty($aData['url_pattern']) && $aData['url_pattern'][0] == '/' && @preg_match($aData['url_pattern'], '') === false) {
            lovd_errorAdd('url_pattern', 'You seem to have used a regular expression in the \'URLs to limit access to\' field, but it does not seem to contain valid PHP Perl compatible regexp syntax.');
            if (preg_match('/^\/[A-Za-z0-9_\/-]+[^\/]$/', $aData['url_pattern'])) {
                lovd_errorAdd('url_pattern', 'Did you perhaps mean to type "/^' . preg_quote($aData['url_pattern'], '/') . '"?');
            }
        }

        if (isset($aData['max_hits_per_min']) && $aData['max_hits_per_min'] < 6) {
            lovd_errorAdd('max_hits_per_min', 'Setting the number of allowed hits per minute lower than 6 is not supported.');
        }

        if (!empty($aData['delay']) && $aData['delay'] > 10) {
            lovd_errorAdd('delay', 'Setting the delay to over 10 seconds is not supported, as it allows others to occupy all your web server threads, making LOVD unreachable.');
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

        // Array which will make up the form table.
        $this->aFormData =
            array(
                array('POST', '', '', '', '35%', '14', '65%'),
                array('', '', 'print', '<B>Rate limit details</B>'),
                array('Activate this rate limit?', 'If you\'re creating a draft, leave this unset.', 'checkbox', 'active'),
                array('Name', 'Use a name that will make you recognize this rate limit well. E.g., "Slow down API usage from IP xxx.xxx.xxx.xxx."', 'text', 'name', 50),
                array('IP pattern', '', 'text', 'ip_pattern', 50),
                array('', '', 'note', 'Specify which IP or IP range(s) this rate limit should apply to. Set to * if you want to apply this to all IPs (e.g., when trying to slow down a search engine bot). In that case, you <B>MUST</B> be specific about the user agent string.<BR><B>Please be extremely careful using this setting.</B> Using this setting too widely, can deny large numbers of users access to LOVD.<BR>Set to \'*\' to allow all IP addresses, use \'-\' to specify a range and use \';\' to separate addresses or ranges.'),
                array('User agent pattern', '', 'text', 'user_agent_pattern', 50),
                array('', '', 'note', 'Specify which user agent this rate limit should apply to. Just paste the full user agent here, without quotes. You can also use regular expressions; in that case, use slash delimiters.<BR><B>Please be extremely careful using this setting.</B> Using this setting too widely, can deny large numbers of users access to LOVD.'),
                array('URLs to limit access to', '', 'text', 'url_pattern', 50),
                array('', '', 'note', 'Leave this empty to apply this rate limit to all of LOVD. Otherwise, to include or exclude only specific pages, use a regular expression with slash delimiters.<BR>E.g., use "/^\/api/" to only apply this rate limit to the API.'),
                array('Max hits per minute', 'How many hits are allowed per minute? Any more requests than this number, will be blocked.', 'text', 'max_hits_per_min', 5),
                array('', '', 'note', 'Setting this to 60 will limit the user to one request per second.'),
                array('Also delay the user this many seconds', 'Add the number of seconds the user is also delayed. Read the notes to understand the danger of this setting.', 'text', 'delay', 5),
                array('', '', 'note', 'Adding a delay makes the user wait, with or without them reaching the rate limit. Without any delay (the default value of 0), the user gets their output immediately if they haven\'t reached their limit yet, or an error otherwise. When they have reached their limit, badly implemented scripts may then immediately try again. When you see this, you can add a delay here. The delay can, however, also prevent the error from occurring as the user is slowed down significantly.<BR><B>Never set this to more than just a few seconds!</B> You will, otherwise, risk using up all your web server threads.'),
                array('Message for the user', '', 'textarea', 'message', 55, 5),
                array('', '', 'note', 'When the user sends too many requests, this message will be shown. Be informative about what limit they\'ve reached, and how they can prevent getting blocked. When left empty, a standard message will appear.'),
                'skip',
                array('Enter your password for authorization', '', 'password', 'password', 20),
            );

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['active_'] = '<IMG src="gfx/mark_' . (int) $zData['active'] . '.png" alt="" width="11" height="11">';
        if ($sView == 'list') {
            $zData['name'] = '<A href="' . $zData['row_link'] . '" class="hide">' . lovd_shortenString($zData['name']) . '</A>';
            $zData['ip_pattern_'] = lovd_shortenString($zData['ip_pattern'], 25);
            $zData['user_agent_pattern_'] = lovd_shortenString($zData['user_agent_pattern'], 25);
        }
        $zData['delay'] = (int) $zData['delay'];

        return $zData;
    }





    function setDefaultValues ()
    {
        // Sets default values of fields in $_POST.
        $_POST['max_hits_per_min'] = 300;
        $_POST['delay'] = 0;
        return true;
    }
}
?>
