<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2010-06-25
 * For LOVD    : 3.0-pre-07
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

define('_INC_TOP_INCLUDED_', true);

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" 
        "http://www.w3.org/TR/html4/loose.dtd">
<HTML lang="en_US">
<HEAD>
  <TITLE>Leiden Open Variation Database</TITLE>
  <META http-equiv="Content-Type" content="text/html; charset=UTF-8"> 
  <META name="Author" content="LOVD development team, LUMC, Netherlands">
  <META name="Generator" content="gPHPEdit / GIMP @ GNU/Linux (Ubuntu)">
  <BASE href="<?php echo lovd_getInstallURL(); ?>">
  <LINK rel="stylesheet" type="text/css" href="styles.css">
</HEAD>

<BODY style="margin : 0px;">

<TABLE border="0" cellpadding="0" cellspacing="0" width="100%" class="logo" style="border-bottom : 2px solid #000000;">
  <TR>
    <TD width="150">
      <IMG src="gfx/LOVD_logo130x50.jpg" alt="LOVD - Leiden Open Variation Database" width="130" height="50">
    </TD>
<?php
print('    <TD valign="top" style="padding-top : 2px;">' . "\n" .
      '      <H2>' . $_CONF['system_title'] . '</H2>' . "\n" .
      '    </TD>' . "\n" .
      '    <TD valign="top" align="right" style="padding-right : 5px; padding-top : 2px;">' . "\n" .
      '      LOVD v.' . $_STAT['tree'] . ' Build ' . $_STAT['build'] . '<BR>' . "\n" .
      '    </TD>' . "\n" .
      '  </TR>' . "\n" .
      '</TABLE>' . "\n\n");





// Shows sidebar with installation steps and the installation progress bar.
// Sidebar with the steps laid out.
if (ROOT_PATH == '../') { // Basically, when installing!
    print('<BR>' . "\n\n" .
          '<TABLE border="0" cellpadding="0" cellspacing="0" width="100%" style="padding : 0px 10px;">' . "\n" .
          '  <TR valign="top">' . "\n" .
          '    <TD width="190">' . "\n" .
          '      <TABLE border="0" cellpadding="5" cellspacing="0" align="left" width="100%" class="S11">' . "\n" .
          '        <TR align="center" class="S13">' . "\n" .
          '          <TH style="background : #224488; color : #FFFFFF; height : 20px; border : 1px solid #002266;"><IMG src="gfx/trans.png" alt="" width="178" height="1"><BR><I>Installation steps</I></TH></TR>');

    foreach ($aInstallSteps as $nStep => $aStep) {
        // Loop through install steps.
        print("\n" .
              '        <TR align="center">' . "\n" .
              '          <TD style="height : 60px; border : 1px solid #002266; border-top : 0px; background : #' . ($nStep == $_GET['step']? 'CCE0FF; font-weight : bold' : ($nStep < $_GET['step']? 'F0F0F0; color : #666666' : 'FFFFFF')) . ';">' . $aStep[1] . '</TD></TR>');
    }

    // Close table.
    print('</TABLE></TD>' . "\n" .
          '    <TD style="padding-left : 10px;">' . "\n\n");



    // Top progress bar.
    print('
      <TABLE border="0" cellpadding="0" cellspacing="0" class="S11" width="100%">
        <TR>
          <TD colspan="2">Installation progress</TD></TR>
        <TR>
          <TD colspan="2" style="border : 1px solid black; height : 5px;">
            <IMG src="gfx/trans.png" alt="" title="' . $aInstallSteps[$_GET['step']][0] . '%" width="' . $aInstallSteps[$_GET['step']][0] . '%" height="5" id="lovd_install_bar" style="background : #99EE66;"></TD></TR>
        <TR>
          <TD align="left">0%</TD>
          <TD align="right">100%</TD></TR></TABLE><BR>' . "\n\n");
}
?>







