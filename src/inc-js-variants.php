<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-11-08
 * Modified    : 2011-11-08
 * For LOVD    : 3.0-alpha-06
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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

header('Content-type: text/javascript; charset=UTF-8');

define('AJAX_FALSE', '0');
define('AJAX_TRUE', '1');
define('AJAX_DATA_ERROR', '9');
?>

function lovd_checkHGVS () {
    var oVariantDNA = $(this);
    if (oVariantDNA.attr('name') == 'VariantOnGenome/DNA') {
        var sVariantNotation = 'g:' + oVariantDNA.val(); // The actual chromosome is not important, it's just the syntax that matters here.
    } else {
        var sVariantNotation = 'c:' + oVariantDNA.val(); // The actual transcript is not important, it's just the syntax that matters here.
    }
    $.get('ajax/check_hgvs.php', { variant: sVariantNotation },
        function(sData) {
            if (sData != '<?php echo AJAX_TRUE; ?>') {
                // Mutalyzer says No, or our regexp didn't find a c. or g. at the beginning, or user lost $_AUTH.
                $(oVariantDNA).siblings('img:first').attr({
                    src: 'gfx/cross.png',
                    alt: 'Not a valid HGVS syntax!',
                    title: 'Not a valid HGVS syntax!'
                }).show();
                <?php echo (isset($_GET['geneid'])? '$(oVariantDNA).siblings(\'button:eq(0)\').hide();' : '') ?>
            } else {
                $(oVariantDNA).siblings('img:first').attr({
                    src: 'gfx/check.png',
                    alt: 'Valid HGVS syntax!',
                    title: 'Valid HGVS syntax!'
                }).show();
                <?php echo (isset($_GET['geneid'])? '$(oVariantDNA).siblings(\'button:eq(0)\').show();' : '') ?>
            }
        });
    return false;
};

function lovd_convertPosition (oElement) {
    var oThisDNA = $(oElement).siblings('input:first');
    if (oThisDNA.attr('name') == 'VariantOnGenome/DNA') {
        var sVariantNotation = 'chr<?php echo $_GET['chromosome']; ?>:' + oThisDNA.val();
    } else {
        var sVariantNotation = aTranscripts[oThisDNA.attr('name').substring(0,5)] + ':' + oThisDNA.val();
    }
    $.get('ajax/convert_position.php', { variant: sVariantNotation, gene: '<?php echo (isset($_GET['geneid'])? $_GET['geneid'] : '') ?>' },
        function(sData) {
            if (sData != '<?php echo AJAX_DATA_ERROR; ?>' && sData != '<?php echo AJAX_FALSE; ?>') {
                if (oThisDNA.attr('name') == 'VariantOnGenome/DNA') {
                    aVariants = sData.split(';');
                    var nVariants = aVariants.length;
                    for (i=0; i<nVariants; i++) {
                        var aVariant = /^(NM_\d{6,9}\.\d{1,2}):(c\..+)$/.exec(aVariants[i]);
                        if (aVariant != null) {
                            var oInput = $('#variantForm input[id_ncbi="' + aVariant[1] + '"]');
                            if (oInput[0] != undefined) {
                                oInput.attr('value', aVariant[2]);
                                oInput.siblings('img:first').attr({
                                    src: 'gfx/check.png',
                                    alt: 'Valid HGVS syntax!',
                                    title: 'Valid HGVS syntax!'
                                }).show();
                                oInput.siblings('button:eq(0)').hide();
                            }
                        }
                    }
                    $(oThisDNA).siblings('button:eq(0)').hide();
                } else {
                    var aVariant = /:(g\..+)$/.exec(sData);
                    if (aVariant != null) {
                        var oInput = $('#variantForm input[name="VariantOnGenome/DNA"]');
                        oInput.attr('value', aVariant[1]);
                        oInput.siblings('img:first').attr({
                            src: 'gfx/check.png',
                            alt: 'Valid HGVS syntax!',
                            title: 'Valid HGVS syntax!'
                        }).show();
                        lovd_convertPosition(oInput.siblings('button:eq(0)'));
                    }
                }
            } else {
                $(oThisDNA).siblings('img:first').attr({
                    src: 'gfx/cross.png',
                    alt: 'Not a valid HGVS syntax!',
                    title: 'Not a valid HGVS syntax!'
                }).show();
                $(oThisDNA).siblings('button:eq(0)').hide();
            }
        });
    return false;
};

$( function () {
    var oGenomicVariant = $('#variantForm input[name="VariantOnGenome/DNA"]');
    $(oGenomicVariant).parent().append('&nbsp;&nbsp;<IMG style="display:none;">' + '<?php echo (isset($_GET['geneid'])? '&nbsp;<BUTTON onclick="lovd_convertPosition(this); return false;" style="display:none;">Map variant</BUTTON>' : '') ?>');
    $(oGenomicVariant).change(lovd_checkHGVS);
    var oTranscriptVariants = $('#variantForm input[name$="_VariantOnTranscript/DNA"]');
    if (oTranscriptVariants[0] != undefined) {
        $(oTranscriptVariants).parent().append('&nbsp;&nbsp;<IMG style="display:none;">' + '<?php echo (isset($_GET['geneid'])? '&nbsp;<BUTTON onclick="lovd_convertPosition(this); return false;" style="display:none;">Map variant</BUTTON>' : '') ?>');
        var nTranscriptVariants = oTranscriptVariants.size();
        for (i=0; i<nTranscriptVariants; i++) {
            $(oTranscriptVariants[i]).attr('id_ncbi', aTranscripts[$(oTranscriptVariants[i]).attr('name').substring(0,5)]);
        }
        $(oTranscriptVariants).change(lovd_checkHGVS);
    }
});
