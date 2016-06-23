<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2016-06-23
 * For LOVD    : 3.0-16
 *
 * Copyright   : 2014-2016 Leiden University Medical Center; http://www.LUMC.nl/
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

// Email body to be sent to users who are the recipient of shared access to
// someone's data. The text contains wildcards for the following information:
// 1. Recipient's name
// 2. LOVD installation identifier (url)
// 3. Granter's name (user who granted the permissions)
// 4. Granter's institute
// 5. Granter's email address
// 6. Shared resource (either 'data of Name (Institute)' or if the granter
//    is sharing their own data: 'their data')
// 7. URL to recipient's user account
define('EMAIL_NEW_COLLEAGUE', <<<EMAILDOC
Dear %1\$s,

%3\$s (%4\$s) has given you access to
%6\$s in LOVD located at:
%2\$s

If you know this person, you can consider to share access to them so
they can view and edit your data. You can do so by going to your
account page, click "Share access to your entries with other users",
select the appropriate user accounts and click "save".
Access your account here: %7\$s

If you think this email is not intended for you, please contact the
sender: %3\$s %5\$s

Kind regards,
    LOVD system at Leiden University Medical Center
EMAILDOC
);


// Email body to be sent to users of whom access has been shared (in
// case somebody else granted them).
// 1. Sharer's name
// 2. Granter's name
// 3. Granter's institute
// 4. Granter's email
// 5. List of recipient names and institutions (newline separated).
// 6. URL of sharer's user account
define('EMAIL_SHARER_NEW_COLLEAGUE', <<<EMAILDOC
Dear %1\$s,

%2\$s (%3\s) has granted access to your data to
the following people:
%5\$s

If you think this was a mistake, please contact %2\$s
(%4\$s). You can also manage the permissions yourself from
your account page:
%6\$s

Kind regards,
    LOVD system at Leiden University Medical Center
EMAILDOC
);




function lovd_mailNewColleagues ($sUserID, $sUserFullname, $sUserInstitute, $sUserEmail,
                                 $aNewColleagues)
{
    // Send an email to users with an ID in $aNewColleagues, letting them know
    // the user denoted by $sUserID has shared access to his data with them.
    require_once ROOT_PATH . 'inc-lib-form.php';
    global $_DB, $_SETT, $_AUTH;

    if (!is_array($aNewColleagues) || !$aNewColleagues) {
        // Nothing to be done.
        return false;
    }

    // Fetch names/email addresses for new colleagues.
    $sPlaceholders = '(?' . str_repeat(',?', count($aNewColleagues)-1) . ')';
    $sColleagueQuery = 'SELECT id, name, institute, email FROM ' . TABLE_USERS . ' WHERE id IN ' .
        $sPlaceholders;
    $zColleagues = $_DB->query($sColleagueQuery, $aNewColleagues)->fetchAllAssoc();

    $sApplicationURL = lovd_getInstallURL();
    $sGranterFullname = $_AUTH['name'];
    $sGranterInstitute = $_AUTH['institute'];
    $aGranterEmails = explode("\r\n", $_AUTH['email']);
    $sGranterEmail = isset($aGranterEmails[0])? $aGranterEmails[0] : '';

    if ($sUserID == $_AUTH['id']) {
        // User who is granting permissions is the same as who's data is being shared.

        $sResourceDescription = 'their data';
    } else {
        // Somebody else (e.g. a manager) is granting access to someone else's data.

        $sResourceDescription = 'data of ' . $sUserFullname . ' (' . $sUserInstitute . ')';

        // Send notification email to the one who's data is being shared.
        $aSharerEmails = explode("\r\n", $sUserEmail);
        $sSharerEmail = isset($aSharerEmails[0])? $aSharerEmails[0] : '';
        $aRecipients = array();
        foreach ($zColleagues as $zColleague) {
            $aRecipients[] = '* ' . $zColleague['name'] . ' (' . $zColleague['institute'] . ')';
        }
        $sRecipients = join("\n", $aRecipients);
        $sSharerAccountURL = $sApplicationURL . 'users/' . $sUserID;
        $sSharerMailbody = sprintf(EMAIL_SHARER_NEW_COLLEAGUE, $sUserFullname, $_AUTH['name'],
                                   $_AUTH['institute'], $sGranterEmail, $sRecipients,
                                   $sSharerAccountURL);
        lovd_sendMail(array(array($sUserFullname, $sSharerEmail)), 'LOVD access sharing',
                      $sSharerMailbody, $_SETT['email_headers'], false, false);
    }


    foreach ($zColleagues as $zColleague) {
        $sRecipientAccountURL = $sApplicationURL . 'users/' . $zColleague['id'];

        // Setup mail text and fill placeholders.
        $sMailBody = sprintf(EMAIL_NEW_COLLEAGUE, $zColleague['name'], $sApplicationURL,
            $sGranterFullname, $sGranterInstitute, $sGranterEmail, $sResourceDescription,
            $sRecipientAccountURL);

        // Only use Windows-style line endings, so that it looks good on all platforms.
        $sMailBody = str_replace("\n", "\r\n", $sMailBody);

        // Note: email field is new-line separated list of email addresses.
        lovd_sendMail(array(array($zColleague['name'], $zColleague['email'])),
                      'LOVD access sharing', $sMailBody, $_SETT['email_headers'], false, false);
    }
}





function lovd_colleagueTableHTML ($sUserID, $sUserListID, $aColleagues = null, $bAllowGrantEdit = true)
{
    // Returns HTML for form to share access of a user's objects with another
    // user.

    global $_DB;

    require_once ROOT_PATH . 'inc-lib-form.php';

    if (is_null($aColleagues)) {
        // Get colleagues of given user from database.
        $sQuery = 'SELECT
                     u.id,
                     u.name,
                     c.allow_edit
                   FROM ' . TABLE_COLLEAGUES . ' AS c
                    LEFT JOIN ' . TABLE_USERS . ' AS u ON (u.id = c.userid_to)
                   WHERE c.userid_from = ?';
        $aColleagues = $_DB->query($sQuery, array($sUserID))->fetchAllAssoc();
    }

    $sEditStyleAttribute = ($bAllowGrantEdit? '' : 'display: none;');

    // HTML for row in colleague list. This contains 4 string directives:
    // 1=user's ID, 2=user's name, 3=attribute for edit checkbox (e.g.
    // "checked" or an empty string), 4=css style for the edit checkbox.
    // Note: this is to be parsed by sprintf(), so remember to escape %-signs.
    $sTableColleaguesRow = <<<DOCCOLROW
<LI id="li_%1\$s">
    <INPUT type="hidden" name="colleagues[]" value="%1\$s">
    <INPUT type="hidden" name="colleague_name[]" value="%2\$s">
    <TABLE width="100%%">
        <TR>
            <TD>%2\$s (#%1\$s)</TD>
            <TD style="width: 100; text-align: right;">
                <INPUT type="checkbox" name="allow_edit[]" value="%1\$s" %3\$s style="%4\$s">
            </TD>
            <TD width="30" align="right">
                <A href="#" onclick="lovd_removeUserShareAccess('$sUserListID', '%1\$s'); return false;" title="Remove">
                    <IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0">
                </A>
            </TD>
        </TR>
    </TABLE>
</LI>
DOCCOLROW;

    // HTML for list (table) of users. This contains 2 string directives:
    // 1=row html (as in $sTableColleaguesRow), 2=style for the edit checkboxes
    // and table heading. Note: this is to be parsed by sprintf(), so
    // remember to escape %-signs.
    $sTableColleagues = <<<DOCCOL
<TABLE class="sortable_head" style="width : 552px;">
    <TR>
        <TH>Name</TH>
        <TH style="width: 100; text-align: right;"><SPAN style="%2\$s\$">Allow edit</SPAN></TH>
        <TH width="30">&nbsp;</TH>
    </TR>
</TABLE>
<UL id="$sUserListID" class="sortable" style="margin-top : 0px; width : 550px;">
%1\$s
</UL>
<SCRIPT type='text/javascript'>
function lovd_addUserShareAccess (aUser)
{
    // Adds user to the list of colleagues.
    // FIXME: How do we standardize this in such a way, that we don't duplicate
    //  the code for this LI? It's currently in PHP and JS.
    $('#$sUserListID').append(
        '<LI id="li_' + aUser.id + '">' +
            '<INPUT type="hidden" name="colleagues[]" value="' + aUser.id + '">' +
            '<INPUT type="hidden" name="colleague_name[]" value="' + aUser.name + '">' +
            '<TABLE width="100%%">' +
                '<TR>' +
                    '<TD>' + aUser.name + ' (#' + aUser.id + ')</TD>' +
                    '<TD style="width: 100; text-align: right;">' +
                        '<INPUT type="checkbox" name="allow_edit[]" value="' + aUser.id + '" style="%2\$s">' +
                    '</TD>' +
                    '<TD width="30" align="right">' +
                        '<A href="#" onclick="lovd_removeUserShareAccess(\'$sUserListID\', \'' + aUser.id + '\'); return false;" title="Remove user">' +
                            '<IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0">' +
                        '</A>' +
                    '</TD>' +
                '</TR>' +
            '</TABLE>' +
        '</LI>');
}



function lovd_removeUserShareAccess (sViewListID, nID)
{
    // Removes user from the list of colleagues and reloads the VL with the user
    //  back in there.

    objViewListF = document.getElementById('viewlistForm_' + sViewListID);
    objLI = document.getElementById('li_' + nID);

    // First remove from block, simply done (no fancy animation).
    objLI.parentNode.removeChild(objLI);

    // Reset the viewList.
    // Does an ltrim, too. But trim() doesn't work in IE < 9.
    objViewListF.search_id.value = objViewListF.search_id.value.replace('!' + nID, '').replace('  ', ' ').replace(/^\s*/, '');
    lovd_AJAX_viewListSubmit(sViewListID);

    return true;
}
</SCRIPT>
DOCCOL;

    // Construct list of colleagues
    $sColleaguesHTML = '';
    foreach ($aColleagues as $aUserFields) {
        $sChecked = ($aUserFields['allow_edit'] == '1'? 'checked' : '');
        $sColleaguesHTML .= sprintf($sTableColleaguesRow, $aUserFields['id'], $aUserFields['name'],
                                    $sChecked, $sEditStyleAttribute);
    }
    $sFullHTML = sprintf($sTableColleagues, $sColleaguesHTML, $sEditStyleAttribute);
    return array($aColleagues, $sFullHTML);
}





function lovd_setColleagues ($sUserID, $sUserFullname, $sUserInsititute, $sUserEmail, $aColleagues,
                             $bAllowGrantEdit = true)
{
    // Removes all existing colleagues for user $sUserID and replaces them with
    // all IDs in $aColleagues.
    // Throws an exception (Exception) when something goes wrong.

    global $_DB;

    if (!$bAllowGrantEdit) {
        foreach ($aColleagues as $aColleague) {
            if ($aColleague['allow_edit']) {
                lovd_displayError('ShareAccess', 'Not allowed to grant edit permissions.');
            }
        }
    }

    $sOldColleaguesQuery = 'SELECT userid_to FROM ' . TABLE_COLLEAGUES . ' WHERE userid_from = ?';
    $aOldColleagueIDs = $_DB->query($sOldColleaguesQuery, array($sUserID))->fetchAllColumn();
    $aColleagueIDs = array();

    $_DB->beginTransaction();

    // Delete all current colleague records with given user in 'from' field.
    $_DB->query('DELETE FROM ' . TABLE_COLLEAGUES . ' WHERE userid_from = ?', array($sUserID));

    if (count($aColleagues)) {
        // Build parts for multi-row insert query.
        $sPlaceholders = '(?,?,?)' . str_repeat(',(?,?,?)', count($aColleagues)-1);
        $aData = array();
        foreach ($aColleagues as $aColleague) {
            array_push($aColleagueIDs, $aColleague['id']);
            array_push($aData, $sUserID, $aColleague['id'], $aColleague['allow_edit']);
        }

        $_DB->query('INSERT INTO ' . TABLE_COLLEAGUES .
                    ' (userid_from, userid_to, allow_edit) VALUES ' . $sPlaceholders, $aData);
    }
    $_DB->commit();

    // Notify only the newly added colleagues by email.
    // Note: call array_values() on the parameter array to make sure the
    // indices are normalized to what PDO expects.
    $aNewColleagueIDs = array_values(array_diff($aColleagueIDs, $aOldColleagueIDs));
    lovd_mailNewColleagues($sUserID, $sUserFullname, $sUserInsititute, $sUserEmail, $aNewColleagueIDs);

    // Write to log.
    $aColleagueIDsAdded = array_values(array_diff($aColleagueIDs, $aOldColleagueIDs));
    $aColleagueIdsRemoved = array_values(array_diff($aOldColleagueIDs, $aColleagueIDs));
    $sMessage = 'Updated colleagues for user #' . $sUserID . "\nAdded: ";
    for ($i = 0; $i < count($aColleagueIDsAdded); $i++) {
        $aColleagueIDsAdded[$i] = 'user #' . $aColleagueIDsAdded[$i];
    }
    $sMessage .= $aColleagueIDsAdded? join(', ', $aColleagueIDsAdded) : 'none';
    $sMessage .= "\nRemoved: ";
    for ($i = 0; $i < count($aColleagueIdsRemoved); $i++) {
        $aColleagueIdsRemoved[$i] = 'user #' . $aColleagueIdsRemoved[$i];
    }
    $sMessage .= $aColleagueIdsRemoved? join(', ', $aColleagueIdsRemoved) : 'none';
    $sMessage .= "\n";
    lovd_writeLog('Event', LOG_EVENT, $sMessage);
}





function lovd_showPageAccessDenied ($sLogMessage = null, $sPageTitle = 'Access denied',
                                    $sInfoText = 'You do not have access to this content.')
{
    // Show a page saying access denied.
    global $_T;

    $_T->printHeader();

    if (!is_null($sPageTitle)) {
        $_T->printTitle($sPageTitle);
    } else {
        $_T->printTitle();
    }

    if (!is_null($sLogMessage)) {
        lovd_writeLog('Error', 'HackAttempt', $sLogMessage);
    }
    lovd_showInfoTable($sInfoText, 'stop');
    $_T->printFooter();
}
