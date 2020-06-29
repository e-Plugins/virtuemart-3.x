<?php
/**
 * Activates iDEAL, Bancontact, Sofort Banking, Visa / Mastercard Credit cards, PaysafeCard, AfterPay, BankWire, PayPal and Refunds in VirtueMart
 * @author DigiWallet.nl <techsupport@targetmedia.nl>
 * @url https://www.digiwallet.nl
 * @copyright Copyright (C) 2018 - 2020 e-plugins.nl
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
*/

defined('_JEXEC') or die();
vmJsApi::addJScript('/plugins/vmpayment/digiwallet/digiwallet/assets/js/site.js');
vmJsApi::css('digiwallet', 'plugins/vmpayment/digiwallet/digiwallet/assets/css/');

$selectedPlugin = $viewData['selectedPlugin'];
$bankArrByPaymentOption = $viewData['bankArrByPaymentOption'];
$pluginName = $viewData['pluginName'];
$plugin = $viewData['plugin'];
$pluginmethod_id = $viewData['pluginmethod_id'];
$digiwallet_method = $viewData['digiwallet_method'];
$_psType = $viewData['_psType'];
$_type = $viewData['_type'];

$show_hide_method = false;
$checked = '';
$dynUpdate = '';
if ($selectedPlugin == $plugin->$pluginmethod_id) {
    $checked = ' checked="checked"';
    $show_hide_method = true;
}
if (VmConfig::get('oncheckout_ajax', false)) {
    $dynUpdate = ' data-dynamic-update="1" ';
}
?>
<div class="block-method">
<input id="<?= $_psType . '_id_' . $plugin->$pluginmethod_id;?>" <?= $dynUpdate ?> name="<?= $pluginmethod_id;?>" value="<?=$plugin->$pluginmethod_id; ?>" <?= $checked; ?> type="radio">
<label for="<?= $_psType . '_id_' . $plugin->$pluginmethod_id;?>">
    <span class="<?=$_type;?>"><?=$plugin->$pluginName; ?></span>
</label>
<?php if($show_hide_method):?>
    <div class="digiwallet-methods clearfix" >
        <?php
        $payment_method = ! empty($digiwallet_method['payment_method']) ? $digiwallet_method['payment_method'] : 'IDE';
        $payment_method_option = ! empty($digiwallet_method['payment_method_option']) ? $digiwallet_method['payment_method_option'] : '';
        ?>
        <?php foreach ($bankArrByPaymentOption as $paymentOption => $bankCodesArr) :?>
        <?php
            $checked_method = '';
            $bankListCount = count($bankCodesArr);
        if ($paymentOption == $payment_method) {
            $checked_method = 'checked="checked"';
        }
        ?>
        <div class="method_<?=$paymentOption; ?>">
            <input id="digiwallet_method_<?=$paymentOption; ?>" name="digiwallet_method" value="<?=$paymentOption; ?>" <?=$checked_method ?> type="radio">
            <label for="digiwallet_method_<?=$paymentOption; ?>">
                <img class="digiwallet-method-icon" src="<?= JURI::base() . JROUTE::_('plugins/vmpayment/digiwallet/digiwallet/assets/images/method-' . strtolower($paymentOption) . '.png'); ?>" title="<?=JText::_('VMPAYMENT_DIGIWALLET_PAYMENT_OPTION_' . $paymentOption); ?>">
            </label>
            <?php if ($bankListCount == 0) :?>
                <?= JText::_('VMPAYMENT_DIGIWALLET_PAYMENT_OPTION_NOT_FOUNT'); ?>
            <?php elseif ($bankListCount == 1) : ?>
                <input value="<?=$paymentOption; ?>" name="payment_option_select[<?=$paymentOption;?>]" type="hidden">
            <?php else :?>
                <select data-method="digiwallet_method_<?=$paymentOption; ?>" class="sel-payment-data" name="payment_option_select[<?=$paymentOption;?>]">
                <?php foreach ($bankCodesArr as $key => $value) :?>
                    <?php
                        $checked_option_method = '';
                    if ($key == $payment_method_option) {
                        $checked_option_method = 'selected';
                    }
                    ?>
                    <option <?=$checked_option_method ?> value="<?=$key?>"><?=$value?></option>
                <?php endforeach;?>
                </select>
            <?php endif;?>
        </div>
        <hr>
        <?php endforeach;?>
    </div>
<?php endif;?>
</div>