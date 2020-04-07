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

/**
 * Configuration page for the Project60 Membership extension
 */
class CRM_Rcont_Sequence {

  protected $cycle;
  protected $most_recent_contribution;
  protected $cycle_day                = NULL;
  protected $minimum_cycle_day_offset = 0;
  protected $maximum_cycle_day_offset = 0;
  protected $cycle_day_offset_list    = array();
  protected $contribution_sequence    = array();
  protected $cycle_tolerance          = NULL;
  protected $max_skips                = 0;       // tolerate missing $max_skips periods
  protected $max_skip_time            = 0;       // tolerate a maximum of $max_skip_time seconds of skipped periods.
  protected $hash                     = '';

  public static $identical_fields  = array(
    'financial_type_id'     => 'financial_type_id',
    'campaign_id'           => 'campaign_id',
    // 'payment_instrument_id' => 'payment_instrument_id',
    'contact_id'            => 'contact_id',
    'amount'                => 'total_amount',
    'currency'              => 'currency',
    );

  /**
   * create a new series with the most recent contribution
   */
  public function __construct($contribution, $cycle, $tolerance = '7 days', $max_skips = 0, $max_skip_days = NULL) {
    $this->cycle = $cycle;
    $this->cycle_tolerance = strtotime($tolerance, 0);
    $this->most_recent_contribution = $contribution;
    $this->contribution_sequence[] = $contribution;
    $this->cycle_day = date('d', strtotime($contribution['receive_date']));
    $this->cycle_day_offset_list[] = 0;
    $this->max_skips = (int) $max_skips;
    if ($max_skip_days === NULL) {
      // defaults to $cycle x ($max_skips+1) + $tolerance
      $this->max_skip_time = strtotime($cycle, 0) * ($this->max_skips + 1) + $this->cycle_tolerance;
      // error_log("SKIP time: " . $this->max_skip_time / 60 / 60 / 24);
    } else {
      $this->max_skip_time = strtotime("+{$max_skip_days} days", 0);
    }
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
   *
   * @return intervals (usually 1, but more if skip allowed). FALSE if it doesn't match
   */
  public function matches($contribution) {
    // first: without receive date there's nothing we can do
    if (empty($contribution['receive_date'])) {
      return FALSE;
    }

    // now: check if all values are identical
    foreach (self::$identical_fields as $field_name) {
      if ($contribution[$field_name] != $this->most_recent_contribution[$field_name]) {
        // error_log("FIELD MISMATCH");
        return FALSE;
      }
    }

    $this_receive_date = strtotime($contribution['receive_date']);

    for ($skips = 0; $skips <= $this->max_skips; $skips++) {
      // all attributes seem o.k., tolerance
      $last_receive_date = $this->expectedReceiveDate(1 + $skips);
      $offset = ($this_receive_date - $last_receive_date);

      // if the skip time
      if (abs($offset) > $this->max_skip_time) break;

      // error_log("Expd date: " . date('Y-m-d H:i:s', $last_receive_date));
      // error_log("This date: " . date('Y-m-d H:i:s', $this_receive_date));
      // error_log("OFFSET: $offset");
      // error_log("TOLR:   " . $this->cycle_tolerance);
      if ($offset < $this->minimum_cycle_day_offset) {
        if ($offset >= $this->maximum_cycle_day_offset - $this->cycle_tolerance) {
          return $skips + 1;
        }
      } elseif ($offset > $this->maximum_cycle_day_offset) {
        if ($offset <= $this->minimum_cycle_day_offset + $this->cycle_tolerance) {
          return $skips + 1;
        }
      } else {
        return $skips + 1;
      }
    }

    return FALSE;
  }

  /**
   * add a contribution to the sequence
   * the contribution should have previously been checked with the matches() method
   *
   * @param $contribution the contribtuion to add
   * @param $intervals    the intervals after the current end (usually 1, but more when skips occur)
   */
  public function add($contribution, $intervals = 1) {
    // update cycle day stats
    $expected_receive_date = $this->expectedReceiveDate($intervals);
    $this_receive_date = strtotime($contribution['receive_date']);
    $offset = ($this_receive_date - $expected_receive_date);
    if ($offset < $this->minimum_cycle_day_offset) $this->minimum_cycle_day_offset = $offset;
    if ($offset > $this->maximum_cycle_day_offset) $this->maximum_cycle_day_offset = $offset;
    $this->cycle_day_offset_list[] = $offset;

    // add to list
    $this->contribution_sequence[] = $contribution;
    $this->hash = sha1($this->hash . $contribution['id']);
  }

  /**
   * calculate the previous receive date in the sequence
   */
  public function expectedReceiveDate($intervals = 1) {
    $last_contribution = end($this->contribution_sequence);
    $last_receive_date = strtotime($last_contribution['receive_date']);
    $next_receive_date = $last_receive_date;
    for ($i = 0; $i < $intervals; $i++) {
      $next_receive_date = strtotime('-'.$this->cycle, $next_receive_date);
    }
    return $next_receive_date;
  }

  /**
   * extract a recurring contriution object from the contribution data
   */
  public function getRecurringContribution($cycle_day_adjust = 'median') {
    $first_contribution = reset($this->contribution_sequence);
    $last_contribution  = end($this->contribution_sequence);

    // optimise cycle_day if requested
    sort($this->cycle_day_offset_list);
    switch ($cycle_day_adjust) {

      case 'median':
        $best_offset = $this->cycle_day_offset_list[count($this->cycle_day_offset_list) / 2];
        break;

      case 'minimum':
        $best_offset = $this->cycle_day_offset_list[0];
        break;

      case 'average':
        $offset_sum = 0.0;
        foreach ($his->cycle_day_offset_list as $offset) {
          $offset_sum += $offset;
        }
        $best_offset = $offset_sum / count($this->cycle_day_offset_list);
        break;

      default:
      case 'no_adjustment':
        $best_offset = 0;
        break;
    }

    $best_cycle_day = $this->cycle_day + (int) ($best_offset / 60 / 60 / 24);
    if ($best_cycle_day > 30) $best_cycle_day -= 30;
    if ($best_cycle_day < 1)  $best_cycle_day += 30;

    $frequency = explode(' ', $this->cycle);
    $recurring_contribution = array(
      'hash'               => $this->hash,
      'contribution_count' => count($this->contribution_sequence),
      'cycle_day'          => $best_cycle_day,
      'frequency_unit'     => $frequency[1],
      'frequency_interval' => $frequency[0],
      'end_date'           => $first_contribution['receive_date'],
      'start_date'         => $last_contribution['receive_date']);

    // add contribution IDs
    $contribution_ids = array();
    foreach ($this->contribution_sequence as $contribution) {
      $contribution_ids[] = $contribution['id'];
    }
    $recurring_contribution['_contribution_ids'] = $contribution_ids;

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
