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

namespace Wrightwaydigital\Enconnector\Service;

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Registry;

class StockService
{
    /**
     * @var ProductFactory
     */
    private $productFactory;
    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;
    /**
     * @var License
     */
    private $configurationService;
    private $coreRegistry;
    public function __construct(
        ProductFactory         $productFactory,
        StockRegistryInterface $stockRegistry,
        ConfigurationService                $configurationService,
        Registry               $coreRegistry
    )
    {
        $this->productFactory = $productFactory;
        $this->stockRegistry = $stockRegistry;
        $this->configurationService = $configurationService;
        $this->coreRegistry = $coreRegistry;
    }
    public function updateStock($model)
    {
        $stock = 0;
        if ($model->LocationId == $this->configurationService->getConfiguration()->getEposnowStoreLocation()) {
            foreach ($model->ProductStockBatches as $batch) {
                $stock += $batch['CurrentStock'];
            }
        }
        $products = $this->productFactory->create();
        /**
         *
         * @var Product
         */
        $product = $products->getCollection()->addAttributeToFilter('eposnow_id', $model->ProductId)->getFirstItem();
        $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
        $stockItem->setQty($stock);
        $this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem);
    }
}
