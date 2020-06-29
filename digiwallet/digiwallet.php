<?php
/**
 * Activates iDEAL, Bancontact, Sofort Banking, Visa / Mastercard Credit cards, PaysafeCard, AfterPay, BankWire, PayPal and Refunds in VirtueMart
 * @author DigiWallet.nl <techsupport@targetmedia.nl>
 * @url https://www.digiwallet.nl
 * @copyright Copyright (C) 2018 - 2020 e-plugins.nl
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @ver 4.0.3 - 06-24-2020 set automaticSelectedPayment to false
*/

use digiwallet\helpers\DigiwalletCore;

defined('_JEXEC') or die('Restricted access');
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . '/vmpsplugin.php');
}

if (!class_exists('DigiwalletCore')) {
    require(JPATH_ROOT . '/plugins/vmpayment/digiwallet/digiwallet/helpers/digiwallet.class.php');
}

class plgVmpaymentDigiwallet extends vmPSPlugin
{
    const DIGIWALLET_CURRENCY = 'EUR';
    const DIGIWALLET_BANKWIRE_METHOD = 'BW';

    public $listMethods = array(
        "IDE" => array(
            'name' => 'iDEAL',
            'min' => 0.84,
            'max' => 10000
        ),
        "MRC" => array(
            'name' => 'Bancontact',
            'min' => 0.49,
            'max' => 10000
        ),
        "DEB" => array(
            'name' => 'Sofort Banking',
            'min' => 0.1,
            'max' => 5000
        ),
        'WAL' => array(
            'name' => 'Paysafecard',
            'min' => 0.1,
            'max' => 150
        ),
        'CC' => array(
            'name' => 'Creditcard',
            'min' => 1,
            'max' => 10000
        ),
        'AFP' => array(
            'name' => 'Afterpay',
            'min' => 5,
            'max' => 10000
        ),
        'PYP' => array(
            'name' => 'Paypal',
            'min' => 0.84,
            'max' => 10000
        ),
        'BW' => array(
            'name' => 'Bankwire',
            'min' => 0.84,
            'max' => 10000
        )
    );

    public static $_this = false;

    public $salt = 'e381277';

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        // unique filelanguage for all digiwallet methods
        $jlang = JFactory::getLanguage();
        $jlang->load('plg_vmpayment_digiwallet', JPATH_PLUGINS . '/vmpayment/digiwallet', null, true);
        $this->_loggable = true;
        $this->_debug = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id'; // virtuemart_digiwallet_id';
        $this->_tableId = 'id'; // 'virtuemart_digiwallet_id';

        $varsToPush = array(
            'digiwallet_rtlo' => array(
                '',
                'char'
            ),
            'payment_currency' => array(
                '',
                'char'
            ),
            'countries' => array(
                '',
                'char'
            ),
            'status_pending' => array(
                '',
                'char'
            ),
            'status_success' => array(
                '',
                'char'
            ),
            'status_canceled' => array(
                '',
                'char'
            ),
            'status_review' => array(
                '',
                'char'
            )
        );
        foreach ($this->listMethods as $id => $method) {
            $varName = 'digiwallet_enable_' . strtolower($id);
            $varsToPush[$varName] = array('', 'int');
        }
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => ' char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,2) NOT NULL DEFAULT \'0.00\'',
            'payment_currency' => 'char(3) ',
            'tp_rtlo' => 'int(11) NOT NULL',
            'tp_user_session' => 'varchar(255)',
            'tp_method' => 'varchar(8) NOT NULL DEFAULT \'IDE\'',
            'tp_paid_amount' => 'decimal(15,2) NOT NULL DEFAULT \'0.00\'',
            'tp_bank' => 'varchar(8)',
            'tp_country' => 'varchar(8)',
            'tp_trxid' => 'varchar(255) NOT NULL',
            'tp_status' => 'varchar(3)',
            'tp_message' => 'varchar(255)',
            'tp_meta_data' => 'varchar(1000)',
        );
        return $SQLfields;
    }

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Digiwallet Table');
    }

    /**
     * Hook to confirm page
     *
     * @param unknown $cart
     * @param unknown $order
     * @return NULL|boolean
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        $application = JFactory::getApplication();
        $jinput = $application->input;
        $post_data = $jinput->post->getArray();
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        // update status order
        $this->_updateOrderStatus($order['details']['BT']->virtuemart_order_id, $method->status_pending);
        // get session id
        $session = JFactory::getSession();
        $return_context = $session->getId();

        $this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . '/models/orders.php');
        }
        if (!class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . '/models/currency.php');
        }

        if (!class_exists('TableVendors')) {
            require(JPATH_VM_ADMINISTRATOR . '/table/vendors.php');
        }
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);
        $total = $totalInPaymentCurrency['value'];
        if ($total <= 0) {
            vmInfo(JText::_('VMPAYMENT_DIGIWALLET_PAYMENT_AMOUNT_INCORRECT'));
            return false;
        }
        $payment_method = $post_data['digiwallet_method'];
        if (!$payment_method) {
            vmError(JText::_('VMPAYMENT_DIGIWALLET_PAYMENT_EMPTY_PAYMENT_METHOD'));
            $application->redirect('index.php?option=com_virtuemart&view=cart');
            die;
        }
        $lang = JFactory::getLanguage();
        $tag = substr($lang->get('tag'), 0, 2);

        $order_number = $order['details']['BT']->order_number;
        $option = (!empty($post_data['payment_option_select'][$post_data['digiwallet_method']]) ? $post_data['payment_option_select'][$post_data['digiwallet_method']] : false);
        $description = 'Order id: ' . $order_number;
        // start payment
        $digiwalletObj = new DigiwalletCore($post_data['digiwallet_method'], $method->digiwallet_rtlo, 'nl');
        if ($option) {
            if ($digiwalletObj->getPayMethod() == 'IDE')
                $digiwalletObj->setBankId($option);

            if ($digiwalletObj->getPayMethod() == 'DEB')
                $digiwalletObj->setCountryId($option);
        }
        $digiwalletObj->setAmount(($total * 100));
        $digiwalletObj->setDescription($description);
        $returnUrl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);
        $reportUrl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order_number);
        $digiwalletObj->setReturnUrl($returnUrl);
        $digiwalletObj->setReportUrl($reportUrl);
        //add param email in start url
        if (!empty($order['details']['BT']->email)) {
            $digiwalletObj->bindParam('email', $order['details']['BT']->email);
        }

        $this->additionalParameters($order, $digiwalletObj, $total);

        $result = @$digiwalletObj->startPayment();
        if (!$result) {
            $this->sendEmailToVendorAndAdmins("Error with Digiwallet: ", $digiwalletObj->getErrorMessage());
            $this->logInfo('Process IPN ' . $digiwalletObj->getErrorMessage());
            vmError(JText::_('VMPAYMENT_DIGIWALLET_DISPLAY_GWERROR') . " " . $digiwalletObj->getErrorMessage());
            $application->redirect('index.php?option=com_virtuemart&view=cart');
            die;
        }
        // Prepare data that should be stored in Payment Digiwallet Table
        $dbValues = [];
        $dbValues['order_number'] = $order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['payment_order_total'] = $order['details']['BT']->order_total;
        $dbValues['payment_currency'] = $order['details']['BT']->order_currency;
        $dbValues['tp_rtlo'] = $method->digiwallet_rtlo;
        $dbValues['tp_user_session'] = $return_context;
        $dbValues['tp_method'] = $digiwalletObj->getPayMethod();
        $dbValues['tp_bank'] = $digiwalletObj->getBankId();
        $dbValues['tp_country'] = $digiwalletObj->getCountryId();
        $dbValues['tp_trxid'] = $digiwalletObj->getTransactionId();
        $dbValues['tp_status'] = $method->status_pending;
        $dbValues['tp_message'] = Jtext::_('VMPAYMENT_DIGIWALLET_PAYMENT_PROCESSING');
        $this->storePSPluginInternalData($dbValues);
        $this->logInfo('Transaction id: ' . $digiwalletObj->getTransactionId() . 'Payment Url:' . $digiwalletObj->getBankUrl(), 'message');

        //show instruction page if method == bw
        if ($digiwalletObj->getPayMethod() == self::DIGIWALLET_BANKWIRE_METHOD) {
            $html = $this->renderByLayout('bw_response', array(
                'order_number' => $order['details']['BT']->order_number,
                'order_pass' => $order['details']['BT']->order_pass,
            	'payment_name' => @$listMethods[$digiwalletObj->getPayMethod()]['name'],
                'total' => $total,
            	'more' => $digiwalletObj->getMoreInformation(),
                'billing_email' => $order['details']['BT']->email
            ));

            //We delete the old stuff
            $cart->emptyCart();
            vRequest::setVar('html', $html);
            return TRUE;
        }
        else {
            $cart->_confirmDone = false;
            $cart->_dataValidated = false;
            $cart->setCartIntoSession();
            $application->redirect($digiwalletObj->getBankUrl(), "");
        }
    }

    /**
     * Check payment if not checked & show payment result
     *
     * @param unknown $html
     * @return NULL|string|boolean
     */
    public function plgVmOnPaymentResponseReceived(&$html)
    {
        $jinput = JFactory::getApplication()->input;

        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . '/helpers/cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . '/helpers/shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . '/models/orders.php');
        }

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = $jinput->getInt('pm', 0);
        $order_number = $jinput->getString('on', 0);

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        } // Another method was selected, do nothing

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }

        if (! ($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;

        }
        $payResult = array(
            'state' => 'ERROR_ORDER_NUMBER_NOT_FOUND'
        );
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($virtuemart_order_id);

        //check if order not success => update order status
        // order_status needs to be a non-final status before checking to update
        // This avoids a "shipped" status flipping back to "success" accidentally.
        // To achieve this, we will do nothing if the VirtueMart order status is either of the below:
        // C = Confirmed, R = Refunded, S = Shipped
        $virtueMartOrderStatus = $order['details']['BT']->order_status;
        if (!in_array($virtueMartOrderStatus, array('C', 'R', 'S')) && $virtueMartOrderStatus != $method->status_success) {
            $this->_updatePaymentInfo($paymentTable, $method, ['trxid' => $paymentTable->tp_trxid]);
            //refresh order object
            $order = $orderModel->getOrder($virtuemart_order_id);
        }

        if ($paymentTable->tp_status == $method->status_success) {
            // empty cart
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();
        }
        VmConfig::loadJLang('com_virtuemart_orders', TRUE);

        $html = $this->renderByLayout('response', [
            'paymentTable' => $paymentTable,
            'order' => $order,
            'status' => shopFunctionsF::getOrderStatusName($order['details']['BT']->order_status)
        ]);
        return true;
    }

    /**
     * get param from digiwallet via POST method & check payment & update order & Payment Digiwallet Table
     *
     * @return void|boolean
     */
    public function plgVmOnPaymentNotification()
    {
        $jinput = JFactory::getApplication()->input;
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . '/models/orders.php');
        }
        $post_data = $jinput->post->getArray();
        $order_number = $jinput->getString('on', 0);

        if (empty($post_data['trxid']) && empty($post_data['acquirerID']) && empty($post_data['invoiceID'])) { // Trxid not set
            die('error');
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            $this->logInfo(__FUNCTION__ . ' Can\'t get VirtueMart order id', 'message');
            return false;
        }

        if (! ($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {

            $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
            return;
        }
        
        $method = $this->getVmPluginMethod($paymentTable->virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement($method->payment_element)) {
            $this->logInfo(__FUNCTION__ . ' payment method not selected', 'message');
            return false;
        }
        $modelOrder = VmModel::getModel('orders');
        $vmorder = $modelOrder->getOrder($virtuemart_order_id);

        $virtueMartOrderStatus = $vmorder['details']['BT']->order_status;
        //check if order not success => update order status
        // order_status needs to be a non-final status before checking to update
        // This avoids a "shipped" status flipping back to "success" accidentally.
        // To achieve this, we will do nothing if the VirtueMart order status is either of the below:
        // C = Confirmed, R = Refunded, S = Shipped
        if (in_array($virtueMartOrderStatus, array('C', 'R', 'S')) || $virtueMartOrderStatus == $method->status_success) {
            echo "order $virtuemart_order_id had been done";
            die;
        }

        //update order status
        $log_msg = 'Prev status= ' . $vmorder['details']['BT']->order_status . PHP_EOL;
        $this->_updatePaymentInfo($paymentTable, $method, $post_data);

        $vmorder = $modelOrder->getOrder($virtuemart_order_id);
        $log_msg .= 'current status= ' . $vmorder['details']['BT']->order_status . PHP_EOL;
        $log_msg .= 'order number= ' . $virtuemart_order_id . PHP_EOL;
        $log_msg .= 'Version= 1.x';
        
        die($log_msg);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on success, false on failures, null when this plugin was not selected.
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $cart->automaticSelectedPayment = false;
        $html = $this->_getDigiwalletPluginHtml($cart, $selected);
        if (!empty($html)) {
            $htmlIn[] = [
                $html
            ];
        }
        else {
            return null;
        }
        return true;
    }

    /**
     * hook to order detail in BE to shown addition information
     *
     * @param unknown $virtuemart_order_id
     * @param unknown $payment_method_id
     * @return NULL|string
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null;
        } // Another method was selected, do nothing

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }
        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('VMPAYMENT_DIGIWALLET_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('VMPAYMENT_DIGIWALLET_PAYMENT_RTLO', $paymentTable->tp_rtlo);
        $html .= $this->getHtmlRowBE('VMPAYMENT_DIGIWALLET_PAYMENT_METHOD', @$this->listMethods[$paymentTable->tp_method]['name']);
        $html .= $this->getHtmlRowBE('VMPAYMENT_DIGIWALLET_PAYMENT_TRXID', $paymentTable->tp_trxid);
        $html .= $this->getHtmlRowBE('VMPAYMENT_DIGIWALLET_PAYMENT_STATUS', shopFunctionsF::getOrderStatusName($paymentTable->tp_status));
        $html .= $this->getHtmlRowBE('VMPAYMENT_DIGIWALLET_PAYMENT_RESULT', $paymentTable->tp_message);
        $html .= $this->getHtmlRowBE('VMPAYMENT_DIGIWALLET_PAYMENT_PAID_AMOUNT', $paymentTable->tp_paid_amount);
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * set payment method & payment option to session
     *
     * {@inheritDoc}
     *
     * @see vmPSPlugin::checkConditions()
     */
    public function checkConditions($cart, $method, $cart_prices)
    {
        $jinput = JFactory::getApplication()->input;
        $post_data = $jinput->post->getArray();
        if (!empty($post_data)) {
            $session = JFactory::getSession();
            // set payment method to session
            $digiwallet_method['payment_method'] = $post_data['digiwallet_method'];
            $digiwallet_method['payment_method_option'] = $post_data['payment_option_select'][$post_data['digiwallet_method']];
            $session->set('digiwallet_method', $digiwallet_method);
        }
        return true;
    }

    /**
     * Build html for digiwallet plugin
     *
     * @param object $cart
     *            Cart object
     * @param int $selectedPlugin
     *
     * @return string
     */
    private function _getDigiwalletPluginHtml($cart, $selectedPlugin)
    {
        $html = '';
        if (!empty($this->methods)) {
            $session = JFactory::getSession();
            $digiwallet_method = $session->get('digiwallet_method');
            $pluginmethod_id = $this->_idName;
            $pluginName = $this->_psType . '_name';
            $cartPrices = $cart->cartPrices['billTotal'];

            foreach ($this->methods as $plugin) {
                $bankArrByPaymentOption = array();
                /* remove unwanted paymethods */
                foreach ($this->listMethods as $id => $method) {
                    $varName = 'digiwallet_enable_' . strtolower($id);
                    if ($plugin->$varName == 1 && ($cartPrices <= $method['max'] && $cartPrices >= $method['min'])) {
                        $bankArrByPaymentOption[$id] = $this->paymentArraySelection($id, $plugin->digiwallet_rtlo);
                    }
                }
                if (!empty($bankArrByPaymentOption)) {
                    $html .= $this->renderByLayout('method_form', [
                        'selectedPlugin' => $selectedPlugin,
                        'bankArrByPaymentOption' => $bankArrByPaymentOption,
                        'pluginName' => $pluginName,
                        'plugin' => $plugin,
                        'pluginmethod_id' => $pluginmethod_id,
                        'digiwallet_method' => $digiwallet_method,
                        '_psType' => $this->_psType,
                        '_type' => $this->_type
                    ]);
                }
            }
        }
        return $html;
    }

    /**
     * Get array option of method
     *
     * @param string $method
     * @param string $rtlo
     * @return array
     */
    public function paymentArraySelection($method, $rtlo)
    {
        switch ($method) {
            case "IDE":
                $idealOBJ = new DigiwalletCore($method, $rtlo);
                return $idealOBJ->getBankList();
                break;
            case "DEB":
                $directEBankingOBJ = new DigiwalletCore($method, $rtlo);
                return $directEBankingOBJ->getCountryList();
                break;
            case "MRC":
            case "WAL":
            case "CC":
            case "BW":
            case "PYP":
            case "AFP":
                return array($method => $method);
                break;
            default:
        }
    }

    /**
     * Update data in Payment Digiwallet Table base on virtuemart_order_id column
     *
     * @param unknown $method
     * @param unknown $paymentTable
     * @param array $post_data
     * @return none
     */
    public function _storeInternalData($method, $paymentTable, $post_data = array())
    {
        // set old value to arr
        $response_fields = [];
        $response_fields['virtuemart_order_id'] = $paymentTable->virtuemart_order_id;
        $response_fields['order_number'] = $paymentTable->order_number;
        $response_fields['virtuemart_paymentmethod_id'] = $paymentTable->virtuemart_paymentmethod_id;
        $response_fields['payment_name'] = $paymentTable->payment_name;
        $response_fields['payment_order_total'] = $paymentTable->payment_order_total;
        $response_fields['payment_currency'] = $paymentTable->payment_currency;
        $response_fields['created_by'] = $paymentTable->created_by;
        $response_fields['modified_by'] = $paymentTable->modified_by;

        // added column
        $response_fields['tp_rtlo'] = $paymentTable->tp_rtlo;
        $response_fields['tp_user_session'] = $paymentTable->tp_user_session;
        $response_fields['tp_method'] = $paymentTable->tp_method;
        $response_fields['tp_paid_amount'] = @$post_data['paid_amount'];
        $response_fields['tp_bank'] = $paymentTable->tp_bank;
        $response_fields['tp_country'] = $paymentTable->tp_country;
        $response_fields['tp_trxid'] = $paymentTable->tp_trxid;
        $response_fields['tp_status'] = $paymentTable->tp_status;
        $response_fields['tp_message'] = $paymentTable->tp_message;
        $response_fields['tp_meta_data'] = $paymentTable->tp_meta_data;

        if (!empty($post_data)) {
            $response_fields['tp_meta_data'] = json_encode($post_data);
        }
        $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);
    }

    /**
     * update Payment Digiwallet Table & it's order
     *
     * @param Object $paymentTable
     * @param object $method
     * @param array $post_data
     */
    public function _updatePaymentInfo($paymentTable, $method, $post_data = array())
    {
        $digiwalletObj = new DigiwalletCore($paymentTable->tp_method, $paymentTable->tp_rtlo, 'nl');
        $trxid = $paymentTable->tp_trxid;

        $digiwalletObj->checkPayment($trxid, $this->getAdditionParametersReport($paymentTable));
        if ($digiwalletObj->getPaidStatus()) { //success
            $amountPaid = $paymentTable->payment_order_total;
            if ($paymentTable->tp_method == self::DIGIWALLET_BANKWIRE_METHOD) {
                $paymentIsPartial = false;
                $consumber_info = $digiwalletObj->getConsumerInfo();
                if (!empty($consumber_info) && $consumber_info['bw_paid_amount'] > 0) {
                    $amountPaid = number_format($consumber_info['bw_paid_amount'] / 100, 2);
                    if ($consumber_info['bw_paid_amount'] < $consumber_info['bw_due_amount']) {
                        $paymentIsPartial = true;
                    }
                }
                if ($paymentIsPartial) {
                    $paymentTable->tp_status = $method->status_review;
                    $paymentTable->tp_message = Jtext::_('VMPAYMENT_DIGIWALLET_PAYMENT_CHECK_PARTIAL');
                    $comments = JText::sprintf('VMPAYMENT_DIGIWALLET_PAYMENT_STATUS_REVIEW', $paymentTable->order_number);
                }
                else {
                    $paymentTable->tp_status = $method->status_success;
                    $paymentTable->tp_message = Jtext::_('VMPAYMENT_DIGIWALLET_PAYMENT_CHECK_SUCCESS');
                    $comments = JText::sprintf('VMPAYMENT_DIGIWALLET_PAYMENT_STATUS_CONFIRMED', $paymentTable->order_number);
                }
            }
            else {
                $paymentTable->tp_status = $method->status_success;
                $paymentTable->tp_message = Jtext::_('VMPAYMENT_DIGIWALLET_PAYMENT_CHECK_SUCCESS');
                $comments = JText::sprintf('VMPAYMENT_DIGIWALLET_PAYMENT_STATUS_CONFIRMED', $paymentTable->order_number);
            }
            $post_data['paid_amount'] = $amountPaid;
        }
        else {
            $paymentTable->tp_status = $method->status_canceled;
            $paymentTable->tp_message = $digiwalletObj->getErrorMessage();
            $comments = JText::sprintf('VMPAYMENT_DIGIWALLET_PAYMENT_CANCELLED', $paymentTable->order_number);
        }
        $this->_storeInternalData($method, $paymentTable, $post_data);
        // update orders
        $this->_updateOrderStatus($paymentTable->virtuemart_order_id, $paymentTable->tp_status, $comments);
    }

    /**
     * Update status of order
     *
     * @param unknown $virtuemart_order_id
     * @param unknown $status
     * @return none
     */
    public function _updateOrderStatus($virtuemart_order_id, $status, $comments = null)
    {
        $modelOrder = VmModel::getModel('orders');
        $vmorder = $modelOrder->getOrder($virtuemart_order_id);
        $order = array();
        $order['customer_notified'] = 1;
        if ($comments) {
            $order['comments'] = $comments;
        }
        $order['order_status'] = $status;
        $this->logInfo('plgVmOnPaymentNotification return new_status:' . $status, 'message');
        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
    }

    /**
     *  Bind parameters
     */
    public function additionalParameters($order, $digiwalletObj, $total)
    {
        $order_details = $order['details']['BT'];
        $order_detailsST = $order['details']['ST'];
        switch ($digiwalletObj->getPayMethod()) {
            case 'IDE':
            case 'MRC':
            case 'DEB':
            case 'CC':
            case 'WAL':
            case 'PYP':
                break;
            case 'BW':
                $digiwalletObj->bindParam('salt', $this->salt);
                $digiwalletObj->bindParam('email', $order_details->email);
                $digiwalletObj->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
                break;
            case 'AFP':
                // Getting the items in the order
                $invoicelines = [];
                $total_amount_by_products = 0;
                // Iterating through each item in the order
                foreach ($order['items'] as $item_data) {
                    // Get the product name
                    $product_name = $item_data->order_item_name;
                    // Get the item quantity
                    $item_quantity = $item_data->product_quantity;
                    // Get the item line total
                    $item_total = $item_data->product_subtotal_with_tax;

                    $tax_rates = @ShopFunctions::getTaxByID($item_data->product_tax_id)['calc_value'];
                    $invoicelines[] = [
                        'productCode' => $item_data->virtuemart_product_id,
                        'productDescription' => $product_name,
                        'quantity' => $item_quantity,
                        'price' => $item_total,
                        'taxCategory' => $digiwalletObj->getTax($tax_rates)
                    ];
                    $total_amount_by_products += $item_total;
                }
                $invoicelines[] = [
                    'productCode' => '000000',
                    'productDescription' => "Other fees (shipping, additional fees)",
                    'quantity' => 1,
                    'price' => $total - $total_amount_by_products,
                    'taxCategory' => 3
                ];

                $billingCountry = ShopFunctions::getCountryByID($order_details->virtuemart_country_id, 'country_3_code');
                $billingCountry = $billingCountry == 'BEL' ? 'BEL' : 'NLD';
                $shippingCountry = ShopFunctions::getCountryByID($order_detailsST->virtuemart_country_id, 'country_3_code');
                $shippingCountry = $shippingCountry == 'BEL' ? 'BEL' : 'NLD';

                $streetParts = self::breakDownStreet($order_details->address_1);

                $digiwalletObj->bindParam('billingstreet', $streetParts['street']);
                $digiwalletObj->bindParam('billinghousenumber', empty($streetParts['houseNumber'] . $streetParts['houseNumberAdd']) ? $order_details->address_1 : $streetParts['houseNumber'] . ' ' .$streetParts['houseNumberAdd']);
                $digiwalletObj->bindParam('billingpostalcode', $order_details->zip);
                $digiwalletObj->bindParam('billingcity', $order_details->city);
                $digiwalletObj->bindParam('billingpersonemail', $order_details->email);
                $digiwalletObj->bindParam('billingpersoninitials', "");
                $digiwalletObj->bindParam('billingpersongender', "");
                $digiwalletObj->bindParam('billingpersonbirthdate', "");
                $digiwalletObj->bindParam('billingpersonsurname', $order_details->first_name);
                $digiwalletObj->bindParam('billingpersonsurname', $order_details->last_name);
                $digiwalletObj->bindParam('billingcountrycode', $billingCountry);
                $digiwalletObj->bindParam('billingpersonlanguagecode', $billingCountry);
                $digiwalletObj->bindParam('billingpersonphonenumber', self::format_phone($billingCountry, $order_details->phone_1));

                $streetParts = self::breakDownStreet($order_detailsST->address_1);

                $digiwalletObj->bindParam('shippingstreet', $streetParts['street']);
                $digiwalletObj->bindParam('shippinghousenumber', empty($streetParts['houseNumber'] . $streetParts['houseNumberAdd']) ? $order_detailsST->address_1 : $streetParts['houseNumber'] . ' ' .$streetParts['houseNumberAdd']);
                $digiwalletObj->bindParam('shippingpostalcode', $order_detailsST->zip);
                $digiwalletObj->bindParam('shippingcity', $order_detailsST->city);
                $digiwalletObj->bindParam('shippingpersonemail', $order_detailsST->email);
                $digiwalletObj->bindParam('shippingpersoninitials', "");
                $digiwalletObj->bindParam('shippingpersongender', "");
                $digiwalletObj->bindParam('shippingpersonbirthdate', "");
                $digiwalletObj->bindParam('shippingpersonsurname', $order_detailsST->first_name);
                $digiwalletObj->bindParam('shippingpersonsurname', $order_detailsST->last_name);
                $digiwalletObj->bindParam('shippingcountrycode', $shippingCountry);
                $digiwalletObj->bindParam('shippingpersonlanguagecode', $shippingCountry);
                $digiwalletObj->bindParam('shippingpersonphonenumber', self::format_phone($shippingCountry, $order_detailsST->phone_1));

                $digiwalletObj->bindParam('invoicelines', json_encode($invoicelines));
                $digiwalletObj->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
                break;
        }
    }

    private static function format_phone($country, $phone)
    {
        $function = 'format_phone_' . strtolower($country);
        if (method_exists('plgVmpaymentDigiwallet', $function)) {
            return self::$function($phone);
        }
        else {
            echo "unknown phone formatter for country: " . $function;
            exit;
        }
        return $phone;
    }

    private static function format_phone_nld($phone)
    {
        // note: making sure we have something
        if (!isset($phone{3})) {
            return '';
        }
        // note: strip out everything but numbers
        $phone = self::getPhone($phone);
        $length = strlen($phone);
        switch ($length) {
            case 9:
                return "+31" . $phone;
                break;
            case 10:
                return "+31" . substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+" . $phone;
                break;
            default:
                return $phone;
                break;
        }
    }

    private static function format_phone_bel($phone)
    {
        // note: making sure we have something
        if (!isset($phone{3})) {
            return '';
        }
        // note: strip out everything but numbers
        $phone = self::getPhone($phone);
        $length = strlen($phone);
        switch ($length) {
            case 9:
                return "+32" . $phone;
                break;
            case 10:
                return "+32" . substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+" . $phone;
                break;
            default:
                return $phone;
                break;
        }
    }

    private static function getPhone($phone)
    {
        $phone = preg_replace("/[^0-9]/", "", $phone);
        return $phone;
    }

    private static function breakDownStreet($street)
    {
        $out = [
            'street' => null,
            'houseNumber' => null,
            'houseNumberAdd' => null,
        ];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);

        if (! $addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        if (empty($addressResult)) {
            $out['street'] = $street;
            
            return $out;
        }
        
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        
        return $out;
    }

    /**
     * addition params for report
     * @return array
     */
    protected function getAdditionParametersReport($paymentTable)
    {
        $param = [];
        if ($paymentTable->tp_method == self::DIGIWALLET_BANKWIRE_METHOD) {
            $checksum = md5($paymentTable->tp_trxid . $paymentTable->tp_rtlo . $this->salt);
            $param['checksum'] = $checksum;
        }

        return $param;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @author Valérie Isaksen
     *
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected.
     * It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart :
     *            the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented.
     * If not reimplemented, then the default values from this function are taken.
     *
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available.
     * If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @author Valerie Isaksen
     * @param
     *            VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found, virtuemart_xxx_id if only one plugin is found
     *
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id
     *            The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id
     *            The order ID
     * @param integer $method_id
     *            method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    public function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
} // end of class plgVmpaymentDigiwallet
