<?php

require_once 'rcont.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function rcont_civicrm_config(&$config) {
  _rcont_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function rcont_civicrm_xmlMenu(&$files) {
  _rcont_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function rcont_civicrm_install() {
  _rcont_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function rcont_civicrm_uninstall() {
  _rcont_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function rcont_civicrm_enable() {
  _rcont_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function rcont_civicrm_disable() {
  _rcont_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function rcont_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _rcont_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function rcont_civicrm_managed(&$entities) {
  _rcont_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function rcont_civicrm_caseTypes(&$caseTypes) {
  _rcont_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function rcont_civicrm_angularModules(&$angularModules) {
_rcont_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function rcont_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _rcont_civix_civicrm_alterSettingsFolders($metaDataFolders);
}


/**
 * put the new rcontribtion edit mask in the action links
 */
function rcont_civicrm_links( $op, $objectName, $objectId, &$links, &$mask, &$values ) {
  if ($op == 'contribution.selector.recurring') {
    foreach ($links as $key => &$link) {
      if ($link['name'] == 'Edit') {
        $link['url'] = 'civicrm/rcont/edit';
        $link['qs'] = 'reset=1&rcid=%%crid%%';
        // $link['class'] = 'no-popup';
      }
    }
  }
}

/**
 * put a new rcontribtion edit button in rcontribution view
 */
function rcont_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contribute_Page_ContributionRecur') {
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'CRM/Rcont/Page/ContributionRecur.rcont.tpl'
    ));
  }
}

/**
 * put a new rcontribtion action in summary action list
 */
function rcont_civicrm_summaryActions( &$actions, $contactID ) {
  $actions['add_rcontribution'] = array(
      'title'           => ts("Add Recurring Contribution"),
      'weight'          => 5,
      'ref'             => 'add-recurring-contribution',
      'key'             => 'add_rcontribution',
      'component'       => 'CiviContribute',
      'href'            => CRM_Utils_System::url('civicrm/rcont/edit', "cid=$contactID"),
      'permissions'     => array('access CiviContribute', 'edit contributions')
    );
}
