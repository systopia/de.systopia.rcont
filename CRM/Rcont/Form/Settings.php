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

/**
 * Recurring Contribution Editor Settings
 */
class CRM_Rcont_Form_Settings extends CRM_Core_Form
{
    public function buildQuickForm()
    {

        // default values
        $this->add(
            'select',
            'currency',
            ts('Currency'),
            $this->getDefaultOptions(self::getCurrencies()),
            true,
            []
        );

        $this->add(
            'select',
            'frequency',
            ts('Frequency'),
            $this->getDefaultOptions(self::getFrequencies()),
            true,
            []
        );

        $this->add(
            'select',
            'collection_day',
            ts('Collection Day'),
            $this->getDefaultOptions(self::getCollectionDays()),
            true,
            []
        );

        $this->add(
            'select',
            'campaign',
            ts('Campaign'),
            $this->getDefaultOptions(self::getCampaigns()),
            true,
            []
        );

        $this->add(
            'select',
            'status',
            ts('Status'),
            $this->getDefaultOptions(self::getContributionStatus()),
            true,
            []
        );

        $this->add(
            'select',
            'payment_instrument',
            ts('Payment Instrument'),
            $this->getDefaultOptions(self::getPaymentInstruments()),
            true,
            []
        );

        $this->add(
            'select',
            'financial_type',
            ts('Financial Type'),
            $this->getDefaultOptions(self::getFinancialTypes()),
            true,
            []
        );

        // set current settings

        parent::buildQuickForm();
    }


    public function postProcess()
    {
        $values  = $this->exportValues();

        // TODO: store values

        parent::postProcess();
    }

}
