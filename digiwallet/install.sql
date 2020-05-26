CREATE TABLE IF NOT EXISTS `#__virtuemart_payment_plg_digiwallet` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `virtuemart_order_id` int(1) unsigned DEFAULT NULL,
  `order_number` char(64) DEFAULT NULL,
  `virtuemart_paymentmethod_id` mediumint(1) unsigned DEFAULT NULL,
  `payment_name` varchar(5000) DEFAULT NULL,
  `payment_order_total` decimal(15,2) NOT NULL DEFAULT '0.00000',
  `payment_currency` char(3) DEFAULT NULL,
  `tp_rtlo` int(11) NOT NULL,
  `tp_user_session` varchar(255) DEFAULT NULL,
  `tp_method` varchar(8) NOT NULL DEFAULT 'IDE',
  `tp_paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00000',
  `tp_bank` varchar(8) DEFAULT NULL,
  `tp_country` varchar(8) DEFAULT NULL,
  `tp_trxid` varchar(255) NOT NULL,
  `tp_status` varchar(3) DEFAULT NULL,
  `tp_message` varchar(255) DEFAULT NULL,
  `tp_meta_data` varchar(1000) DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_by` int(11) NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified_by` int(11) NOT NULL DEFAULT '0',
  `locked_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `locked_by` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `#__virtuemart_orderstates` (`order_status_code`, `order_status_name`, `order_status_description`, `order_stock_handle`, `ordering`, `virtuemart_vendor_id`)
SELECT * FROM (SELECT 'V', 'Review', '', 'R',0, 1) AS tmp
WHERE NOT EXISTS (
    SELECT `order_status_name` FROM `#__virtuemart_orderstates` WHERE `order_status_name` = 'Review'
) LIMIT 1;

