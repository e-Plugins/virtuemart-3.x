<?php
defined ('_JEXEC') or die();

/**
 * @author Valérie Isaksen
 * @version $Id$
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2004-Copyright (C) 2004 - 2016 Virtuemart Team. All rights reserved.   - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
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