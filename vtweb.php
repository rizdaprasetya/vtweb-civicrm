<?php
/**
 * ### Veritrans Payment Plugin for CiviCRM ###
 *
 * This plugin allow your CiviCRM to accept payment from customer using Veritrans Payment Gateway solution.
 *
 * @category   CiviCRM Payment Plugin
 * @author     Rizda Dwi Prasetya <rizda.prasetya@veritrans.co.id>
 * @version    1.0
 * @link       http://docs.veritrans.co.id
 * (This plugin is made based on Payment Plugin Template by CiviCRM)
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// require_once(dirname(__FILE__).'../../../../plugins/civicrm/civicrm/CRM/Core/Payment.php');
require_once('CRM/Core/Payment.php');

// Import Veritrans Library TODO activate this!
// require_once(dirname(__FILE__) . '/veritrans/Veritrans.php');
// require_once(dirname(__FILE__) . '/veritrans/Veritrans/Notification.php');
// require_once(dirname(__FILE__) . '/veritrans/Veritrans/Transaction.php');

class com_veritrans_payment_vtweb extends CRM_Core_Payment
{
    
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
    
    /**
     * Constructor
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    function __construct($mode, &$paymentProcessor)
    {
    	// error_log("++ class com_veritrans_payment_vtweb constructed"); // debugan
        $this->_mode             = $mode;
        $this->_paymentProcessor = $paymentProcessor;
        $this->_processorName    = ts('Veritrans'); 
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
    static function &singleton($mode, &$paymentProcessor)
    {
        $processorName = $paymentProcessor['name'];
        if (self::$_singleton[$processorName] === null) {
            self::$_singleton[$processorName] = new com_veritrans_payment_vtweb($mode, $paymentProcessor);   
        }
        returnself::$_singleton[$processorName];
    }
    
    
    /**
     * This function checks to see if we have the right config values
     *
     * @return string the error message if any
     * @public
     */
    function checkConfig()
    {
        $config = CRM_Core_Config::singleton();
        $error  = array();
        if (empty($this->_paymentProcessor['user_name'])) {
            $error[] = ts('The "Veritrans Server Key" is not set in the Administer CiviCRM Payment Processor.');   
        }
        if (!empty($error)) {
            return implode('<p>', $error);   
        }
        else {
            return NULL;
        }
    }
    
    function doDirectPayment(&$params)
    {
        CRM_Core_Error::fatal(ts('This function is not implemented'));   
    }
    
    /**
     * Sets appropriate parameters for checking out to UCM Payment Collection
     *
     * @param array $params name value pair of contribution datat
     *
     * @return void
     * @access public
     * 
     */
    function doTransferCheckout(&$params, $component)
    {
    	// error_log(print_r($params,true)); // debug
        // Start building our paramaters.
        // We get this from the user_name field even though in our info.xml file we specified it was called "Purchase Item ID"
        // Also assign to array that will be stored to DB cache
		$vtCache['user_name']      = $VeritransServerKey = $this->_paymentProcessor['user_name'];
        $vtCache['invoiceID']      = $transactionOrderId = $params['invoiceID'];
        $vtCache['qfKey']          = $qfKey              = $params['qfKey'];
        $vtCache['contactID']      = $contactID          = $params['contactID'];
        $vtCache['contributionID'] = $contributionID     = $params['contributionID'];
        $vtCache['contributionTypeID'] = $contributionTypeID = $params['contributionTypeID'];
        $vtCache['eventID']        = $eventID            = $params['eventID'];
        $vtCache['participantID']  = $participantID      = $params['participantID'];
        $vtCache['membershipID']   = $membershipID       = $params['membershipID'];
        $vtCache['amount']         = $transactionAmount  = ceil($params['amount']); // round up the value
        $vtCache['description']    = $description        = $params['description'];

        // Store array vtCache to DB cache
        CRM_Core_BAO_Cache::setItem($vtCache, 'com.veritrans.payment.vtweb',"Veritrans_orderID_{$transactionOrderId}", null);

        if (isset($params['last_name'])) {
            $custLastName = $params['last_name'];    
        }
        
        if (isset($params['first_name'])) {
            $custFirstName = $params['first_name'];
        }
        
        if (isset($params['street_address-1'])) {
            $custAddress = $params['street_address-1'];
        }
        
        if (isset($params['city-1'])) {
            $custCity = $params['city-1'];
        }
        
        if (isset($params['state_province-1'])) {
            $custProvince = CRM_Core_PseudoConstant::stateProvinceAbbreviation($params['state_province-1']);
        }
        
        if (isset($params['postal_code-1'])) {
            $custPostalCode = $params['postal_code-1'];
        }
        
        if (isset($params['email-5'])) {
            $custBillingEmail = $params['email-5'];
        }
        
        $custPhone   = "0"; // TODO set with real value
        $custEmail   = $params['email-Primary']; // set with real value
        if (isset($params['email'])) {
        	$custEmail = $params['email'];
        }
        $custCountry = "IDN"; // TODO set with real value
        
        // Allow further manipulation of the arguments via custom hooks ..
        CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $vtwebParams);
        
        // set serverkey, isporduction, & 3DS
        $veritrans                      = new Veritrans_Config();
        Veritrans_Config::$serverKey    = $VeritransServerKey;
        Veritrans_Config::$isProduction = !$this->_paymentProcessor['is_test'];
        // Veritrans_Config::$isProduction = false; // TODO remove this
        Veritrans_Config::$is3ds        = true;
        Veritrans_Config::$isSanitized  = true;
        
        // build vtweb params
        // Build billing details array
        $params_billing_address = array(
            'first_name' => $custFirstName,
            'last_name' => $custLastName,
            'address' => $custAddress,
            'city' => $custCity,
            'postal_code' => $custPostalCode,
            'phone' => $custPhone,
            'country_code' => $custCountry
        );
        
        // Build customer details array
        $params_customer_details = array(
            'first_name' => $custFirstName,
            'last_name' => $custLastName,
            'email' => $custEmail,
            'phone' => $custPhone,
            'billing_address' => $params_billing_address
        );
        
        // Build item details
        $items             = array();
        $item1             = array();
        $item1['id']       = $contributionID;
        $item1['price']    = $transactionAmount;
        $item1['quantity'] = 1;
        $item1['name']     = $description;
        $items[]           = $item1;
        
        // Build vtweb params
        $params_all = array(
        	'vtweb' => array(
        		// set return URLs
				'finish_redirect_url' => $this->getReturnSuccessUrl($params['qfKey']),
				'unfinish_redirect_url' => $this->getGoBackUrl($params['qfKey'], NULL),
				'error_redirect_url' => $this->getReturnFailUrl($params['qfKey'])
    		),
            // 'enabled_payments' => $list_enable_payments,
            'transaction_details' => array(
                'order_id' => $transactionOrderId,
                'gross_amount' => $transactionAmount
            ),
            'item_details' => $items,
            'customer_details' => $params_customer_details
        );

        // error_log("CiviCRM params : ".print_r($params,true)); // debug
        // error_log("VT params : ".print_r($params_all,true)); // debug

        // request redirection URL
        $vtwebUrl = Veritrans_VtWeb::getRedirectionUrl($params_all);
        
        // Redirect the user to the payment url.
        CRM_Utils_System::redirect($vtwebUrl);
        exit();
        // notif URL handler ( http://localhost/wp/civicrm/?page=CiviCRM&q=civicrm/payment/ipn/11& ) 
        // TODO Get the static notif URL handler? is it possible?
    }

    public function handlePaymentNotification() {
    	$input_source = "php://input";
    	$notif_json = file_get_contents($input_source);
    	$notif_obj = json_decode($notif_json);

    	// echo("request before passed:  ".print_r($input_source,true)); // debug
    	// print("its here!"); // debug
    	// error_log("its here!"); // debug
    	require_once(dirname(__FILE__).'/vtwebIPN.php');
    	// $vtwebIPN = new com_veritrans_payment_vtwebIPN($input_source,$VeritransServerKey = $this->_paymentProcessor['user_name']);
    	$vtwebIPN = new com_veritrans_payment_vtwebIPN($notif_obj,$this->_paymentProcessor);
    	$vtwebIPN->main();
    }
}