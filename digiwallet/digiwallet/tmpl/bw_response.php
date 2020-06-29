<?php
defined ('_JEXEC') or die();

/**
 * Activates iDEAL, Bancontact, Sofort Banking, Visa / Mastercard Credit cards, PaysafeCard, AfterPay, BankWire, PayPal and Refunds in VirtueMart
 * @author DigiWallet.nl <techsupport@targetmedia.nl>
 * @url https://www.digiwallet.nl
 * @copyright Copyright (C) 2018 - 2020 e-plugins.nl
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

?>
<?php 
list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $viewData['more']);
?>
<div class="bankwire-info">
    <p><?= JText::sprintf('VMPAYMENT_DIGIWALLET_BANKWIRE_RESPONSE_TEXT_1', $viewData['total'], $iban, $beneficiary)?></p>
    <p><?= JText::sprintf('VMPAYMENT_DIGIWALLET_BANKWIRE_RESPONSE_TEXT_2',$trxid, $viewData['billing_email'])?></p>
    <p><?= JText::sprintf('VMPAYMENT_DIGIWALLET_BANKWIRE_RESPONSE_TEXT_3',$bic, $bank)?></p>
    <p><i><?= JText::_('VMPAYMENT_DIGIWALLET_BANKWIRE_RESPONSE_TEXT_4')?></i></p>
</div>
<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$viewData["order_number"].'&order_pass='.$viewData["order_pass"], false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>