<?php

namespace Wrightwaydigital\Enconnector\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

class Uninstall implements UninstallInterface
{
    /**
     * Uninstall script code
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        $connection = $installer->getConnection();
        $connection->dropTable($installer->getTable('eposnow_configuration'));
        $connection->dropTable($installer->getTable('eposnow_eposdata'));
        $connection->dropTable($installer->getTable('eposnow_eposnowcolours'));
        $connection->dropTable($installer->getTable('eposnow_license'));
        $connection->dropTable($installer->getTable('eposnow_syncprocess'));
        $connection->dropTable($installer->getTable('eposnow_taxmatch'));
        $tableName = $installer->getTable('setup_module');
        $where = ['module = ?' => 'Wrightwaydigital_Enconnector'];
        $connection->delete($tableName, $where);
        $installer->endSetup();
    }
}
