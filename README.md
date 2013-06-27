com.webaccessglobal.intuit
==========================

Intuit payment processor extension for CiviCRM
follow the instruction to install the extension: http://wiki.civicrm.org/confluence/display/CRMDOC40/Extensions+Admin

Set payment processor as per instuction on civicrm site ie
 http://wiki.civicrm.org/confluence/display/CRM/Setting+up+Intuit+QuickBooks+Payment+Processor+for+CiviCRM

Do not set 'Support recurring intervals' for recurring contribution as itintervals are not handeled in Intuit.


You need to set the cron link for recurring contribution in crontab as follows.

 eg: 

 For Test: http://YourDomain/sites/all/modules/civicrm/bin/cron?job=run_intuit_cron&is_test=<Istest?>&processor_name=Intuit&name=Username&pass=Password&key=Your Site Key

 For Live: http://YourDomain/sites/all/modules/civicrm/bin/cron?job=run_intuit_cron&processor_name=Intuit&name=Username&pass=Password&key=Your Site Key

For more info check : http://wiki.civicrm.org/confluence/display/CRMDOC41/Managing+Scheduled+Jobs
 
