<?php

require_once 'CRM/Core/Payment/BaseIPN.php';
 
class com_veritrans_payment_vtwebIPN extends CRM_Core_Payment_BaseIPN {
 
    static protected $_notification_obj ;
    static protected $_paymentProcessor ;

    /**
     * We only need one instance of this object. So we use the singleton
     * pattern and cache the instance in this variable
     *
     * @var object
     * @static
     */
    static private $_singleton = null;
 
    /**
     * mode of operation: live or test
     *
     * @var object
     * @static
     */
    static protected $_mode = null;
 
    static function retrieve( $name, $type, $object, $abort = true ) {
      $value = CRM_Utils_Array::value($name, $object);
      if ($abort && $value === null) {
        CRM_Core_Error::debug_log_message("Could not find an entry for $name");
        echo "Failure: Missing Parameter";
        exit();
      }
 
      if ($value) {
        if (!CRM_Utils_Type::validate($value, $type)) {
          CRM_Core_Error::debug_log_message("Could not find a valid entry for $name");
          echo "Failure: Invalid Parameter";
          exit();
        }
      }
 
      return $value;
    }
 
 
    /**
     * Constructor
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    public function __construct($notif_obj, &$paymentProcessor) {
      parent::__construct();
      
      $this->_notification_obj = $notif_obj;
      $this->_paymentProcessor = $paymentProcessor;
    }
 
    /**
     * Unused Constructor TODO remove this
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    // function __construct($mode, &$paymentProcessor) {
    //   parent::__construct();
 
    //   $this->_mode = $mode;
    //   $this->_paymentProcessor = $paymentProcessor;
    // }
 
    /**
     * The function gets called when a new order takes place.
     *
     * @param xml   $dataRoot    response send by google in xml format
     * @param array $privateData contains the name value pair of <merchant-private-data>
     *
     * @return void
     *
     */
    function newOrderNotify( $status, $privateData, $component, $amount, $transactionReference ) {
        $ids = $input = $params = array( );
 
        $input['component'] = strtolower($component);
 
        $ids['contact']          = self::retrieve( 'contactID'     , 'Integer', $privateData, true );
        $ids['contribution']     = self::retrieve( 'contributionID', 'Integer', $privateData, true );
 
        if ( $input['component'] == "event" ) {
            $ids['event']       = self::retrieve( 'eventID'      , 'Integer', $privateData, true );
            $ids['participant'] = self::retrieve( 'participantID', 'Integer', $privateData, true );
            $ids['membership']  = null;
        } else {
            $ids['membership'] = self::retrieve( 'membershipID'  , 'Integer', $privateData, false );
        }
        $ids['contributionRecur'] = $ids['contributionPage'] = null;
 
        if ( ! $this->validateData( $input, $ids, $objects ) ) {
            return false;
        }
 
        // make sure the invoice is valid and matches what we have in the contribution record
        $input['invoice']    =  $privateData['invoiceID'];
        $input['newInvoice'] =  $transactionReference;
        $contribution        =& $objects['contribution'];
        $input['trxn_id']  =    $transactionReference;
 
        if ( $contribution->invoice_id != $input['invoice'] ) {
            CRM_Core_Error::debug_log_message( "Invoice values dont match between database and IPN request" );
            echo "Failure: Invoice values dont match between database and IPN request";
            return;
        }
 
        // lets replace invoice-id with Payment Processor -number because thats what is common and unique
        // in subsequent calls or notifications sent by google.
        $contribution->invoice_id = $input['newInvoice'];
 
        $input['amount'] = $amount;
 
        if ( $contribution->total_amount != $input['amount'] ) {
            CRM_Core_Error::debug_log_message( "Amount values dont match between database and IPN request" );
            echo "Failure: Amount values dont match between database and IPN request. ".$contribution->total_amount." / ".$input['amount']."";
            return;
        }
 
        require_once 'CRM/Core/Transaction.php';
        $transaction = new CRM_Core_Transaction( );
 
        // check if contribution is already completed, if so we ignore this ipn
 
        if ( $contribution->contribution_status_id == 1 ) {
            CRM_Core_Error::debug_log_message( "returning since contribution has already been handled" );
            echo "Success: Contribution has already been handled";
            return true;
        } else {
            /* Since trxn_id hasn't got any use here,
             * lets make use of it by passing the eventID/membershipTypeID to next level.
             * And change trxn_id to the payment processor reference before finishing db update */
            if ( $ids['event'] ) {
                $contribution->trxn_id = $ids['event'].CRM_Core_DAO::VALUE_SEPARATOR.$ids['participant'] ;
            } else {
                $contribution->trxn_id = $ids['membership'];
            }
        }
        if ($status == "success")
          $this->completeTransaction ( $input, $ids, $objects, $transaction);
        else if ($status == "pending")
          $this->pending ($objects, $transaction);
        else if ($status == "fail")
          $this->failed ($objects, $transaction);

        // bypassing!
        // require_once 'CRM/Contribute/BAO/Contribution.php';
        // CRM_Contribute_BAO_Contribution::completeOrder($input, $ids, $objects, $transaction, $recur = false, $contribution, $isRecurring = false, $isFirstOrLastRecurringPayment = false);

        return true;
    }
 
 
    /**
     * singleton function used to manage this object
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return object
     * @static
     */
    static function &singleton( $mode, $component, &$paymentProcessor ) {
        if ( self::$_singleton === null ) {
            self::$_singleton = new com_veritrans_payment_vtwebIPN( $mode, $paymentProcessor );
        }
        return self::$_singleton;
    }
 
    /**
     * The function returns the component(Event/Contribute..)and whether it is Test or not
     *
     * @param array   $privateData    contains the name-value pairs of transaction related data
     *
     * @return array context of this call (test, component, payment processor id)
     * @static
     */
    static function getContext($privateData)    {
      require_once 'CRM/Contribute/DAO/Contribution.php';
 
      $component = null;
      $isTest = null;
 
      $contributionID = $privateData['contributionID'];
      $contribution = new CRM_Contribute_DAO_Contribution();
      $contribution->id = $contributionID;
 
      if (!$contribution->find(true)) {
        CRM_Core_Error::debug_log_message("Could not find contribution record: $contributionID");
        echo "Failure: Could not find contribution record for $contributionID";
        exit();
      }
 
      if (stristr($contribution->source, 'Online Contribution')) {
        $component = 'contribute';
      }
      elseif (stristr($contribution->source, 'Online Event Registration')) {
        $component = 'event';
      }
      $isTest = $contribution->is_test;

      // error_log(" ======== Contribution ======= "); // debugan
      // error_log(print_r($contribution,true)); // debugan
      // error_log(" ======== Component ======= "); // debugan
      // error_log($component); // debugan
 
      $duplicateTransaction = 0;
      if ($contribution->contribution_status_id == 1) {
        //contribution already handled. (some processors do two notifications so this could be valid)
        $duplicateTransaction = 1;
      }
 
      if ($component == 'contribute') {
        if (!$contribution->contribution_page_id) {
          CRM_Core_Error::debug_log_message("Could not find contribution page for contribution record: $contributionID");
          echo "Failure: Could not find contribution page for contribution record: $contributionID";
          exit();
        }
 
        // get the payment processor id from contribution page
        $paymentProcessorID = $privateData['paymentProcessorID'];
        // $paymentProcessorID = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionPage', $contribution->contribution_page_id, 'payment_processor_id'); // replaced
      }
      else {
        $eventID = $privateData['eventID'];
 
        if (!$eventID) {
          CRM_Core_Error::debug_log_message("Could not find event ID");
          echo "Failure: Could not find eventID";
          exit();
        }
 
        // we are in event mode
        // make sure event exists and is valid
        require_once 'CRM/Event/DAO/Event.php';
        $event = new CRM_Event_DAO_Event();
        $event->id = $eventID;
        if (!$event->find(true)) {
          CRM_Core_Error::debug_log_message("Could not find event: $eventID");
          echo "Failure: Could not find event: $eventID";
          exit();
        }
        // error_log("Event object found: ".print_r($event,true)); // debug
        // get the payment processor id from contribution page
        $paymentProcessorID = $event->payment_processor;
      }
 
      if (!$paymentProcessorID) {
        CRM_Core_Error::debug_log_message("Could not find payment processor for event record: $eventID");
        echo "Failure: Could not find payment processor for event record: $eventID";
        exit();
      }
      return array($isTest, $component, $paymentProcessorID, $duplicateTransaction);
    }
 
 
    /**
     * This method is handles the response that will be invoked (from UCMercedPaymentCollectionNotify.php) every time
     * a notification or request is sent by the UCM Payment Collection Server.
     *
     */
    function main() {
      $config = CRM_Core_Config::singleton();
       
      // Make sure there are POST params.
      if ( empty($this->_notification_obj) ) {
        CRM_Core_Error::debug_log_message("No notification object created, probably no HTTP POST request received");
        echo "No notification object created, probably no HTTP POST request received";
        exit();
      }
      else {
        $notification = $this->_notification_obj; // TODO implement get status instead of extracting raw notif obj

        error_log("notification object : ".print_r($notification,true)); // debug

        // Get cached txn details array from cache DB
        $vtCache = CRM_Core_BAO_Cache::getItem('com.veritrans.payment.vtweb',"Veritrans_orderID_{$notification->order_id}", null);
        // CRM_Core_BAO_Cache::deleteGroup(NULL,"Veritrans_orderID_{$veritrans_orderId}"); // TODO when to clear cache (settlement, expire)

        // check signature key to determine if this is a valid Veritrans notification sent by Veritrans
        if ( hash('sha512', $notification->order_id.$notification->status_code.$notification->gross_amount.$vtCache['user_name'])!=$notification->signature_key ){
          CRM_Core_Error::debug_log_message("HTTP POST request verification failed, signature does not match with verification algorithm");
          echo "HTTP POST request verification failed, signature does not match with verification algorithm";
          exit; return;
        } else { // Close connection immediately to save time, then continue the script
          ob_start();
          echo "Valid notification received, script will continue"; // send the response
          header('Connection: close');
          header('Content-Length: '.ob_get_length());
          ob_end_flush();
          ob_flush();
          flush();
        }

        // It is important that $privateData contains these exact keys.
        // Otherwise getContext may fail.
        $privateData['invoiceID']          = $vtCache['invoiceID'];
        $privateData['qfKey']              = $vtCache['qfKey'];
        $privateData['contactID']          = $vtCache['contactID'];
        $privateData['contributionID']     = $vtCache['contributionID'];
        $privateData['contributionTypeID'] = $vtCache['contributionTypeID'];
        $privateData['eventID']            = $vtCache['eventID'];
        $privateData['participantID']      = $vtCache['participantID'];
        $privateData['membershipID']       = $vtCache['membershipID'];
        $privateData['paymentProcessorID'] = $this->_paymentProcessor['id']; // additonal
        $amount                            = $notification->gross_amount;

        error_log("private data array : ".print_r($privateData,true)); // debug
       
        list($mode, $component, $paymentProcessorID, $duplicateTransaction) = self::getContext($privateData);
        $mode = $mode ? 'test' : 'live';
       
        error_log("_paymentProcessor['id'] : ".$this->_paymentProcessor['id']); // debug
        
        $paymentProcessor = CRM_Financial_BAO_PaymentProcessor::getPayment($this->_paymentProcessor['id'], $mode);
        $ipn=& self::singleton( $mode, $component, $paymentProcessor );
       
        $success = TRUE; // TODO set with real value
        if ($duplicateTransaction == 0) {

          // TODO implement notification check logic
          if ($notification->transaction_status == 'capture') {
            if ($notification->fraud_status == 'accept') {
              $this->newOrderNotify("success", $privateData, $component, $amount, $privateData['invoiceID']); // Process the transaction.
              echo $notification->transaction_status."-accept notification received for order_id: ".$privateData['invoiceID'];
            }
            else if ($notification->fraud_status == 'challenge') {
              // update to pending waiting for action
              $this->newOrderNotify("pending", $privateData, $component, $amount, $privateData['invoiceID']); // Process the transaction.
              echo $notification->transaction_status."-challange notification received for order_id: ".$privateData['invoiceID'];
            }
          }
          else if ($notification->transaction_status == 'cancel') {
            // update to fail
            $this->newOrderNotify("fail", $privateData, $component, $amount, $privateData['invoiceID']); // Process the transaction.
            CRM_Core_BAO_Cache::deleteGroup(NULL,"Veritrans_orderID_{$veritrans_orderId}"); // delete from cache table
            echo $notification->transaction_status." notification received for order_id: ".$privateData['invoiceID'];
          }
          else if ($notification->transaction_status == 'expire') {
            // update to fail
            $this->newOrderNotify("fail", $privateData, $component, $amount, $privateData['invoiceID']); // Process the transaction.
            CRM_Core_BAO_Cache::deleteGroup(NULL,"Veritrans_orderID_{$veritrans_orderId}"); // delete from cache table
            echo $notification->transaction_status." notification received for order_id: ".$privateData['invoiceID'];
          }
          else if ($notification->transaction_status == 'deny') {
            // update to fail
            $this->newOrderNotify("fail", $privateData, $component, $amount, $privateData['invoiceID']); // Process the transaction.
            CRM_Core_BAO_Cache::deleteGroup(NULL,"Veritrans_orderID_{$veritrans_orderId}"); // delete from cache table
            echo $notification->transaction_status." notification received for order_id: ".$privateData['invoiceID'];
          }
          else if ($notification->transaction_status == 'settlement') {
            if($notification->payment_type != 'credit_card'){
              $this->newOrderNotify("success", $privateData, $component, $amount, $privateData['invoiceID']); // Process the transaction.
            } else{
              // delete from cache table
            }
            CRM_Core_BAO_Cache::deleteGroup(NULL,"Veritrans_orderID_{$veritrans_orderId}"); // delete from cache table
            echo $notification->transaction_status." notification received for order_id: ".$privateData['invoiceID'];
          }
          else if ($notification->transaction_status == 'pending') {
            // update to pending or do nothing
            echo $notification->transaction_status." notification received for order_id: ".$privateData['invoiceID'];
            $this->newOrderNotify("pending", $privateData, $component, $amount, $privateData['invoiceID']); // Process the transaction.
          }
        }
      exit;
      
      }       
    
    }


}