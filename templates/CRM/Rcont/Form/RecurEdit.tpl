{*-------------------------------------------------------+
| YfC UI modicications                                   |
| Copyright (C) 2015 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

<table class="form-layout-compressed">
  <tbody>
    <tr class="crm-rcontribution-form-block-contact_id">
      <td class="label">{$form.contact_id.label}</td>
      <td class="font-size12pt"><strong>{$contact.display_name}&nbsp;[{$contact.id}]</strong></td>
    </tr>
    <tr class="crm-rcontribution-form-block-amount">
      <td class="label">{$form.amount.label}</td>
      <td class="content">{$form.amount.html}&nbsp;GBP</td>
    </tr>
    <tr class="crm-rcontribution-form-block-frequency">
      <td class="label">{$form.frequency.label}</td>
      <td class="content">{$form.frequency.html}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-cycle_day">
      <td class="label">{$form.cycle_day.label}</td>
      <td class="content">{$form.cycle_day.html}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-campaign_id">
      <td class="label">{$form.campaign_id.label}</td>
      <td class="content">{$form.campaign_id.html}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-contribution_status_id">
      <td class="label">{$form.contribution_status_id.label}</td>
      <td class="content">{$form.contribution_status_id.html}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-financial_type_id">
      <td class="label">{$form.financial_type_id.label}</td>
      <td class="content">{$form.financial_type_id.html}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-start_date">
      <td class="label">{$form.start_date.label}</td>
      <td>{include file="CRM/common/jcalendar.tpl" elementName=start_date}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-end_date">
      <td class="label">{$form.end_date.label}</td>
      <td class="content">{include file="CRM/common/jcalendar.tpl" elementName=end_date}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-invoice_id">
      <td class="label">{$form.invoice_id.label}</td>
      <td class="content">{$form.invoice_id.html}</td>
    </tr>
    <tr class="crm-rcontribution-form-block-trxn_id">
      <td class="label">{$form.trxn_id.label}</td>
      <td class="content">{$form.trxn_id.html}</td>
    </tr>
  </tbody>
</table>

<div style="display: none">
  {$form.contact_id.html}
  {$form.rcontribution_id.html}
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
