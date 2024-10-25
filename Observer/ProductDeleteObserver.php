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
use Wrightwaydigital\Enconnector\Service\EposNow\EposProductService;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use stdClass;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;

class ProductDeleteObserver implements ObserverInterface
{
    protected $eposProductService;
    protected $configurationService;
    public $licenseService;
    public function __construct(
        EposProductService   $eposProductService,
        ConfigurationService $configurationService,
        LicenseService                         $licenseService
    )
    {
        $this->eposProductService = $eposProductService;
        $this->configurationService = $configurationService;
        $this->licenseService = $licenseService;
    }
    public function execute(Observer $observer)
    {
        if ($this->configurationService->getConfiguration()->getSyncProducts() && $this->licenseService->getLicenseConnected()) {
            $product = $observer->getEvent()->getProduct()->load((int)$observer->getEvent()->getProduct()->getId());
            $deleteData = new stdClass();
            $deleteData->Id = $product->getEposnowId();
            $response = $this->eposProductService->deleteProduct([$deleteData]);
        }
    }
}
