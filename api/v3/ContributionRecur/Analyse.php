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

function civicrm_api3_contribution_recur_analyse($params) {
  // reset last ID
  if (!empty($params['reset'])) {
    CRM_Core_BAO_Setting::setItem(0, 'de.systopia.rcont', 'bulk_last_contact_id');
  }

  if (!empty($params['contact_id'])) {
    // analyse one single contact
    $recurring_contributions = CRM_Rcont_Analyser::evaluateContactRcur($params['contact_id'], $params);
  
  } elseif (!empty($params['bulk_count'])) {
    // analyse chunk of contacts
    $recurring_contributions = array();
    $countdown = (int) $params['bulk_count'];
    while ($countdown > 0) {
      // do the query every time, maybe there is another thread...
      $last_contact_id = (int) CRM_Core_BAO_Setting::getItem('de.systopia.rcont', 'bulk_last_contact_id');
      if (empty($last_contact_id)) $last_contact_id = 0;
      $next_contact_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_contact WHERE id > $last_contact_id AND is_deleted <> 1;");
      if (empty($next_contact_id)) break;

      // look into this contact
      CRM_Core_BAO_Setting::setItem($next_contact_id, 'de.systopia.rcont', 'bulk_last_contact_id');
      $recurring_contributions[$next_contact_id] = CRM_Rcont_Analyser::evaluateContactRcur($next_contact_id, $params);

      $countdown -= 1;
    }

  } else {
    // no contact_id no bulk_count => nothing to do
    return civicrm_api3_create_error("You need to provide at least 'contact_id' or 'bulk_count'.");
  }

  // done
  return civicrm_api3_create_success($recurring_contributions);
}

/**
 * basically the same as ContributionRecur:analyse, 
 * but adjusting the recurring contributions according to the
 * findings
 */
function civicrm_api3_contribution_recur_adjust($params) {
  $params['apply_changes'] = 1;
  return civicrm_api3_contribution_recur_analyse($params);
}




/**
 * API specs for ContributionRecur:analyse
 */
function _civicrm_api3_contribution_recur_analyse_spec(&$params) {
  // bulk-analyse a set of contacts, <buik_count> at once, keeping track of the last you did
  $params['bulk_count'] =  array('api.required' => 0);

  // reset the last visited ID
  $params['reset'] = array('api.required' => 0,
                           'api.default' => 0);

  // restrict to the given payment instruments. 
  $params['payment_instrument_ids'] = array('api.required' => 0);

  // restrict to the given payment instruments. 
  $params['financial_type_ids'] = array('api.required' => 0);

  // tolerance in days
  $params['tolerance'] = array('api.required' => 0);

  // if >0 allows skipping installments of a sequence
  $params['max_skips'] = array('api.required' => 0);

  // if restricts the total time when skipping installments
  $params['max_skip_days'] = array('api.required' => 0);

  // absolute path of an log file with the results
  $params['analysis_log'] = array('api.required' => 0);

  // analyse a single contact ID
  $params['contact_id'] =  array('api.required' => 0);
}

/**
 * API specs for ContributionRecur:adjust
 */
function _civicrm_api3_contribution_recur_adjust_spec(&$params) {
  _civicrm_api3_contribution_recur_analyse_spec($params);

  // if true, will assign the contributions to the respective recurring contributions
  $params['asssign_contributions'] = array('api.required' => 0);

  // absolute path of an log file with the changes
  $params['change_log'] = array('api.required' => 0);
}
