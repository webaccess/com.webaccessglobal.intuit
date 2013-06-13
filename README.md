com.webaccessglobal.intuit
==========================

Intuit payment processor extension for CiviCRM

// Intuit-Quickbooks Payment Processor Installation

 Set payment processor as per instuction on civicrm site ie
 http://wiki.civicrm.org/confluence/display/CRM/Setting+up+Intuit+QuickBooks+Payment+Processor+for+CiviCRM

 This Payment Processor you can install by creating extension folder and 
 creating  extension directory path in civicrm.
 Do not set 'Support recurring intervals' for recurring contribution as itintervals are not handeled in Intuit.

 IntuitQuickbooksIPN.php file you need to put in the civicrm/bin folder

 You need to make the cron file for recurring contribution One for live contribution and one for test contribution to test it properly.

 For eg: 

 For Test: http://<YourDomain>/sites/all/modules/civicrm/bin/cron?job=run_payment_cron&is_test=<Istest?>&processor_name=Intuit&name=<Username>&pass=<Password>&key=<Your Site Key>

 For Live: http://<YourDomain>/sites/all/modules/civicrm/bin/cron?job=run_payment_cron&processor_name=Intuit&name=<Username>&pass=<Password>&key=<Your Site Key>

 And you need to set the cron for every hour.

 //
