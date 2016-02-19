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
  $recurring_contributions = CRM_Rcont_Analyser::evaluateContact($params['contact_id']);

  return civicrm_api3_create_success($recurring_contributions);
}

function _civicrm_api3_contribution_recur_analyse(&$params) {
  $params['contact_id'] =  array('api.required' => 1);
}
