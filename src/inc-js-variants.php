<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-11-08
 * Modified    : 2020-07-23
 * For LOVD    : 3.0-25
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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
header('Expires: ' . date('r', time()+(180*60)));

define('AJAX_FALSE', '0');
define('AJAX_TRUE', '1');
define('AJAX_UNKNOWN_RESPONSE', '6');
define('AJAX_CONNECTION_ERROR', '7');
define('AJAX_NO_AUTH', '8');
define('AJAX_DATA_ERROR', '9');

$_SETT = array('objectid_length' => array('transcripts' => 8));
?>

function lovd_checkHGVS (e)
{
    // Function that is being called everytime a change has been made to a DNA field,
    // either from an onKeyUp or onChange, although the onKeyUp only uses this function partially.
    // This will run the Mutalyzer checkHGVS module (if needed) and will return the response to the user.

    var oVariantDNA = $(this);
    oVariantDNA.removeClass();

    // If we're a "preliminary" trigger, actually run when a key has been pressed, we just want a quick check
    // if the DNA field seems correct. If so, we show the mark and the buttons, just like a "real" onChange().
    // However, when it doesn't look good, we don't request Mutalyzer (to confirm, they should know best)
    // unless we're a "real" onChange() request.

    var bHGVS; // True -> correct syntax; False -> We don't recognize it, but Mutalyzer might.
    // First check: genomic field should start with g. or m., cDNA field should start with c. or n..
    if (oVariantDNA.attr('name') == 'VariantOnGenome/DNA' && !/^(g|m)\./.test(oVariantDNA.val().substring(0, 2))) {
        bHGVS = false;
    } else if (oVariantDNA.attr('name') != 'VariantOnGenome/DNA' && !/^(c|n)\./.test(oVariantDNA.val().substring(0, 2))) {
        bHGVS = false;
    } else {
        // Try to match simple stuff: deletions, duplications, insertions, inversions and substitutions.
        var oRegExp = /^[cgmn]\.\-?\d+([-+]\d+)?([ACGT]>[ACGT]|(_\-?\d+([-+]\d+)?)?d(el|up)([ACGT])*|_\-?\d+([-+]\d+)?(inv|ins([ACGT])+))$/;
        // "false" doesn't necessarily mean false here! Just means this check doesn't recognize it. Mutalyzer may still.
        bHGVS = (oRegExp.test(oVariantDNA.val()));
    }

    // Grab the corresponding protein description field if it exists.
    var oProtein = $(oVariantDNA).parent().parent().siblings().find('input[name="' + $(oVariantDNA).attr('name').substring(0, <?php echo $_SETT['objectid_length']['transcripts']; ?>) + '_VariantOnTranscript/Protein"]');

    // Add a transparent placeholder for the indicator at the protein field, so that the form will not shift when it is added.
    oProtein.siblings('img:first').removeClass().attr('src', 'gfx/trans.png');

    if (e.type == 'change' && !bHGVS && oVariantDNA.val()) {
        // This is a "real" onChange call(), we couldn't match the variant, but we do have something filled in. Check with Mutalyzer!
        if (oVariantDNA.attr('name') == 'VariantOnGenome/DNA') {
            var sVariantNotation = 'g:' + oVariantDNA.val(); // The actual chromosome is not important, it's just the variant syntax that matters here.
        } else {
            var sVariantNotation = 'c:' + oVariantDNA.val(); // The actual transcript is not important, it's just the variant syntax that matters here.
        }

        // Now we have to check with Mutalyzer...
        $(oVariantDNA).siblings('img:first').attr({
            src: 'gfx/lovd_loading.gif',
            alt: 'Loading...',
            title: 'Loading...',
            className: '',
            onmouseover: '',
            onmouseout: ''
        }).show();

        // Make the call to Mutalyzer to see if the variant is correct HGVS.
        $.get('ajax/check_hgvs.php', { variant: sVariantNotation },
            function(sData) {
                if (sData != '<?php echo AJAX_TRUE; ?>') {
                    // Either Mutalyzer says No, our regexp didn't find a c. or g. at the beginning or user lost $_AUTH.
                    oVariantDNA.siblings('img:first').attr({
                        src: 'gfx/cross.png',
                        alt: (sData == <?php echo AJAX_UNKNOWN_RESPONSE; ?>? 'Unexpected response from Mutalyzer. Please try again later.' : 'Not a valid HGVS syntax!'),
                        title: (sData == <?php echo AJAX_UNKNOWN_RESPONSE; ?>? 'Unexpected response from Mutalyzer. Please try again later.' : 'Not a valid HGVS syntax!'),
                    }).show();
                    // Now hide the "Map variant" and "Predict" buttons.
                    if (!$.isEmptyObject(aTranscripts)) {
                        oVariantDNA.siblings('button:eq(0)').hide();
                        oProtein.siblings('button:eq(0)').hide();
                    }

                } else {
                    oVariantDNA.siblings('img:first').attr({
                        src: 'gfx/check.png',
                        alt: 'Valid HGVS syntax!',
                        title: 'Valid HGVS syntax!'
                    }).show();
                    // Check if the variant description is a c.? or a g.?. If it is, then do not let the user map the variant.
                    if (oVariantDNA.val().substring(1,3) == '.?') {
                        oVariantDNA.siblings('button:eq(0)').hide();
                        oProtein.siblings('button:eq(0)').hide();
                    } else if (!$.isEmptyObject(aTranscripts)) {
                        // Only enable the mapping buttons when there are transcripts added to this variant.
                        oVariantDNA.siblings('button:eq(0)').show();
                        oProtein.siblings('button:eq(0)').show();
                        // Hide possible 'view prediction button'
                        $('#' + jq_escape(oProtein.attr('name')) + '_view_prediction').remove();
                    }
                }
            });

    } else if (bHGVS) {
        // We didn't need Mutalyzer, and we know we've got a good-looking variant here.
        oVariantDNA.siblings('img:first').attr({
            src: 'gfx/check.png',
            alt: 'Valid HGVS syntax!',
            title: 'Valid HGVS syntax!'
        }).show();
        if (!$.isEmptyObject(aTranscripts)) {
            // Only enable the mapping buttons when there are transcripts added to this variant.
            oVariantDNA.siblings('button:eq(0)').show();
            oProtein.siblings('button:eq(0)').show();
            // Hide possible 'view prediction button'
            $('#' + jq_escape(oProtein.attr('name')) + '_view_prediction').remove();
        }

    } else {
        // No HGVS syntax, but no "real" onChange trigger yet, either.
        oVariantDNA.siblings('img:first').hide();
        if (!$.isEmptyObject(aTranscripts)) {
            oVariantDNA.siblings('button:eq(0)').hide();
            oProtein.siblings('button:eq(0)').hide();
        }
    }
    return false;
}





function jq_escape (jqstr)
{
    // Escape characters in jQuery selectors (e.g. '/' becomes '\\/').
    // Based on: https://learn.jquery.com/using-jquery-core/faq/how-do-i-select-an-element-by-an-id-that-has-characters-used-in-css-notation/
    if (typeof(jqstr) == 'string') {
        return jqstr.replace(/(:|\.|\[|\]|,|\/)/g, "\\$1");
    }
    return jqstr;
}




function lovd_convertPosition (oElement)
{
    // Function that can map a variant to other transcripts or the genome.

    var oThisDNA = $(oElement).siblings('input:first');
    var oAllDNA = $('input[name$="_VariantOnTranscript/DNA"]');
    $(oAllDNA).removeClass().siblings('img:first').attr({
        src: 'gfx/trans.png',
        alt: '',
        title: '',
        className: '',
        onmouseover: '',
        onmouseout: ''
    }).show();
    var oAllProteins = $('input[name$="_VariantOnTranscript/Protein"]');
    $(oAllProteins).siblings('img:first').attr({
        src: 'gfx/trans.png',
        alt: '',
        title: ''
    }).show();
    $(oThisDNA).siblings('img:first').attr({
        src: 'gfx/lovd_loading.gif',
        alt: 'Loading...',
        title: 'Loading...',
        className: '',
        onmouseover: '',
        onmouseout: ''
    }).show();

    if (oThisDNA.attr('name') == 'VariantOnGenome/DNA') {
        // This function was called from the genomic variant, so build a list of genes and prepare the variant accordingly for mutalyzer.
        var sVariantNotation = 'chr<?php echo $_GET['chromosome']; ?>:' + oThisDNA.val();
        var aGenes = [];
        for (nTranscriptID in aTranscripts) {
            if ($.inArray(aTranscripts[nTranscriptID][1], aGenes) == -1) {
                aGenes.push(aTranscripts[nTranscriptID][1]);
            }
        }
    } else {
        // This function was called from a transcript variant, so prepare the variant accordingly for mutalyzer.
        var nTranscriptID = oThisDNA.attr('name').substring(0, <?php echo $_SETT['objectid_length']['transcripts']; ?>);
        var sVariantNotation = aTranscripts[nTranscriptID][0] + ':' + oThisDNA.val();
        // This value will not be used by mutalyzer for mapping to the genome, but we
        // need to fill something in for the call.
        var aGenes = [ aTranscripts[nTranscriptID][1] ];
    }

    for (i in aGenes) {
        // Run the following code for each gene the variant is mapped to, since Mutalyzer can only map per gene.
        var sGene = aGenes[i];
        $.get('ajax/convert_position.php', { variant: sVariantNotation, gene: sGene },
            function(sData) {
                if (sData != '<?php echo AJAX_DATA_ERROR; ?>' && sData != '<?php echo AJAX_FALSE; ?>' && sData != '<?php echo AJAX_NO_AUTH; ?>') {
                    if (oThisDNA.attr('name') == 'VariantOnGenome/DNA') {
                        // This function was called from the genomic variant, so fill in the return values from mutalyzer in the transcript DNA fields.
                        aVariants = sData.split(';');
                        var nVariants = aVariants.length;
                        for (i = 0; i < nVariants; i++) {
                            var aVariant = /^([A-Z]{2}_\d{6,9}\.\d{1,2}(?:\([A-Z0-9]+_v\d{3}\))?):([cn]\..+)$/.exec(aVariants[i]);
                            if (aVariant != null) {
                                var oInput = $('#variantForm input[id_ncbi="' + aVariant[1] + '"]');
                                if (oInput[0] != undefined) {
                                    // If the transcript returned by mutalyzer is present in the form, fill in the respons from mutalyzer.
                                    oInput.val(aVariant[2]);
                                    oInput.siblings('img:first').attr({
                                        src: 'gfx/check.png',
                                        alt: 'Valid HGVS syntax!',
                                        title: 'Valid HGVS syntax!'
                                    }).show();
                                    // Hide the "Map variant" button, so that the button cannot be pressed again. It has finished anyway and there
                                    // is no use to run this function again when the DNA field hasn't changed.
                                    oInput.siblings('button:eq(0)').hide();
                                    // Grab the corresponding protein description field if it exists.
                                    var oProtein = $(oInput).parent().parent().siblings().find('input[name="' + $(oInput).attr('name').substring(0, <?php echo $_SETT['objectid_length']['transcripts']; ?>) + '_VariantOnTranscript/Protein"]');
                                    if (!oInput[0].disabled) {
                                        // Transcript is not disabled, so let mutalyzer predict the protein description.
                                        lovd_getProteinChange(oProtein);
                                    } else {
                                        // Transcript is disabled, empty the protein field.
                                        oProtein.val('');
                                    }
                                }
                            }
                        }
                        // Hide the "Map variant" button.
                        $(oThisDNA).siblings('button:eq(0)').hide();

                    } else {
                        // This function was called from a transcript variant, so fill in the return value from mutalyzer in the genomic DNA field.
                        var aVariant = /:([gm]\..+)$/.exec(sData);
                        if (aVariant != null) {
                            var oInput = $('#variantForm input[name="VariantOnGenome/DNA"]');
                            oInput.val(aVariant[1]);
                            oInput.siblings('img:first').attr({
                                src: 'gfx/check.png',
                                alt: 'Valid HGVS syntax!',
                                title: 'Valid HGVS syntax!'
                            }).show();
                            // Call this function again, but with the new genomic information. This way, the variant will be mapped from the genome to all transcripts.
                            lovd_convertPosition(oInput.siblings('button:eq(0)'));
                        }
                    }
                    if (aVariant != null) {
                        $(oThisDNA).siblings('img:first').attr({
                            src: 'gfx/check.png',
                            alt: 'Valid HGVS syntax!',
                            title: 'Valid HGVS syntax!'
                        }).show();
                    } else {
                        // Call was successful, but we were unable to get any variant back from Mutalyzer. Probably NM not in mapping DB.
                        $(oThisDNA).attr('class', 'warn');
                        $(oThisDNA).siblings('img:first').attr({
                            src: 'gfx/lovd_form_information.png',
                            alt: '',
                            title : '',
                            className: 'help',
                            onmouseover : 'lovd_showToolTip(\'Could not map variant using this transcript! Probably Mutalyzer does not have this transcript in its mapping database yet.\');',
                            onmouseout: 'lovd_hideToolTip();'
                        }).show();
                        $(oThisDNA).siblings('button:eq(0)').hide(); // Hide the mapping button.
                    }

                } else {
                    // Either Mutalyzer says No, our regexp didn't match with the full variant notation or user lost $_AUTH.
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





function lovd_getProteinChange (oElement)
{
    // Function that can predict a protein description of a variant based on a transcript DNA field.

    var oThisProtein = $(oElement).parent().find('input:first');
    $(oThisProtein).val('');
    $(oThisProtein).removeClass();
    $(oThisProtein).siblings('img:first').attr({
        src: 'gfx/lovd_loading.gif',
        alt: 'Loading...',
        title: 'Loading...'
    }).show();
    // Collect the corresponding transcript variant information, because Mutalyzer needs it to make a prediction.
    var nTranscriptID = $(oThisProtein).attr('name').substring(0, <?php echo $_SETT['objectid_length']['transcripts']; ?>);
    var oThisDNA = $(oElement).parent().parent().siblings().find('input[name="' + nTranscriptID + '_VariantOnTranscript/DNA"]');
    var oThisRNA = $(oElement).parent().parent().siblings().find('input[name="' + nTranscriptID + '_VariantOnTranscript/RNA"]');
    $(oThisRNA).val('');
    $(oThisRNA).removeClass();

    $.get('ajax/check_variant.php', { reference: aUDrefseqs[aTranscripts[nTranscriptID][1]],
                                      gene: aTranscripts[nTranscriptID][1],
                                      transcript: aTranscripts[nTranscriptID][0],
                                      variant: $(oThisDNA).val()},
        function(aData, sStatus) {
            if (aData.length == 1 || aData['mutalyzer_error']) {
                // Either Mutalyzer says No, our regexp didn't match with the full variant notation or user lost $_AUTH.
                if (aData === '<?php echo AJAX_NO_AUTH; ?>') {
                    alert('Lost your session!');
                } else if (aData === '<?php echo AJAX_DATA_ERROR; ?>') {
                    alert('Invalid input, or input missing.');
                }
                if (!oThisProtein.prop('disabled')) {
                    $(oThisProtein).siblings('img:first').attr({
                        src: 'gfx/cross.png',
                        onclick: '',
                        style: '',
                        alt: 'Error on mutalyzer request!\nError code: ' + (!$.isArray(aData)? aData : aData['mutalyzer_error']),
                        title: 'Error on mutalyzer request!\nError code: ' + (!$.isArray(aData)? aData : aData['mutalyzer_error'])
                    }).show();
                }

            } else if (aData === '' && oThisDNA.val().lastIndexOf('n.', 0) === 0) {
                // No data, but no errors either! No wonder... it's an n. variant!
                // No prediction on protein level possible!
                oThisProtein.siblings('img:first').attr({
                    src: 'gfx/cross.png',
                    onclick: '',
                    style: '',
                    alt: 'Unable to predict protein change for non-coding transcripts!',
                    title: 'Unable to predict protein change for non-coding transcripts!'
                }).show();
                oThisProtein.siblings('button:eq(0)').hide();

            } else {
                // Decide what to do with the analyzed Mutalyzer output.
                var sErrorMessages = '';
                if (aData['error'] || !aData['predict']) {
                    // Mutalyzer returned one or more errors, so we add the err class to make the field red. We also add an image with a tooltip that shows the error.
                    var firstError = true;
                    for (index in aData['error']) {
                        if (firstError !== true) {
                            sErrorMessages += '<BR>';
                        }
                        sErrorMessages +=  '<B>' + index + ':</B> ' + aData['error'][index];
                        firstError = false;
                    }
                    if (!oThisProtein.prop('disabled')) {
                        $(oThisDNA).attr('class', 'err');
                        $(oThisDNA).siblings('img:first').attr({
                            src: 'gfx/lovd_form_warning.png',
                            alt: '',
                            title : '',
                            className: 'help',
                            onmouseover : 'lovd_showToolTip(\'' + escape(sErrorMessages) + '\');',
                            onmouseout: 'lovd_hideToolTip();'
                        }).show();
                        $(oThisProtein).siblings('img:first').attr({
                            src: 'gfx/cross.png',
                            onclick: '',
                            style: '',
                            alt: 'Encountered an error during protein prediction!',
                            title: 'Encountered an error during protein prediction!'
                        }).show();
                        $(oThisProtein).siblings('button:eq(0)').hide();
                    }

                } else {
                    // No errors returned by Mutalyzer.
                    if (aData['warning']) {
                        // Mutalyzer returned a warning so we add the warn class to make the field yellow. We also add an image with a tooltip that shows the warning.
                        var firstWarning = true;
                        for (index in aData['warning']) {
                            if (firstWarning !== true) {
                                sErrorMessages += '<BR>';
                            }
                            sErrorMessages +=  '<B>' + index + ':</B> ' + aData['warning'][index];
                            firstWarning = false;
                        }
                        if (!oThisProtein.prop('disabled')) {
                            $(oThisDNA).attr('class', 'warn');
                            $(oThisDNA).siblings('img:first').attr({
                                src: 'gfx/lovd_form_information.png',
                                alt: '',
                                title : '',
                                className: 'help',
                                onmouseover : 'lovd_showToolTip(\'' + escape(sErrorMessages) + '\');',
                                onmouseout: 'lovd_hideToolTip();'
                            }).show();

                            // Add warning colors to RNA and protein input fields.
                            $(oThisRNA).attr('class', 'warn');
                            $(oThisProtein).attr('class', 'warn');
                            $(oThisProtein).siblings('img:first').attr({
                                src: 'gfx/check_orange.png',
                                alt: 'Encountered a warning during protein prediction!',
                                title: 'Encountered a warning during protein prediction!'
                            }).show();
                        }

                    } else {
                        // No warnings or errors returned by Mutalyzer.
                        $(oThisDNA).siblings('img:first').attr({
                            src: 'gfx/check.png',
                            alt: 'HGVS compliant!',
                            title : 'HGVS compliant!'
                        }).show();

                        $(oThisProtein).siblings('img:first').attr({
                            src: 'gfx/check.png',
                            alt: 'Prediction OK!',
                            title: 'Prediction OK! Click to see result on Mutalyzer.'
                        }).show();
                    }
                    $(oThisRNA).val(aData['predict']['RNA']);
                    $(oThisProtein).val(aData['predict']['protein']);
                    lovd_highlightInput(oThisRNA);
                    lovd_highlightInput(oThisProtein);

                    $(oThisProtein).siblings('button:eq(0)').hide();
                }
            }

            if (typeof(aData['mutalyzer_url']) != 'undefined' &&
                !$(oThisProtein).siblings('button').is(':visible')) {

                // Show button-link to mutalyzer namechecker. (only if no other button (e.g.
                // 'predict') is shown).
                $('<button>View prediction</button>')
                    .appendTo(oThisProtein.parent())
                    .attr({
                        id: oThisProtein.attr('name') + '_view_prediction',
                        onclick: 'lovd_openWindow("' + aData['mutalyzer_url'] + '"); return false;'
                    }).show();
            }
        },"json"
    );
    return false;
}





function lovd_highlightInput (oElement)
{
    // Hightlights an element after it was filled in automatically by LOVD.
    if (!$(oElement).attr('style') || $(oElement).attr('style').search('background') == -1) {
        $(oElement).attr('style', 'background : #AAFFAA;');

        // Fade background to white, then remove the style.
        var nColor = 170;
        for (i = nColor; i < 255; i++) {
            setTimeout(function () {
                $(oElement).attr('style', 'background : #' + nColor.toString(16).toUpperCase() + 'FF' + nColor.toString(16).toUpperCase() + ';');
                nColor ++;
            }, (i - 130) * 40);
        }
        setTimeout(function () {
            $(oElement).attr('style', '');
        }, (i - 130) * 40);
    }
}





$(function ()
{
    var oGenomicVariant = $('#variantForm input[name="VariantOnGenome/DNA"]');
    var oTranscriptVariants = $('#variantForm input[name$="_VariantOnTranscript/DNA"]');
    // Add the button and image at the end of the genomic DNA field.
    oGenomicVariant.parent().append('&nbsp;&nbsp;<IMG style="display:none;" align="top" width="16" height="16">&nbsp;<BUTTON class="mapVariant" type="button" onclick="lovd_convertPosition(this); return false;" style="display:none;">Map to transcript' + (oTranscriptVariants.length == 1? '' : 's') + '</BUTTON>');
    // Add an onChange event that runs lovd_checkHGVS.
    oGenomicVariant.change(lovd_checkHGVS);
    // Add same function to the onKeyUp event, but then it will check itself if the variant is likely to be complete.
    oGenomicVariant.keyup(lovd_checkHGVS);

    if (oGenomicVariant.val() !== '') {
        // Variant field already has content, check HGVS now because if we're on an edit form we
        // want the buttons to be ready.
        oGenomicVariant.change();
    }

    if (oTranscriptVariants[0] != undefined) {
        // Add the buttons and images at the end of the transcripts DNA fields.
        oTranscriptVariants.parent().append('&nbsp;&nbsp;<IMG style="display:none;" align="top" width="16" height="16">&nbsp;<BUTTON class="mapVariant" type="button" onclick="lovd_convertPosition(this); return false;" style="display:none;">Map to genome</BUTTON>');
        var nTranscriptVariants = oTranscriptVariants.size();
        for (i=0; i < nTranscriptVariants; i++) {
            // Add an artificial attribute "id_ncbi" to the transcripts DNA input field. This is needed to link the response from Mutalyzer to this field, if needed.
            $(oTranscriptVariants[i]).attr('id_ncbi', aTranscripts[$(oTranscriptVariants[i]).attr('name').substring(0, <?php echo $_SETT['objectid_length']['transcripts']; ?>)][0]);
        }
        // Add an onChange event that runs lovd_checkHGVS.
        oTranscriptVariants.change(lovd_checkHGVS);
        // Add same function to the onKeyUp event, but then it will check itself if the variant is likely to be complete.
        oTranscriptVariants.keyup(lovd_checkHGVS);

        var oProteinVariants = $('#variantForm input[name$="_VariantOnTranscript/Protein"]');
        if (oProteinVariants[0] != undefined) {
            // Add the buttons and images at the end of the protein description fields.
            oProteinVariants.parent().append('&nbsp;&nbsp;<IMG src="gfx/trans.png" style="display:inline;" align="top" width="16" height="16">&nbsp;<BUTTON class="proteinChange" type="button" onclick="lovd_getProteinChange(this); return false;" style="display:none;">Predict</BUTTON>');
        }

        if (oTranscriptVariants.val() !== '') {
            // Variant field already has content, check HGVS now because if we're on an edit form we
            // want the buttons to be ready.
            oTranscriptVariants.change();
        }
    }
});
