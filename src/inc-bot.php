<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-15
 * Modified    : 2012-04-05
 * For LOVD    : 3.0-beta-04
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Jerry Hoogenboom <J.Hoogenboom@LUMC.nl>
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
?>










    </TD>
  </TR>
</TABLE>
</DIV>
<BR>

<TABLE border="0" cellpadding="0" cellspacing="0" width="100%" class="footer">
  <TR>
    <TD width="84">
      &nbsp;
    </TD>
    <TD align="center">
<?php
if (substr(lovd_getProjectFile(), 0, 6) == '/docs/') {
    // In documents section.
    print('  For the latest version of the LOVD manual, <A href="' . $_SETT['upstream_URL'] . $_SETT['system']['tree'] . '/docs/" target="_blank">check the online version</A>.<BR>' . "\n");
    
}
print('  Powered by <A href="' . $_SETT['upstream_URL'] . $_STAT['tree'] . '/" target="_blank">LOVD v.' . $_STAT['tree'] . '</A> Build ' . $_STAT['build'] . '<BR>' . "\n" .
      '  &copy;2004-2012 <A href="http://www.lumc.nl/" target="_blank">Leiden University Medical Center</A>' . "\n");
?>
    </TD>
    <TD width="42" align="right">
      <IMG src="<?php echo ROOT_PATH; ?>gfx/lovd_mapping_99.png" alt="" title="" width="32" height="32" id="mapping_progress" style="margin : 5px;">
    </TD>
    <TD width="42" align="right">
<?php
if (!defined('_NOT_INSTALLED_')) {
    if ((time() - strtotime($_STAT['update_checked_date'])) > (60*60*24)) {
        // Check for updates!
        $sImgURL = 'check_update?icon';
    } else {
        // No need to re-check, use saved info.
        if ($_STAT['update_version'] == 'Error') {
            $sType = 'error';
        } elseif (lovd_calculateVersion($_STAT['update_version']) > lovd_calculateVersion($_SETT['system']['version'])) {
            $sType = 'newer';
        } else {
            $sType = 'newest';
        }
        $sImgURL = 'gfx/lovd_update_' . $sType . '_blue.png';
    }
    if ($_AUTH && ($_AUTH['level'] >= LEVEL_MANAGER || count($_AUTH['curates']))) {
        print('      <A href="#" onclick="lovd_openWindow(\'check_update\', \'CheckUpdate\', 650, 175); return false;"><IMG src="' . $sImgURL . '" alt="" width="32" height="32" style="margin : 5px;"></A>' . "\n");
    } else {
        print('      <IMG src="' . $sImgURL . '" alt="" width="32" height="32" style="margin : 5px;">' . "\n");
    }
}
?>
    </TD>
  </TR>
</TABLE>

</TD></TR></TABLE>
<SCRIPT type="text/javascript">
  <!--
<?php
print('
function lovd_mapVariants ()
{
    // This function requests the script that will do the actual work.

    // First unbind any onclick handlers on the status image.
    $("#mapping_progress").unbind();

    // Now request the script.
    $.get("' . ROOT_PATH . 'ajax/map_variants.php", function (sResponse)
        {
            // The server responded successfully. Let\'s see what he\'s saying.
            aResponse = sResponse.split("\t");
            $("#mapping_progress").attr({"src": "' . ROOT_PATH . 'gfx/lovd_mapping_" + aResponse[1] + ".png", "title": aResponse[2]});

            if (sResponse.indexOf("Notice") >= 0 || sResponse.indexOf("Warning") >= 0 || sResponse.indexOf("Error") >= 0 || sResponse.indexOf("Fatal") >= 0) {
                // Something went wrong while processing the request, don\'t try again.
                $("#mapping_progress").attr({"src": "' . ROOT_PATH . 'gfx/lovd_mapping_99.png", "title": "There was a problem with LOVD while mapping variants to transcripts."});
            } else if (aResponse[0] == "' . AJAX_TRUE . '") {
                // More variants to map. Re-call.
                setTimeout("lovd_mapVariants()", 50);
            } else {
                // No more variants to map. But allow the user to try.
                $("#mapping_progress").click(lovd_mapVariants);
            }
        }
    ).error(function ()
        {
            // Something went wrong while contacting the server, don\'t try again.
            $("#mapping_progress").attr({"src": "' . ROOT_PATH . 'gfx/lovd_mapping_99.png", "title": "There was a problem with LOVD while mapping variants to transcripts."});
        }
    );
}
');

// Not every page request should trigger the mapping...
if (!empty($_SESSION['mapping']['time_complete']) && $_SESSION['mapping']['time_complete'] >= (time() - 60 * 60 * 24)) {
    // If it is less than one day ago that mapping was complete, don't start it automatically.
    print('$("#mapping_progress").click(lovd_mapVariants);' . "\n");
} elseif (!empty($_SESSION['mapping']['time_error']) && $_SESSION['mapping']['time_error'] >= (time() - 60 * 60)) {
    // If it is less than one hour ago that an error occurred, don't start it either.
    print('$("#mapping_progress").click(lovd_mapVariants);' . "\n");
    print('$("#mapping_progress").attr("Title", "Mapping is temporarily suspended because of network problems on the last attempt. Click to retry.");' . "\n");
} else {
    // If we won't start it, the user should be able to start it himself.
    print('setTimeout("lovd_mapVariants()", 500);' . "\n");
}
?>
  // -->
</SCRIPT>

<?php
if (!defined('_INC_BOT_CLOSE_HTML_') || _INC_BOT_CLOSE_HTML_ !== false) {
    // Sounds kind of stupid, but this prevents the inc-bot to actually close the <BODY> and <HTML> tags.
    print('</BODY>' . "\n" .
          '</HTML>' . "\n");
} else {
    flush();
}
