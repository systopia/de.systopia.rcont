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


  /**
   * Will look into the group of recurring contribtions and check/mend 
   * common inconsitencies:
   *  1. overlap in time => will end the older ones
   */
  public static function mendRecurringContributions($recurring_contribution_ids, &$params) {
    $query = civicrm_api3('ContributionRecur', 'get', array('id' => array('IN' => $recurring_contribution_ids), 'option.limit' => 99999));
    $rcontributions = $query['values'];
    $completed_status_ids = array(
      CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name'),
      CRM_Core_OptionGroup::getValue('contribution_status', 'Cancelled', 'name'),
      CRM_Core_OptionGroup::getValue('contribution_status', 'Failed', 'name'),
      CRM_Core_OptionGroup::getValue('contribution_status', 'Refunded', 'name'),
    );
    $open_status_ids = array(
      CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
      CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name'),
    );
    $log = array();

    // first: check individually
    foreach ($rcontributions as &$rcontribution) {
      // all recurring contributions should have start dates
      if (empty($rcontribution['start_date'])) {
        $log[] = "PROBLEM: Recurring contribution [{$rcontribution['id']}] has no start date.";
      }

      // they shold have a certain length
      if (!empty($rcontribution['end_date'])) {
        $duration = strtotime($rcontribution['end_date']) - strtotime($rcontribution['start_date']);
        TODO
      }

      // all ended recurring contributions should have an end_date
      if (empty($rcontribution['end_date'])) {
        if ($rcontribution['contribution_status_id'] == CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name')) {
          $log[] = "PROBLEM: Recurring contribution [{$rcontribution['id']}] is closed/ended/cancelled but has no end date.";
          // TODO: set to last contribution?
        }
      } else {
        if ($rcontribution['end_date'] < date('Y-m-d h:i:s')) {
          if (!in_array($rcontribution['contribution_status_id'], $completed_status_ids)) {
            $log[] = "PROBLEM: Recurring contribution [{$rcontribution['id']}] has an end date in the past, but is not closed/ended/cancelled.";
            if (!empty($params['contribution_status_id'])) {
              $rcontribution['contribution_status_id'] = CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');
              civicrm_api3('ContributionRecur', 'create', array('id' => $rcontribution['id'], 'contribution_status_id' => $rcontribution['contribution_status_id']));
              $log[] = "FIXED: Recurring contribution [{$rcontribution['id']}] was set to 'Completed'.";
            } else {
              $log[] = "SUGGESTION: Set recurring contribution [{$rcontribution['id']}] to 'Completed'. (set contribution_status_id=1)";
            }
          }
        }
      }
    }

    // sort by start_date and test for overlap
    uasort($rcontributions, '_CRM_Rcont_Updater_compare_start_dates');
    $last_rcur = NULL;
    foreach ($rcontributions as &$rcontribution) {
      if (empty($rcontribution['start_date'])) continue;

      // check if there is an obvious overlap
      if ($last_rcur) {
        if (!empty($last_rcur['end_date'])) {
          if ($last_rcur['end_date'] > $rcontribution['start_date']) {
            $log[] = "PROBLEM: Recurring contributions [{$last_rcur['id']}] and [{$rcontribution['id']}] overlap. (adjust manually)";
            // TODO: fix?
          }
        } else {
          // last one has no end date...
          $last_rcur_changes = array();
          if (in_array($rcontribution['contribution_status_id'], $open_status_ids)) {
            // ... but there is an active follow-up:
            $log[] = "PROBLEM: Recurring contribution [{$last_rcur['id']}] is succeeded and overlapped by [{$rcontribution['id']}].";
            $last_rcur['end_date']         = $rcontribution['start_date'];
            $last_rcur_changes['end_date'] = $last_rcur['end_date'];
            if (!in_array($last_rcur['contribution_status_id'], $completed_status_ids)) {
              $last_rcur['contribution_status_id']         = CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');
              $last_rcur_changes['contribution_status_id'] = $last_rcur['contribution_status_id'];
            }
          }

          if (!empty($last_rcur_changes)) {
            if (!empty($params['fix_sequence'])) {
              if (!empty($last_rcur_changes['contribution_status_id']))
                $log[] = "FIXED: Set status for [{$last_rcur['id']}] to 'Completed'.";
              if (!empty($last_rcur_changes['end_date']))
                $log[] = "FIXED: Set end date for [{$last_rcur['id']}] to '{$last_rcur_changes['end_date']}'.";
              $last_rcur_changes['id'] = $last_rcur['id'];
              civicrm_api3('ContributionRecur', 'create', $last_rcur_changes);
            } else {
              if (!empty($last_rcur_changes['contribution_status_id']))
                $log[] = "SUGGESTION: Set status for [{$last_rcur['id']}] to 'Completed'. (set fix_sequence=1)";
              if (!empty($last_rcur_changes['end_date']))
                $log[] = "SUGGESTION: Set end date for [{$last_rcur['id']}] to '{$last_rcur_changes['end_date']}'. (set fix_sequence=1)";
            }
          }
        }
      }

      $last_rcur = &$rcontribution;
    }

    return $log;
  }


}


/**
 * compare function for self::mendRecurringContributions
 */
function _CRM_Rcont_Updater_compare_start_dates($a, $b) {
  if ($a['start_date'] == $b['start_date']) {
    return 0;
  } else {
    return ($a['start_date'] < $b['start_date']) ? -1 : 1;
  }
}
