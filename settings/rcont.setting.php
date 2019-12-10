<?php

return [
  'rcont_remember_values' => [
    'name' => 'rcont_remember_values',
    'type' => 'Boolean',
    'quick_form_type' => 'YesNo',
    'default' => TRUE,
    'html_type' => 'radio',
    'add' => '0.7',
    'title' => ts('Remember previously entered recurring contribution form values?'),
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('If enabled, Recurring Contributions Tools will remember the previously entered values and use them to prefill the form.'),
  ]
];
