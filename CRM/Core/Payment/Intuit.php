<?php

/*
 * Copyright (C) 2010
 * Licensed to CiviCRM under the Academic Free License version 3.0.
 *
 * Written and contributed by Parvez Husain (http://www.parvez.me)
 *
 */

/**
 *
 * @package CRM
 * @author Marshal Newrock <marshal@idealso.com>
 * $Id: Intuit.php 26018 2010-01-25 09:00:59Z deepak $
 */
/* NOTE:
 * When looking up response codes in the Intuit Quickbooks API, they
 * begin at one, so always delete one from the "Position in Response"
 */


class CRM_Core_Payment_Intuit extends CRM_Core_Payment {

  static protected $_mode = null;
  static protected $_params = array();

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = null;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct($mode, &$paymentProcessor) {

    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('Intuit Quickbooks');


    $config = CRM_Core_Config::singleton();
    $this->_setParam('applicationLogin', $paymentProcessor['user_name']);
    $this->_setParam('connectionTicket', $paymentProcessor['password']);
    $this->_setParam('applicationID', $paymentProcessor['signature']);
    $this->_setParam('applicationURL', $paymentProcessor['url_site']);
    if ($this->_mode == 'live') {
      $this->_setParam('pemFile', '/tmp/pems/intuit.pem');
    }
    else {
      $this->_setParam('pemFile', '/tmp/pems/intuit-test.pem');
    }
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    $error = array();
    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('Application Login is not set for this payment processor');
    }

    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('Connection Ticket is not set for this payment processor');
    }

    if (empty($this->_paymentProcessor['signature'])) {
      $error[] = ts('Application ID is not set for this payment processor');
    }

    if (empty($this->_paymentProcessor['url_site'])) {
      $error[] = ts('Application URL is not set for this payment processor');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return null;
    }
  }

  /**
   * Submit an Automated Recurring Billing subscription
   *
   * @param  array $params assoc array of input parameters for this transaction
   * @return array the result in a nice formatted array (or an error object)
   * @public
   */
  function doDirectPayment(&$params) {

    if (!defined('CURLOPT_SSLCERT')) {
      return self::error(9001, 'Intuit Quickbooks requires curl with SSL support');
    }

    foreach ($params as $field => $value) {
      $this->_setParam($field, $value);
    }

    $PHP_QBMSXML[0] = '<?xml version="1.0" ?>
        <?qbmsxml version="4.5"?>
        <QBMSXML>
        <SignonMsgsRq>
        <SignonAppCertRq>
        <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
        <ApplicationLogin>' . $this->_getParam('applicationLogin') . '</ApplicationLogin>
        <ConnectionTicket>' . $this->_getParam('connectionTicket') . '</ConnectionTicket>
        </SignonAppCertRq>
        </SignonMsgsRq>
        </QBMSXML>';

    // submit to intuit
    $gatewayUrl = $this->_getParam('applicationURL');
    $response = $this->sendToIntuit($gatewayUrl, $PHP_QBMSXML[0], $this->_getParam('pemFile'));

    //Go ahead and get the session ticket
    //Find the location of the start tag
    $PHP_TempString = strstr($response, "<SessionTicket>");
    $PHP_EndLocation = strpos($PHP_TempString, "</SessionTicket>");
    $PHP_SessionTicket = substr($PHP_TempString, 15, $PHP_EndLocation - 15);

    if ($params['is_recur'] == 1) {
      $PHP_QBMSXML[1] = '<?xml version="1.0"?>
          <?qbmsxml version="4.5"?>
          <QBMSXML>
          <SignonMsgsRq>
           <SignonTicketRq>
            <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
            <SessionTicket>' . $PHP_SessionTicket . '</SessionTicket>
           </SignonTicketRq>
          </SignonMsgsRq>
          <QBMSXMLMsgsRq>
           <CustomerCreditCardWalletAddRq>
               <CustomerID>Customer-' . $params['contactID'] . '</CustomerID>
               <CreditCardNumber>' . $this->_getParam('credit_card_number') . '</CreditCardNumber>
               <ExpirationMonth>' . str_pad($this->_getParam('month'), 2, '0', STR_PAD_LEFT) . '</ExpirationMonth>
               <ExpirationYear>' . $this->_getParam('year') . '</ExpirationYear>
               <NameOnCard>' . $this->_getParam('billing_first_name') . ' ' . $this->_getParam('billing_last_name') . '</NameOnCard>
               <CreditCardAddress>' . $this->_getParam('street_address') . '</CreditCardAddress>
               <CreditCardPostalCode>' . $this->_getParam('postal_code') . '</CreditCardPostalCode>
          </CustomerCreditCardWalletAddRq>
         </QBMSXMLMsgsRq>
         </QBMSXML>';

      $response = $this->sendToIntuit($gatewayUrl, $PHP_QBMSXML[1], $this->_getParam('pemFile'));

      //Go ahead and get the Wallet ID
      //Find the location of the start tag
      $PHP_TempString = strstr($response, "<WalletEntryID>");
      $PHP_EndLocation = strpos($PHP_TempString, "</WalletEntryID>");
      $PHP_WalletId = substr($PHP_TempString, 15, $PHP_EndLocation - 15);
      $PHP_TempString = strstr($response, "<IsDuplicate>");
      $PHP_EndLocation = strpos($PHP_TempString, "</IsDuplicate>");
      $PHP_DuplWallet = substr($PHP_TempString, 13, $PHP_EndLocation - 13);
      if ($PHP_DuplWallet == 'true') {
        $params['invoice_id'] = $PHP_WalletId . '-' . rand();
      }
      else {
        $params['invoice_id'] = $PHP_WalletId;
      }

      $tomorrow = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d") + 1, date("Y")));
      if ($params['frequency_unit'] == 'day') {
        $freqInterval = $params['frequency_interval'];
      }
      else if ($params['frequency_unit'] == 'week') {
        if ($params['frequency_interval']) {
          $interval = $params['frequency_interval'] * 7;
        }
        else {
          $interval = 7;
        }
        $freqInterval = $interval;
      }
      else if ($params['frequency_unit'] == 'month') {
        $d = date("d") + 1;
        $freqInterval = "0 0 0 " . $d . " * ?";
      }
      else if ($params['frequency_unit'] == 'year') {
        $d = date("d") + 1;
        $freqInterval = "0 0 0 " . $d . " " . date("m") . " ?";
      }

      $PHP_QBMSXML[2] = '<?xml version="1.0"?>
        <?qbmsxml version="4.5"?>
        <QBMSXML>
        <SignonMsgsRq>
         <SignonTicketRq>
          <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
          <SessionTicket>' . $PHP_SessionTicket . '</SessionTicket>
         </SignonTicketRq>
        </SignonMsgsRq>
        <QBMSXMLMsgsRq>
         <CustomerScheduledBillingAddRq>
           <CustomerID>Customer-' . $params['contactID'] . '</CustomerID>
           <WalletEntryID>' . $PHP_WalletId . '</WalletEntryID>
           <PaymentType>CreditCard</PaymentType>
           <Amount>' . $this->_getParam('amount') . '</Amount>
           <StartDate>' . $tomorrow . '</StartDate>
           <FrequencyExpression>' . $freqInterval . '</FrequencyExpression>
           <ScheduledBillingStatus>Active</ScheduledBillingStatus>
         </CustomerScheduledBillingAddRq>
        </QBMSXMLMsgsRq>
        </QBMSXML>';

      $response = $this->sendToIntuit($gatewayUrl, $PHP_QBMSXML[2], $this->_getParam('pemFile'));

      //Go ahead and get the session ticket
      //Find the location of the start tag
      $PHP_TempString = strstr($response, "<ScheduledBillingID>");
      $PHP_EndLocation = strpos($PHP_TempString, "</ScheduledBillingID>");
      $PHP_ScheduleBillId = substr($PHP_TempString, 20, $PHP_EndLocation - 20);

      $params['processor_id'] = $PHP_ScheduleBillId;
      $params['trxn_id'] = $PHP_ScheduleBillId;
      self::processRecurContribution($component = 'contribute', $params);
      $recur = new CRM_Contribute_DAO_ContributionRecur( );
      $recur->id = $params['contributionRecurID'];
      $recur->find(true);
      $subscriptionPaymentStatus = 'START';
      CRM_Contribute_BAO_ContributionPage::recurringNofify($subscriptionPaymentStatus, $params['contactID'], $params['contributionPageID'], $recur);
    }
    else {
      $PHP_QBMSXML[1] = '<?xml version="1.0" ?>
        <?qbmsxml version="4.5"?>
        <QBMSXML>
        <SignonMsgsRq>
        <SignonTicketRq>
        <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
        <SessionTicket>' . $PHP_SessionTicket . '</SessionTicket>
        </SignonTicketRq>
        </SignonMsgsRq>
        <QBMSXMLMsgsRq>
        <CustomerCreditCardChargeRq>
        <TransRequestID>' . $this->_getParam('invoiceID') . '</TransRequestID>
        <CreditCardNumber>' . $this->_getParam('credit_card_number') . '</CreditCardNumber>
        <ExpirationMonth>' . str_pad($this->_getParam('month'), 2, '0', STR_PAD_LEFT) . '</ExpirationMonth>
        <ExpirationYear>' . $this->_getParam('year') . '</ExpirationYear>
        <IsECommerce>true</IsECommerce>
        <Amount>' . $this->_getParam('amount') . '</Amount>
        <NameOnCard>' . $this->_getParam('billing_first_name') . ' ' . $this->_getParam('billing_last_name') . '</NameOnCard>
        <CreditCardAddress>' . $this->_getParam('street_address') . '</CreditCardAddress>
        <CreditCardPostalCode>' . $this->_getParam('postal_code') . '</CreditCardPostalCode>
        <CommercialCardCode></CommercialCardCode>
        <SalesTaxAmount>0.00</SalesTaxAmount>
        <CardSecurityCode>' . $this->_getParam('cvv2') . '</CardSecurityCode>
        </CustomerCreditCardChargeRq>
        </QBMSXMLMsgsRq>
        </QBMSXML>';

      // submit to intuit
      $response = $this->sendToIntuit($gatewayUrl, $PHP_QBMSXML[1], $this->_getParam('pemFile'));

      $xml = simplexml_load_string($response);
      if ($xml->QBMSXMLMsgsRs->CustomerCreditCardChargeRs['statusCode'] != "0") {
        return self::error($xml->QBMSXMLMsgsRs->CustomerCreditCardChargeRs['statusCode'], $xml->QBMSXMLMsgsRs->CustomerCreditCardChargeRs['statusMessage']);
      }

      $params['trxn_id'] = (string) $xml->QBMSXMLMsgsRs->CustomerCreditCardChargeRs->CreditCardTransID;
    }
    return $params;
  }

  /**
   * Get the value of a field if set
   *
   * @param string $field the field
   * @return mixed value of the field, or empty string if the field is
   * not set
   */
  function _getParam($field) {
    return CRM_Utils_Array::value($field, $this->_params, '');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   *
   */
  static function &singleton($mode, &$paymentProcessor) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === null) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_Intuit($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  function &error($errorCode = null, $errorMessage = null) {
    $e = & CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, null, $errorMessage);
    }
    else {
      $e->push(9001, 0, null, 'Unknowns System Error.');
    }
    return $e;
  }

  /**
   * Set a field to the specified value.  Value must be a scalar (int,
   * float, string, or boolean)
   *
   * @param string $field
   * @param mixed $value
   * @return bool false if value is not a scalar, true if successful
   */
  function _setParam($field, $value) {
    if (!is_scalar($value)) {
      return false;
    }
    else {
      $this->_params[$field] = $value;
    }
  }

  /**
   * sendToIntuit function used to send the params to intuit and get the response
   */
  function sendToIntuit($url, $parameters, $pemFile) {
    $server = $url;
    $PHP_QBMSXML = $parameters;
    $result = null;
    if (function_exists('curl_init')) {
      $PHP_Header[] = "Content-type: application/x-qbmsxml";
      $PHP_Header[] = "Content-length: " . strlen($PHP_QBMSXML);

      $submit = curl_init();
      curl_setopt($submit, CURLOPT_POST, 1);
      curl_setopt($submit, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($submit, CURLOPT_CUSTOMREQUEST, 'POST');
      curl_setopt($submit, CURLOPT_URL, $server);
      curl_setopt($submit, CURLOPT_TIMEOUT, 10);
      curl_setopt($submit, CURLOPT_HTTPHEADER, $PHP_Header);
      curl_setopt($submit, CURLOPT_POSTFIELDS, $PHP_QBMSXML);
      curl_setopt($submit, CURLOPT_VERBOSE, 1);
      curl_setopt($submit, CURLOPT_SSL_VERIFYPEER, 1);
      curl_setopt($submit, CURLOPT_SSLCERT, $pemFile);
      curl_setopt($submit, CURLOPT_SSL_VERIFYPEER, false);
      $response = curl_exec($submit);
      if (!$response) {
        return self::error(curl_errno($submit), curl_error($submit));
      }
      curl_close($submit);
    }
    return $response;
  }

  /**
   * processRecurContribution function to make the transaction entry and financial transaction entry
   */
  function processRecurContribution($component = 'contribute', $params) {
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();
    $completed['contribution_status_id'] = array_search('Completed', $contributionStatus);

    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution', $params['contributionID'], 'trxn_id', $params['trxn_id']);
    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution', $params['contributionID'], 'contribution_status_id', $completed);

    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur', $params['contributionRecurID'], 'processor_id', $params['processor_id']);
    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur', $params['contributionRecurID'], 'invoice_id', $params['invoice_id']);
    $trxnParams = array(
      'contribution_id' => $params['contributionID'],
      'trxn_date' => date('YmdHis'),
      'trxn_type' => 'Debit',
      'total_amount' => $params['amount'],
      'fee_amount' => CRM_Utils_Array::value('fee_amount', $params['trxn_id']),
      'net_amount' => CRM_Utils_Array::value('net_amount', $params['trxn_id'], $params['amount']),
      'currency' => $params['currencyID'],
      'payment_processor' => $this->_paymentProcessor['name'],
      'trxn_id' => $params['trxn_id'],
      'trxn_result_code' => NULL,
    );
    $trxn = & CRM_Core_BAO_FinancialTrxn::create($trxnParams);
  }

  public function handlePaymentCron() {

    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus();

    $isTest = trim(CRM_Utils_Array::value('is_test', $_REQUEST));

    if (!$isTest) {
      $isTest = 0;
      $pemFile = '/tmp/pems/intuit.pem';
    }
    else {
      $pemFile = '/tmp/pems/intuit-test.pem';
    }

    $processor_info = array('class_name' => 'Payment_Intuit',
      'is_test' => $isTest);
    CRM_Core_BAO_PaymentProcessor::retrieve($processor_info, $defaults);
    $AppLogin = $defaults['user_name'];
    $connTckt = $defaults['password'];
    $AppID = $defaults['signature'];
    $gatewayUrl = $appUrl = $defaults['url_site'];

    $today = date('Y-m-d');
    $recurContribution = "
    SELECT  recur.id, recur.frequency_unit, recur.frequency_interval, recur.payment_instrument_id,recur.contribution_type_id,recur.contribution_status_id,
            recur.installments, recur.start_date, recur.trxn_id, recur.processor_id, recur.invoice_id, recur.cancel_date, recur.amount,
            count( contri.contribution_recur_id ) as count_id
      FROM  civicrm_contribution contri
INNER JOIN  civicrm_contribution_recur recur ON ( recur.id = contri.contribution_recur_id
       AND  recur.is_test = %1
       AND  contri.contribution_status_id = 1
       AND  recur.payment_processor_id = %2 )
  GROUP BY  contri.contribution_recur_id";

    $queryParams = array(1 => array($isTest, 'Integer'),
      2 => array($defaults['id'], 'Integer'));

    $recurResult = CRM_Core_DAO::executeQuery($recurContribution, $queryParams);
    $i = 0;

    $PHP_QBMSXML[0] = '<?xml version="1.0" ?>
        <?qbmsxml version="4.5"?>
        <QBMSXML>
        <SignonMsgsRq>
        <SignonAppCertRq>
        <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
        <ApplicationLogin>' . $AppLogin . '</ApplicationLogin>
        <ConnectionTicket>' . $connTckt . '</ConnectionTicket>
        </SignonAppCertRq>
        </SignonMsgsRq>
        </QBMSXML>';

    // submit to intuit
    $response = self::sendToIntuit($gatewayUrl, $PHP_QBMSXML[0], $pemFile);

    //Go ahead and get the session ticket
    //Find the location of the start tag
    $PHP_TempString = strstr($response, "<SessionTicket>");
    $PHP_EndLocation = strpos($PHP_TempString, "</SessionTicket>");
    $PHP_SessionTicket = substr($PHP_TempString, 15, $PHP_EndLocation - 15);

    while ($recurResult->fetch()) {
      $firstParams = self::getParams($recurResult->trxn_id);
      $nCount = $recurResult->count_id;
      $freqInstall = $recurResult->installments;

      $i++;
      $details = array();
      $address = array();
      $todays_date = date("Y-m-d");
      $today = strtotime($todays_date);
      $expiration_date = strtotime($recurResult->cancel_date);
      if ((($recurResult->contribution_status_id == 3) && isset($expiration_date) && ($expiration_date == $today)) || ($freqInstall == $nCount && $recurResult->contribution_status_id == 2)) {
        $wallet[0] = $recurResult->invoice_id;

        if (strstr($recurResult->invoice_id, '-')) {
          $wallet = explode('-', $recurResult->invoice_id);
        }
        else {
          $wallet[0] = $recurResult->invoice_id;
        }
        $PHP_QBMSXML[1] = '<?xml version="1.0"?>
        <?qbmsxml version="4.5"?>
        <SignonMsgsRq>
        <SignonTicketRq>
        <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
        <SessionTicket>' . $PHP_SessionTicket . '</SessionTicket>
        </SignonTicketRq>
        </SignonMsgsRq>
        <QBMSXML>
        <QBMSXMLMsgsRq>
        <CustomerCreditCardWalletDelRq>
        <WalletEntryID >' . $wallet[0] . '</WalletEntryID>
        <CustomerID >Customer-' . $firstParams['contact_id'] . '</CustomerID>
        </CustomerCreditCardWalletDelRq>
        <CustomerScheduledBillingDelRq>
        <ScheduledBillingID >' . $recurResult->processor_id . '</ScheduledBillingID>
        <CustomerID >Customer-' . $firstParams['contact_id'] . '</CustomerID>
        </CustomerScheduledBillingDelRq>
        </QBMSXMLMsgsRq>
        </QBMSXML>';

        $response = self::sendToIntuit($gatewayUrl, $PHP_QBMSXML[1], $pemFile);
        $xml = simplexml_load_string($response);

        $recurResult1 = CRM_Contribute_BAO_ContributionRecur::getRecurContributions($contriParams['contact_id']);
        $array = $recurResult1;

        foreach ($array as $key => $value) {
          $id = $value['id'];
        }
        foreach ($array[$id] as $key => $value) {
          $object->$key = $value;
        }
        if ($recurResult->contribution_status_id == 3) {
          $completed['contribution_status_id'] = array_search('Cancelled', $contributionStatus);
        }
        elseif ($recurResult->contribution_status_id == 2) {
          $completed['contribution_status_id'] = array_search('Completed', $contributionStatus);
        }
        CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur', $recurResult->id, 'contribution_status_id', $completed);
        CRM_Contribute_BAO_ContributionPage::recurringNofify(CRM_Core_Payment::RECURRING_PAYMENT_END, $contriParams['contact_id'], $contriParams['contribution_page_id'], $object, false);
      }
      elseif (($freqInstall > $nCount) && ($recurResult->contribution_status_id == 2)) {
        $PHP_QBMSXML[1] = '<?xml version="1.0"?>
        <?qbmsxml version="4.5"?>
        <QBMSXML>
        <SignonMsgsRq>
        <SignonTicketRq>
        <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
        <SessionTicket>' . $PHP_SessionTicket . '</SessionTicket>
        </SignonTicketRq>
        </SignonMsgsRq>
        <QBMSXMLMsgsRq>
        <CustomerScheduledBillingPaymentQueryRq>
         <LogicalExpr1>
         <LogicalOp >And</LogicalOp>
         <RelativeExpr>
         <RelativeOp >Equals</RelativeOp>
         <Name>ScheduledBillingID</Name>
         <Value>' . $recurResult->processor_id . '</Value>
         </RelativeExpr>
         </LogicalExpr1>
        </CustomerScheduledBillingPaymentQueryRq>
        </QBMSXMLMsgsRq>
        </QBMSXML>';

        $response = self::sendToIntuit($gatewayUrl, $PHP_QBMSXML[1], $pemFile);
        $xml = simplexml_load_string($response);
        $recurXml = get_object_vars($xml->QBMSXMLMsgsRs->CustomerScheduledBillingPaymentQueryRs);
        $recurPayments = $recurXml['ScheduledBillingPayment'];

        if (is_array($recurXml['ScheduledBillingPayment'])) {
          foreach ($recurPayments as $recurKey) {
            $contri['contact_id'] = $contriParams['contact_id'] = $firstParams['contact_id'];
            $contriParams['total_amount'] = $recurKey->Amount;
            $contriParams['address_id'] = $firstParams['address_id'];
            $contriParams['contribution_source'] = $firstParams['contribution_source'];
            $contriParams['source'] = $firstParams['source'];
            $contriParams['contribution_type_id'] = $firstParams['contribution_type_id'];
            $contriParams['contribution_page_id'] = $firstParams['contribution_page_id'];
            $contriParams['payment_instrument_id'] = $firstParams['payment_instrument_id'];
            $contriParams['contribution_recur_id'] = $firstParams['contribution_recur_id'];
            if ($recurKey->ResultStatusCode == 0) {
              $contriParams['contribution_status_id'] = array_search('Completed', $contributionStatus);
            }
            else {
              $contriParams['contribution_status_id'] = array_search('Failed', $contributionStatus);
              CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur', $recurResult->id, 'contribution_status_id', $completed);
            }
            $contriParams['currency'] = $firstParams['currency'];
            $contriParams['is_test'] = $firstParams['is_test'];
            $contriParams['receive_date'] = $recurKey->PaymentDate;
            $contri['trxn_id'] = $contriParams['trxn_id'] = $recurKey->ScheduledBillingPaymentID;
            $contribution = CRM_Contribute_BAO_Contribution::retrieve($contri, $null);
            if (!$contribution->id) {
              $contribution = CRM_Contribute_BAO_Contribution::create($contriParams, $null);
              $trxnParams = array(
                'contribution_id' => $contribution->id,
                'trxn_date' => $contriParams['receive_date'],
                'trxn_type' => 'Debit',
                'total_amount' => $contribution->total_amount,
                'fee_amount' => CRM_Utils_Array::value('fee_amount', $contriParams['trxn_id']),
                'net_amount' => CRM_Utils_Array::value('net_amount', $contriParams['trxn_id'], $contriParams['total_amount']),
                'currency' => $contribution->currency,
                'payment_processor' => $defaults['name'],
                'trxn_id' => $contriparams['trxn_id'],
                'trxn_result_code' => NULL,
              );
              $trxn = & CRM_Core_BAO_FinancialTrxn::create($trxnParams);
            }
            if (( date('Y-m-d', strtotime($count, strtotime($recurKey->PaymentDate))) == $today) && ($recurKey->ResultStatusCode == 0) && ($contribution->thankyou_date == NULL)) {
              self::contribution_receipt($contriParams, $firstParams, $contribution->id);
            }
          }
        }
        elseif (isset($recurPayments) && !empty($recurPayments)) {
          $contriParams['contact_id'] = $firstParams['contact_id'];
          $contriParams['total_amount'] = $recurPayments->Amount;
          $contriParams['address_id'] = $firstParams['address_id'];
          $contriParams['contribution_source'] = $firstParams['contribution_source'];
          $contriParams['source'] = $firstParams['source'];
          $contriParams['contribution_type_id'] = $firstParams['contribution_type_id'];
          $contriParams['contribution_page_id'] = $firstParams['contribution_page_id'];
          $contriParams['payment_instrument_id'] = $firstParams['payment_instrument_id'];
          $contriParams['contribution_recur_id'] = $firstParams['contribution_recur_id'];
          $contriParams['currency'] = $firstParams['currency'];
          $contriParams['is_test'] = $firstParams['is_test'];
          $contribution = CRM_Contribute_BAO_Contribution::retrieve($contriParams, $null);
          $nCount = 0;
          if (!( date('Y-m-d', strtotime($count, strtotime($recurKey->PaymentDate))) <= $today)) {

            CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution', $contribution->id, 'receive_date', $recurPayments->PaymentDate);
            CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution', $contribution->id, 'contribution_status_id', $recurVal['ResultStatusCode']);
            CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution', $contribution->id, 'trxn_id', $recurPayments->ScheduledBillingPaymentID);
            self::contribution_receipt($contriParams, $firstParams, $contribution->id);
          }
        }
        if ($freqInstall == $nCount + 1) {

          $PHP_QBMSXML[2] = '<?xml version="1.0"?>
        <?qbmsxml version="4.5"?>
        <QBMSXML>
        <SignonMsgsRq>
        <SignonTicketRq>
        <ClientDateTime>' . date('Y-m-d\TH:i:s') . '</ClientDateTime>
        <SessionTicket>' . $PHP_SessionTicket . '</SessionTicket>
        </SignonTicketRq>
        </SignonMsgsRq>
        <QBMSXMLMsgsRq>
        <CustomerScheduledBillingQueryRq>
        <LogicalExpr1>
        <LogicalOp >And</LogicalOp>
        <RelativeExpr>
        <RelativeOp >Equals</RelativeOp>
        <Name>ScheduledBillingID</Name> <!-- required -->
        <Value>' . $recurResult->processor_id . '</Value> <!-- required -->
        </RelativeExpr>
        </LogicalExpr1>
        </CustomerScheduledBillingQueryRq>
        <CustomerScheduledBillingDelRq>
        <ScheduledBillingID >' . $recurResult->processor_id . '</ScheduledBillingID>
        <CustomerID >Customer-' . $firstParams['contact_id'] . '</CustomerID>
        </CustomerScheduledBillingDelRq>
        </QBMSXMLMsgsRq>
        </QBMSXML>';
          $response = self::sendToIntuit($gatewayUrl, $PHP_QBMSXML[2], $pemFile);
          $xml = simplexml_load_string($response);

          $recurResult1 = CRM_Contribute_BAO_ContributionRecur::getRecurContributions($contriParams['contact_id']);
          $array = $recurResult1;
          $object = new StdClass();

          foreach ($array as $key => $value) {
            $id = $value['id'];
          }
          foreach ($array[$id] as $key => $value) {
            $object->$key = $value;
          }

          $completed['contribution_status_id'] = array_search('Completed', $contributionStatus);
          CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur', $recurResult->id, 'contribution_status_id', $completed);

          CRM_Contribute_BAO_ContributionPage::recurringNofify(CRM_Core_Payment::RECURRING_PAYMENT_END, $firstParams['contact_id'], $firstParams['contribution_page_id'], $object, false);
        }
      }
    }
  }

  function getParams($trid) {
    CRM_Core_DAO::commonRetrieveAll('CRM_Contribute_DAO_Contribution', 'invoice_id', $trid, $details, NULL);

    foreach ($details as $key => $value) {

      $params = array('contact_id' => $value['contact_id'],
        'contribution_type_id' => $value['contribution_type_id'],
        'contribution_page_id' => $value['contribution_page_id'],
        'payment_instrument_id' => $value['payment_instrument_id'],
        'contribution_recur_id' => $value['contribution_recur_id'],
        'total_amount' => $value['total_amount'],
        'trxn_id' => $value['trxn_id'],
        'address_id' => $value['address_id'],
        'source' => $value['source'],
        'contribution_source' => $value['contribution_source'],
        'non_deductible_amount' => $value['non_deductible_amount'],
        'contribution_page_id' => $value['contribution_page_id'],
        'currency' => $value['currency'],
        'is_test' => $value['is_test'],
        'is_pay_later' => $value['is_pay_later'],);
    }
    return $params;
  }

  function contribution_receipt($contriParams, $firstParams, $contributionid) {

    CRM_Core_DAO::commonRetrieveAll('CRM_Contribute_DAO_ContributionPage', 'id', $contriParams['contribution_page_id'], $getInfo, NULL);
    foreach ($getInfo as $Key => $values) {
      unset($values['recur_frequency_unit']);
    }
    CRM_Core_OptionGroup::getAssoc("civicrm_contribution_page.amount.{$contriParams['contribution_page_id']}", $values['amount'], true);
    $values['contribution_id'] = $contributionid;
    $values['custom_post_id'] = 1;
    $values['custom_pre_id'] = NULL;
    $values['accountingCode'] = NULL;
    $values['footer_text'] = NULL;
    $values['membership_id'] = NULL;

    $billingName = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Address', $firstParams['address_id'], 'name');
    $billingName = str_replace(CRM_Core_DAO::VALUE_SEPARATOR, ' ', $billingName);
    CRM_Core_DAO::commonRetrieveAll('CRM_Core_DAO_Address', 'id', $firstParams['address_id'], $address, NULL);

    if (!empty($address[$firstParams['address_id']]['state_province_id'])) {
      $state_province = CRM_Core_PseudoConstant::stateProvinceAbbreviation($address[$firstParams['address_id']]['state_province_id'], false);
    }

    if (!empty($address[$firstParams['address_id']]['country_id'])) {
      $country = CRM_Core_PseudoConstant::country($address[$firstParams['address_id']]['country_id']);
    }

    $billingAddress = $address[$firstParams['address_id']]['street_address'] . "\n" . $address[$firstParams['address_id']]['city'] . ", " . $state_province . " " . $address[$firstParams['address_id']]['postal_code'] . "\n" . $country . "\n";

    $trxnid = $contriParams['trxn_id'];
    $smarty = & CRM_Core_Smarty::singleton();
    foreach ($values['amount'] as $k => $val) {
      $smarty->assign('amount', $val['value']);
    }
    $smarty->assign('address', $billingAddress);
    $smarty->assign('title', $values['title']);
    $smarty->assign('receive_date', $contriParams['receive_date']);
    $smarty->assign('trxn_id', $trxnid['0']);
    $smarty->assign('billingName', $billingName);
    $smarty->assign('is_monetary', $values['is_monetary']);

    CRM_Contribute_BAO_ContributionPage::sendMail($contriParams['contact_id'], $values, $isTest, $returnMessageText = false);
    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution', $contributionid, 'thankyou_date', $today);
  }

}
