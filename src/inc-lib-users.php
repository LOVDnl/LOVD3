<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-21
 * Modified    : 2016-04-21
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
            '<TABLE>' +
                '<TR>' +
                    '<TD>' + viewlistItem.Name + ' (#' + viewlistItem.ID + ')</TD>' +
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




function lovd_setColleagues($sUserID, $aColleagues) {
    // Removes all existing colleagues for user $sUserID and replaces them with
    // all IDs in $aColleagues.
    // Throws an exception (Exception) when something goes wrong.

    global $_DB;

    $sLogEvent = 'SetColleagues';

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
        lovd_writeLog('Error', $sLogEvent, $sMessage);
        throw new Exception($sMessage);
    }

    // Write to log.
    $sMessage = 'updated colleagues for user (' . $sUserID . ') with (' .
        join(', ', $aColleagues) . ')';
    lovd_writeLog('Event', $sLogEvent, $sMessage);
}



function lovd_showPageAccessDenied() {
    // Show a page saying access denied.

    global $_T;

    $_T->printHeader();
    $_T->printTitle('Access denied');
    lovd_showInfoTable('You do not have access to this content.', 'warning');
    $_T->printFooter();
}
