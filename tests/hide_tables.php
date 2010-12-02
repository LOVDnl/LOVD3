<?php
define('ROOT_PATH', '../src/');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<HTML lang="en_US">
<HEAD>
  <TITLE>Leiden Open Variation Database</TITLE>
  <META http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <META name="Author" content="LOVD development team, LUMC, Netherlands">
  <META name="Generator" content="gPHPEdit / GIMP @ GNU/Linux (Ubuntu)">
  <LINK rel="stylesheet" type="text/css" href="<?php echo ROOT_PATH; ?>styles.css">
</HEAD>

<BODY style="margin : 10px;">

<TABLE border="0" cellpadding="0" cellspacing="0" width="100%">
  <TR>
    <TD>

<SCRIPT type="text/javascript">
  <!--
  function showAll()
  {
      document.getElementById('colgroup1').style.visibility='visible';
      document.getElementById('colgroup2').style.visibility='visible';
      document.getElementById('colgroup3').style.visibility='visible';
      document.getElementById('colgroup4').style.visibility='visible';
      document.getElementById('colgroup5').style.visibility='visible';
      document.getElementById('row1').style.visibility='visible';
      document.getElementById('row2').style.visibility='visible';
      document.getElementById('row3').style.visibility='visible';
      document.getElementById('row4').style.visibility='visible';
      document.getElementById('row5').style.visibility='visible';
  }  
  
  // -->
</SCRIPT>

<TABLE border="1" style="background : #CCCCCC;">
  <COLGROUP id="colgroup1"></COLGROUP>
  <COLGROUP id="colgroup2"></COLGROUP>
  <COLGROUP id="colgroup3"></COLGROUP>
  <COLGROUP id="colgroup4"></COLGROUP>
  <COLGROUP id="colgroup5"></COLGROUP>
  <TR>
    <TH width="100">One</TH>
    <TH width="100">Two</TH>
    <TH width="100">Three</TH>
    <TH width="100">Four</TH>
    <TH width="100">Five</TH>
    <TH width="100">Hide?</TH>
  </TR>
  <TR id="row1">
    <TD>1.1</TD>
    <TD>1.2</TD>
    <TD>1.3</TD>
    <TD>1.4</TD>
    <TD>1.5</TD>
    <TD><BUTTON onClick="document.getElementById('row1').style.visibility='collapse';">Hide</COLLAPSE></TD>
  </TR>
  <TR id="row2">
    <TD>2.1</TD>
    <TD>2.2</TD>
    <TD>2.3</TD>
    <TD>2.4</TD>
    <TD>2.5</TD>
    <TD><BUTTON onClick="document.getElementById('row2').style.visibility='collapse';">Hide</COLLAPSE></TD>
  </TR>
  <TR id="row3">
    <TD>3.1</TD>
    <TD>3.2</TD>
    <TD>3.3</TD>
    <TD>3.4</TD>
    <TD>3.5</TD>
    <TD><BUTTON onClick="document.getElementById('row3').style.visibility='collapse';">Hide</COLLAPSE></TD>
  </TR>
  <TR id="row4">
    <TD>4.1</TD>
    <TD>4.2</TD>
    <TD>4.3</TD>
    <TD>4.4</TD>
    <TD>4.5</TD>
    <TD><BUTTON onClick="document.getElementById('row4').style.visibility='collapse';">Hide</COLLAPSE></TD>
  </TR>
  <TR id="row5">
    <TD>5.1</TD>
    <TD>5.2</TD>
    <TD>5.3</TD>
    <TD>5.4</TD>
    <TD>5.5</TD>
    <TD><BUTTON onClick="document.getElementById('row5').style.visibility='collapse';">Hide</COLLAPSE></TD>
  </TR>
  <TR>
    <TD><BUTTON onClick="document.getElementById('colgroup1').style.visibility='collapse';">Hide</BUTTON></TD>
    <TD><BUTTON onClick="document.getElementById('colgroup2').style.visibility='collapse';">Hide</BUTTON></TD>
    <TD><BUTTON onClick="document.getElementById('colgroup3').style.visibility='collapse';">Hide</BUTTON></TD>
    <TD><BUTTON onClick="document.getElementById('colgroup4').style.visibility='collapse';">Hide</BUTTON></TD>
    <TD><BUTTON onClick="document.getElementById('colgroup5').style.visibility='collapse';">Hide</BUTTON></TD>
  </TR>
</TABLE>

<BR>
<BUTTON onClick="showAll();">Show all</BUTTON>

    </TD>
  </TR>
</TABLE>

</BODY>
</HTML>
