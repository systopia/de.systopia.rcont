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
    // 'payment_instrument_id' => 'payment_instrument_id',
    'contact_id'            => 'contact_id',
    'amount'                => 'total_amount',
    'currency'              => 'currency',
    'fee_amount'            => 'fee_amount',
    'net_amount'            => 'net_amount');

  public static $comparisonWeights  = array(
    'financial_type_id'     => 20,
    'cycle_day'             => 1,    // multiplied with cycle day diff
    'campaign_id'           => 15,
    'amount'                => 20,   // will be multiplied by percentage
    'currency'              => 100,
    'frequency_interval'    => 100,
    'frequency_unit'        => 100);

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
      $params['installments'] = 5;
    }

    if (empty($params['apply_changes'])) {
      $params['apply_changes'] = '';
    }


    // calculat recurring contributions
    $extracted_contributions = CRM_Rcont_Analyser::extractRecurringContributions($contact_id, $params);
    // error_log("CALCULATED: ".print_r($extracted_contributions,1));

    // get existing contributions
    $existing_contributions = CRM_Rcont_Analyser::currentRecurringContributions($contact_id, $params);
    // error_log("ACTUAL: ".print_r($existing_contributions,1));

    // match extracted with existing contribtions
    $changes = CRM_Rcont_Analyser::matchRecurringContributions($existing_contributions, $extracted_contributions);
    // error_log("PROPOSED CHANGES: ".print_r($changes,1));
  
    // ANALYSIS LOG
    if (!empty($params['analysis_log']) && !empty($changes)) {
      $log_entry = "Contact [$contact_id]:\n";
      foreach ($changes as $change) {
        if ($change['from'] == NULL) {
          $message = "Create new recurring contribution: " . self::recurringContributiontoString($change['to']);
        } elseif ($change['to'] == NULL) {
          $message = "End/delete recurring contribution: " . self::recurringContributiontoString($change['from']);
        } elseif ($change['match']) {
          $old = self::recurringContributiontoString($change['from']);
          $message = "Confirmed recurring contribution [{$change['from']['id']}] ($old)"; 
        } else {
          $old = self::recurringContributiontoString($change['from']);
          $new = self::recurringContributiontoString($change['to']);
          $percent = (int) $change['similarity'];
          $message = "Update recurring contribution ({$percent}%) [{$change['from']['id']}]: $old => $new"; 
        }
        $log_entry .= $message . "\n";
      }
      $log_entry .= "\n";
      file_put_contents($params['analysis_log'], $log_entry, FILE_APPEND);
    }

    // APPLY CHANGES
    if (!empty($params['apply_changes']) && !empty($changes)) {
      $change_types = explode(',', $params['apply_changes']);
      $log_entries = array("Contact [$contact_id]:");
      foreach ($changes as $change) {
        if ($change['from'] == NULL) {
          // this is a 'create' action
          if (in_array('create', $change_types)) {
            $rcontribution = self::createRecurringContribution($change['to']);
            $log_entries[] = "Created new recurring contribution [{$rcontribution['id']}]: " . self::recurringContributiontoString($rcontribution);
          }
        } elseif ($change['to'] == NULL) {
          // this is a 'delete' action
          if (in_array('delete', $change_types)) {
            $rcontribution = self::deleteRecurringContribution($change['from']);
            $log_entries[] = "Deleted recurring contribution [{$change['from']['id']}]" . self::recurringContributiontoString($change['from']);
          }
        } elseif (!$change['match']) {
          // this is a 'update' action
          if (in_array('update', $change_types)) {          
            self::updateRecurringContribution($change['from'], $change['to']);
            $old = self::recurringContributiontoString($change['from']);
            $new = self::recurringContributiontoString($change['to']);
            $percent = (int) $change['similarity'];
            $log_entries[] = "Updated recurring contribution ({$percent}%) [{$change['from']['id']}]: $old => $new";
          }
        } else {
          // this is a MATCH event
          if (in_array('assign', $change_types)) {
            // TODO: assign all contributoins in $change['to'] to $change['from']['id']
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
   * try to deduce the currently active recurring contributions from 
   *  patterns in the contact's contributions
   */
  public static function extractRecurringContributions($contact_id, $params) {
    $horizon = $params['horizon'];
    $intervals = $params['intervals'];
    $last_sequence_start = strtotime("-$horizon");
    $financial_type_clause     = self::getSQLClause($params, 'financial_type_ids', 'financial_type_id');
    $payment_instrument_clause = self::getSQLClause($params, 'payment_instrument_ids', 'payment_instrument_id');

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
                AND total_amount > 0
                AND contribution_status_id = 1
                AND $financial_type_clause
                AND $payment_instrument_clause
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
    $horizon = $params['horizon'];

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
              AND (`contribution_status_id` IN (1,2,5))
              AND ($interval_sql);";
    $query = CRM_Core_DAO::executeQuery($sql);

    // load them all
    while ($query->fetch()) {
      $recurring_contributions[] = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $query->id));
    }
    return $recurring_contributions;
  }


  /**
   * create a matching between the existing and the calculated contributions
   */
  public static function matchRecurringContributions($existing_contributions, $extracted_contributions) {
    // first step: find similarities
    $similarities = array();
    foreach ($existing_contributions as $existing_contribution) {
      foreach ($extracted_contributions as $extracted_contribution) {
        $similarity = self::calculateSimilarity($existing_contribution, $extracted_contribution);
        // error_log($similarity);
        $similarities[] = array('existing'   => $existing_contribution,
                                'extracted'  => $extracted_contribution,
                                'similarity' => $similarity);
      }
    }
    usort($similarities, "CRM_Rcont_Analyser::compareSimilarityEntries");
    // error_log(print_r($similarities,1));

    // second step, accept matches with good enough ratings
    $matches = array();
    $matched_rcontributions = array();
    $threshold = 65;
    foreach ($similarities as $match) {
      // stop if $threshold is not matched:
      $similarity = $match['similarity'];
      if ($similarity < $threshold) break;

      // check if haven't already been matched
      $fp1 = sha1(json_encode($match['existing']));
      if (in_array($fp1, $matched_rcontributions)) continue;
      $fp2 = sha1(json_encode($match['extracted']));
      if (in_array($fp2, $matched_rcontributions)) continue;

      // all good: record as match
      $matches[] = array('from'  => $match['existing'],
                         'to'    => $match['extracted'],
                         'match' => ($similarity==100),
                         'similarity' => $similarity);

      // ...and mark both as matched
      $matched_rcontributions[] = $fp1;
      $matched_rcontributions[] = $fp2;
    }

    // now, put the remaining ones
    foreach ($existing_contributions as $existing_contribution) {
      $fp = sha1(json_encode($existing_contribution));
      if (!in_array($fp, $matched_rcontributions)) {
        $matches[] = array('from' => $existing_contribution,
                           'to'   => NULL);
      }
    }

    foreach ($extracted_contributions as $extracted_contribution) {
      $fp = sha1(json_encode($extracted_contribution));
      if (!in_array($fp, $matched_rcontributions)) {
        $matches[] = array('from' => NULL,
                           'to'   => $extracted_contribution);
      }
    }

    return $matches;
  }

  /**
   * calculate similarity between two recurring contributions
   */
  public static function calculateSimilarity($existing_rcont, $extracted_rcont) {
    $similarity = 100;
    foreach (self::$comparisonWeights as $attribute => $weight) {
      switch ($attribute) {
        case 'amount':
          // amount penalty gets multiplied by percentage of decrease
          if ($extracted_rcont['amount'] >= $existing_rcont['amount']) {
            // this is an increase (more likely to be correct)
            $increase = (double) $extracted_rcont['amount'] / (double) $existing_rcont['amount'];
            $factor = $increase - 1.0;
          } else {
            // decreases are unlikely and will get the full penalty
            $factor = 1.0;
          }
          $similarity -= $weight * $factor;
          break;
        
        case 'cycle_day':
          $cycle_day_diff = abs($existing_rcont['cycle_day'] - $extracted_rcont['cycle_day']);
          // mind rolling diff (eg. 30th-2nd)
          $cycle_day_diff = min($cycle_day_diff, abs(30 - $cycle_day_diff));
          // use diff as penalty
          $similarity -= $cycle_day_diff * $weight;
          break;

        default:
          if ($existing_rcont[$attribute] != $extracted_rcont[$attribute]) {
            $similarity -= $weight;
          }
          break;
      }
    }
    return $similarity;
  }

  /** Generate string representation of the recurring contribution */
  public static function recurringContributiontoString($rcontribution) {
    $financial_types = CRM_Contribute_PseudoConstant::financialType();
    $financial_type = $financial_types[$rcontribution['financial_type_id']];
    if (empty($rcontribution['campaign_id'])) {
      $campaign = 'NO_CAMPAIGN';
    } else {
      $entity = civicrm_api3('Campaign', 'getsingle', array('id' => $rcontribution['campaign_id']));
      $campaign = $entity['title'];
    }
    if (empty($rcontribution['contribution_count'])) {
      $counter = "[n/a]";
    } else {
      $counter = "[{$rcontribution['contribution_count']}x]";
    }

    return "{$rcontribution['amount']}/{$rcontribution['frequency_interval']}_{$rcontribution['frequency_unit']}@{$rcontribution['cycle_day']}.({$financial_type},{$campaign}){$counter}";
  }

  public static function compareSimilarityEntries($entry1, $entry2) {
    if ($entry1['similarity'] == $entry2['similarity']) {
      return 0;
    } else {
      return ($entry1['similarity'] < $entry2['similarity']) ? 1 : -1;
    }
  }

  public static function getSQLClause($params, $key, $table_name) {
    $sql_clause = 'TRUE';
    if (!empty($params[$key])) {
      // make sure it's only integers
      if (is_array($params[$key])) {
        $raw_entries = $params[$key];
      } else {
        $raw_entries = explode(',', $params[$key]);
      }
      $entries = array();
      foreach ($raw_entries as $entry) {
        if ((int) $entry > 0) {
          $entries[] = (int) $entry;
        }
      }
      if (!empty($entries)) {
        $idstring = implode(',', $entries);
        $sql_clause = "(`$table_name` IN ($idstring))";
      }
    }
    return $sql_clause;
  }



  // Changeing Recurring contributions

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
}
