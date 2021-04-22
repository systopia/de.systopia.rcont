<?php
/*-------------------------------------------------------+
| de.systopia.rcont - Recurring Contribution Tools       |
| Copyright (C) 2016-2021 SYSTOPIA                       |
| Author: B. Endres (endres@systopia.de)                 |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

require_once 'CRM/Core/Form.php';

use CRM_Rcont_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Rcont_Form_RecurEdit extends CRM_Core_Form {

  public function buildQuickForm() {
    $rcontribution_id = 0;

    if (!empty($_REQUEST['rcid'])) {
      // EDIT existing contribution recur
      $rcontribution_id = (int) $_REQUEST['rcid'];
      $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));
      $contact_id = $rcontribution['contact_id'];
      $this->setExistingDefaults($rcontribution);

    } elseif (!empty($this->_submitValues['rcontribution_id'])) {
      $rcontribution_id = (int) $this->_submitValues['rcontribution_id'];
      $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $rcontribution_id));
      $contact_id = $rcontribution['contact_id'];
        $this->setExistingDefaults($rcontribution);

    } elseif (!empty($_REQUEST['cid'])) {
      $contact_id = (int) $_REQUEST['cid'];
      $rcontribution = array('contact_id' => $contact_id);
      CRM_Utils_System::setTitle(E::ts('Create Recurring Contribution'));
      $this->setDefaults(CRM_Rcont_Form_Settings::getRecurringDefaults());

    } elseif (!empty($this->_submitValues['contact_id'])) {
      $contact_id = (int) $this->_submitValues['contact_id'];
      $rcontribution = array('contact_id' => $contact_id);
      CRM_Utils_System::setTitle(E::ts('Create Recurring Contribution'));
      $this->setDefaults(CRM_Rcont_Form_Settings::getRecurringDefaults());

    } else {
      // no rcid or cid: ERROR
      CRM_Core_Session::setStatus('Error. You have to provide cid or rcid.', 'Error', 'error');
      $dashboard_url = CRM_Utils_System::url('civicrm/dashboard', 'reset=1');
      CRM_Utils_System::redirect($dashboard_url);
      return;
    }

    // make sure this is not a SEPA mandate
    if ($rcontribution_id && $rcontribution && !empty($rcontribution['payment_instrument_id'])) {
      $non_sepa_pis = CRM_Rcont_Form_Settings::getPaymentInstruments($rcontribution['payment_instrument_id']);
      if (empty($non_sepa_pis[$rcontribution['payment_instrument_id']])) {
        CRM_Core_Session::setStatus('You cannot edit SEPA mandates with this form.', 'Error', 'error');
        return;
      }
    }

    // LOAD contact
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
    $this->assign('contact', $contact);

    // get option lists
    $current_payment_instrument = $this->getCurrentValue('payment_instrument_id', $rcontribution);
    $payment_instruments = CRM_Rcont_Form_Settings::getPaymentInstruments($current_payment_instrument);
    $campaigns = CRM_Rcont_Form_Settings::getCampaigns();
    $currencies = CRM_Rcont_Form_Settings::getCurrencies();
    $cycle_day_list = CRM_Rcont_Form_Settings::getCollectionDays();
    $frequencies = CRM_Rcont_Form_Settings::getFrequencies();
    $status_list = CRM_Rcont_Form_Settings::getContributionStatus();

    // FORM ELEMENTS
    $this->add(
      'text',
      'amount',
      E::ts('Amount'),
      ['size'=>4],
      true
    );
    $this->addRule('amount', "Please enter a valid amount.", 'money');

    $currency = $this->add(
      'select',
      'currency',
      E::ts('Currency'),
      $currencies,
      true,
      array('class' => 'crm-select2')
    );

    $this->add(
      'select',
      'frequency',
      E::ts('Frequency'),
      $frequencies,
      true,
      array('class' => 'crm-select2')
    );

    $this->add(
      'select',
      'campaign_id',
      E::ts('Campaign'),
      $campaigns,
      true,
      array('class' => 'crm-select2')
    );

    $this->add(
      'select',
      'payment_instrument_id',
      E::ts('Payment Instrument'),
      $payment_instruments,
      true,
      array('class' => 'crm-select2')
    );

    $this->add(
      'select',
      'financial_type_id',
      E::ts('Financial Type'),
      CRM_Rcont_Form_Settings::getFinancialTypes(),
      true,
      array('class' => 'crm-select2')
    );

    // DATES
    $this->add(
      'select',
      'cycle_day',
      E::ts('Collection Day'),
      $cycle_day_list,
      true,
      array('class' => 'crm-select2')
    );

    $this->add(
        'datepicker',
        'start_date',
        E::ts('Begins'),
        ['formatType' => 'activityDateTime']
    );

    $this->add(
        'datepicker',
        'end_date',
        E::ts('Ends'),
        ['formatType' => 'activityDateTime']
    );

    $this->add(
      'select',
      'contribution_status_id',
      'Status',
      $status_list,
      true,
      array('class' => 'crm-select2')
    );

    // DATA
    $this->add(
      'text',
      'invoice_id',
      'Invoice ID',
      array('invoice_id' => $this->getCurrentValue('invoice_id', $rcontribution), 'size'=>30),
      false
    );

    $this->add(
      'text',
      'trxn_id',
      'Transaction ID',
      array('trxn_id' => $this->getCurrentValue('trxn_id', $rcontribution), 'size'=>30),
      false
    );

    // special fields
    $this->add(
      'text',
      'contact_id',
      'Contact',
      array('value' => $contact_id),
      false
    );

    // store the ID too
    $this->add(
        'text',
        'rcontribution_id',
        '',
        ['value' => $rcontribution_id, 'hidden'=>1],
        true
    );

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ]
    ]);

    parent::buildQuickForm();
  }


  public function postProcess() {
    $values = $this->exportValues();
    CRM_Rcont_Form_Settings::storeLastSubmit($values);

    // compile contribution object with required values
    $rcontribution = array(
      'contact_id'             => $values['contact_id'],
      'amount'                 => $values['amount'],
      'currency'               => $values['currency'],
      'cycle_day'              => $values['cycle_day'],
      'contribution_status_id' => $values['contribution_status_id'],
      'financial_type_id'      => $values['financial_type_id'],
      'payment_instrument_id'  => $values['payment_instrument_id'],
      );

    if (!empty($values['campaign_id'])) {
      $rcontribution['campaign_id'] = $values['campaign_id'];
    }

    // set ID (causes update instead of create)
    if (!empty($values['rcontribution_id'])) {
      $rcontribution['id'] = (int) $values['rcontribution_id'];
    }

    // add cycle period
    $period = preg_split("/-/", $values['frequency']);
    $rcontribution['frequency_interval'] = $period[0];
    $rcontribution['frequency_unit']     = $period[1];

    // add dates
    $rcontribution['start_date']         = date('Y-m-d', strtotime($values['start_date']));
    if (!empty($values['end_date'])) {
      $rcontribution['end_date']         = date('Y-m-d', strtotime($values['end_date']));
    } else {
      $rcontribution['end_date']         = '';
    }

    // add non-required values
    $rcontribution['trxn_id']            = $values['trxn_id'];
    $rcontribution['invoice_id']         = $values['invoice_id'];

    $result = civicrm_api3('ContributionRecur', 'create', $rcontribution);
    if (empty($rcontribution['id'])) {
      CRM_Core_Session::setStatus(E::ts('Recurring contribution [%1] created.', array(1 => $result['id'])), E::ts("Success"), "info");
    } else {
      CRM_Core_Session::setStatus(E::ts('Recurring contribution [%1] updated.', array(1 => $result['id'])), E::ts("Success"), "info");
    }

    parent::postProcess();
  }

/**
 * Set the default values based on an existing contribution
 *
 * @param $recurring_contribution array
 *   data of the existing contribution
 */
  public function setExistingDefaults($recurring_contribution) {
    $this->setDefaults($recurring_contribution);
    $this->setDefaults([
        'frequency' => "{$recurring_contribution['frequency_interval']}-{$recurring_contribution['frequency_unit']}"
    ]);
  }

  /**
   * get the current value of a key either from the values
   * that are about to be submitted (in case of a validation error)
   * or the ones stored in the settings (in case of a fresh start)
   */
  public function getCurrentValue($key, $rcontribution) {
    if (!empty($this->_submitValues)) {
      return CRM_Utils_array::value($key, $this->_submitValues);
    } elseif (CRM_Utils_Array::value($key, $rcontribution)) {
      return CRM_Utils_Array::value($key, $rcontribution);
    } else {
      // custom form field defaults can be configured via rcont_default_field_name settings
      return Civi::settings()->get("rcont_default_{$key}");
    }
    return NULL;
  }

  /**
   * same as getCurrentValue but adds date formatting
   */
  public function getCurrentDate($key, $rcontribution) {
    $date = $this->getCurrentValue($key, $rcontribution);
    if (empty($date)) {
      return NULL;
    } else {
      return date('m/d/Y', strtotime($date));
    }
  }

  /**
   * Pre-processing for the form.
   *
   * @throws \Exception
   */
  public function preProcess()
  {
    $this->setAction(CRM_Core_Action::UPDATE);
    CRM_Utils_System::setTitle(E::ts('Update Recurring Contribution'));
  }
}
