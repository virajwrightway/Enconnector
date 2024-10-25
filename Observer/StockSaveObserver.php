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

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\EposNow\StockService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Catalog\Model\ProductRepository;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;

class StockSaveObserver implements ObserverInterface
{
    /**
     * @var License
     */
    private $configurationService;
    /**
     * @var StockService
     */
    private $stockService;
    /**
     * @var Registry
     */
    private $coreRegistry;
    private $productRepository;
    public $licenseService;
    public function __construct(
        ConfigurationService           $configurationService,
        StockService      $stockService,
        Registry          $coreRegistry,
        ProductRepository $productRepository,
        LicenseService                         $licenseService
    )
    {
        $this->configurationService = $configurationService;
        $this->stockService = $stockService;
        $this->coreRegistry = $coreRegistry;
        $this->productRepository = $productRepository;
        $this->licenseService = $licenseService;
    }
    public function execute(Observer $observer)
    {
        if ($this->configurationService->getConfiguration()->getSyncStock() && $this->licenseService->getLicenseConnected()) {
            if (!$this->coreRegistry->registry('isCreatedBySync')) {
                $item = $observer->getEvent()->getItem();
                $productId = $item->getProductId();
                $product = $this->productRepository->getById($productId);
                if ($product->getEposnowId() > 0) {
                    $this->stockService->updateStock($product->getEposnowId(), $item->getQty());
                }
            }
        }
    }
}
