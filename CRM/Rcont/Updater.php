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
 * Performs DB changes based on the data produced by the CRM_Rcont_Analyser
 */
class CRM_Rcont_Updater {

  /** 
   * analyse the given contact for recurring contributions
   *
   * @return a list of recurring contributions
   */
  public static function updateContactRcur($contact_id, &$changes, $params) {
    // APPLY CHANGES
    if (!empty($changes)) {
      $log_entries = array("Contact [$contact_id]:");
      foreach ($changes as $change) {
        if ($change['from'] == NULL) {
          // this is a 'create' action
          if (!empty($params['rcont_create'])) {
            $rcontribution = self::createRecurringContribution($change['to']);
            $log_entries[] = "Created new recurring contribution [{$rcontribution['id']}]: " . CRM_Rcont_Analyser::recurringContributiontoString($rcontribution);
            if (!empty($params['assign_contributions'])) {
              $log_entries[] = self::assignContributions($change['to']['_contribution_ids'], $rcontribution['id']);
            }
          }
        } elseif ($change['to'] == NULL) {
          // this is a 'delete' action
          if (!empty($params['rcont_delete'])) {
            $rcontribution = self::deleteRecurringContribution($change['from']);
            $log_entries[] = "Deleted recurring contribution [{$change['from']['id']}]" . CRM_Rcont_Analyser::recurringContributiontoString($change['from']);
          }
        } elseif (!$change['match']) {
          // this is a 'update' action
          if (!empty($params['rcont_update'])) {          
            self::updateRecurringContribution($change['from'], $change['to']);
            $old = CRM_Rcont_Analyser::recurringContributiontoString($change['from']);
            $new = CRM_Rcont_Analyser::recurringContributiontoString($change['to']);
            $percent = (int) $change['similarity'];
            $log_entries[] = "Updated recurring contribution ({$percent}%) [{$change['from']['id']}]: $old => $new";
            if (!empty($params['assign_contributions'])) {
              $log_entries[] = self::assignContributions($change['to']['_contribution_ids'], $change['from']['id']);
            }
          }
        } else {
          // this is a MATCH event
          if (!empty($params['assign_contributions'])) {
            error_log("ASSIGN!");
            $log_entries[] = self::assignContributions($change['to']['_contribution_ids'], $change['from']['id']);
          }
        }
      }
      if (count($log_entries) > 1 && !empty($params['change_log'])) {
        $log_entry = implode("\n", $log_entries);
        file_put_contents($params['change_log'], $log_entry . "\n\n", FILE_APPEND);
      }
    }
    
    return $changes;
  }


  /**
   * create a new recurring contribution with the given data
   */
  public static function createRecurringContribution($rcontribution) {
    // copy standard fields
    $fields = array('contact_id','amount','currency','frequency_unit','frequency_interval','start_date','cycle_day','financial_type_id','payment_instrument_id','campaign_id');
    $data = array();
    foreach ($fields as $field_name) {
      if (isset($rcontribution[$field_name])) {
        $data[$field_name] = $rcontribution[$field_name];
      }
    }

    // set some extra fields
    $data['contribution_status_id'] = 5; // "in Progress"
    $data['is_test'] = 0;
    $data['create_date'] = date('Ymdhis');
    $data['modified_date'] = date('Ymdhis');

    // finally create the recurring contribution
    $result = civicrm_api3('ContributionRecur', 'create', $data);

    // return the newly created recurring contribution
    return civicrm_api3('ContributionRecur', 'getsingle', array('id' => $result['id']));
  }

  /**
   * delete a recurring contribution
   */
  public static function deleteRecurringContribution($rcontribution) {
    if (!empty($rcontribution['id'])) {
      civicrm_api3('ContributionRecur', 'delete', array('id' => (int) $rcontribution['id']));
    }
  }

  /**
   * update a recurring contribution
   */
  public static function updateRecurringContribution($rcurFrom, $rcurTo) {
    if (empty($rcurFrom['id'])) {
      return;
    }

    // copy standard fields
    $fields = array('contribution_status_id','contact_id','amount','currency','frequency_unit','frequency_interval','start_date','cycle_day','financial_type_id','payment_instrument_id','campaign_id');
    $data = array();
    foreach ($fields as $field_name) {
      if (isset($rcurTo[$field_name])) {
        $data[$field_name] = $rcurTo[$field_name];
      } elseif (isset($rcurFrom[$field_name])) {
        $data[$field_name] = $rcurFrom[$field_name];
      }
    }

    // set some extra fields
    $data['id'] = $rcurFrom['id'];
    $data['is_test'] = 0;
    $data['modified_date'] = date('Ymdhis');
    
    // move end date (if exists)
    if (!empty($rcurFrom['end_date'])) {
      $old_end_date = strtotime($rcurFrom['end_date']);
      $new_end_date = strtotime($rcurTo['end_date']);
      if ($old_end_date < $new_end_date) {
        $data['end_date'] = date('Ymdhis', $new_end_date);
      } else {
        $data['end_date'] = date('Ymdhis', $old_end_date);
      }
    }

    // finally perform the update
    civicrm_api3('ContributionRecur', 'create', $data);
  }

  /**
   * assign the given contribution IDs to the contribution recur id
   */
  public static function assignContributions($contribution_ids, $contribution_recur_id) {
    if (empty($contribution_ids)) {
      error_log("NO CONTRBUTIONS!");
      return;
    }

    if (empty($contribution_recur_id)) {
      error_log("NO RECURRING CONTRIBUTION!");
      return;
    }

    // set contribution_recur_id
    $contribution_id_list = implode(',', $contribution_ids);
    CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_recur_id = $contribution_recur_id WHERE id IN ($contribution_id_list);");
  }
}
