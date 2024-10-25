<?php
namespace Wrightwaydigital\Enconnector\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Sales\Model\Order;
use Magento\Catalog\Model\Category;
use Magento\Customer\Model\Customer;
use Psr\Log\LoggerInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;

class InstallPatch implements DataPatchInterface, PatchRevertableInterface
{
    private $moduleDataSetup;
    private $eavSetupFactory;
    private $logger;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory          $eavSetupFactory,
        LoggerInterface          $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->logger = $logger;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        try {
            $this->insertConfigurationData();
            $this->insertSyncProgressData();
            $this->insertLicenseData();
            $this->addEposNowAttributes();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function insertConfigurationData()
    {
        $configuratonData = [
            ['id' => '1', 'eposnow_store_location' => 123, 'sync_stock' => 1, 'sync_products' => 1, 'sync_categories' => 1, 'sync_orders' => 1, 'en_variantGrp_status' => 0, 'eposnow_validated' => 0, 'default_tender' => 0, 'api_locked' => 0, 'should_cron' => 0, 'is_cron' => 0, 'is_ajax' => 0]
        ];
        $this->moduleDataSetup->getConnection()->insertMultiple(
            $this->moduleDataSetup->getTable('eposnow_configuration'),
            $configuratonData
        );
    }

    private function insertSyncProgressData()
    {
        $syncPogressData = [
            ['type' => 'category', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 1, 'last_fetched' => 0, 'direction' => 'magento'],
            ['type' => 'product', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 2, 'last_fetched' => 0, 'direction' => 'magento'],
            ['type' => 'stock', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 3, 'last_fetched' => 0, 'direction' => 'magento'],
            ['type' => 'order', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 4, 'last_fetched' => 0, 'direction' => 'magento'],
            ['type' => 'category', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 1, 'last_fetched' => 0, 'direction' => 'eposnow'],
            ['type' => 'product', 'synced_count' => 0, 'last_page' => 1, 'last_id' => 0, 'status' => 0, 'priority' => 2, 'last_fetched' => 0, 'direction' => 'eposnow'],
            ['type' => 'variant', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 3, 'last_fetched' => 0, 'direction' => 'eposnow'],
            ['type' => 'stock', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 4, 'last_fetched' => 0, 'direction' => 'eposnow'],
            ['type' => 'order', 'synced_count' => 0, 'last_page' => 0, 'last_id' => 0, 'status' => 0, 'priority' => 5, 'last_fetched' => 0, 'direction' => 'eposnow']
        ];
        $this->moduleDataSetup->getConnection()->insertMultiple(
            $this->moduleDataSetup->getTable('eposnow_syncprocess'),
            $syncPogressData
        );
    }

    private function insertLicenseData()
    {
        $licenseData = [
            ['id' => 1, 'license_key' => ' ', 'eposnow_token' => 0, 'domain' => 0, 'app_version' => 0, 'hash' => 0]
        ];
        $this->moduleDataSetup->getConnection()->insertMultiple(
            $this->moduleDataSetup->getTable('eposnow_license'),
            $licenseData
        );
    }

    private function addEposNowAttributes()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $this->addEposNowAttribute($eavSetup, Product::ENTITY, 'eposnow_id', 'Product Details');
        $this->addEposNowAttribute($eavSetup, Order::ENTITY, 'eposnow_id', 'Order Details');
        $this->addEposNowAttribute($eavSetup, Category::ENTITY, 'eposnow_id', 'Category Details');
        $this->addEposNowAttribute($eavSetup, Customer::ENTITY, 'eposnow_id', 'Customer Details');
    }

    private function addEposNowAttribute(EavSetup $eavSetup, $entity, $attributeCode, $group)
    {
        $eavSetup->addAttribute(
            $entity,
            $attributeCode,
            [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Epos Now Id',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => $group
            ]
        );
    }

    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        try {
            $this->removeAttributes();
            $this->clearCustomTables();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function removeAttributes()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, 'eposnow_id');
        $eavSetup->removeAttribute(Order::ENTITY, 'eposnow_id');
        $eavSetup->removeAttribute(Category::ENTITY, 'eposnow_id');
        $eavSetup->removeAttribute(Customer::ENTITY, 'eposnow_id');
    }

    private function clearCustomTables()
    {
        $this->moduleDataSetup->getConnection()->delete(
            $this->moduleDataSetup->getTable('eposnow_configuration'),
            ['id = ?' => 1]
        );
        $this->moduleDataSetup->getConnection()->delete(
            $this->moduleDataSetup->getTable('eposnow_syncprocess'),
            []
        );
        $this->moduleDataSetup->getConnection()->delete(
            $this->moduleDataSetup->getTable('eposnow_license'),
            ['id = ?' => 1]
        );
    }

    public function getAliases()
    {
        return [];
    }

    public static function getDependencies()
    {
        return [];
    }
}
