<?php

require_once 'intuit.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function intuit_civicrm_config(&$config) {
  _intuit_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function intuit_civicrm_xmlMenu(&$files) {
  _intuit_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function intuit_civicrm_install() {

  // Create entry in civicrm_job table for cron call
  if (CRM_Core_DAO::checkTableExists('civicrm_job'))
    CRM_Core_DAO::executeQuery("
                INSERT INTO civicrm_job (
                   id, domain_id, run_frequency, last_run, name, description,
                   api_entity, api_action, parameters, is_active
                ) VALUES (
                   NULL, %1, 'Daily', NULL, 'Process Intuit Recurring Payments',
                   'Processes any Intuit recurring payments that are due',
                   'job', 'run_payment_cron', 'processor_name=Intuit', 0
                )
                ", array(
      1 => array(CIVICRM_DOMAIN_ID, 'Integer')
        )
    );

  return _intuit_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function intuit_civicrm_uninstall() {

  CRM_Core_DAO::executeQuery("DELETE pp FROM civicrm_payment_processor pp
RIGHT JOIN  civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
WHERE ppt.name = 'Intuit'");

  $affectedRows = mysql_affected_rows();

  if ($affectedRows)
    CRM_Core_Session::setStatus("Intuit Payment Processor Message:
    <br />Entries for Intuit hosted Payment Processor are now Deleted!
    <br />");

  return _intuit_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function intuit_civicrm_enable() {

  CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor pp
RIGHT JOIN  civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
SET pp.is_active = 1
WHERE ppt.name = 'Intuit'");

  $affectedRows = mysql_affected_rows();

  if ($affectedRows)
    CRM_Core_Session::setStatus("Intuit Payment Processor Message:
    <br />Entries for Intuit hosted Payment Processor are now Enabled!
    <br />");

  return _intuit_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function intuit_civicrm_disable() {

  CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor pp
RIGHT JOIN  civicrm_payment_processor_type ppt ON ppt.id = pp.payment_processor_type_id
SET pp.is_active = 0
WHERE ppt.name = 'Intuit'");

  $affectedRows = mysql_affected_rows();

  if ($affectedRows)
    CRM_Core_Session::setStatus("Intuit Payment Processor Message:
    <br />Entries for Intuit hosted Payment Processor are now Disabled!
    <br />");

  return _intuit_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function intuit_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _intuit_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function intuit_civicrm_managed(&$entities) {

  $entities[] = array(
    'module' => 'com.webaccessglobal.intuit',
    'name' => 'Intuit',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'Intuit',
      'title' => 'Intuit',
      'description' => 'Intuit Payment Processor',
      'class_name' => 'Payment_Intuit',
      'billing_mode' => 'form',
      'user_name_label' => 'Application Login',
      'password_label' => 'Connection Ticket',
      'signature_label' => 'Application ID',
      'url_site_default' => 'https://webmerchantaccount.quickbooks.com/j/AppGateway',
      'url_site_test_default' => 'https://webmerchantaccount.ptc.quickbooks.com/j/AppGateway',
      'is_recur' => 1,
      'payment_type' => 1,
    ),
  );

  return _intuit_civix_civicrm_managed($entities);
}
