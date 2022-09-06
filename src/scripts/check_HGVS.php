<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-12-03
 * Modified    : 2022-09-06
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               L. Werkman <L.Werkman@LUMC.nl>
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';

if (ACTION || PATH_COUNT > 2) {
    // !URL: /scripts/check_HGVS.php
    // This is not the basic URL. We have an ACTION or additional stuff behind the URL.
    header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <!-- Bootstrap Font Icon CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.2/font/bootstrap-icons.css" rel="stylesheet">

    <title>HGVS DNA variant description syntax checker</title>
    <BASE href="<?php echo lovd_getInstallURL(); ?>">
</head>
<body class="bg-light">

<div class="container">
    <main>
        <div class="py-5 text-center">
            <h1>HGVS DNA variant description syntax checker</h1>
            <p class="lead">
                Validate the syntax of DNA variant descriptions using this form.
                Our tool checks your description and, when invalid, tries to correct your description into a valid HGVS-compliant description.
                To also validate your variant description on the sequence level, please select the VariantValidator option below the input field.
                This feature requires you to include a reference sequence in your descriptions.
            </p>
        </div>

        <ul class="nav nav-tabs" id="hgvsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="single-variant" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">
                    Check a single variant
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mutiple-variants" data-bs-toggle="tab" data-bs-target="#multiple" type="button" role="tab" aria-controls="multiple" aria-selected="false">
                    Check a list of variants
                </button>
            </li>
        </ul>

        <div class="tab-content" id="hgvsTabsContent">
                <div class="py-3 tab-pane fade show active" id="single" role="tabpanel">
                    <FORM onsubmit="showResponse('singleVariant'); return false;" action="">
                        <div class="py-2">
                            <input type="text" class="form-control" id="singleVariant" placeholder="NM_002225.3:c.157C>T" value="">
                        </div>
                        <div class="py-2">
                            <input type="checkbox" class="form-check-input" id="singleVariantUseVV">
                            <label class="form-check-label mx-2" for="singleVariantUseVV">Besides checking the syntax, also use VariantValidator.org to validate this variant on the sequence level (slower)</label>
                        </div>
                        <div class="py-2 d-flex justify-content-between">
                            <div>
                                <button class="btn btn-primary" type="submit" id="singleVariantButton">Validate this variant description</button>
                            </div>
                            <div>
                                <button class="btn btn-primary d-none" id="singleVariantDownloadButton">Download this result</button>
                            </div>
                        </div>
                    </FORM>
                    <DIV class="py-2" id="singleVariantResponse"></DIV>
                </div>
                <div class="py-3 tab-pane fade" id="multiple" role="tabpanel">
                    <FORM onsubmit="showResponse('multipleVariants'); return false;" action="">
                        <div class="py-2">
                            <textarea class="form-control" id="multipleVariants" placeholder="NM_002225.3:c.157C>T
NC_000015.9:g.40699840C>T" rows="3"></textarea>
                        </div>
                        <div class="py-2">
                            <input type="checkbox" class="form-check-input" id="multipleVariantsUseVV">
                            <label class="form-check-label mx-2" for="multipleVariantsUseVV">Besides checking the syntax, also use VariantValidator.org to validate these variants on the sequence level (slower)</label>
                        </div>
                        <div class="py-2 d-flex justify-content-between">
                            <div>
                                <button class="btn btn-primary" type="submit" id="multipleVariantsButton">Validate these variant descriptions</button>
                            </div>
                            <div>
                                <button class="btn btn-primary d-none" id="multipleVariantsDownloadButton">Download these results</button>
                            </div>
                        </div>
                    </FORM>
                    <DIV class="py-2" id="multipleVariantsResponse"></DIV>
                </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha384-tsQFqpEReu7ZLhBV2VZlAu7zcOV+rXbYlF2cqB8txI/8aZajjp4Bqd+V6D5IgvKT" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<SCRIPT type="text/javascript">
    // Disable buttons when there is nothing to submit.
    $("#hgvsTabsContent").find(
        "input[type=text], textarea"
    ).keyup(
        function ()
        {
            if ($(this).val() == '') {
                $(this).parents('form').find('button').prop('disabled', true);
            } else {
                $(this).parents('form').find('button').prop('disabled', false);
            }
        }
    ).keyup();

    // Disable buttons when clicked and indicate the process is loading.
    $("#hgvsTabsContent").find("button").click(
        function ()
        {
            $(this).parents("form").submit();
            $(this).prop('disabled', true).append('\n&nbsp;\n<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            return true;
        }
    );



    function showResponse (sMethod)
    {
        // This function sends the data over to the ajax script, formats, and displays the response.
        if (sMethod == undefined || $("#" + sMethod) == null) {
            alert("showResponse() called with an incorrect method.");
            return false;
        }

        var sInput = $("#" + sMethod).val();
        var bCallVV = $("#" + sMethod + "UseVV").is(":checked");
        $.getJSON(
            "ajax/check_HGVS.php?var=" + encodeURIComponent(sInput) + "&callVV=" + bCallVV,
            function (data)
            {
                // If we get here, the JSON was already parsed, and we know it was successful.
                // We should have received an object with variants as keys, and their results as the value.

                // Empty previous result.
                $("#" + sMethod + "Response").html("");

                // Remove download button, in case it's shown.
                $("#" + sMethod + "DownloadButton").addClass("d-none");

                // Loop through the results.
                $.each(
                    data,
                    function (sVariant, aVariant)
                    {
                        // Style used, icon used? I don't like bootstrap's "warning" colors much, so make it "secondary".
                        var sStyle = (aVariant.color == 'green'? 'success' : (aVariant.color == 'orange'? 'secondary' : 'danger'));
                        var sIcon = (aVariant.is_hgvs == null? 'question' : (aVariant.color == 'orange'? 'x' : (aVariant.is_hgvs? 'check' : 'exclamation'))) + '-circle-fill';

                        // What's in the body?
                        var aMessages = [];
                        if (aVariant.is_hgvs) {
                            aMessages.push({'style': sStyle, 'icon': sIcon, 'data': 'OK', 'body':
                                'This variant description\'s syntax is valid.'});
                            if (!bCallVV) {
                                if ('WNOTSUPPORTED' in aVariant.variant_info.warnings) {
                                    aMessages.push({'style': sStyle, 'icon': 'info-circle-fill', 'data': 'Note', 'body':
                                        'This variant has not been validated on the sequence level.' +
                                        ' However, this variant description is not currently supported for sequence-level validation.'});
                                } else {
                                    aMessages.push({'style': 'secondary', 'icon': 'exclamation-circle-fill', 'data': 'Note', 'body':
                                        'This variant has not been validated on the sequence level.' +
                                        ' For sequence-level validation, please select the VariantValidator option.'});
                                }
                            }

                        } else if (aVariant.is_hgvs != null && !("EFAIL" in aVariant.variant_info.errors)) {
                            aMessages.push({'style': sStyle, 'icon': sIcon, 'data': 'Error', 'body':
                                'This variant description is invalid.'});
                        }

                        // Add errors. As errors can be both an array or an object, let's use jQuery.
                        $.each(
                            aVariant.variant_info.errors,
                            function (sCode, sError)
                            {
                                var sStyle = 'danger';
                                var sIcon = 'exclamation-circle-fill';
                                var sData = 'Error';
                                if (sCode == 'ENOTSUPPORTED') {
                                    sStyle = 'secondary';
                                    sError =
                                        'This variant description contains unsupported syntax.' +
                                        ' Although we aim to support all of the HGVS nomenclature rules,' +
                                        ' some complex variants are not fully implemented yet in our syntax checker.';
                                    sData = 'Note';
                                }
                                aMessages.push({'style': sStyle, 'icon': sIcon, 'data': sData, 'body': sError});
                            }
                        );

                        // Add warnings. As warnings can be both an array or an object, let's use jQuery.
                        $.each(
                            aVariant.variant_info.warnings,
                            function (sCode, sWarning)
                            {
                                var sStyle = 'secondary';
                                var sIcon = 'x-circle-fill';
                                if (sCode == 'IREFSEQMISSING' || sCode == 'WNOTSUPPORTED') {
                                    return;
                                }
                                aMessages.push({'style': sStyle, 'icon': sIcon, 'data': 'Warning', 'body': sWarning});
                            }
                        );

                        // Add VV's output, if present. As this can be both an array or an object, let's use jQuery.
                        $.each(
                            aVariant.VV,
                            function (sCode, sMessage)
                            {
                                var sStyle = 'danger';
                                var sIcon = 'dash-circle-fill';
                                if (sCode == 'ENOTSUPPORTED') {
                                    // The user saw this message already.
                                    return;
                                } else if (sCode == 'WNOTSUPPORTED') {
                                    sStyle = 'secondary';
                                    sIcon = 'info-circle-fill';
                                } else if (sCode == 'EINTERNAL') {
                                    sIcon = 'x-circle-fill';
                                } else if (sCode == 'WCORRECTED') {
                                    sStyle = 'warning';
                                    sIcon = 'arrow-right-circle-fill';
                                } else if (sCode == 'IOK') {
                                    sStyle = 'success';
                                    sIcon = 'check-circle-fill';
                                }
                                aMessages.push({'style': sStyle, 'icon': sIcon, 'data': 'VariantValidator', 'body': sMessage});
                            }
                        );

                        // If not VV, but we fixed the variant, mention this.
                        if (!("WCORRECTED" in aVariant.VV) && aVariant.fixed_variant != sVariant && aVariant.fixed_variant_is_hgvs) {
                            sMessage = 'We automatically corrected the variant description to';
                            if (aVariant.fixed_variant_confidence == 'medium') {
                                sMessage = 'We suggest that perhaps the correct variant description is';
                            } else {
                                sMessage = 'Maybe you meant to describe the variant as';
                            }
                            aMessages.push({'style': 'warning', 'icon': 'arrow-right-circle-fill', 'data': 'Correction', 'body':
                                sMessage + ' <B>' + aVariant.fixed_variant + '</B>.'});
                        }

                        // Add the IREFSEQMISSING last (never set if we called VV).
                        if ("IREFSEQMISSING" in aVariant.variant_info.warnings && !("EFAIL" in aVariant.variant_info.errors)) {
                            aMessages.push({'style': 'secondary', 'icon': 'info-circle-fill', 'data': 'Note', 'body': aVariant.variant_info.warnings.IREFSEQMISSING});
                        };

                        var sBody = '<ul class="list-group list-group-flush">';
                        aMessages.forEach(
                            function (aMessage)
                            {
                                sBody +=
                                    '<li class="list-group-item list-group-item-' + aMessage.style + ' d-flex" data-type="' + aMessage.data + '">' +
                                    '<i class="bi bi-' + aMessage.icon + ' me-2"></i><div>' +
                                    aMessage.body +
                                    '</div></li>\n';
                            }
                        );
                        sBody += '</ul>';

                        $("#" + sMethod + "Response").append(
                            '\n' +
                            '<div class="card w-100 mb-3 border-' + sStyle + ' bg-' + sStyle + '">\n' +
                              '<div class="card-header text-white">\n' +
                                '<h5 class="card-title mb-0"><i class="bi bi-' + sIcon + ' me-1"></i> ' + sVariant + '</h5>\n' +
                              '</div>\n'
                              + sBody + '\n' +
                            '</div>'
                        );
                    }
                );

                // Mention the stats. We're collecting this all from what we've printed on the screen.
                // I think that's easier than to pollute the code above with counts.
                aCards = $("#" + sMethod + "Response div.card");
                $.each(
                    aCards,
                    function (index, aCard)
                    {
                        // Determine, per card, what category it falls into.
                        // We'll store it in the data so that the download feature can use it, too.
                        // However, note that when using .data() rather than .attr() to set data-* fields, the DOM
                        //  doesn't actually get changed. Interesting read at:
                        //  https://learningjquery.com/2011/09/using-jquerys-data-apis
                        // This also means that you find this data, you can't use the normal jQuery attribute selection
                        //  methods. find("div.card[data-status='success']") simply won't find anything.
                        // jQueryUI has a :data() selector, but we don't have jQuery UI here.
                        // You could also solve this my building a complex filter; see:
                        //  https://stackoverflow.com/questions/7344361/how-to-select-elements-with-jquery-that-have-a-certain-value-in-a-data-attribute/7344459#7344459
                        //  $("div.card").filter(function(){return($(this).data('status') == 'success');})
                        //  but that's a bit too much. Therefore, we use attr() here.

                        // Anything with a yellow line (a suggested fix), can be fixed.
                        if ($(aCard).find("li.list-group-item-warning").length) {
                            $(aCard).attr('data-status', 'warning');
                        } else if ($(aCard).find("li.list-group-item-danger").length) {
                            // Otherwise, if we find any red line, it's bad.
                            $(aCard).attr('data-status', 'error');
                        } else if (!$(aCard).find("li.list-group-item").not("li.list-group-item-secondary").length) {
                            // Cards only gray are unsupported.
                            $(aCard).attr('data-status', 'unsupported');
                        } else {
                            // Then we must be left with green cards with some silent warnings (VV not run, refseq not given).
                            $(aCard).attr('data-status', 'success');
                        }
                    }
                );
                var nVariants = aCards.length;
                var nVariantsSuccess = $(aCards).filter("[data-status='success']").length;
                var nVariantsNotSupported = $(aCards).filter("[data-status='unsupported']").length;
                var nVariantsWarning = $(aCards).filter("[data-status='warning']").length;
                var nVariantsError = $(aCards).filter("[data-status='error']").length;
                $("#" + sMethod + "Response").prepend(
                    '\n' +
                    '<div class="alert alert-primary" role="alert">\n' +
                    (sMethod == 'singleVariant' && nVariants == 1? '' :
                        '<div><i class="bi bi-clipboard2-check me-1"></i>' + nVariants + ' variant' + (nVariants == 1? '' : 's') + ' received.</div>\n') +
                    (!nVariantsSuccess? '' :
                        '<div><i class="bi bi-check-circle-fill me-1"></i>' + nVariantsSuccess + ' variant' + (nVariantsSuccess == 1? '' : 's') + ' validated successfully.</div>\n') +
                    (!nVariantsNotSupported? '' :
                        '<div><i class="bi bi-question-circle-fill me-1"></i>' + nVariantsNotSupported + ' variant' + (nVariantsNotSupported == 1? ' is' : 's are') + ' not supported.</div>\n') +
                    (!nVariantsWarning? '' :
                        '<div><i class="bi bi-dash-circle-fill me-1"></i>' + nVariantsWarning + ' variant' + (nVariantsWarning == 1? '' : 's') + ' can be fixed.</div>\n') +
                    (!nVariantsError? '' :
                        '<div><i class="bi bi-exclamation-circle-fill me-1"></i>' + nVariantsError + ' variant' + (nVariantsError == 1? '' : 's') + ' failed to validate.</div>\n') +
                    '</div>');

                // Reset button.
                $("#" + sMethod + "Button").find("span").remove();
                $("#" + sMethod + "Button").html(
                    $("#" + sMethod + "Button").html().replace(/&nbsp;/g, "").trim()
                ).prop("disabled", false);

                // Enable download button.
                $("#" + sMethod + "DownloadButton").removeClass("d-none").click(
                    function ()
                    {
                        downloadResponse(sMethod);
                    }
                );

                return true;
            }
        ).fail(
            function()
            {
                alert("Error checking variant, please try again later.");
            }
        );
        return false;
    }



    function downloadResponse (sMethod)
    {
        // Download the result or results into a tab-delimited file.
        if (sMethod == undefined || $("#" + sMethod + "Response") == null) {
            alert("downloadResponse() called with an incorrect method.");
            return false;
        }

        // Add a spinner and disable the button while we're working.
        $("#" + sMethod + "DownloadButton").prop('disabled', true).append('\n&nbsp;\n<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

        var aCards = $("#" + sMethod + "Response div.card");
        var fileContent =
            "data:text/tab-seperated-values;charset=utf-8," +
            '"Input"\t"Status"\t"Suggested correction"\t"Messages"\n';

        // Loop through cards and convert them into tab-delimited data.
        $.each(
            aCards,
            function (index, aCard)
            {
                // Collect the body first.
                var sBody = '';
                $(aCard).find("li.list-group-item").each(
                    function ()
                    {
                        // Awkward way of escaping double quotes, but common for spreadsheet users.
                        sBody += $(this).data("type") + ": " + $(this).text().replace(/"/g, '""') + " ";
                    }
                );
                fileContent +=
                    '"' + $(aCard).children("div.card-header").text().trim() + '"\t' +
                    '"' + $(aCard).data("status") + '"\t' +
                    '"' + $(aCard).find("li.list-group-item-warning b").text() + '"\t' +
                    '"' + sBody.trim() + '"\r\n';
            }
        );
        fileContent += '\r\n';

        var link = document.createElement("a");
        link.setAttribute("href", fileContent);
        var d = new Date();
        // Offset the timezone.
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        link.setAttribute("download", "LOVD_checkHGVS_" + d.toISOString().slice(0, 19) + ".txt");
        document.body.appendChild(link);
        link.click();

        // Reset button.
        $("#" + sMethod + "DownloadButton").find("span").remove();
        $("#" + sMethod + "DownloadButton").html(
            $("#" + sMethod + "DownloadButton").html().replace(/&nbsp;/g, "").trim()
        ).prop("disabled", false);

        // Clean up.
        link.remove();
    }
</SCRIPT>

</body>
</html>
