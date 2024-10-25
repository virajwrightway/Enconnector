<?php
/*********************************************************************************
 *
 * CONFIDENTIAL
 * __________________
 *
 *  Copyright (C) WrightWay Digital, Ltd.
 *  All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of WrightWay Digital Ltd and its suppliers, if any.
 * The intellectual and technical concepts contained herein are proprietary
 * to WrightWay Digital Ltd and its suppliers and may be covered by UK and Foreign Patents,
 * or patents in process, and are protected by trade secret or copyright law.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from WrightWay Digital Ltd.
 *
 * @author WrightWay Digital, Ltd.
 * @copyright 2023 WrightWay Digital, Ltd.
 * @license LICENSE.txt
 ********************************************************************************/

namespace Wrightwaydigital\Enconnector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Wrightwaydigital\Enconnector\Service\EposNow\StockService;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Magento\Framework\Registry;
use Wrightwaydigital\Enconnector\Service\EposNow\EposProductService;
use stdClass;
use Wrightwaydigital\Enconnector\Service\SyncService;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;

class ProductSaveAfterObserver implements ObserverInterface
{
    protected $coreRegistry;
    protected $configurationService;
    protected $stockService;
    protected $eposProductService;
    private $categoryService;
    private $syncService;
    public $licenseService;
    public function __construct(
        ConfigurationService $configurationService,
        StockService         $stockService,
        Registry             $coreRegistry,
        EposProductService   $eposProductService,
        SyncService          $syncService,
        CategoryService      $categoryService,
        LicenseService                         $licenseService

    )
    {
        $this->configurationService = $configurationService;
        $this->stockService = $stockService;
        $this->coreRegistry = $coreRegistry;
        $this->eposProductService = $eposProductService;
        $this->syncService = $syncService;
        $this->categoryService = $categoryService;
        $this->licenseService = $licenseService;
    }
    public function execute(Observer $observer)
    {
        if (!$this->coreRegistry->registry('isCreatedBySync') && $this->configurationService->getConfiguration()->getSyncProducts() && $this->licenseService->getLicenseConnected()) {
            $product = $observer->getEvent()->getProduct();
            $requestData = new stdClass();
            if ($this->configurationService->getConfiguration()->getSyncTitle() || !($product->getEposnowId() > 0)) {
                $requestData->Name = $product->getName();
            }
            if ($this->configurationService->getConfiguration()->getSyncDesc() || !($product->getEposnowId() > 0)) {
                if ($this->configurationService->getConfiguration()->getSyncDescsize() == 1) {
                    $requestData->Description = $product->getDescription();
                } else {
                    $requestData->Description = $product->getShortDescription();
                }
            }
            if ($this->configurationService->getConfiguration()->getSyncPrice() || !($product->getEposnowId() > 0)) {
                $requestData->CostPrice = (float)$product->getPrice();
                $requestData->SalePrice = (float)$product->getPrice();
                $requestData->EatOutPrice = (float)$product->getPrice();
            }
            if (count($product->getCategoryIds()) > 0) {
                $requestData->CategoryId = $this->categoryService->getEposnowIdById($product->getCategoryIds()[0]);
            }
            $requestData->Sku = $product->getSku();
            $requestData->SellOnWeb = true;
            $type_id = $product->getTypeId();
            if (!$product->getEposnowId() && $type_id != 'configurable' && $type_id != 'virtual') {
                $response = $this->eposProductService->postProduct([$requestData]);
//                $response = ((array)$response[0]);
                $response = $response[0];
                $this->syncService->updateProductsBySku($response);
            } else if ($type_id == 'configurable' && !$product->getEposnowId()) {
                $response = $this->eposProductService->postProduct([$requestData]);
                $product->setEposnowId($response[0]->Id);
                $product->getResource()->saveAttribute($product, 'eposnow_id');
                $this->syncService->deleteEnParentProducts([$product]);
                $this->syncService->createChildProductsByParent($product);
            } else if (($product->getEposnowId() > 0) && $type_id != 'configurable') {
                $requestData->Id = $product->getEposnowId();
                $response = $this->eposProductService->putProduct([$requestData]);
            }
        }
//        if ($this->configurationService->getConfiguration()->getSyncStock()) {
//            if (!$this->coreRegistry->registry('isCreatedBySync')) {
//                $product = $observer->getEvent()->getProduct();
//                if ($product->getEposnowId() > 0 && isset($product->getStockData()['qty'])) {
//                    $this->stockService->updateStock($product->getEposnowId(), $product->getStockData()['qty']);
//                }
//            }
//        }
    }
}
