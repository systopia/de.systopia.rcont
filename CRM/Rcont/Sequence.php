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

  protected $cycle;
  protected $most_recent_contribution;
  protected $cycle_day                = NULL;
  // protected $minimum_cycle_day_offset = 0;
  // protected $maximum_cycle_day_offset = 0;
  // protected $cycle_day_offset_sum     = 0;
  protected $cycle_day_offset_list    = array();
  protected $contribution_sequence    = array();
  protected $cycle_tolerance          = '6 days';
  protected $hash                     = '';

  public static $identical_fields  = array(
    'financial_type_id'     => 'financial_type_id',
    'campaign_id'           => 'campaign_id',
    // 'payment_instrument_id' => 'payment_instrument_id',
    'contact_id'            => 'contact_id',
    'amount'                => 'total_amount',
    'currency'              => 'currency',
    'fee_amount'            => 'fee_amount',
    'net_amount'            => 'net_amount');

  /**
   * create a new series with the most recent contribution
   */
  public function __construct($contribution, $cycle, $tolerance = '6 days') {
    $this->cycle = $cycle;
    $this->cycle_tolerance = strtotime($tolerance, 0);
    $this->most_recent_contribution = $contribution;
    $this->contribution_sequence[] = $contribution;
    $this->cycle_day = date('d', strtotime($contribution['receive_date']));
    $this->cycle_day_offset_list[] = 0;
  }

  /**
   * check if the sequence has enough entries to be considered a real sequence
   */
  public function isSequence($minimum_count = 5) {
    error_log($minimum_count);
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
        // error_log("FIELD MISMATCH");
        return FALSE;
      }
    }

    // all attributes seem o.k., check cycle
    $last_receive_date = $this->expectedReceiveDate();
    $this_receive_date = strtotime($contribution['receive_date']);
    // error_log("Expd date: " . date('Y-m-d H:i:s', $last_receive_date));
    // error_log("This date: " . date('Y-m-d H:i:s', $this_receive_date));
    // error_log("DIFF: " . ($last_receive_date - $this_receive_date));
    // error_log("TOLR: " . $this->cycle_tolerance);
    if (abs($last_receive_date - $this_receive_date) <= $this->cycle_tolerance) {
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
    // update cycle day stats
    $expected_receive_date = $this->expectedReceiveDate();
    $this_receive_date = strtotime($contribution['receive_date']);
    // $offset = ($this_receive_date - $expected_receive_date);
    // if ($offset < $this->minimum_cycle_day_offset) $this->minimum_cycle_day_offset = $offset;
    // if ($offset > $this->maximum_cycle_day_offset) $this->maximum_cycle_day_offset = $offset;
    // $this->cycle_day_offset_sum += $offset;
    $offset = round(($this_receive_date - $expected_receive_date) / 60 / 60 / 24);
    $this->cycle_day_offset_list[] = $offset;
// TODO: use median, not average. 
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

    // error_log("ADDED");

    // add
    $this->hash = sha1($this->hash . $contribution['id']);
    $this->contribution_sequence[] = $contribution;
    $this->expected_receive_date = NULL;
  }

  /**
   * calculate the previous receive date in the sequence
   */
  public function expectedReceiveDate() {
    if (!$this->expected_receive_date) {
      $last_contribution = end($this->contribution_sequence);
      $last_receive_date = strtotime($last_contribution['receive_date']);
      $next_receive_date = strtotime('-'.$this->cycle, $last_receive_date);
      // error_log('-'.$this->cycle);
      // $half_cycle = strtotime($this->cycle, 0) / 60 / 60 / 24;

      // $cycle_day_offset = $this->cycle_day - date('d', $next_receive_date);
      // $cycle_day_delta  = abs($cycle_day_offset);
      // if ($cycle_day_delta < $half_cycle) {
      //   if ($cycle_day_offset > 0) {
      //     $next_receive_date = strtotime("+$cycle_day_delta days", $next_receive_date);
      //   } elseif ($cycle_day_offset < 0) {
      //     $next_receive_date = strtotime("-$cycle_day_delta days", $next_receive_date);
      //   }
      // } else {
      //   if ($cycle_day_offset > 0) {
      //     $next_receive_date = strtotime("-$cycle_day_delta days", $next_receive_date);
      //     $next_receive_date = strtotime('+'.$this->cycle, $next_receive_date);
      //   } elseif ($cycle_day_offset < 0) {
      //     $next_receive_date = strtotime("+$cycle_day_delta days", $next_receive_date);
      //   }
      // }

      // error_log("Last date: " . date('Y-m-d', $last_receive_date));
      // error_log("Next date: " . date('Y-m-d', $next_receive_date));
      $this->expected_receive_date = $next_receive_date;
    }
    return $this->expected_receive_date;
  }

  /**
   * extract a recurring contriution object from the contribution data
   */
  public function getRecurringContribution() {
    $first_contribution = reset($this->contribution_sequence);
    $last_contribution  = end($this->contribution_sequence);

    // calculate 'better' cycle day with the stats
    // $optimisedOffset = ($this->cycle_day_offset_sum / count($this->contribution_sequence));
    // // this would be the ideal one, but we have to make sure we don't 
    // //  violate the tolerance when moving
    // if ($this->maximum_cycle_day_offset - $optimisedOffset > $cycle_tolerance) {
    //   $optimisedOffset = $this->maximum_cycle_day_offset - $cycle_tolerance;
    // } elseif ($optimisedOffset - $this->minimum_cycle_day_offset > $cycle_tolerance) {
    //   $optimisedOffset = $this->minimum_cycle_day_offset + $cycle_tolerance;
    // }
    // $best_cycle_day = $this->cycle_day + round(($optimisedOffset / 60 / 60 / 24));

    // use median offset to calculate 'better' cycle day
    sort($this->cycle_day_offset_list);
    $median_offset = $this->cycle_day_offset_list[count($this->cycle_day_offset_list) / 2];
    $best_cycle_day = $this->cycle_day + $median_offset);
    // TODO: this would be the ideal one, but we have to make sure we don't 
    //  violate the tolerance when moving
    if ($best_cycle_day > 30) $best_cycle_day -= 30;
    if ($best_cycle_day < 1)  $best_cycle_day += 30;


    // $cycle_day_sum = 0;
    // foreach ($this->contribution_sequence as $contribution) {
    //   $cycle_day_sum += date('d', strtotime($contribution['receive_date']));
    // }
    // $cycle_day = round((double) $cycle_day_sum / (double) count($this->contribution_sequence));

    $frequency = explode(' ', $this->cycle);
    $recurring_contribution = array(
      'hash'               => $this->hash,
      'contribution_count' => count($this->contribution_sequence),
      'cycle_day'          => $best_cycle_day,
      'frequency_unit'     => $frequency[1],
      'frequency_interval' => $frequency[0],
      'end_date'           => $first_contribution['receive_date'],
      'start_date'         => $last_contribution['receive_date']);

    foreach (self::$identical_fields as $rcur_field_name => $field_name) {
      $recurring_contribution[$rcur_field_name] = $first_contribution[$field_name];
    }

    return $recurring_contribution;
  }

  /**
   * simply add all contained contributions to the list 
   *  with a reference to this object
   */
  public function addContributionsToList(&$matched_contributions) {
    foreach ($this->contribution_sequence as $contribution) {
      $matched_contributions[$contribution['id']] = $this;
    }
  }
}
