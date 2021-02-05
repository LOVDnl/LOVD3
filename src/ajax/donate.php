<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-02-01
 * Modified    : 2021-02-05
 * For LOVD    : 3.0-26
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Overwrite the settings, so we'll hide this dialog after this.
// Not calling inc-init.php here, why connect to the DB etc just to make sure
//  $_COOKIE['lovd_settings'] exists? We can also replace lovd_getInstallURL()
//  with this $_SERVER['SCRIPT_NAME']-based code.
// If ever including inc-init.php again, remove the json_decode() below!
// To prevent notices just in case:
if (!empty($_COOKIE['lovd_settings'])) {
    setcookie(
        'lovd_settings',
        json_encode(
            array(
                'donation_dialog_last_seen' => time()
            ) + json_decode($_COOKIE['lovd_settings'], true)),
        strtotime('+1 year'),
        dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/'
    );
}

header('Content-type: text/javascript; charset=UTF-8');
?>
// Make sure we have and show the dialog.
if (!$("#donate_dialog").length) {
    $("body").append("<DIV id='donate_dialog' title='Donate to the LOVD project'>The LOVD software that runs this database is provided free of charge to the scientific community. However, creating and maintaining the software, keeping the servers running, and curating the central &quot;Global Variome shared LOVD&quot; database is not for free. Please consider supporting our work through a donation to &quot;Global Variome&quot; (select &quot;Support Global Variome Shared LOVD&quot;).<IMG src='gfx/donate_qr_100x100.png' alt='' width='100' height='100' style='float: right;'><BR><BR>Thank you in advance for your generous support,<BR>the LOVD team, Leiden, Netherlands<BR><BR><A href='https://www.paypal.com/donate/?hosted_button_id=DHJVLF3Z2TA2U' target='_blank'><IMG src='https://www.paypalobjects.com/en_GB/i/btn/btn_donate_LG.gif' border='0' title='PayPal - The safer, easier way to pay online!' alt='Donate with PayPal button'></A></DIV>");
}
if (!$("#donate_dialog").hasClass("ui-dialog-content") || !$("#donate_dialog").dialog("isOpen")) {
    $("#donate_dialog").dialog(
    {
        draggable:true,resizable:false,minWidth:500,show:"fade",closeOnEscape:true,hide:"fade",modal:false,position:{my:"top",at:"bottom",of:$("#stickyheader").siblings("table")[0]},
        // Stick Dialog to the screen, not the DOM.
        // Thanks to msander @ https://stackoverflow.com/questions/2657076/jquery-ui-dialog-fixed-positioning
        create: function (event, ui) {
            $(event.target).parent().css('position', 'fixed');
        },
        resizeStop: function (event, ui) {
            var position = [(Math.floor(ui.position.left) - $(window).scrollLeft()),
                            (Math.floor(ui.position.top) - $(window).scrollTop())];
            $(event.target).parent().css('position', 'fixed');
            $("#donate_dialog").dialog('option','position',position);
        },
        open: function (event, ui) {
            // Remove the focus off of the donate button.
            // Blur() doesn't remove the focus as many suggest. This does work.
            // Thanks to scott.gonzalez @ https://forum.jquery.com/topic/dialog-close-button-is-focused-on-open
            $(this).parents('.ui-dialog').attr('tabindex', -1)[0].focus();
        }
    });
}
