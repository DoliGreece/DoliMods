<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       sellyoursaas/class/sellyoursaasutils.class.php
 *  \ingroup    sellyoursaas
 *  \brief      Class with utilities
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('sellyoursaas/lib/sellyoursaas.lib.php');


/**
 *	Class with cron tasks of SellYourSaas module
 */
class SellYourSaasUtils
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)

    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
        return 1;
    }



    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doSendWelcomeMessage()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doSendWelcomeMessage.log';

    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	// ...

    	return 0;
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doAlertSoftEndTrial()
    {
    	global $conf, $langs, $user;

    	$mode = 'test';

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doAlertSoftEndTrial.log';

    	$this->output = '';
    	$this->error='';

    	$error = 0;
    	$now = dol_now();

    	$delayindaysshort = $conf->global->SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_SOFT_ALERT;
    	$delayindayshard = $conf->global->SELLYOURSAAS_NBDAYS_BEFORE_TRIAL_END_FOR_HARD_ALERT;
    	if ($delayindaysshort <= 0 || $delayindayshard <= 0)
    	{
    		$this->error='BadValueForDelayBeforeTrialEndForAlert';
    		return -1;
    	}
    	dol_syslog(__METHOD__." we send email warning ".$delayindays." days before end of trial", LOG_DEBUG);

    	$sql = 'SELECT c.rowid, c.ref_customer, cd.rowid as lid';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	//$sql.= " AND cd.date_fin_validite < '".$this->db->idate(dol_time_plus_duree($now, abs($delayindaysshort), 'd'))."'";
    	//$sql.= " AND cd.date_fin_validite > '".$this->db->idate(dol_time_plus_duree($now, abs($delayindayshard), 'd'))."'";
    	$sql.= " AND date_format(cd.date_fin_validite, '%Y-%m-%d') = date_format('".$this->db->idate(dol_time_plus_duree($now, abs($delayindaysshort), 'd'))."', '%Y-%m-%d')";
    	$sql.= " AND cd.statut = 4";
		//print $sql;

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		$contractprocessed = array();

    		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
    		include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
    		$formmail=new FormMail($this->db);

    		$i=0;
    		while ($i < $num)
    		{
    			$obj = $this->db->fetch_object($resql);
    			if ($obj)
    			{
    				if (! empty($contractprocessed[$object->id])) continue;

    				// Test if this is a paid or not instance
    				$object = new Contrat($this->db);
    				$object->fetch($obj->rowid);
    				$object->fetch_thirdparty();

    				$outputlangs = new Translate('', $conf);
    				$outputlangs->setDefaultLang($object->thirdparty->default_lang);

    				$arraydefaultmessage=$formmail->getEMailTemplate($this->db, 'GentleTrialExpiringReminder', $user, $outputlangs, 0);

    				$ispaid = sellyoursaasIsPaidInstance($object);
    				if ($mode == 'test' && $ispaid) continue;											// Discard if this is a paid instance when we are in test mode
    				//if ($mode == 'paid' && ! $ispaid) continue;											// Discard if this is a test instance when we are in paid mode

    				// Suspend instance
    				$expirationdate = sellyoursaasGetExpirationDate($object);

    				if ($expirationdate && $expirationdate < dol_time_plus_duree($now, abs($delayindaysshort), 'd'))
    				{
    					$substitutionarray=getCommonSubstitutionArray($outputlangs, 0, null, $object);
    					$substitutionarray['__SELLYOURSAAS_EXPIRY_DATE__']=dol_print_date($expirationdate, 'day', $outputlangs, 'tzserver');
    					complete_substitutions_array($substitutionarray, $outputlangs, $object);

    					//$object->array_options['options_deployment_status'] = 'suspended';
    					$subject = make_substitutions($arraydefaultmessage['topic'], $substitutionarray);
    					$msg     = make_substitutions($arraydefaultmessage['content'], $substitutionarray);
    					$from = $conf->global->SELLYOURSAAS_NOREPLY_EMAIL;
    					$to = $object->thirdparty->email;

    					$cmail = new CMailFile($subject, $to, $from, $msg, array(), array(), array(), '', '', 0, 1);
    					$result = $cmail->sendfile();
    					if (! $result)
    					{
    						$error++;
    						$this->error = $cmail->error;
    						$this->errors = $cmail->errors;
    					}

    					$contractprocessed[$object->id]=$object->id;
    				}
    			}
    			$i++;
    		}
    	}
    	else $this->error = $this->db->lasterror();

    	$this->output = count($contractprocessed).' email(s) sent';

    	return ($error ? 1: 0);
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doSuspendExpiredTestInstances()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doSuspendExpiredTestInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	return $this->doSuspendInstances('test');
    }

    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doSuspendExpiredRealInstances()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doSuspendExpiredRealInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	return $this->doSuspendInstances('paid');
    }


   	/**
   	 * Called by doSuspendExpiredTestInstances or doSuspendExpiredRealInstances
   	 *
   	 * @param	string	$mode		'test' or 'paid'
   	 * @return	int					0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
   	 */
   	private function doSuspendInstances($mode)
   	{
    	global $conf, $langs, $user;

    	if ($mode != 'test' && $mode != 'paid')
    	{
    		$this->error = 'Function doSuspendInstances called with bad value for parameter $mode';
    		return -1;
    	}

    	$this->output = '';
    	$this->error='';

    	$error = 0;
    	$now = dol_now();

    	$sql = 'SELECT c.rowid, c.ref_customer, cd.rowid as lid';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	//$sql.= " AND cd.date_fin_validite < '".$this->db->idate(dol_time_plus_duree($now, 1, 'd'))."'";
    	$sql.= " AND cd.date_fin_validite < '".$this->db->idate($now)."'";
    	$sql.= " AND cd.statut = 4";

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		$contractprocessed = array();

    		$i=0;
    		while ($i < $num)
    		{
				$obj = $this->db->fetch_object($resql);
				if ($obj)
				{
					if (! empty($contractprocessed[$object->id])) continue;

					// Test if this is a paid or not instance
					$object = new Contrat($this->db);
					$object->fetch($obj->rowid);

					$ispaid = sellyoursaasIsPaidInstance($object);
					if ($mode == 'test' && $ispaid) continue;											// Discard if this is a paid instance when we are in test mode
					if ($mode == 'paid' && ! $ispaid) continue;											// Discard if this is a test instance when we are in paid mode

					// Suspend instance
					$expirationdate = sellyoursaasGetExpirationDate($object);

					if ($expirationdate && $expirationdate < $now)
					{
						//$object->array_options['options_deployment_status'] = 'suspended';
						$result = $object->closeAll($user);			// This may execute trigger that make system actions to suspend instance
						if ($result < 0)
						{
							$error++;
							$this->error = $object->error;
							$this->errors = $object->errors;
						}

						$contractprocessed[$object->id]=$object->id;
					}
				}
    			$i++;
    		}
    	}
    	else $this->error = $this->db->lasterror();

    	$this->output = count($contractprocessed).' contract(s) suspended';

    	return ($error ? 1: 0);
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doUndeployOldSuspendedTestInstances()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doUndeployOldSuspendedTestInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	return $this->doUndeployOldSuspendedInstances('test');
    }

    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doUndeployOldSuspendedRealInstances()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doUndeployOldSuspendedRealInstances.log';

    	dol_syslog(__METHOD__, LOG_DEBUG);
    	return $this->doUndeployOldSuspendedInstances('paid');
    }

    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
   	 * @param	string	$mode		'test' or 'paid'
     * @return	int					0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doUndeployOldSuspendedInstances($mode)
    {
    	global $conf, $langs, $user;

    	if ($mode != 'test' && $mode != 'paid')
    	{
    		$this->error = 'Function doUndeployOldSuspendedInstances called with bad value for parameter $mode';
    		return -1;
    	}

    	$this->output = '';
    	$this->error='';

    	$error = 0;
		$now = dol_now();

    	$delayindays = 9999999;
    	if ($mode == 'test') $delayindays = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_TRIAL_UNDEPLOYMENT;
    	if ($mode == 'paid') $delayindays = $conf->global->SELLYOURSAAS_NBDAYS_AFTER_EXPIRATION_BEFORE_PAID_UNDEPLOYMENT;
		if ($delayindays <= 1)
		{
			$this->error='BadValueForDelayBeforeUndeploymentCheckSetup';
			return -1;
		}
    	dol_syslog(__METHOD__." we undeploy instances mode=".$mode." that are expired since more than ".$delayindays." days", LOG_DEBUG);

    	global $conf, $langs, $user;

    	$this->output = '';
    	$this->error='';

    	$sql = 'SELECT c.rowid, c.ref_customer, cd.rowid as lid';
    	$sql.= ' FROM '.MAIN_DB_PREFIX.'contrat as c, '.MAIN_DB_PREFIX.'contratdet as cd, '.MAIN_DB_PREFIX.'contrat_extrafields as ce';
    	$sql.= ' WHERE cd.fk_contrat = c.rowid AND ce.fk_object = c.rowid';
    	$sql.= " AND ce.deployment_status = 'done'";
    	$sql.= " AND cd.date_fin_validite < '".$this->db->idate(dol_time_plus_duree($now, -1 * abs($delayindays), 'd'))."'";
    	$sql.= " AND cd.statut = 5";

    	$resql = $this->db->query($sql);
    	if ($resql)
    	{
    		$num = $this->db->num_rows($resql);

    		$contractprocessed = array();

    		$i=0;
    		while ($i < $num)
    		{
    			$obj = $this->db->fetch_object($resql);
    			if ($obj)
    			{
    				if (! empty($contractprocessed[$object->id])) continue;

    				// Test if this is a paid or not instance
    				$object = new Contrat($this->db);
    				$object->fetch($obj->rowid);

    				$ispaid = sellyoursaasIsPaidInstance($object);
    				if ($mode == 'test' && $ispaid) continue;										// Discard if this is a paid instance when we are in test mode
    				if ($mode == 'paid' && ! $ispaid) continue;										// Discard if this is a test instance when we are in paid mode

    				// Undeploy instance
    				$expirationdate = sellyoursaasGetExpirationDate($object);

    				if ($expirationdate && $expirationdate < ($now - (abs($delayindays)*24*3600)))
    				{
    					//$object->array_options['options_deployment_status'] = 'suspended';
    					$result = sellyoursaasUndeploy($object, $mode);
    					if ($result < 0)
    					{
    						$error++;
    						$this->error = $object->error;
    						$this->errors = $object->errors;
    					}

    					$contractprocessed[$object->id]=$object->id;
    				}
    			}
    			$i++;
    		}
    	}
    	else $this->error = $this->db->lasterror();

    	$this->output = count($contractprocessed).' contract(s) undeployed';

    	return ($error ? 1: 0);
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doTakePaymentPaypal()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doTakePaymentPaypal.log';

    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	// ...

    	return 0;
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doTakePaymentStripe()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doTakePaymentStripe.log';

    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	// ...

    	return 0;
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doAlertCreditCardExpiration()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doAlertCreditCardExpiration.log';

    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	// ...

    	return 0;
    }


    /**
     * Action executed by scheduler
     * CAN BE A CRON TASK
     *
     * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
     */
    public function doAlertPaypalExpiration()
    {
    	global $conf, $langs;

    	$conf->global->SYSLOG_FILE = 'DOL_DATA_ROOT/dolibarr_doAlertPaypalExpiration.log';

    	$this->output = '';
    	$this->error='';

    	dol_syslog(__METHOD__, LOG_DEBUG);

    	// ...

    	return 0;
    }

}
