<?php
/* Copyright (C) 2007-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2011      Dimitri Mouillard <dmouillard@teclib.com>
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

/**
 *  Displays the log of actions performed in the module.
 *
 *  \file       view_log.php
 *  \ingroup    congespayes
 */

require('pre.inc.php');
require_once(DOL_DOCUMENT_ROOT. "/user/class/user.class.php");

// Protection if external user
if ($user->societe_id > 0) accessforbidden();

// Si l'utilisateur n'a pas le droit de lire cette page
if(!$user->rights->congespayes->view_log) accessforbidden();



/*
 * View
*/

llxHeader($langs->trans('CPTitreMenu'));


$cp = new Congespayes($db);
$log_conges = $cp->fetchLog('','');

print_fiche_titre($langs->trans('LogCP'));

print '<table class="noborder" width="100%">';
print '<tbody>';
print '<tr class="liste_titre">';

print '<td class="liste_titre">'.$langs->trans('ID').'</td>';
print '<td class="liste_titre" align="center">'.$langs->trans('Date').'</td>';
print '<td class="liste_titre">'.$langs->trans('ActionByCP').'</td>';
print '<td class="liste_titre">'.$langs->trans('UserUpdateCP').'</td>';
print '<td class="liste_titre">'.$langs->trans('ActionTypeCP').'</td>';
print '<td class="liste_titre" align="right">'.$langs->trans('PrevSoldeCP').'</td>';
print '<td class="liste_titre" align="right">'.$langs->trans('NewSoldeCP').'</td>';

print '</tr>';
$var=true;

foreach($cp->logs as $logs_CP)
{
   	$var=!$var;

   	$user_action = new User($db);
   	$user_action->fetch($logs_CP['fk_user_action']);

   	$user_update = new User($db);
   	$user_update->fetch($logs_CP['fk_user_update']);

   	print '<tr '.$bc[$var].'>';
   	print '<td>'.$logs_CP['rowid'].'</td>';
   	print '<td style="text-align: center;">'.$logs_CP['date_action'].'</td>';
   	print '<td>'.$user_action->getFullName($langs).'</td>';
   	print '<td>'.$user_update->getFullName($langs).'</td>';
   	print '<td>'.$logs_CP['type_action'].'</td>';
   	print '<td style="text-align: right;">'.$logs_CP['prev_solde'].' jours</td>';
   	print '<td style="text-align: right;">'.$logs_CP['new_solde'].' jours</td>';
   	print '</tr>'."\n";

}

if($log_conges == '2')
{
    print '<tr>';
    print '<td colspan="7" class="pair" style="text-align: center; padding: 5px;">'.$langs->trans('NoResult').'</td>';
    print '</tr>';
}

print '</tbody>'."\n";
print '</table>'."\n";


// Fin de page
$db->close();
llxFooter();
?>
