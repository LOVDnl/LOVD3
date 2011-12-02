<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-01-14
 * Modified    : 2011-12-02
 * For LOVD    : 3.0-alpha-07
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
$_MENU = array(
                'genes' => (!empty($_SESSION['currdb'])? $_SESSION['currdb'] . ' homepage' : 'Home'),
                'genes_' =>
                 array(
                        '' => array('menu_magnifying_glass.png', 'View all genes', 0),
                        '/genes/' . $_SESSION['currdb'] => array('menu_magnifying_glass.png', 'View the ' . $_SESSION['currdb'] . ' gene homepage', 0),
                        'create' => array('plus.png', 'Create a new gene entry', LEVEL_MANAGER),
                      ),
                'transcripts' => 'View transcripts',
                'transcripts_' =>
                 array(
                        '' => array('menu_magnifying_glass.png', 'View all transcripts', 0),
                        '/transcripts/' . $_SESSION['currdb'] => array('menu_magnifying_glass.png', 'View all transcripts of the ' . $_SESSION['currdb'] . ' gene', 0),
                        'create' => array('plus.png', 'Create a new transcript information entry', LEVEL_CURATOR),
                      ),
                'variants' => 'View variants',
                'variants_' =>
                 array(
                        '' => array('menu_magnifying_glass.png', 'View all genomic variants', 0),
                        '/variants/in_gene' => array('menu_magnifying_glass.png', 'View all variants affecting transcripts', 0),
                        '/variants/' . $_SESSION['currdb'] => array('menu_magnifying_glass.png', 'View all variants in the ' . $_SESSION['currdb'] . ' gene', 0),
                        '/submit' => array('plus.png', 'Create a new data submission', LEVEL_SUBMITTER),
                      ),
                'individuals' => 'View individuals',
                 array(
                        '' => array('menu_magnifying_glass.png', 'View all individuals', 0),
                        'create' => array('plus.png', 'Create a new data submission', LEVEL_SUBMITTER),
                        'hr',
                        '/columns/Individual?search_active_=1' => array('', 'View active custom columns', LEVEL_MANAGER),
                        '/columns/Individual?search_active_=0' => array('', 'Enable more custom columns', LEVEL_MANAGER),
                      ),
                'diseases' => 'View diseases',
                 array(
                        '' => array('menu_magnifying_glass.png', 'View all diseases', 0),
                        'create' => array('plus.png', 'Create a new disease information entry', LEVEL_MANAGER), // FIXME; level_curator?
                      ),
                'screenings' => 'View screenings',
                 array(
                        '' => array('menu_magnifying_glass.png', 'View all screenings', 0),
                        '/submit' => array('plus.png', 'Create a new data submission', LEVEL_SUBMITTER),
                      ),
                'submit' => 'Submit new data',
                 array(
                         '' => array('plus.png', 'Submit new data', 0),
                      ),
                'users' => 'LOVD users &amp; submitters',
                'users_' =>
                 array(
                        '' => array('menu_magnifying_glass.png', 'View all users', LEVEL_MANAGER),
                        'create' => array('plus.png', 'Register a new user account', LEVEL_MANAGER), // FIXME; submitter_register?
                        // Public list of submitters?
                        // My submissions?
                      ),
/*
                'config' =>
                         array(
                                array('', '', 'Configuration', 'LOVD configuration area', 'lovd_config'),
                                array('', 'switch_db', 'Switch gene', 'Switch gene', 'lovd_database_switch'),
                                array('variants.php', 'search_all&search_status_=Submitted%7CNon_Public%7CMarked', 'Curate', 'Curate', 'lovd_variants_curate'),
                                'vr',
                                array('config_free_edit.php', 'fnr', 'Find &amp; Replace', 'Find &amp; Replace', 'lovd_free_edit_fnr'),
                                array('config_free_edit.php', 'copy', 'Copy Column', 'Copy Column', 'lovd_free_edit_copy'),
                                'vr',
                                array('columns', 'add', 'Add column', 'Add unselected pre-configured custom variant column to the ' . $_SESSION['currdb'] . ' gene', 'lovd_columns_add'),
                                array('columns', 'view_all', 'Edit columns', 'Manage selected custom columns in the ' . $_SESSION['currdb'] . ' gene', 'lovd_columns_edit'),
                                'vr',
                                array('genes', 'manage', 'Edit gene db', 'Manage ' . $_SESSION['currdb'] . ' gene', 'lovd_database_edit'),
                                array('genes', 'empty', 'Empty gene db', 'Empty ' . $_SESSION['currdb'] . ' gene', 'lovd_database_empty'),
                                'vr',
                                array('download.php', 'view_all', 'Download', 'Download all variants from the ' . $_SESSION['currdb'] . ' gene database', 'lovd_save'),
                                array('import', '', 'Import', 'Import variants into the ' . $_SESSION['currdb'] . ' gene database', 'lovd_database_import'),
                                'vr',
                                array('scripts', '', 'Scripts', 'LOVD scripts', 'lovd_scripts'),
                              ),
*/
                'setup' => 'LOVD system setup',
                'setup_' =>
                 array(
                        '/settings?edit' => array('menu_settings.png', 'LOVD system settings', LEVEL_MANAGER),
                        'hr',
                        '/columns?create' => array('menu_columns_create.png', 'Create new custom data column', LEVEL_MANAGER),
                        '/columns' => array('menu_columns.png', 'Browse all custom data columns', LEVEL_MANAGER),
                        'hr',
                        '/links?create' => array('menu_links.png', 'Create a new custom link', LEVEL_MANAGER),
                        '/links' => array('menu_links.png', 'Browse all available custom links', LEVEL_MANAGER),
                        'hr',
                        '/logs' => array('menu_logs.png', 'View system logs', LEVEL_MANAGER),
                      ),
//                'docs' => 'LOVD documentation',
//                 array(
//                        '' => array('', 'LOVD manual table of contents', 0),
//                      ),
              );

// Remove certain menu entries, if the user has no access to them.
if (!$_AUTH || $_AUTH['level'] < LEVEL_MANAGER) {
    unset($_MENU['users'], $_MENU['users_']); // FIXME; Submitter list should be public.
    unset($_MENU['setup'], $_MENU['setup_']);
}

// Remove certain menu entries, if there is no gene selected.
if (!$_SESSION['currdb']) {
    unset($_MENU['genes_']['/genes/']);
    unset($_MENU['transcripts_']['/transcripts/']);
    unset($_MENU['variants_']['/variants/']);
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
<?php
// FIXME; later?
/*  <LINK rel="alternate" type="application/atom+xml" title="<?php echo $_CONF['system_title']; ?> Atom 1.0 feed" href="<?php echo ROOT_PATH; ?>api/feed.php" />*/
?>

  <SCRIPT type="text/javascript">
    <!--

<?php
// A quick way to switch genes, regardless of on which page you are.
// DMD_SPECIFIC, this does not work yet, needs to be rewritten, how do we do that?
/*
print('    function lovd_switchGeneInline () {' . "\n" .
// IF THIS IS IMPORTED IN 3.0, you'll need to check this properly. Probably don't want to use SCRIPT_NAME here.
      '      varForm = \'<FORM action="' . $_SERVER['SCRIPT_NAME'] . '" id="SelectGeneDBInline" method="get" style="margin : 0px;"><SELECT name="select_db" onchange="document.getElementById(\\\'SelectGeneDBInline\\\').submit();">');
$q = lovd_queryDB_Old('SELECT id, CONCAT(id, " (", name, ")") AS name FROM ' . TABLE_GENES . ' ORDER BY id');
while ($z = mysql_fetch_assoc($q)) {
    // This will shorten the gene names nicely, to prevent long gene names from messing up the form.
    $z['name'] = lovd_shortenString($z['name'], 75);
    if (substr($z['name'], -3) == '...') {
        $z['name'] .= str_repeat(')', substr_count($z['name'], '('));
    }
    // The str_replace will translate ' into \' so that it does not disturb the JS code.
    print('<OPTION value="' . $z['id'] . '"' . ($_SESSION['currdb'] == $z['id']? ' selected' : '') . '>' . str_replace("'", "\'", $z['name']) . '</OPTION>');
}
print('</SELECT>');
// Only use the $_GET variables that we have received (and not the ones we created ourselves).
$aGET = explode('&', $_SERVER['QUERY_STRING']);
foreach ($aGET as $val) {
    if ($val) { // Added if() to make sure pages without $_GET variables don't throw a notice.
        @list($key, $val) = explode('=', $val);
        if (lovd_getProjectFile() == '/variants.php' && $key == 'view' && !is_numeric($val)) {
            // Fix problem when switching gene while viewing detailed variant information.
            $val = preg_replace('/^([0-9]+).*$/', "$1", $val);
        }
        if (!in_array($key, array('select_db', 'sent'))) {
            print('<INPUT type="hidden" name="' . htmlspecialchars(rawurldecode($key), ENT_QUOTES) . '" value="' . htmlspecialchars(rawurldecode($val), ENT_QUOTES) . '">');
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
lovd_includeJS('lib/jQuery/jquery-1.6.2.min.js', 1);
lovd_includeJS('lib/jQuery/jquery-ui-1.8.15.core.min.js', 1);
lovd_includeJS('lib/jeegoocontext/jquery.jeegoocontext.min.js', 1);
?>
  <LINK rel="stylesheet" type="text/css" href="lib/jeegoocontext/style.css">
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
print('    <TD valign="top" width="' . ($nWidth + 20) . '" height="' . ($nHeight + 5) . '">' . "\n" .
      '      <IMG src="' . $_CONF['logo_uri'] . '" alt="LOVD - Leiden Open Variation Database" ' . $sSize . '>' . "\n" .
      '    </TD>' . "\n");

$sCurrSymbol = $sCurrGene = '';
/*
// FIXME; how will we handle this?
// During submission, show the gene we're submitting to instead of the currently selected gene.
if (lovd_getProjectFile() == '/submit.php' && !empty($_POST['gene']) && $_POST['gene'] != $_SESSION['currdb']) {
    // Fetch gene's info from db... we don't have it anywhere yet.
    list($sCurrSymbol, $sCurrGene) = mysql_fetch_row(lovd_queryDB_Old('SELECT id, gene FROM ' . TABLE_DBS . ' WHERE id = ?', array($_POST['gene'])));
} else*/if (!empty($_SESSION['currdb'])) {
    // Just use currently selected database.
    $sCurrSymbol = $_SESSION['currdb'];
    $sCurrGene = $_SETT['currdb']['name'];
}

print('    <TD valign="top" style="padding-top : 2px;">' . "\n" .
      '      <H2 style="margin-bottom : 2px;">' . $_CONF['system_title'] . '</H2>' . "\n" .
//      ($sCurrSymbol && $sCurrGene? '      <H5 id="gene_name">' . $sCurrGene . ' (' . $sCurrSymbol . ')&nbsp;<A href="#" onclick="javascript:lovd_switchGeneInline(); return false;"><IMG src="gfx/lovd_database_switch_inline.png" width="23" height="23" alt="Switch gene" title="Switch gene database" align="top"></A></H5>' . "\n" : '') .
      ($sCurrSymbol && $sCurrGene? '      <H5 id="gene_name">' . $sCurrGene . ' (' . $sCurrSymbol . ')</H5>' . "\n" : '') .
      '    </TD>' . "\n" .
      '    <TD valign="top" align="right" style="padding-right : 5px; padding-top : 2px;">' . "\n" .
      '      LOVD v.' . $_STAT['tree'] . ' Build ' . $_STAT['build'] . ' [ <A href="status">Current LOVD status</A> ]<BR>' . "\n");
if ($_AUTH) {
    print('      <B>Welcome, ' . $_AUTH['name'] . '</B><BR>' . "\n" .
          '      <A href="users/' . $_AUTH['id'] . '"><B>Your account</B></A> | ' . (false && $_AUTH['level'] == LEVEL_SUBMITTER && $_CONF['allow_submitter_mods']? '<A href="variants?search_created_by=' . $_AUTH['id'] . '"><B>Your submissions</B></A> | ' : '') . '<A href="logout"><B>Log out</B></A>' . "\n");
} else {
    print('      <A href="users?register"><B>Register as submitter</B></A> | <A href="login"><B>Log in</B></A>' . "\n");
}

print('    </TD>' . "\n" .
      '  </TR>' . "\n");

// Add curator info to header.
if ($sCurrSymbol && $sCurrGene) {
    $sCurators = '';
    $aCurators = $_DB->query('SELECT u.name, u.email FROM ' . TABLE_USERS . ' AS u LEFT JOIN ' . TABLE_CURATES . ' AS u2g ON (u.id = u2g.userid) WHERE u2g.geneid = ? AND u2g.allow_edit = 1 AND u2g.show_order > 0 ORDER BY u2g.show_order ASC, u.level DESC, u.name ASC', array($sCurrSymbol))->fetchAllAssoc();
    $nCurators = count($aCurators);
    foreach ($aCurators as $i => $z) {
        $i ++;
        $sCurators .= ($sCurators? ($i == $nCurators? ' and ' : ', ') : '') . '<A href="mailto:' . str_replace(array("\r\n", "\r", "\n"), ', ', trim($z['email'])) . '">' . $z['name'] . '</A>';
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
$aMenus    = array();
foreach ($_MENU as $sPrefix => $sTitle) {
    // Arrays (children links of parent tabs) can only be processed if we still have the $sFile from the previous run.
    if (is_array($sTitle)) {
        if (empty($sFile)) {
            continue;
        }
        $sPrefix = substr($sFile, 4); // Remove 'tab_'.

        // Menu will be built in an UL, that will be transformed into a dropdown menu by using the Jeegocontext script by www.planitworks.nl.
        $sUL = '<UL id="menu_' . $sFile . '" class="jeegoocontext">' . "\n";
        
        foreach ($sTitle as $sURL => $aItem) {
            if (!is_array($aItem)) {
                if ($aItem == 'hr') {
                    // Not using the "separator" class from the original code, since it's not compatible to our changes.
                    $sUL .= '  <LI class="hr"><HR></LI>' . "\n";
                }
                continue;
            }
            list($sIMG, $sName, $nRequiredLevel) = $aItem;
            $bDisabled = false;
            if ($nRequiredLevel && $nRequiredLevel > $_AUTH['level']) {
                $bDisabled = true;
            } else {
                if (!$sURL) {
                    // Default action of default page.
                    $sURL = $sPrefix;
                } elseif ($sURL{0} == '/') {
                    // Direct URL.
                    $sURL = substr($sURL, 1);
                } else {
                    // Action given.
                    $sURL = $sPrefix . '?' . $sURL;
                }
            }

            if (!$bDisabled) {
                // IE (who else) refuses to respect the BASE href tag when using JS. So we have no other option than to include the full path here.
                $sUL .= '  <LI' . (!$sIMG? '' : ' class="icon"') . '><A href="' . lovd_getInstallURL(false) . $sURL . '">' .
                    (!$sIMG? '' : '<SPAN class="icon" style="background-image: url(gfx/' . $sIMG . ');"></SPAN>') . $sName .
                    '</A></LI>' . "\n";
            }
// class disabled, disabled. Nu gewoon maar even weggehaald.
//            $sUL .= '  <LI' . ($bDisabled? ' class="disabled">' : (!$sIMG? '' : ' class="icon"') . '><A href="' . $sURL . '">') .
//                (!$sIMG? '' : '<SPAN class="icon" style="background-image: url(gfx/' . $sIMG . ');"></SPAN>') . $sName .
//                ($bDisabled? '' : '</A>') . '</LI>' . "\n";
        }
        $sUL .= '</UL>' . "\n";

        $aMenus[$sFile] = $sUL;
        continue;
    }



    // Determine if we're the current tab.
    $bSel = (substr(lovd_getProjectFile(), 1, strrpos(lovd_getProjectFile(), '.') - 1) == $sPrefix);
    // Auch! Hard coded exception!
    if (!$bSel && $sPrefix == 'docs' && substr(lovd_getProjectFile(), 0, 6) == '/docs/') { $bSel = true; }
    $sFile = 'tab_' . $sPrefix;

    // Print transition.
    print('      <IMG src="gfx/tab_' . (!$n? '0' : ($bPrevSel? 'F' : 'B')) . ($bSel? 'F' : 'B') . '.png" alt="" width="25" height="25" align="left">' . "\n");

    // Get header info.
    $sFileName = 'gfx/' . $sFile . '_' . ($bSel? 'F' : 'B') . '.png';
    $aImage = @getimagesize($sFileName);
    $sSize = $aImage[3];

    // Print header.
    $sURL = $sPrefix;
    // If a gene has been selected, some of the tabs get different default URLs.
    if ($_SESSION['currdb']) {
        if (in_array($sPrefix, array('genes', 'transcripts', 'variants'))) {
            $sURL = $sPrefix . '/' . $_SESSION['currdb'];
        } elseif ($sPrefix == 'diseases') {
            $sURL = $sPrefix . '?search_genes_=' . $_SESSION['currdb'];
        }
    }
    print('      <A href="' . $sURL . '"><IMG src="' . $sFileName . '" alt="' . $sTitle . '" id="' . $sFile . '" ' . $sSize . ' align="left"></A>' . "\n");

    $bPrevSel = $bSel;
    $n ++;
}

// Closing transition and close TR.
print('      <IMG src="gfx/tab_' . ($bPrevSel? 'F' : 'B') . '0.png" alt="" width="25" height="25" align="left">' . "\n" .
      '    </TD>' . "\n" .
      '  </TR>' . "\n" .
      '</TABLE>' . "\n\n");

// Attach dropdown menus.
print('<!-- Start drop down menu definitions -->' . "\n");
foreach ($aMenus as $sUL) {
    print($sUL . "\n");
}
print('
<SCRIPT type="text/javascript">
  $(function(){
    var aMenuOptions = {
        widthOverflowOffset: 0,
        heightOverflowOffset: 1,' .
//        submenuLeftOffset: -4,
//        submenuTopOffset: -2,
'
        startLeftOffset: -20,
        event: "mouseover",
        openBelowContext: true,
        autoHide: true,
        delay: 100,
        onSelect: function(e, context){
            if($(this).hasClass("disabled"))
            {              
                return false;
            } else {
                window.location = $(this).find("a").attr("href");
                return false;
            }
        },
    };' . "\n");

foreach (array_keys($aMenus) as $sTabID) {
    print('    $(\'#' . $sTabID . '\').jeegoocontext(\'menu_' . $sTabID . '\', aMenuOptions);' . "\n");
}
print('  });
</SCRIPT>' . "\n" .
'<!-- End drop down menu definitions -->' . "\n");
?>



<DIV style="padding : 0px 10px;">
<TABLE border="0" cellpadding="0" cellspacing="0" width="100%">
  <TR>
    <TD style="padding-top : 10px;">







