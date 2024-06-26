<?php
/*
 +-----------------------------------------------------------------------+
 | program/steps/mail/headers.inc                                        |
 |                                                                       |
 | This file is part of the RoundCube Webmail client                     |
 | Copyright (C) 2005-2007, RoundCube Dev. - Switzerland                 |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Fetch message headers in raw format for display                     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+

 $Id: headers.inc,v 1.1 2011/08/01 19:22:03 eldy Exp $

*/

if ($uid = get_input_value('_uid', RCUBE_INPUT_POST))
{
    $source = $IMAP->get_raw_headers($uid);

    if ($source !== false) {
        $source = htmlspecialchars(trim($source));
        $source = preg_replace(
            array(
                '/\n[\t\s]+/',
                '/^([a-z0-9_:-]+)/im',
                '/\r?\n/'
            ),
            array(
                "\n&nbsp;&nbsp;&nbsp;&nbsp;",
                '<font class="bold">\1</font>',
                '<br />'
            ), $source);

        $OUTPUT->command('set_headers', $source);
    }
    else {
        $RCMAIL->output->show_message('messageopenerror', 'error');
    }

    $OUTPUT->send();
}

exit;


