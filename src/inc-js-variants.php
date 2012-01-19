<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-11-08
 * Modified    : 2012-01-09
 * For LOVD    : 3.0-beta-01
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
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
define('AJAX_NO_AUTH', '8');
define('AJAX_DATA_ERROR', '9');
?>

function lovd_checkHGVS () {
    var oVariantDNA = $(this);
    $(oVariantDNA).siblings('img:first').attr({
        src: 'gfx/lovd_loading.gif',
        width: '16px',
        height: '16px',
        alt: 'Loading...',
        title: 'Loading...'
    }).show();
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
                if (!$.isEmptyObject(aTranscripts)) {
                    $(oVariantDNA).siblings('button:eq(0)').hide();
                }

            } else {
                $(oVariantDNA).siblings('img:first').attr({
                    src: 'gfx/check.png',
                    alt: 'Valid HGVS syntax!',
                    title: 'Valid HGVS syntax!'
                }).show();
                if (!$.isEmptyObject(aTranscripts)) {
                    $(oVariantDNA).siblings('button:eq(0)').show();
                }

            }
        });
    return false;
};

function lovd_convertPosition (oElement) {
    var oThisDNA = $(oElement).siblings('input:first');
    $(oThisDNA).siblings('img:first').attr({
        src: 'gfx/lovd_loading.gif',
        alt: 'Loading...',
        title: 'Loading...'
    }).show();
    if (oThisDNA.attr('name') == 'VariantOnGenome/DNA') {
        var sVariantNotation = 'chr<?php echo $_GET['chromosome']; ?>:' + oThisDNA.val();
        var aGenes = [];
        for (nTranscriptID in aTranscripts) {
            if ($.inArray(aTranscripts[nTranscriptID][1], aGenes) == -1) { 
                aGenes.push(aTranscripts[nTranscriptID][1]);
            }
        }
    } else {
        var nTranscriptID = oThisDNA.attr('name').substring(0,5);
        var sVariantNotation = aTranscripts[nTranscriptID][0] + ':' + oThisDNA.val();
        // This value will not be used by mutalyzer for mapping to the genome, but we
        // need to fill something in for the call.
        var aGenes = [ aTranscripts[nTranscriptID][1] ];
    }
    for (i in aGenes) {
        var sGene = aGenes[i];
        $.get('ajax/convert_position.php', { variant: sVariantNotation, gene: sGene },
            function(sData) {
                if (sData != '<?php echo AJAX_DATA_ERROR; ?>' && sData != '<?php echo AJAX_FALSE; ?>' && sData != '<?php echo AJAX_NO_AUTH; ?>') {
                    if (oThisDNA.attr('name') == 'VariantOnGenome/DNA') {
                        aVariants = sData.split(';');
                        var nVariants = aVariants.length;
                        for (i = 0; i < nVariants; i++) {
                            var aVariant = /^(NM_\d{6,9}\.\d{1,2}):(c\..+)$/.exec(aVariants[i]);
                            if (aVariant != null) {
                                var oInput = $('#variantForm input[id_ncbi="' + aVariant[1] + '"]');
                                if (oInput[0] != undefined && !oInput.attr('disabled')) {
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
                    $(oThisDNA).siblings('img:first').attr({
                        src: 'gfx/check.png',
                        alt: 'Valid HGVS syntax!',
                        title: 'Valid HGVS syntax!'
                    }).show();
                } else {
                    $(oThisDNA).siblings('img:first').attr({
                        src: 'gfx/cross.png',
                        alt: 'Error during mapping!',
                        title: 'Error during mapping!'
                    }).show();
                    $(oThisDNA).siblings('button:eq(0)').hide();
                }
            });
    }
    return false;
};

$( function () {
    var oGenomicVariant = $('#variantForm input[name="VariantOnGenome/DNA"]');
    $(oGenomicVariant).parent().append('&nbsp;&nbsp;<IMG style="display:none;">&nbsp;<BUTTON class="mapVariant" type="button" onclick="lovd_convertPosition(this); return false;" style="display:none;">Map variant</BUTTON>');
    $(oGenomicVariant).change(lovd_checkHGVS);
    var oTranscriptVariants = $('#variantForm input[name$="_VariantOnTranscript/DNA"]');
    if (oTranscriptVariants[0] != undefined) {
        $(oTranscriptVariants).parent().append('&nbsp;&nbsp;<IMG style="display:none;">&nbsp;<BUTTON class="mapVariant" type="button" onclick="lovd_convertPosition(this); return false;" style="display:none;">Map variant</BUTTON>');
        var nTranscriptVariants = oTranscriptVariants.size();
        for (i=0; i<nTranscriptVariants; i++) {
            $(oTranscriptVariants[i]).attr('id_ncbi', aTranscripts[$(oTranscriptVariants[i]).attr('name').substring(0,5)][0]);
        }
        $(oTranscriptVariants).change(lovd_checkHGVS);
    }
});
