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
    $recurring_contributions = CRM_Rcont_Analyser::evaluateContact($params['contact_id'], $params);
  
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
      $recurring_contributions[$next_contact_id] = CRM_Rcont_Analyser::evaluateContact($next_contact_id, $params);

      $countdown -= 1;
    }

  } else {
    // no contact_id no bulk_count => nothing to do
    return civicrm_api3_create_error("You need to provide at least 'contact_id' or 'bulk_count'.");
  }

  // done
  return civicrm_api3_create_success($recurring_contributions);
}


function _civicrm_api3_contribution_recur_analyse_spec(&$params) {
  // analyse a single contact ID
  $params['contact_id'] =  array('api.required' => 0);

  // bulk-analyse a set of contacts, <buik_count> at once, keeping track of the last you did
  $params['bulk_count'] =  array('api.required' => 0);

  // log file to write your results to
  $params['logfile'] = array('api.required' => 0);

  // reset the last visited ID
  $params['reset'] = array('api.required' => 0);

}
