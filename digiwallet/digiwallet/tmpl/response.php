<?php
/**
 *
 * Digiwallet payment plugin
 *
 * @author Harry
 * @package Digiwallet
 */

defined('_JEXEC') or die();
vmJsApi::css('digiwallet', 'plugins/vmpayment/digiwallet/digiwallet/assets/css/');

$paymentTable = $viewData['paymentTable'];
?>
<br />
<table class="ordersummary">
<?php
    echo $this->getHtmlRow('DIGIWALLET_PAYMENT_NAME', $paymentTable->payment_name);
    echo $this->getHtmlRow('DIGIWALLET_ORDER_NUMBER', $paymentTable->order_number);
    echo $this->getHtmlRow(Jtext::_('VMPAYMENT_DIGIWALLET_PAYMENT_CHECK_RESULT'), $viewData['status']);
?>
</table>
<br />
<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$viewData["order"]['details']['BT']->order_number.'&order_pass='.$viewData["order"]['details']['BT']->order_pass, false)?>"><?php echo vmText::_('VMPAYMENT_DIGIWALLET_PAYMENT_VIEW_ORDER'); ?></a>
