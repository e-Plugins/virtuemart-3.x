<?php
/**
 *
 * TargetPay payment plugin
 *
 * @author Harry
 * @package Targetpay
 */

defined('_JEXEC') or die();
vmJsApi::addJScript('/plugins/vmpayment/targetpay/targetpay/assets/js/site.js');
vmJsApi::css('targetpay', 'plugins/vmpayment/targetpay/targetpay/assets/css/');

$selectedPlugin = $viewData['selectedPlugin'];
$bankArrByPaymentOption = $viewData['bankArrByPaymentOption'];
$pluginName = $viewData['pluginName'];
$plugin = $viewData['plugin'];
$pluginmethod_id = $viewData['pluginmethod_id'];
$targetpay_method = $viewData['targetpay_method'];
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
    <div class="targetpay-methods clearfix" >
        <?php
        $payment_method = ! empty($targetpay_method['payment_method']) ? $targetpay_method['payment_method'] : 'IDE';
        $payment_method_option = ! empty($targetpay_method['payment_method_option']) ? $targetpay_method['payment_method_option'] : '';
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
            <input id="targetpay_method_<?=$paymentOption; ?>" name="targetpay_method" value="<?=$paymentOption; ?>" <?=$checked_method ?> type="radio">
            <label for="targetpay_method_<?=$paymentOption; ?>">
                <img class="targetpay-method-icon" src="<?= JROUTE::_('/plugins/vmpayment/targetpay/targetpay/assets/images/method-' . strtolower($paymentOption) . '.png'); ?>" title="<?=JText::_('VMPAYMENT_TARGETPAY_PAYMENT_OPTION_' . $paymentOption); ?>">
            </label>
            <?php if ($bankListCount == 0) :?>
                <?= JText::_('VMPAYMENT_TARGETPAY_PAYMENT_OPTION_NOT_FOUNT'); ?>
            <?php elseif ($bankListCount == 1) : ?>
                <input value="<?=$paymentOption; ?>" name="payment_option_select[<?=$paymentOption;?>]" type="hidden">
            <?php else :?>
                <select data-method="targetpay_method_<?=$paymentOption; ?>" class="sel-payment-data" name="payment_option_select[<?=$paymentOption;?>]">
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