<?php
/* Copyright (C) 2018	Laurent Destailleur	<eldy@users.sourceforge.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/sellyoursaas.lib.php
 * \ingroup sellyoursaas
 * \brief   Library files with common functions for SellYourSaas module
 */


/**
 * Return if instance is a paid instance or not
 * Check if there is a template invoice
 *
 * @param 	Contrat $contract		Object contract
 * @return	int						>0 if this is a paid contract
 */
function sellyoursaasIsPaidInstance($contract)
{
	$contract->fetchObjectLinked();
	$foundtemplate=0;

	if (is_array($contract->linkedObjects['facturerec']))
	{
		foreach($contract->linkedObjects['facturerec'] as $idtemplateinvoice => $templateinvoice)
		{
			$foundtemplate++;
			break;
		}
	}

	if ($foundtemplate) return 1;

	if (is_array($contract->linkedObjects['facture']))
	{
		foreach($contract->linkedObjects['facture'] as $idinvoice => $invoice)
		{
			$foundinvoice++;
			break;
		}
	}

	if ($foundinvoice) return 1;

	/*
	$nbinvoicenotpayed = 0;
	$amountdue = 0;
	foreach ($listofcontractid as $id => $contract)
	{
		$contract->fetchObjectLinked();
		if (is_array($contract->linkedObjects['facture']))
		{
			foreach($contract->linkedObjects['facture'] as $idinvoice => $invoice)
			{
				if ($invoice->statut != $invoice::STATUS_CLOSED)
				{
					$nbinvoicenotpayed++;
				}
				$alreadypayed = $invoice->getSommePaiement();
				$amount_credit_notes_included = $invoice->getSumCreditNotesUsed();
				$amountdue = $invoice->total_ttc - $alreadypayed - $amount_credit_notes_included;
			}
		}
	}*/

	return 0;
}


/**
 * Return if instance has a last payment in error or not
 *
 * @param 	Contrat $contract		Object contract
 * @return	int						>0 if this is a contract with current payment error
 */
function sellyoursaasIsPaymentKo($contract)
{
	global $db;

	$contract->fetchObjectLinked();
	$paymenterror=0;

	if (is_array($contract->linkedObjects['facture']))
	{
		foreach($contract->linkedObjects['facture'] as $idinvoice => $invoice)
		{
			if ($invoice->fk_statut == Facture::STATUS_CLOSED) continue;
			// The invoice is not paid, we check if there is at least one payment issue
			$sql=' SELECT id FROM '.MAIN_DB_PREFIX."actioncomm WHERE elementtype = 'invoice' AND fk_element = ".$invoice->id." AND code='INVOICE_PAYMENT_ERROR'";
			$resql=$db->query($sql);
			if ($resql)
			{
				$num=$db->num_rows($resql);
				$db->free($resql);
				return $num;
			}
			else dol_print_error($db);
		}
	}

	return $paymenterror;
}


/**
 * Return date of expiration
 * Take lowest planed end date for services (whatever is service status)
 *
 * @param 	Contrat $contract		Object contract
 * @return	array					Array of data array('expirationdate'=>Timestamp of expiration date, or 0 if error or not found)
 */
function sellyoursaasGetExpirationDate($contract)
{
	global $db;

	$expirationdate = 0;
	$duration_value = 0;
	$duration_unit = '';
	$nbofusers = 0;

	include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	$tmpprod = new Product($db);

	// Loop on each line to get lowest expiration date
	foreach($contract->lines as $line)
	{
		if ($line->date_end)	// Planned end date of service
		{
			if ($expirationdate > 0) $expirationdate = min($expirationdate, $line->date_end);
			else $expirationdate = $line->date_end;
		}

		if ($line->fk_product > 0)
		{
			$tmpprod->fetch($line->fk_product);
			if ($tmpprod->array_options['options_app_or_option'] == 'app')
			{
				$duration_value = $tmpprod->duration_value;
				$duration_unit = $tmpprod->duration_unit;
			}
			if ($tmpprod->array_options['options_app_or_option'] == 'system' && preg_match('/user/i', $tmpprod->label))
			{
				$nbofusers = $line->qty;
			}
		}
	}

	return array('expirationdate'=>$expirationdate, 'duration_value'=>$duration_value, 'duration_unit'=>$duration_unit, 'nbusers'=>$nbofusers);
}



/**
 * Return if contract is suspenced/close
 * Take lowest planed end date for services (whatever is service status)
 *
 * @param 	Contrat $contract		Object contract
 * @return	boolean					Return if a contract is suspended or not
 */
function sellyoursaasIsSuspended($contract)
{
	global $db;

	if ($contract->nbofserviceswait > 0 || $contract->nbofservicesopened > 0 || $contract->nbofservicesexpired > 0) return false;
	if ($contract->nbofservicesclosed > 0) return true;

	return false;
}
