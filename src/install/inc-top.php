<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2009-10-19
 * Modified    : 2012-04-20
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

define('_INC_TOP_INCLUDED_', true);
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Install');
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<HTML lang="en_US">
<HEAD>
  <TITLE><?php echo (!PAGE_TITLE? '' : PAGE_TITLE . ' - ') . $_CONF['system_title']; ?></TITLE>
  <META http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <META name="author" content="LOVD development team, LUMC, Netherlands">
  <META name="generator" content="gPHPEdit / GIMP @ GNU/Linux (Ubuntu)">
  <BASE href="<?php echo lovd_getInstallURL(); ?>">
  <LINK rel="stylesheet" type="text/css" href="styles.css">
  <LINK rel="shortcut icon" href="favicon.ico" type="image/x-icon">
</HEAD>

<BODY style="margin : 0px;">

<TABLE border="0" cellpadding="0" cellspacing="0" width="100%" class="logo" style="border-bottom : 2px solid #000000;">
  <TR>
    <TD valign="top" width="150">
      <IMG src="gfx/LOVD_logo130x50.jpg" alt="LOVD - Leiden Open Variation Database" width="130" height="50">
    </TD>
<?php
print('    <TD valign="top" style="padding-top : 2px;">' . "\n" .
      '      <H2 style="margin-bottom : 2px;">' . $_CONF['system_title'] . '</H2>' . "\n" .
      '    </TD>' . "\n" .
      '    <TD valign="top" align="right" style="padding-right : 5px; padding-top : 2px;">' . "\n" .
      '      LOVD v.' . $_STAT['tree'] . ' Build ' . $_STAT['build'] . '<BR>' . "\n" .
      '    </TD>' . "\n" .
      '  </TR>' . "\n" .
      '</TABLE>' . "\n\n");
?>







