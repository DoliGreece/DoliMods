<?php

/*
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/list.inc                                    |
 |                                                                       |
 | This file is part of the RoundCube Webmail client                     |
 | Copyright (C) 2005-2007, RoundCube Dev. - Switzerland                 |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Send contacts list to client (as remote response)                   |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+

 $Id: list.inc,v 1.1 2011/08/01 19:22:18 eldy Exp $

*/

// get contacts for this user
$result = $CONTACTS->list_records();

// update message count display
$OUTPUT->set_env('pagecount', ceil($result->count / $CONTACTS->page_size));
$OUTPUT->command('set_rowcount', rcmail_get_rowcount_text($rowcount));

// create javascript list
rcmail_js_contacts_list($result);
  
// send response
$OUTPUT->send();

