<?php
/*-------------------------------------------------------+
| de.systopia.rcont - Recurring Contribution Tools       |
| Copyright (C) 2016-2018 SYSTOPIA                       |
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

include_once 'Analyse.php';

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
 * API specs for ContributionRecur:adjust
 */
function _civicrm_api3_contribution_recur_adjust_spec(&$params) {
  _civicrm_api3_contribution_recur_analyse_spec($params);

  // rcont_create,rcont_delete,rcont_update,assign_contributions
  // if true, will assign the contributions to the respective recurring contributions
  $params['assign_contributions'] = array('api.required' => 0);

  // absolute path of an log file with the changes
  $params['change_log'] = array('api.required' => 0);
}
