{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

{crmScope extensionKey='de.systopia.xdedupe'}
<h3>{ts}Create Default Values{/ts}</h3>

  <div class="crm-section">
    <div class="label">{$form.currency.label}</div>
    <div class="content">{$form.currency.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.frequency.label}</div>
    <div class="content">{$form.frequency.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.cycle_day.label}</div>
    <div class="content">{$form.cycle_day.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.campaign_id.label}</div>
    <div class="content">{$form.campaign_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.contribution_status_id.label}</div>
    <div class="content">{$form.contribution_status_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.payment_instrument_id.label}</div>
    <div class="content">{$form.payment_instrument_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.financial_type_id.label}</div>
    <div class="content">{$form.financial_type_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
{/crmScope}