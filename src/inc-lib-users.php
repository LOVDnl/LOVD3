<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2016-04-22
 * For LOVD    : 3.0-15
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

// Email body to be sent to users who are the recipient of shared access to
// someone's data. The text contains wildcards for the following information:
// 1. Recipient's name
// 2. LOVD installation identifier (url)
// 3. Sender's name (user who shares access to their data)
// 4. Sender's user ID
// 5. URL to recipient's user account
// 6. List of administrator names and email addresses
define('EMAIL_NEW_COLLEAGUE', <<<EMAILDOC
Dear %1\$s,

Someone has given you access to their data in LOVD located at:
%2\$s

You can now view and edit all data owned by %3\$s (%4\$s).

If you know this person, you can consider to share access to them so
they can view and edit your data. You can do so by going to your
account, click "Share access to your entries with other users", select
the appropriate user accounts and click "save".
Access your account here: %5\$s

If you think this email is not intended for you, please let us know by
contacting one of the administrators:
%6\$s

Kind regards,
    LOVD system at Leiden University Medical Center
EMAILDOC
);



function lovd_mailNewColleagues($sUserID, $sUserFullname, $aNewColleagues) {
    // Send an email to users with an ID in $aNewColleagues, letting them know
    // the user denoted by $sUserID has shared access to his data with them.
    require_once 'inc-lib-form.php';
    global $_DB, $_SETT;

    if (count($aNewColleagues) == 0) {
        // Nothing to be done.
        return;
    }

    $sApplicationURL = lovd_getInstallURL();
    $aAdminEmails = preg_split('/(\r\n|\r|\n)+/', trim($_SETT['admin']['email']));
    $sAdminContacts = $_SETT['admin']['name'] . ', ' . $aAdminEmails[0] . "\n";


    // Fetch names/email addresses for new colleagues.
    $sPlaceholders = '(?' . str_repeat(',?', count($aNewColleagues)-1) . ')';
    $sColleagueQuery = 'SELECT id, name, email FROM ' . TABLE_USERS . ' WHERE id IN ' . $sPlaceholders;
    $result = $_DB->query($sColleagueQuery, $aNewColleagues);

    while (($zColleague = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $sRecipientAccountURL = $sApplicationURL . 'users/' . $zColleague['id'];

        // Setup mail text and fill placeholders.
        $sMailBody = sprintf(EMAIL_NEW_COLLEAGUE, $zColleague['name'], $sApplicationURL,
            $sUserFullname, $sUserID, $sRecipientAccountURL, $sAdminContacts);

        // Only use Windows-style line endings, so that it looks good on all platforms.
        $sMailBody = str_replace("\n", "\r\n", $sMailBody);

        // Note: email field is new-line separated list of email addresses.
        lovd_sendMail(array(array($zColleague['name'], $zColleague['email'])),
                      'LOVD access sharing', $sMailBody, $_SETT['email_headers'], false);
    }
}





function lovd_shareAccessForm($sUserID, $sUserListID) {
    // Returns HTML for form to share access of a user's objects with another
    // user.

    global $_DB;

    require_once ROOT_PATH . 'inc-lib-form.php';
    require_once ROOT_PATH . 'class/object_users.php';

    // Get colleagues of given user from database.
    $sQuery = 'SELECT
                 u.id,
                 u.name
               FROM ' . TABLE_COLLEAGUES . ' AS c
               LEFT JOIN lovd_v3_users AS u ON (u.id = c.userid_to)
               WHERE c.userid_from = ?;';
    $colleagues = $_DB->query($sQuery, array($sUserID));
    $aColleagues = $colleagues->fetchAllAssoc();

    // HTML for row in colleague list. This contains 2 string directives:
    // 1=user's ID, 2=user's name. Note: this is to be parsed by sprintf(), so
    // remember to escape %-signs.
    $sTableColleaguesRow = <<<DOCCOLROW
<LI id="li_%1\$s">
    <INPUT type="hidden" name="colleagues[]" value="%1\$s">
    <TABLE width="100%%">
        <TR>
            <TD>%2\$s (#%1\$s)</TD>
            <TD width="30" align="right">
                <A href="#" onclick="$('#li_%1\$s').remove(); return false;" title="Remove user">
                    <IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0">
                </A>
            </TD>
        </TR>
    </TABLE>
</LI>
DOCCOLROW;

    // HTML for list (table) of users.
    $sTableColleagues = <<<DOCCOL
<TABLE class="sortable_head" style="width : 552px;">
    <TR>
        <TH>Name</TH>
    </TR>
</TABLE>
<FORM action="users/$sUserID?share_access" method="post">
<UL id="$sUserListID" class="sortable" style="margin-top : 0px; width : 550px;">
%s
</UL>
<INPUT type="submit" value="Save">
<SPAN>&nbsp;</SPAN>
<INPUT type="submit" value="Cancel" onclick="window.location.href=\'users/$sUserID\'; return false;" style="border : 1px solid #FF4422;">
</FORM>
<BR />
<SCRIPT type='text/javascript'>
function lovd_addUserShareAccess(viewlistItem) {
    $('#$sUserListID').append(
        '<LI id="li_' + viewlistItem.ID + '">' +
            '<INPUT type="hidden" name="colleagues[]" value="' + viewlistItem.ID + '">' +
            '<TABLE width="100%%">' +
                '<TR>' +
                    '<TD>' + viewlistItem.Name + ' (#' + viewlistItem.ID + ')</TD>' +
                    '<TD width="30" align="right">' +
                        '<A href="#" onclick="$(\'#li_' + viewlistItem.ID + '\').remove(); return false;" title="Remove user">' +
                            '<IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0">' +
                        '</A>' +
                    '</TD>' +
                '</TR>' +
            '</TABLE>' +
        '</LI>');
}

</SCRIPT>
DOCCOL;

    // Construct list of colleagues
    $sColleaguesHTML = '';
    foreach ($aColleagues as $aUserFields) {
        $sColleaguesHTML .= sprintf($sTableColleaguesRow, $aUserFields['id'], $aUserFields['name']);
    }
    return sprintf($sTableColleagues, $sColleaguesHTML);
}




function lovd_setColleagues($sUserID, $sUserName, $aColleagues) {
    // Removes all existing colleagues for user $sUserID and replaces them with
    // all IDs in $aColleagues.
    // Throws an exception (Exception) when something goes wrong.

    global $_DB;

    $sOldColleaguesQuery = 'SELECT userid_to FROM ' . TABLE_COLLEAGUES . ' WHERE userid_from=?;';
    $aOldColleagues = $_DB->query($sOldColleaguesQuery, array($sUserID))->fetchAllColumn();

    try {
        $_DB->beginTransaction();

        // Delete all current colleague records with given user in 'from' field.
        $_DB->query('DELETE FROM ' . TABLE_COLLEAGUES . ' WHERE userid_from=?;', array($sUserID));

        if (count($aColleagues) > 0) {
            // Build parts for multi-row insert query.
            $sPlaceholders = '(?,?)' . str_repeat(',(?,?)', count($aColleagues)-1);
            $aData = array();
            foreach ($aColleagues as $sColeagueID) {
                array_push($aData, $sUserID, $sColeagueID);
            }

            $_DB->query('INSERT INTO ' . TABLE_COLLEAGUES . ' (userid_from, userid_to) VALUES ' .
                $sPlaceholders, $aData);
        }
        $_DB->commit();

    } catch (Exception $e) {
        $_DB->rollBack();
        $sMessage = 'Failed to update shared access for user (' . $sUserID . '), caused by: ' .
            $e->getMessage();
        lovd_writeLog('Error', LOG_EVENT, $sMessage);
        throw new Exception($sMessage);
    }

    // Notify only the newly added colleagues by email.
    // Note: call array_values() on the parameter array to make sure the
    // indices are normalized to what PDO expects.
    $aNewColleagues = array_values(array_diff($aColleagues, $aOldColleagues));
    lovd_mailNewColleagues($sUserID, $sUserName, $aNewColleagues);

    // Write to log.
    $sMessage = 'updated colleagues for user (' . $sUserID . ') with (' .
        join(', ', $aColleagues) . ')';
    lovd_writeLog('Event', LOG_EVENT, $sMessage);
}



function lovd_showPageAccessDenied() {
    // Show a page saying access denied.

    global $_T;

    $_T->printHeader();
    $_T->printTitle('Access denied');
    lovd_showInfoTable('You do not have access to this content.', 'warning');
    $_T->printFooter();
}
