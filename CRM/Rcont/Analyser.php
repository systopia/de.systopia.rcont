<?php
/*-------------------------------------------------------+
| de.systopia.rcont - Analyse Recurring Contributions    |
| Copyright (C) 2016 SYSTOPIA                            |
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
 
/**
 * Configuration page for the Project60 Membership extension
 */
class CRM_Rcont_Analyser {
  public static $contribution_fields  = array(
    'id'                    => 'id',
    'financial_type_id'     => 'financial_type_id',
    'campaign_id'           => 'campaign_id',
    'payment_instrument_id' => 'payment_instrument_id',
    'contact_id'            => 'contact_id',
    'amount'                => 'total_amount',
    'currency'              => 'currency',
    'fee_amount'            => 'fee_amount',
    'net_amount'            => 'net_amount');


  /** 
   * analyse the given contact for recurring contributions
   *
   * @return a list of recurring contributions
   */
  public static function evaluateContact($contact_id, $params) {
    // set some standard parameters
    if (empty($params['horizon'])) {
      $params['horizon'] = '5 year';
    }

    if (empty($params['intervals'])) {
      // THESE HAVE TO BE IN INCREASING ORDER
      $params['intervals'] = array('1 month', '3 month', '6 month', '1 year');
    }

    if (empty($params['installments'])) {
      $params['installments'] = 3;
    }


    // calculat recurring contributions
    $extracted_contributions = CRM_Rcont_Analyser::extractRecurringContributions($contact_id, $params);
    error_log("CALCULATED: ".print_r($extracted_contributions,1));

    // get existing contributions
    $existing_contributions = CRM_Rcont_Analyser::currentRecurringContributions($contact_id, $params);
    error_log("ACTUAL: ".print_r($existing_contributions,1));

    // match extracted with existing contribtions
    // $changes = CRM_Rcont_Analyser::matchRecurringContributions($existing_contributions, $extracted_contributions);
      
    // TODO: apply changes

    // if (!empty($params['logfile'])) {
      // TODO: logging
    // }
    
    return $changes;
  }



  /**
   * try to deduce the currently active recurring contributions from 
   *  patterns in the contact's contributions
   */
  public static function extractRecurringContributions($contact_id, $params) {
    $horizon = $params['horizon'];
    $intervals = $params['intervals'];
    $last_sequence_start = strtotime("-$horizon");

    // this will contain all the already matched contributions
    $matched_contributions   = array();
    $recurring_contributions = array();
    // $resulting_sequences     = array();

    foreach ($params['intervals'] as $interval) {
      // error_log($interval);
      $sequences = array();

      $sql = "SELECT *
              FROM civicrm_contribution 
              WHERE contact_id = $contact_id 
                AND (is_test = 0 OR is_test IS NULL)
                AND contribution_status_id = 1
              ORDER BY receive_date DESC;";
      $query = CRM_Core_DAO::executeQuery($sql);

      while ($query->fetch()) {
        // check if this contribution is already spoken for
        if (isset($matched_contributions[$query->id])) continue;

        // error_log("Looking into " . $query->id);

        $processed = FALSE;
        // extract the contribution data
        $contribution = array(
          'contact_id'   => $contact_id,
          'id'           => $query->id,
          'receive_date' => $query->receive_date);
        foreach (self::$contribution_fields as $field_names) {
          $contribution[$field_names] = $query->$field_names;
        }

        // sort into sequences
        foreach ($sequences as $sequence) {
          if ($sequence->matches($contribution)) {
            $sequence->add($contribution);
            $processed = TRUE;
            break;
          }
        }
        if ($processed) continue;

        // this doesn't belong to any sequence so far
        if (strtotime($contribution['receive_date']) > $last_sequence_start) {
          // this is still within the limits of starting a new sequence
          $sequences[] = new CRM_Rcont_Sequence($contribution, $interval);
        }
      }

      // error_log("Squences: " . count($sequences));
      foreach ($sequences as $sequence) {
        if ($sequence->isSequence($params['installments'])) {
          // if this is a real recurring contribution, record it
          $recurring_contributions[] = $sequence->getRecurringContribution();

          // also block the contained contributions to go into another one
          $sequence->addContributionsToList($matched_contributions);
        }
      }
    }

    return $recurring_contributions;
  }



  /**
   * try to deduce the currently active recurring contributions from 
   *  patterns in the contact's contributions
   */
  public static function currentRecurringContributions($contact_id, $params) {
    $recurring_contributions = array();

    // build SQL query
    $last_sequence_start = date('Y-m-d', strtotime("-$horizon"));
    $interval_conditions = array();
    foreach ($params['intervals'] as $interval) {
      list($cycle, $unit) = split(' ', $interval);
      $interval_conditions[] = "(`frequency_unit` = '$unit' AND `frequency_interval` = $cycle)";
    }
    $interval_sql = implode(' OR ', $interval_conditions);

    $sql = "SELECT id FROM civicrm_contribution_recur 
            WHERE `contact_id` = $contact_id 
              AND (`end_date` IS NULL OR `end_date` >= DATE('$last_sequence_start'))
              AND ($interval_sql);";
    $query = CRM_Core_DAO::executeQuery($sql);

    // load them all
    while ($query->fetch()) {
      $recurring_contributions[] = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $query->id));
    }
    return $recurring_contributions;
  }
}
