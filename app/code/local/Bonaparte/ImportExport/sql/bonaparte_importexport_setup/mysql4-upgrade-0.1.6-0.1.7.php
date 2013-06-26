<?php

$installer = $this;

$installer->startSetup();

$installer->run("
DROP TABLE IF EXISTS {$this->getTable('bonaparte_styles')};
CREATE TABLE {$this->getTable('bonaparte_styles')} (
  `style` INT(6),
  `configurable_entity_id` INT(10),
  `simple_entity_id` INT(10),
  KEY (`style`)
 ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS {$this->getTable('bonaparte_resources')};
CREATE TABLE {$this->getTable('bonaparte_resources')} (
  `entity_id` INT(10),
  `picture_id` INT(10),
  `picture_name` varchar(100),
  `product_type` INT(1),
  `picture_type` varchar(20),
  `flag_lead` INT(1),
  PRIMARY KEY (`entity_id`),
  KEY (`picture_name`)
 ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

");



$installer->endSetup();
