<?php

$installer = $this;

$installer->startSetup();

$table = $installer->getConnection()
    ->newTable('cocote_token')
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Id')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
    ), 'ID of order')
    ->addColumn('token', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable'  => false,
    ), 'Token')
;
$installer->getConnection()->createTable($table);

$installer->endSetup();