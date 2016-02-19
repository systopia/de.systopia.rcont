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
class CRM_Rcont_Sequence {

  protected $most_recent_contribution = NULL;
  protected $contribution_sequence    = array();
  protected $cycle_day                = NULL;

  public static $identical_fields  = array(
    'financial_type_id'     => 'financial_type_id',
    'campaign_id'           => 'campaign_id',
    'payment_instrument_id' => 'payment_instrument_id',
    'contact_id'            => 'contact_id',
    'amount'                => 'total_amount',
    'currency'              => 'currency',
    'fee_amount'            => 'fee_amount',
    'net_amount'            => 'net_amount');

  protected $params = array('cycle'           => '1 month',
                            'cycle_tolerance' => '6 days');

  /**
   * create a new series with the most recent contribution
   */
  public function __construct($contribution) {
    $this->most_recent_contribution = $contribution;
    $this->contribution_sequence[] = $contribution;
    $this->cycle_day = date('d', strtotime($contribution['receive_date']));
  }

  /**
   * check if the sequence has enough entries to be considered a real sequence
   */
  public function isSequence($minimum_count = 5) {
    return count($this->contribution_sequence) >= $minimum_count;
  }

  /**
   * check if the given contribution matches the 
   * sequence.
   */
  public function matches($contribution) {
    // first: withoug receive date there's nothing we can do
    if (empty($contribution['receive_date'])) {
      return FALSE;
    }

    // now: check if all values are identical
    foreach ($this->identical_fields as $field_name) {
      if ($contribution[$field_name] != $this->most_recent_contribution[$field_name]) {
        return FALSE;
      }
    }

    // all attributes seem o.k., check cycle
    $last_receive_date = $this->expectedPreviousReceiveDate();
    $this_receive_date = strtotime($contribution['receive_date']);
    // error_log("Expd date: " . date('Y-m-d H:i:s', $last_receive_date));
    // error_log("This date: " . date('Y-m-d H:i:s', $this_receive_date));
    // error_log("DIFF: " . ($last_receive_date - $this_receive_date));
    // error_log("TOLR: " . strtotime($this->params['cycle_tolerance'],0));
    if (abs($last_receive_date - $this_receive_date) <= strtotime($this->params['cycle_tolerance'], 0)) {
      // error_log("ADDED");
      return TRUE;
    } else {
      // error_log("NOT ADDED");
      return FALSE;
    }
  }

  /**
   * add a contribution to the sequence
   * the contribution should have previously been checked with the matches() method
   */
  public function add($contribution) {
    // error_log("ADDED");
    $this->contribution_sequence[] = $contribution;

    // $cycle_day = date('d', strtotime($contribution['receive_date']));
    // if ($cycle_day != $this->cycle_day) {
    //   // re-calculate cycle day
    //   $cycle_day_sum = 0;
    //   foreach ($this->contribution_sequence as $contribution) {
    //     $cycle_day_sum += date('d', strtotime($contribution['receive_date']));
    //   }
    //   $this->cycle_day = round((double) $cycle_day_sum / (double) count($this->contribution_sequence));
    //   error_log("CHANGED cycle_day: {$this->cycle_day}");
    // }
    // TODO: verify new cycle day is still valid
  }

  /**
   * calculate the previous receive date in the sequence
   */
  public function expectedPreviousReceiveDate() {
    $last_contribution = end($this->contribution_sequence);
    $last_receive_date = strtotime($last_contribution['receive_date']);
    $next_receive_date = strtotime('-'.$this->params['cycle'], $last_receive_date);
    $half_cycle = strtotime($this->params['cycle'], 0) / 60 / 60 / 24;

    $cycle_day_offset = $this->cycle_day - date('d', $next_receive_date);
    $cycle_day_delta  = abs($cycle_day_offset);
    if ($cycle_day_delta < $half_cycle) {
      if ($cycle_day_offset > 0) {
        $next_receive_date = strtotime("+$cycle_day_delta days", $next_receive_date);
      } elseif ($cycle_day_offset < 0) {
        $next_receive_date = strtotime("-$cycle_day_delta days", $next_receive_date);
      }
    } else {
      if ($cycle_day_offset > 0) {
        $next_receive_date = strtotime("-$cycle_day_delta days", $next_receive_date);
        $next_receive_date = strtotime('+'.$this->params['cycle'], $next_receive_date);
      } elseif ($cycle_day_offset < 0) {
        $next_receive_date = strtotime("+$cycle_day_delta days", $next_receive_date);
      }
    }

    // error_log("Last date: " . date('Y-m-d', $last_receive_date));
    // error_log("Next date: " . date('Y-m-d', $next_receive_date));
    return $next_receive_date;
  }

  /**
   * extract a recurring contriution object from the contribution data
   */
  public function getRecurringContribution() {
    $first_contribution = reset($this->contribution_sequence);
    $last_contribution  = end($this->contribution_sequence);

    // re-calculate cycle day
    $cycle_day_sum = 0;
    foreach ($this->contribution_sequence as $contribution) {
      $cycle_day_sum += date('d', strtotime($contribution['receive_date']));
    }
    $cycle_day = round((double) $cycle_day_sum / (double) count($this->contribution_sequence));

    $frequency = explode(' ', $this->params['cycle']);
    $recurring_contribution = array(
      'contribution_count' => count($this->contribution_sequence),
      'cycle_day'          => $cycle_day,
      'frequency_unit'     => $frequency[1],
      'frequency_interval' => $frequency[0],
      'end_date'           => $first_contribution['receive_date'],
      'start_date'         => $last_contribution['receive_date']);

    foreach (self::$identical_fields as $rcur_field_name => $field_name) {
      $recurring_contribution[$rcur_field_name] = $first_contribution[$field_name];
    }

    return $recurring_contribution;
  }
}
