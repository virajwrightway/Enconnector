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
use Wrightwaydigital\Enconnector\Service\EposNow\StockService;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\EposNow\EposProductService;

use Magento\Framework\Registry;
use stdClass;

class ProductSaveCommitAfterObserver implements ObserverInterface
{
    protected $coreRegistry;
    protected $configurationService;
    protected $eposProductService;
    public function __construct(
        ConfigurationService            $configurationService,
        StockService       $stockService,
        Registry           $coreRegistry,
        EposProductService $eposProductService
    )
    {
        $this->configurationService = $configurationService;
        $this->stockService = $stockService;
        $this->coreRegistry = $coreRegistry;
        $this->eposProductService = $eposProductService;
    }
    public function execute(Observer $observer)
    {
//        Magento is not saving quantity after updating Epos Now stock so this observer resets it with the stock level from Epos Now
//        if ($this->configurationService->getConfiguration()->getSyncStock()) {
//            if (!$this->coreRegistry->registry('isCreatedBySync')) {
//                $product = $observer->getEvent()->getProduct();
//                $product->setStockData(['qty' => $this->stockService->getStockByProductId($product->getEposnowId()), 'is_in_stock' => 1]);
//                $this->coreRegistry->register('isCreatedBySync', true);
//                $product->save();
//                $this->coreRegistry->unregister('isCreatedBySync');
//            }
//        }
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
