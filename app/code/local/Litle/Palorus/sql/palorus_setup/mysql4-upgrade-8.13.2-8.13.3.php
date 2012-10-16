<?php

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('sales/quote_payment'), 'litle_vault_id', 'int(10)');
$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'litle_vault_id', 'int(10)');

$installer->run("

	CREATE TABLE IF NOT EXISTS `_{$installer->getTable('palorus/vault')}_tmp` (
	  `vault_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `customer_id` int(10) unsigned NOT NULL DEFAULT '0',
	  `last4` varchar(4) DEFAULT NULL,
	  `token` varchar(25) DEFAULT NULL,
	  `type` varchar(2) DEFAULT NULL,
	  `bin` varchar(6) DEFAULT NULL,
	  `expiration_month` tinyint(2) DEFAULT NULL,
	  `expiration_year` smallint(4) DEFAULT NULL,
	  `updated` datetime DEFAULT NULL,
	  `created` datetime DEFAULT NULL,
	  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
	  PRIMARY KEY (`vault_id`),
	  UNIQUE KEY `customer_token` (`customer_id`, `token`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");

$installer->run("
	INSERT INTO `_{$installer->getTable('palorus/vault')}_tmp`
		(vault_id,customer_id,last4,token,`type`,`bin`,updated,created)
		SELECT
		v.vault_id,customer_id,last4,v.token,`type`,`bin`,NOW(), NOW()
		FROM `{$installer->getTable('palorus/vault')}` v
		JOIN (
			SELECT MAX(vault_id) vault_id,token FROM `{$installer->getTable('palorus/vault')}` GROUP BY token
		) v2 ON v.vault_id = v2.vault_id;
");

$installer->run("TRUNCATE TABLE `{$installer->getTable('palorus/vault')}`");

$installer->run("
	ALTER TABLE  `{$installer->getTable('palorus/vault')}`
		ADD `expiration_month` TINYINT( 2 ) NULL DEFAULT NULL,
		ADD `expiration_year` SMALLINT( 4 ) NULL DEFAULT NULL,
		ADD `updated` DATETIME NULL DEFAULT NULL,
		ADD `created` DATETIME NULL DEFAULT NULL,
		ADD `is_visible` TINYINT( 1 ) NOT NULL DEFAULT  '1',
		ADD UNIQUE (`customer_id`, `token`)
");

$installer->run("INSERT INTO `{$installer->getTable('palorus/vault')}` SELECT * FROM `_{$installer->getTable('palorus/vault')}_tmp`;");

$installer->run("
	UPDATE `{$installer->getTable('palorus/vault')}` v
		LEFT JOIN `{$installer->getTable('sales/order')}` sfo ON sfo.entity_id=v.order_id
		SET v.order_number = sfo.increment_id;
");

$installer->run("DROP TABLE `_{$installer->getTable('palorus/vault')}_tmp`;");

$installer->endSetup();