<?php

require_once 'intuit.civix.php';
require_once 'Intuitpayment.php';
require_once 'config.intuit.php';

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
  return _intuit_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function intuit_civicrm_uninstall() {

  /**
   * Fix for 4.2
   * change CRM_Financial_DAO_PaymentProcessorType with CRM_Core_DAO_PaymentProcessorType
   * Aleter where condition payment_processor_type = 'Intuit'
   * */
  $intuitID = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessorType', 'Intuit', 'id', 'name');
  if ($intuitID) {
    CRM_Core_DAO::executeQuery("DELETE  FROM civicrm_payment_processor where payment_processor_type_id =" . $intuitID);
    $affectedRows = mysql_affected_rows();

    if ($affectedRows)
      CRM_Core_Session::setStatus("Intuit Payment Processor Message:
    <br />Entries for Intuit Payment Processor are now Deleted!
    <br />");
  }

  return _intuit_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function intuit_civicrm_enable() {

   /**
   * Fix for 4.2
   * change CRM_Financial_DAO_PaymentProcessorType with CRM_Core_DAO_PaymentProcessorType
   * Aleter where condition payment_processor_type = 'Intuit'
   * */
  $intuitID = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessorType', 'Intuit', 'id', 'name');
  if ($intuitID) {
    CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor SET is_active = 1 where payment_processor_type_id =" . $intuitID);
    $affectedRows = mysql_affected_rows();

    if ($affectedRows)
      CRM_Core_Session::setStatus("Intuit Payment Processor Message:
      <br />Entries for Intuit hosted Payment Processor are now Enabled!
       <br />");
  }

  return _intuit_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function intuit_civicrm_disable() {
  /**
   * Fix for 4.2
   * change CRM_Financial_DAO_PaymentProcessorType with CRM_Core_DAO_PaymentProcessorType
   * Aleter where condition payment_processor_type = 'Intuit'
   * */
  $intuitID = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessorType', 'Intuit', 'id', 'name');
  if ($intuitID) {
    CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor SET is_active = 0 where payment_processor_type_id =" . $intuitID);
    $affectedRows = mysql_affected_rows();

    if ($affectedRows)
      CRM_Core_Session::setStatus("Intuit Payment Processor Message:
       <br />Entries for Intuit hosted Payment Processor are now Disabled!
       <br />");
  }

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

  $entities[0] = array(
    'module' => 'com.webaccessglobal.intuit',
    'name' => 'Intuit',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'Intuit',
      'title' => 'Intuit',
      'description' => 'Intuit Payment Processor',
      'class_name' => 'com.webaccessglobal.intuit',
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
 $entities[1] =
      array(
        'name' => 'IntuitCron',
        'entity' => 'Job',
        'module' => 'com.webaccessglobal.intuit',
        'params' =>
        array(
          'version' => 3,
          'name' => 'IntuitCron',
          'description' => 'Processes any Intuit recurring payments that are due',
          'run_frequency' => 'Daily',
          'api_entity' => 'job',
          'api_action' => 'run_payment_cron',
          'parameters' => 'processor_name=Intuit',
        ),
  );

  return _intuit_civix_civicrm_managed($entities);
}
