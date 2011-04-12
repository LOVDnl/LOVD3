<?php
// DMD_SPECIFIC, this code sucks. Look at all that opening and closing the PHP tags!
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2011-04-08
 * For LOVD    : 3.0-pre-19
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
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

// Load menu.
// FIXME; this needs to be a dropdown menu (like www.lgtc.nl)
$_MENU = array(
                'genes' => (!empty($_SESSION['currdb'])? $_SESSION['currdb'] . ' homepage' : 'Home'),
                 array(
                        '' => 'View all gene entries',
                        'create' => 'Create a new gene entry',
                      ),
                'transcripts' => 'View transcripts',
                'variants' => 'View variants',
                'individuals' => 'View individuals',
                'diseases' => 'View diseases',
                 array(
                        '' => 'View all disease entries',
                        'create' => 'Create a new disease information entry',
                      ),
                'screenings' => 'View screenings',
                'submit' => 'Submit new data',
                'users' => 'LOVD users &amp; submitters',
                'setup' => 'LOVD system setup',
                'docs' => 'LOVD documentation',
              );

// Remove certain menu entries, if the user has no access to them.
if (!$_AUTH || $_AUTH['level'] < LEVEL_MANAGER) {
    unset($_MENU['users']);
    unset($_MENU['setup']);
}

if (!defined('PAGE_TITLE')) {
    $sFile = substr(lovd_getProjectFile(), 1, strrpos(lovd_getProjectFile(), '.') - 1);
    if (array_key_exists($sFile, $_MENU)) {
        define('PAGE_TITLE', $_MENU[$sFile]);
    } else {
        define('PAGE_TITLE', '');
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" 
        "http://www.w3.org/TR/html4/strict.dtd">
<HTML lang="en_US">
<HEAD>
  <TITLE><?php echo (!PAGE_TITLE? '' : PAGE_TITLE . ' - ') . $_CONF['system_title']; ?></TITLE>
  <META http-equiv="Content-Type" content="text/html; charset=UTF-8"> 
  <META name="author" content="LOVD development team, LUMC, Netherlands">
  <META name="generator" content="gPHPEdit / GIMP @ GNU/Linux (Ubuntu)">
  <BASE href="<?php echo lovd_getInstallURL(); ?>">
  <LINK rel="stylesheet" type="text/css" href="styles.css">
  <LINK rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<?php
// DMD_SPECIFIC; later?
/*  <LINK rel="alternate" type="application/atom+xml" title="<?php echo $_CONF['system_title']; ?> Atom 1.0 feed" href="<?php echo ROOT_PATH; ?>api/feed.php" />*/
?>

  <SCRIPT type="text/javascript">
    <!--

<?php
// A quick way to switch genes, regardless of on which page you are.
// DMD_SPECIFIC, this does not work yet, needs to be rewritten, how do we do that?
// SOWIESO ALS JE HIER AAN GAAT WERKEN, PAK DE NIEUWE LOVDv.2.0 CODE!
/*
print('    function lovd_switchGeneInline () {' . "\n" .
// IF THIS IS IMPORTED IN 3.0, you'll need to check this properly. Probably don't want to use SCRIPT_NAME here.
      '      varForm = \'<FORM action="' . $_SERVER['SCRIPT_NAME'] . '" id="SelectGeneDBInline" method="get" style="margin : 0px;"><SELECT name="select_db" onchange="document.getElementById(\\\'SelectGeneDBInline\\\').submit();">');
$q = lovd_queryDB('SELECT id, CONCAT(id, " (", name, ")") AS name FROM ' . TABLE_DBS . ' ORDER BY id');
while ($z = mysql_fetch_assoc($q)) {
    // This will shorten the gene names nicely, to prevent long gene names from messing up the form.
    $z['gene'] = lovd_shortenString($z['gene'], 75);
    if (substr($z['gene'], -3) == '...') {
        $z['gene'] .= str_repeat(')', substr_count($z['gene'], '('));
    }
    // The str_replace will translate ' into \' so that it does not disturb the JS code.
    print('<OPTION value="' . $z['id'] . '"' . ($_SESSION['currdb'] == $z['id']? ' selected' : '') . '>' . str_replace("'", "\'", $z['gene']) . '</OPTION>');
}
print('</SELECT>');
// 2009-07-22; 2.0-21; Only use the $_GET variables that we have received (and not the ones we created ourselves).
$aGET = explode('&', $_SERVER['QUERY_STRING']);
foreach ($aGET as $val) {
    if ($val) { // 2009-09-15; 2.0-22; Added if() to make sure pages without $_GET variables don't throw a notice.
        @list($key, $val) = explode('=', $val);
        if (lovd_getProjectFile() == '/variants.php' && $key == 'view' && !is_numeric($val)) {
            // Fix problem when switching gene while viewing detailed variant information.
            $val = preg_replace('/^([0-9]+).*$/', "$1", $val);
        }
        if (!in_array($key, array('select_db', 'sent'))) {
            print('<INPUT type="hidden" name="' . htmlspecialchars(rawurldecode($key)) . '" value="' . htmlspecialchars(rawurldecode($val)) . '">');
        }
    }
}
print('<INPUT type="submit" value="Switch"></FORM>\';' . "\n" .
      '      document.getElementById(\'gene_name\').innerHTML=varForm;' . "\n" .
      '    }' . "\n");
*/
?>

    //-->
  </SCRIPT>
<?php
lovd_includeJS('inc-js-openwindow.php', 1);
lovd_includeJS('inc-js-toggle-visibility.js', 1); // Used on forms and variant overviews for small info tables.
?>
</HEAD>

<BODY style="margin : 0px;">

<TABLE border="0" cellpadding="0" cellspacing="0" width="100%"><TR><TD>

<TABLE border="0" cellpadding="0" cellspacing="0" width="100%" class="logo">
  <TR>
<?php
if (!is_readable(ROOT_PATH . $_CONF['logo_uri'])) {
    $_CONF['logo_uri'] = 'gfx/LOVD_logo130x50.jpg';
}
$aImage = @getimagesize(ROOT_PATH . $_CONF['logo_uri']);
if (!is_array($aImage)) {
    $aImage = array('130', '50', '', 'width="130" heigth="50"');
}    
list($nWidth, $nHeight, $sType, $sSize) = $aImage;
print('    <TD valign="top" width="' . ($nWidth + 20) . '" height="' . ($_CONF['logo_uri'] == 'gfx/LOVD_logo130x50'? $nHeight : $nHeight + 20) . '">' . "\n" .
      '      <IMG src="' . $_CONF['logo_uri'] . '" alt="LOVD - Leiden Open Variation Database" ' . $sSize . '>' . "\n");
?>
    </TD>
<?php
$sCurrSymbol = $sCurrGene = '';
// DMD_SPECIFIC; decide later what will happen here. Only show gene when you're truly working in it? In which case, it's a variable somewhere?
/*
// During submission, show the gene we're submitting to instead of the currently selected gene.
if (lovd_getProjectFile() == '/submit.php' && !empty($_POST['gene']) && $_POST['gene'] != $_SESSION['currdb']) {
    // Fetch gene's info from db... we don't have it anywhere yet.
    list($sCurrSymbol, $sCurrGene) = mysql_fetch_row(lovd_queryDB('SELECT id, gene FROM ' . TABLE_DBS . ' WHERE id = ?', array($_POST['gene'])));
} elseif (!empty($_SESSION['currdb'])) {
    // Just use currently selected database.
    $sCurrSymbol = $_SESSION['currdb'];
    $sCurrGene = $_SETT['currdb']['gene'];
}
*/

print('    <TD valign="top" style="padding-top : 2px;">' . "\n" .
      '      <H2 style="margin-bottom : 2px;">' . $_CONF['system_title'] . '</H2>' . "\n" .
      ($sCurrSymbol && $sCurrGene? '      <H5 id="gene_name">' . $sCurrGene . ' (' . $sCurrSymbol . ')&nbsp;<A href="#" onclick="javascript:lovd_switchGeneInline(); return false;"><IMG src="gfx/lovd_database_switch_inline.png" width="23" height="23" alt="Switch gene" title="Switch gene database" align="top"></A></H5>' . "\n" : '') .
      '    </TD>' . "\n" .
      '    <TD valign="top" align="right" style="padding-right : 5px; padding-top : 2px;">' . "\n" .
      '      LOVD v.' . $_STAT['tree'] . ' Build ' . $_STAT['build'] . ' [ <A href="status">Current LOVD status</A> ]<BR>' . "\n");
if ($_AUTH) {
    print('      <B>Welcome, ' . $_AUTH['name'] . '</B><BR>' . "\n" .
          '      <A href="users/' . $_AUTH['id'] . '"><B>Your account</B></A> | ' . ($_AUTH['level'] == LEVEL_SUBMITTER && $_CONF['allow_submitter_mods']? '<A href="variants?search_created_by=' . $_AUTH['id'] . '"><B>Your submissions</B></A> | ' : '') . '<A href="logout"><B>Log out</B></A><BR>' . "\n");
} else {
    print('      <A href="users?register"><B>Register as submitter</B></A> | <A href="login"><B>Log in</B></A><BR>' . "\n");
}

print('    </TD>' . "\n" .
      '  </TR>' . "\n");

// Add curator info to header.
// DMD_SPECIFIC; Note that users email is now more complex. It can contain more lines. So trim() and explode? Or preg_match until the first whitespace or something?
if ($sCurrSymbol && $sCurrGene) {
    $sCurators = '';
    $qCurators = lovd_queryDB('SELECT u.name, u.email FROM ' . TABLE_USERS . ' AS u LEFT JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) WHERE u2g.geneid = ? AND u2g.allow_edit = 1 ORDER BY u.level DESC, u.name', array($sCurrSymbol));
    $nCurators = mysql_num_rows($qCurators);
    $i = 0;
    while ($z = mysql_fetch_assoc($qCurators)) {
        $i ++;
        $sCurators .= ($sCurators? ($i == $nCurators? ' and ' : ', ') : '') . '<A href="mailto:' . $z['email'] . '">' . $z['name'] . '</A>';
    }

    if ($sCurators) {
        print('  <TR>' . "\n" .
              '    <TD width="150">&nbsp;</TD>' . "\n" .
              '    <TD valign="top" colspan="2" style="padding-bottom : 2px;"><B>' . ($nCurators > 1 ? 'Curators: ' : 'Curator: ') . $sCurators . '</B></TD>' . "\n" .
              '  </TR>' . "\n");
    }
}

print('</TABLE>' . "\n\n");



// Build menu tabs...
print('<TABLE border="0" cellpadding="0" cellspacing="0" width="100%" class="logo">' . "\n" .
      '  <TR>' . "\n" .
      '    <TD align="left" style="background : url(\'gfx/tab_fill.png\'); background-repeat : repeat-x;">' . "\n");

// Loop menu.
$n         = 0;
$bSel      = false;
$bPrevSel  = false;
foreach ($_MENU as $sPrefix => $sTitle) {
    // Array (children links of parent tabs) are not processed here.
    if (is_array($sTitle)) {
        continue;
    }

    // Determine if we're the current tab.
    $bSel = (substr(lovd_getProjectFile(), 1, strrpos(lovd_getProjectFile(), '.') - 1) == $sPrefix);
    // Auch! Hard coded exception!
    if (!$bSel && $sPrefix == 'docs' && substr(lovd_getProjectFile(), 0, 6) == '/docs/') { $bSel = true; }
    $sFileName = 'tab_' . $sPrefix;

    // Print transition.
    print('      <IMG src="gfx/tab_' . (!$n? '0' : ($bPrevSel? 'F' : 'B')) . ($bSel? 'F' : 'B') . '.png" alt="" width="25" height="25" align="left">' . "\n");

    // Get header info.
    $sFileName = 'gfx/' . $sFileName . '_' . ($bSel? 'F' : 'B') . '.png';
    $aImage = @getimagesize($sFileName);
    $sSize = $aImage[3];

    // Print header.
    print('      <A href="' . $sPrefix . '"><IMG src="' . $sFileName . '" alt="' . $sTitle . '" title="' . $sTitle . '" ' . $sSize . ' align="left"></A>' . "\n");

    $bPrevSel = $bSel;
    $n ++;
}

// Closing transition.
print('      <IMG src="gfx/tab_' . ($bPrevSel? 'F' : 'B') . '0.png" alt="" width="25" height="25" align="left">' . "\n");

print('    </TD>' . "\n" .
      '  </TR>' . "\n" .
      '</TABLE>' . "\n\n");
?>



<DIV style="padding : 0px 10px;">
<TABLE border="0" cellpadding="0" cellspacing="0" width="100%">
  <TR>
    <TD style="padding-top : 10px;">
      <?php echo (isset($_PATH_ELEMENTS[1])? '<A href="' . $_PATH_ELEMENTS[0] . '">&lt;- Back</A>' : '' ) ?>







