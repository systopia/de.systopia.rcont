<?php
/*-------------------------------------------------------+
| de.systopia.rcont - Recurring Contribution Tools       |
| Copyright (C) 2016-2020 SYSTOPIA                       |
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


use CRM_Rcont_ExtensionUtil as E;
use \Civi\Core\SettingsManager;
use \Civi\Core\Container;
use \Civi\Core\SettingsBag;

/**
 * Recurring Contribution Editor Settings
 */
class CRM_Rcont_Form_Settings extends CRM_Core_Form
{
    /** @var array recurring contribution fields covered by settings */
    public static $FIELDS = [
        'currency',
        'frequency',
        'cycle_day',
        'campaign_id',
        'contribution_status_id',
        'payment_instrument_id',
        'financial_type_id'
    ];

    public function buildQuickForm()
    {

        // default values
        $this->add(
            'select',
            'currency',
            E::ts('Currency'),
            $this->getMetaOptions(self::getCurrencies()),
            true,
            []
        );

        $this->add(
            'select',
            'frequency',
            E::ts('Frequency'),
            $this->getMetaOptions(self::getFrequencies()),
            true,
            []
        );

        $this->add(
            'select',
            'cycle_day',
            E::ts('Collection Day'),
            $this->getMetaOptions(self::getCollectionDays()),
            true,
            []
        );

        $this->add(
            'select',
            'campaign_id',
            E::ts('Campaign'),
            $this->getMetaOptions(self::getCampaigns()),
            true,
            []
        );

        $this->add(
            'select',
            'contribution_status_id',
            E::ts('Status'),
            $this->getMetaOptions(self::getContributionStatus()),
            true,
            []
        );

        $this->add(
            'select',
            'payment_instrument_id',
            E::ts('Payment Instrument'),
            $this->getMetaOptions(self::getPaymentInstruments()),
            true,
            []
        );

        $this->add(
            'select',
            'financial_type_id',
            E::ts('Financial Type'),
            $this->getMetaOptions(self::getFinancialTypes()),
            true,
            []
        );

        // set current settings
        $this->setDefaults(self::getSettings());

        $this->addButtons(
            [
                [
                    'type'      => 'submit',
                    'name'      => E::ts('Save'),
                    'isDefault' => true,
                ]
            ]
        );

        parent::buildQuickForm();
    }


    /**
     * Simply store the values the settings
     */
    public function postProcess()
    {
        $values = $this->exportValues();

        // extract values
        $settings = [];
        foreach (self::$FIELDS as $field_name) {
            $settings[$field_name] = CRM_Utils_Array::value($field_name, $values, '');
        }

        // and save
        Civi::settings()->set('rcont_settings', $settings);
        parent::postProcess();
    }


    /**
     * Get the current settings
     *
     * @return array
     *   the current settings
     */
    public static function getSettings() {
        $settings = Civi::settings()->get('rcont_settings');
        if (!is_array($settings)) {
            return [];
        } else {
            return $settings;
        }
    }

    /**
     * Store the last submitted recurring contribution values
     *   if necessary
     *
     * @param $values array
     *   the field values
     */
    public static function storeLastSubmit($values) {
        // filter values
        $stored_values = [];
        foreach ($values as $key => $value) {
            if (in_array($key, self::$FIELDS)) {
                $stored_values[$key] = $value;
            }
        }

        // store global values?
        if (self::usesLastSubmit(true)) {
            Civi::settings()->set('rcont_values_global', $stored_values);
        }

        // store per-user values?
        if (self::usesLastSubmit(false)) {
            $settings_manager = Container::getBootService('settings_manager');
            $settings = $settings_manager->getBagByContact(NULL, CRM_Core_Session::getLoggedInContactID());
            $settings->set('rcont_values_individual', $stored_values);
        }
    }

    /**
     * Get the current default values for creating a new recurring contribution
     *
     * @return array
     *    a list of recurring contributions
     */
    public static function getRecurringDefaults() {
        $last_submit_global = $last_submit_individual = [];

        // will we need the global values?
        if (self::usesLastSubmit(true)) {
            $last_submit_global = Civi::settings()->get('rcont_values_global');
        }

        // will we need the global values?
        if (self::usesLastSubmit(false)) {
            $settings_manager = Container::getBootService('settings_manager');
            $settings = $settings_manager->getBagByContact(NULL, CRM_Core_Session::getLoggedInContactID());
            $last_submit_individual = $settings->get('rcont_values_individual');
        }

        // compile the defaults
        $defaults = [];
        $config = self::getSettings();
        foreach ($config as $key => $value) {
            switch ($value) {
                case '_last_value_':
                    $defaults[$key] = CRM_Utils_Array::value($key, $last_submit_global, '');
                    break;

                case '_last_value_by_contact':
                    $defaults[$key] = CRM_Utils_Array::value($key, $last_submit_individual, '');
                    break;

                case '_empty_':
                  $defaults[$key] = '';
                  break;

              default:
                    $defaults[$key] = $value;
                    break;
            }
        }

        return $defaults;
    }

    /**
     * Check whether the current configuration uses
     *  the last submit values (global or indivdual)
     *
     * @param $global boolean
     *  if true, check whether global values are used, individual otherwise
     *
     * @return boolean
     *   does the current settings use last submit data
     */
    protected static function usesLastSubmit($global) {
        $last_submit_token = $global ? '_last_value_' : '_last_value_by_contact';

        $settings = Civi::settings()->get('rcont_settings');
        if (empty($settings)) {
            $settings = [];
        }

        // check if any of the settings uses the $last_submit_token
        foreach ($settings as $key => $value) {
            if ($value == $last_submit_token) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get an option list with global default, but also the
     *  option for 'last used value' and 'last used value by contact'
     *
     * @param $base_options array
     *  key => label option list
     *
     * @return array
     *  key => label option list with the special values
     */
    public function getMetaOptions($base_options)
    {
        $options = [
            '_empty_'                => E::ts("leave empty"),
            '_last_value_'           => E::ts("Last Value Used in Form"),
            '_last_value_by_contact' => E::ts("Last Value Used by You"),
        ];
        foreach ($base_options as $key => $label) {
            if (empty($key)) {
                continue;
            } else {
                $options[$key] = E::ts("always '%1'", [1 => $label]);
            }
        }

        return $options;
    }

    /**
     * Get the currencies to chose from
     *
     * @return array
     *   key => label option list
     */
    public static function getCurrencies()
    {
        return CRM_Core_OptionGroup::values('currencies_enabled');
    }

    /**
     * Get the list of collection day options
     *
     * @return array
     *   key => label option list
     */
    public static function getCollectionDays()
    {
        $cycle_day_list = [
            '' => E::ts('- none -'),
        ];
        $cycle_day_list += range(0, 31);
        // remove the first item, resulting in a one-based array where array keys
        // match the label
        unset($cycle_day_list[0]);
        $cycle_day_list[29] = "29 " . E::ts('(may cause problems)');
        $cycle_day_list[30] = "30 " . E::ts('(may cause problems)');
        $cycle_day_list[31] = "31 " . E::ts('(may cause problems)');
        return $cycle_day_list;
    }

    /**
     * Get the list of collection frequencies
     *
     * @return array
     *   key => label option list
     */
    public static function getFrequencies()
    {
        return [
            ''          => E::ts('- none -'),
            '1-month'   => E::ts('monthly'),
            '2-month'   => E::ts('bi-monthly'),
            '3-month'   => E::ts('quartely'),
            '4-month'   => E::ts('trimestral'),
            '6-month'   => E::ts('semi-anually'),
            '1-year'    => E::ts('anually'),
        ];
    }

    /**
     * Get the list of contribution status
     *
     * @return array
     *   key => label option list
     */
    public static function getContributionStatus()
    {
        return ['' => E::ts('- none -')] + CRM_Core_OptionGroup::values('contribution_status', FALSE, FALSE, FALSE, NULL, 'label');
    }

    /**
     * Get the list of campaigns
     *
     * @return array
     *   key => label option list
     */
    public static function getCampaigns()
    {
        // LOAD lists
        $campaign_query = civicrm_api3('Campaign', 'get', [
            'is_active'    => 1,
            'option.limit' => 0,
            'option.sort'  => 'title'
        ]);

        // this looks odd, but it gives us two things:
        // - an empty default option that triggers form validation (array key '')
        // - a selectable "none" option that passes form validation and means the
        //   user explicitly decided not to assign a campaign
        $campaigns = [
            '' => E::ts('- none -'),
            0  => E::ts('- none -'),
        ];
        foreach ($campaign_query['values'] as $campaign_id => $campaign) {
            $campaigns[$campaign_id] = $campaign['title'];
        }
        return $campaigns;
    }

    /**
     * Get the list of id -> label payment instruments,
     *  excluding CiviSEPA PIs
     *
     * @param $current_payment_instrument integer
     *  if passing a current payment instrument, this gets proposed even if not active
     *
     * @return array
     *   key => label option list
     */
    public static function getPaymentInstruments($current_payment_instrument = null) {
        static $payment_instruments = null;
        if ($payment_instruments === NULL) {
            $query = civicrm_api3('OptionValue', 'get', array(
                'option_group_id' => 'payment_instrument',
                'name'            => ['NOT IN' => ['RCUR', 'FRST', 'OOFF']],
                'return'          => 'value,label,is_active'));
            $payment_instruments = [
                '' => E::ts('- none -')
            ];
            foreach ($query['values'] as $pi) {
                if ($pi['is_active'] || $pi['value'] == $current_payment_instrument) {
                    $payment_instruments[$pi['value']] = $pi['label'];
                }
            }
        }
        return $payment_instruments;
    }


    /**
     * Get the list of financial types
     *
     * @return array
     *   key => label option list
     */
    public static function getFinancialTypes()
    {
        $financial_types = ['' => E::ts('- none -')];

        // load financial types
        $query = civicrm_api3('FinancialType', 'get', [
            'option.limit' => 0,
            'enabled'      => 1,
            'return'       => 'id,name'
        ]);
        foreach ($query['values'] as $type) {
            $financial_types[$type['id']] = $type['name'];
        }

        return $financial_types;
    }
}
