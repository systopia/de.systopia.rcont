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
 * Will look into existing recurring contributions
 * and fix common problems
 */
function civicrm_api3_contribution_recur_mend($params) {
  $group_attributes = array('contact_id', 'financial_type_id', 'payment_instrument_id', 'campaign_id', 'amount', 'frequency_unit', 'frequency_interval');
  $log = array();

  // reset last ID
  if (!empty($params['reset'])) {
    CRM_Core_BAO_Setting::setItem(0, 'de.systopia.rcont', 'mend_last_contact_id');
  }

  if (!empty($params['contact_id'])) {
    $where_clause = "contact_id = " . (int) $params['contact_id'];
    $end_clause = "";

  } elseif (!empty($params['bulk_count'])) {
    $where_clause = "contact_id > " . (int) CRM_Core_BAO_Setting::getItem('de.systopia.rcont', 'mend_last_contact_id');
    $end_clause = "ORDER BY contact_id ASC LIMIT " . $params['bulk_count'];

  } else {
    $where_clause = 'TRUE';
    $end_clause = "";
  }

  if (empty($params['multiples_only'])) {
    $multiples = 0;
  } else {
    $multiples = 1;
  }

  if (empty($params['exclude_ids'])) {
    $exclude_ids = '';
  } else {
    $exclude_ids = "AND contact_id NOT IN ({$params['exclude_ids']})";
  }


  $group_clause = implode(',', $group_attributes);


  $sql   = "SELECT rcont_ids, contact_id
            FROM (SELECT COUNT(id)        AS rcont_count,
                         GROUP_CONCAT(id) AS rcont_ids,
                         contact_id       AS contact_id
                  FROM civicrm_contribution_recur
                  WHERE $where_clause
                  GROUP BY $group_clause
                  ) tmp
            WHERE rcont_count > $multiples
                  $exclude_ids
            $end_clause;";
  $query = CRM_Core_DAO::executeQuery($sql);
  $max_contact_id = 0;
  while ($query->fetch()) {
    $rcont_ids = explode(',', $query->rcont_ids);
    $logs = CRM_Rcont_Updater::mendRecurringContributions($rcont_ids, $params);
    $max_contact_id = max($max_contact_id, $query->contact_id);

    // add to result
    if (!empty($logs)) {
      if (empty($log[$query->contact_id])) {
        $log[$query->contact_id] = $logs;
      } else {
        $log[$query->contact_id] = array_merge($log[$query->contact_id], $logs);
      }      
    }
  }

  // store last contact_id
  if (!empty($params['bulk_count'])) {
    CRM_Core_BAO_Setting::setItem($max_contact_id, 'de.systopia.rcont', 'mend_last_contact_id');
  }

  // done
  return civicrm_api3_create_success($log);
}

/**
 * API specs for ContributionRecur:adjust
 */
function _civicrm_api3_contribution_recur_mend_spec(&$params) {
  // fix_sequence
  // multiples_only
  // exclude_ids
  // contact_id
  // complete_by_end_date
  

  // // rcont_create,rcont_delete,rcont_update,assign_contributions
  // // if true, will assign the contributions to the respective recurring contributions
  // $params['assign_contributions'] = array('api.required' => 0);

  // // absolute path of an log file with the changes
  // $params['change_log'] = array('api.required' => 0);
}
