<?php

/*
 +-----------------------------------------------------------------------+
 | program/steps/mail/sendmdn.inc                                        |
 |                                                                       |
 | This file is part of the RoundCube Webmail client                     |
 | Copyright (C) 2008-2009, RoundCube Dev. - Switzerland                 |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Send a message disposition notification for a specific mail         |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+

 $Id: sendmdn.inc,v 1.1 2011/08/01 19:22:03 eldy Exp $

*/

// only process ajax requests
if (!$OUTPUT->ajax_call)
  return;

if (!empty($_POST['_uid'])) {
  $sent = rcmail_send_mdn(get_input_value('_uid', RCUBE_INPUT_POST), $smtp_error);
}

// show either confirm or error message
if ($sent) {
  $OUTPUT->set_env('mdn_request', false);
  $OUTPUT->show_message('receiptsent', 'confirmation');
}
else if ($smtp_error) {
  $OUTPUT->show_message($smtp_error['label'], 'error', $smtp_error['vars']);
}
else {
  $OUTPUT->show_message('errorsendingreceipt', 'error');
}

$OUTPUT->send();
