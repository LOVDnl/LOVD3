<?php
define('ROOT_PATH', '../src/');
require ROOT_PATH . 'inc-init.php';





define('PAGE_TITLE', 'Create a new user account');
define('LOG_EVENT', 'UserCreate');

require ROOT_PATH . 'inc-lib-form.php';





require ROOT_PATH . 'inc-top.php';
lovd_printHeader(PAGE_TITLE);

// Tooltip JS code.
lovd_includeJS('inc-js-tooltip.php');

// Table.
print('      <FORM action="' . $_PATH_ELEMENTS[0] . '?' . ACTION . '" method="post">' . "\n");

// Array which will make up the form table.
$aForm =
         array(
                        array('POST', '', '', '', '50%', '14', '50%'),
                        array('', '', 'print', '<B>User details</B>'),
                        array('Name', '', 'text', 'name', 30),
                        array('Institute', '', 'text', 'institute', 40),
                        array('Department (optional)', '', 'text', 'department', 40),
                        array('Postal address', '', 'textarea', 'address', 35, 3),
                        array('Email address(es), one per line', '', 'textarea', 'email', 30, 3),
                        array('Telephone (optional)', '', 'text', 'telephone', 20),
          'username' => array('Username', '', 'text', 'username', 20),
            'passwd' => array('Password', '', 'password', 'password_1', 20),
    'passwd_confirm' => array('Password (confirm)', '', 'password', 'password_2', 20),
     'passwd_change' => array('Must change password at next logon', '', 'checkbox', 'password_force_change'),
                        'skip',
                        array('', '', 'print', '<B>Referencing the lab</B>'),
                        array('City', 'Please enter your city, even if it\'s included in your postal address, for sorting purposes.', 'text', 'city', 30),
                        array('Reference (optional)', 'Your submissions will contain a reference to you in the format "Country:City" by default. You may change this to your preferred reference here.', 'text', 'reference', 30),
                        'skip',
                        array('fieldset', 'security', 'Security', false),
                        array('', '', 'print', '<B>Security</B>'),
                        array('Allowed IP address list', 'To help prevent others to try and guess the username/password combination, you can restrict access to the account to a number of IP addresses or ranges.', 'text', 'allowed_ip', 20),
                        array('', '', 'note', '<I>Your current IP address: ' . $_SERVER['REMOTE_ADDR'] . '</I><BR><B>Please be extremely careful using this setting.</B> Using this setting too strictly, can deny the user access to LOVD, even if the correct credentials have been provided.<BR>Set to \'*\' to allow all IP addresses, use \'-\' to specify a range and use \';\' to separate addresses or ranges.'),
            'locked' => array('Locked', '', 'checkbox', 'locked'),
                        'end_fieldset');
lovd_viewForm($aForm);

print('</FORM>' . "\n\n");

require ROOT_PATH . 'inc-bot.php';
exit;
