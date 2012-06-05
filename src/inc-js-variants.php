<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-11-08
 * Modified    : 2012-06-05
 * For LOVD    : 3.0-beta-05
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
    $(oVariantDNA).removeClass();
    $(oVariantDNA).siblings('img:first').attr({
        src: 'gfx/lovd_loading.gif',
        alt: 'Loading...',
        title: 'Loading...',
        class: '',
        onmouseover: '',
        onmouseout: ''
    }).show();
    var oProtein = $(oVariantDNA).parent().parent().siblings().find('input[name="' + $(oVariantDNA).attr('name').substring(0,5) + '_VariantOnTranscript/Protein"]');
    $(oProtein).siblings('img:first').removeClass().attr('src', 'gfx/trans.png');
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
                    $(oProtein).siblings('button:eq(0)').hide();
                }

            } else {
                $(oVariantDNA).siblings('img:first').attr({
                    src: 'gfx/check.png',
                    alt: 'Valid HGVS syntax!',
                    title: 'Valid HGVS syntax!'
                }).show();
                if (!$.isEmptyObject(aTranscripts)) {
                    $(oVariantDNA).siblings('button:eq(0)').show();
                    $(oProtein).siblings('button:eq(0)').show();
                }

            }
        });
    return false;
}

function lovd_convertPosition (oElement) {
    var oThisDNA = $(oElement).siblings('input:first');
    var oAllDNA = $('input[name$="_VariantOnTranscript/DNA"]');
    $(oAllDNA).removeClass().siblings('img:first').attr({
        src: 'gfx/trans.png',
        alt: '',
        title: '',
        class: '',
        onmouseover: '',
        onmouseout: ''
    }).show();
    var oAllProteins = $('input[name$="_VariantOnTranscript/Protein"]');
    $(oAllProteins).siblings('img:first').attr({
        src: 'gfx/trans.png',
        alt: '',
        title: '',
    }).show();
    $(oThisDNA).siblings('img:first').attr({
        src: 'gfx/lovd_loading.gif',
        alt: 'Loading...',
        title: 'Loading...',
        class: '',
        onmouseover: '',
        onmouseout: ''
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
                            var aVariant = /^(N[RM]_\d{6,9}\.\d{1,2}):(c\..+)$/.exec(aVariants[i]);
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
                                    var oProtein = $(oInput).parent().parent().siblings().find('input[name="' + $(oInput).attr('name').substring(0,5) + '_VariantOnTranscript/Protein"]');
                                    if (!oInput[0].disabled) {
                                        lovd_getProteinChange(oProtein);
                                    } else {
                                        oProtein.attr('value', '');
                                    }
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
}

function lovd_getProteinChange (oElement) {
    var oThisProtein = $(oElement).parent().find('input:first');
    $(oThisProtein).attr('value', '').removeAttr('style');
    $(oThisProtein).removeClass();
    $(oThisProtein).siblings('img:first').attr({
        src: 'gfx/lovd_loading.gif',
        alt: 'Loading...',
        title: 'Loading...'
    }).show();
    var nTranscriptID = $(oThisProtein).attr('name').substring(0,5);
    var oThisDNA = $(oElement).parent().parent().siblings().find('input[name="' + nTranscriptID + '_VariantOnTranscript/DNA"]');
    var sVariantNotation = aUDrefseqs[aTranscripts[nTranscriptID][1]] + '(' + aTranscripts[nTranscriptID][1] + '_v' + aTranscripts[nTranscriptID][2] + '):' + $(oThisDNA).val();

    $.get('ajax/run_mutalyzer.php', { variant: sVariantNotation, gene: aTranscripts[nTranscriptID][1] },
            function(sData) {
                if (sData.length == 1) {
                    if (sData == '<?php echo AJAX_NO_AUTH; ?>') {
                        alert('Lost your session!');
                    }
                    if (!oThisProtein.attr('disabled')) {
                        $(oThisProtein).siblings('img:first').attr({
                            src: 'gfx/cross.png',
                            alt: 'Error on mutalyzer request!\nError code: ' + sData,
                            title: 'Error on mutalyzer request!\nError code: ' + sData
                        }).show();
                    }
                } else {
                    var aData = sData.split(';;'); // aData[0] = errors, aData[1] = actual reply.
                    var aError = aData[0].split(':');
                    var sErrorCode = aError[0];
                    aError.splice(0,1);
                    var sErrorMessage = aError.join(':');

                    // Ignore 'ERANGE' as an actual error, because we can always interpret this as p.(=), p.? or p.0.
                    if (sErrorCode == 'ERANGE') {
                        sErrorCode = 'WRANGE';
                        sErrorMessage = '';
                        var aVariantRange = $(oThisDNA).val().split('_');
                        if (aVariantRange.length == 2 && /-u\d+/.exec(aVariantRange[0]) != null && /-u\d+/.exec(aVariantRange[1]) != null) {
                            sPredict = 'p.(=)';
                        } else if (aVariantRange.length == 2 && /-u\d+/.exec(aVariantRange[0]) != null && /\+d\d+/.exec(aVariantRange[1]) != null) {
                            sPredict = 'p.0';
                        } else if (aVariantRange.length == 2 && /\+d\d+/.exec(aVariantRange[0]) != null && /\+d\d+/.exec(aVariantRange[1]) != null) {
                            sPredict = 'p.(=)';
                        } else if (aVariantRange.length == 1 && (/-u\d+/.exec(aVariantRange[0]) != null || /\+d\d+/.exec(aVariantRange[0]) != null)) {
                            sPredict = 'p.(=)';
                        } else {
                            sPredict = 'p.?';
                        }
                        aData[1] = aUDrefseqs[aTranscripts[nTranscriptID][1]] + '(' + aTranscripts[nTranscriptID][1] + '_i' + aTranscripts[nTranscriptID][2] + '):' + sPredict;
                    }
                    if (sErrorCode.substring(0, 1) == 'E' || !aData[1]) {
                        if (!oThisProtein.attr('disabled')) {
                            $(oThisDNA).attr('class', 'err');
                            $(oThisDNA).siblings('img:first').attr({
                                src: 'gfx/lovd_form_warning.png',
                                alt: '',
                                title : '',
                                class: 'help',
                                onmouseover : 'lovd_showToolTip(\'' + escape(sErrorMessage) + '\');',
                                onmouseout: 'lovd_hideToolTip();'
                            }).show();
                            $(oThisProtein).siblings('img:first').attr({
                                src: 'gfx/cross.png',
                                alt: 'Encountered an error during protein prediction!',
                                title: 'Encountered an error during protein prediction!'
                            }).show();
                            $(oThisProtein).siblings('button:eq(0)').hide();
                        }
                    } else {
                        var aProteinDescriptions = aData[1].split(';');
                        $(aProteinDescriptions).each( function(index, value) {
                            if (value.replace(/UD_\d+\(/, '').replace(/\):p\..+/, '') == aTranscripts[nTranscriptID][1] + '_i' + aTranscripts[nTranscriptID][2] && !oThisProtein.attr('disabled')) {
                                if (sErrorMessage && sErrorCode != 'WSPLICE') {
                                    $(oThisDNA).attr('class', 'warn');
                                    $(oThisDNA).siblings('img:first').attr({
                                        src: 'gfx/lovd_form_information.png',
                                        alt: '',
                                        title : '',
                                        class: 'help',
                                        onmouseover : 'lovd_showToolTip(\'' + escape(sErrorMessage) + '\');',
                                        onmouseout: 'lovd_hideToolTip();'
                                    }).show();
                                } else {
                                    $(oThisDNA).siblings('img:first').attr({
                                        alt: 'HGVS compliant!',
                                        title : 'HGVS compliant!'
                                    }).show();
                                }
                                if (sErrorCode != 'WSPLICESELECTED') {
                                    $(oThisProtein).attr('value', value.replace(/UD_\d+\(.+\):/, ''));
                                } else {
                                    $(oThisProtein).attr('value', 'p.?');
                                }
                                // Highlight the protein input field which has been modified.
                                $(oThisProtein).attr('style', 'background : #AAFFAA;');

                                var nColor = 170;
                                for (i = nColor; i < 255; i++) {
                                    setTimeout(function () {
                                        $(oThisProtein).attr('style', 'background : #' + nColor.toString(16).toUpperCase() + 'FF' + nColor.toString(16).toUpperCase() + ';');
                                        nColor ++;
                                    }, (i - 130) * 40);
                                }
                                setTimeout(function () {
                                    $(oThisProtein).removeAttr('style');
                                }, (i - 130) * 40);

                                $(oThisProtein).siblings('img:first').attr({
                                    src: 'gfx/check.png',
                                    alt: 'Prediction OK!',
                                    title: 'Prediction OK!'
                                }).show();
                                $(oThisProtein).siblings('button:eq(0)').hide();
                            }
                        });
                    }
                }
    });
    return false;
}

$( function () {
    var oGenomicVariant = $('#variantForm input[name="VariantOnGenome/DNA"]');
    $(oGenomicVariant).parent().append('&nbsp;&nbsp;<IMG style="display:none;" align="top" width="16" height="16">&nbsp;<BUTTON class="mapVariant" type="button" onclick="lovd_convertPosition(this); return false;" style="display:none;">Map variant</BUTTON>');
    $(oGenomicVariant).change(lovd_checkHGVS);
    var oTranscriptVariants = $('#variantForm input[name$="_VariantOnTranscript/DNA"]');
    if (oTranscriptVariants[0] != undefined) {
        $(oTranscriptVariants).parent().append('&nbsp;&nbsp;<IMG style="display:none;" align="top" width="16" height="16">&nbsp;<BUTTON class="mapVariant" type="button" onclick="lovd_convertPosition(this); return false;" style="display:none;">Map variant</BUTTON>');
        var nTranscriptVariants = oTranscriptVariants.size();
        for (i=0; i < nTranscriptVariants; i++) {
            $(oTranscriptVariants[i]).attr('id_ncbi', aTranscripts[$(oTranscriptVariants[i]).attr('name').substring(0,5)][0]);
        }
        $(oTranscriptVariants).change(lovd_checkHGVS);
    }
    var oProteinVariants = $('#variantForm input[name$="_VariantOnTranscript/Protein"]');
    if (oProteinVariants[0] != undefined) {
        $(oProteinVariants).parent().append('&nbsp;&nbsp;<IMG src="gfx/trans.png" style="display:inline;" align="top" width="16" height="16">&nbsp;<BUTTON class="proteinChange" type="button" onclick="lovd_getProteinChange(this); return false;" style="display:none;">Predict</BUTTON>');
    }
});
