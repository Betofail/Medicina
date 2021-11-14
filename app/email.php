<?php

namespace App;

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

//  =====  CAUTION - DO NOT EDIT THIS FILE ======
// This file contains the default email settings for LimeSurvey
// Do not edit this file as it may change in future revisions of the software.
//
// Correct procedure to set up LimeSurvey is the following:
// 1.) copy the lines corresponding to the parameter you want to change
//   from this file to the config.php file
// 2.) edit these lines in config.php

// Email Settings
// These settings determine how LimeSurvey will send emails
$config = [];
$config['siteadminemail'] = 'albertooviedo83@gmail.com'; // The default email address of the site administrator
$config['siteadminbounce'] = 'albertooviedo83@gmail.com'; // The default email address used for error notification of sent messages for the site administrator (Return-Path)
$config['siteadminname'] = 'Alberto Oviedo'; // The name of the site administrator

$config['emailmethod'] = 'sendmail'; // The following values can be used:
$config['protocol'] = $config['emailmethod'];
// mail      -  use internal PHP Mailer
// sendmail  -  use Sendmail Mailer
// smtp      -  use SMTP relaying

$config['emailsmtphost'] = '170.239.85.204:25'; // Sets the SMTP host. You can also specify a different port than 25 by using
// this format: [hostname:port] (e.g. 'smtp1.example.com:25').

$config['emailsmtpuser'] = ''; // SMTP authorisation username - only set this if your server requires authorization - if you set it you HAVE to set a password too
$config['emailsmtppassword'] = ''; // SMTP authorisation password - empty password is not allowed
$config['emailsmtpssl'] = ''; // Set this to 'ssl' or 'tls' to use SSL/TLS for SMTP connection

$config['emailsmtpdebug'] = 0; // Settings this to 1 activates SMTP debug mode

$config['maxemails'] = 50; // The maximum number of emails to send in one go (this is to prevent your mail server or script from timeouting when sending mass mail)

$config['emailcharset'] = 'utf-8';

return $config; // You can change this to change the charset of outgoing emails to some other encoding  - like 'iso-8859-1'
