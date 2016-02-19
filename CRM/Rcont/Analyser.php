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

  /** 
   * analyse the given contact for recurring contributions
   *
   * @return a list of recurring contributions
   */
  public static function evaluateContact($contact_id) {
    $recurring_contributions = CRM_Rcont_Analyser::extractRecurringContributions($contact_id);
    
    // TODO: Implement
    return $recurring_contributions;
  }



  public static function extractRecurringContributions($contact_id, $horizon = '3 year') {
    $sequences = array();
    $last_sequence_start = strtotime("-$horizon");

    $sql = "SELECT *
            FROM civicrm_contribution 
            WHERE contact_id=$contact_id 
              AND (is_test = 0 OR is_test IS NULL)
              AND contribution_status_id = 1
            ORDER BY receive_date DESC;";
    $query = CRM_Core_DAO::executeQuery($sql);

    while ($query->fetch()) {
      error_log("Looking into " . $query->id);
      $processed = FALSE;
      // extract the contribution data
      $contribution = array(
        'contact_id'   => $contact_id,
        'id'           => $query->id,
        'receive_date' => $query->receive_date);
      foreach (CRM_Rcont_Sequence::$identical_fields as $field_names) {
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
        $sequences[] = new CRM_Rcont_Sequence($contribution);
      }
    }

    $recurring_contributions = array();
    foreach ($sequences as $sequence) {
      if ($sequence->isSequence()) {
        $recurring_contributions[] = $sequence->getRecurringContribution();
      }
    }

    return $recurring_contributions;
  }
}
