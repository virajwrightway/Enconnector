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
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Magento\Framework\Registry;
use stdClass;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;



class ProductSaveObserver implements ObserverInterface
{
    protected $categoryService;
    protected $eposProductService;
    protected $coreRegistry;
    protected $configurationService;
    public $licenseService;
    public function __construct(
        CategoryService    $categoryService,
        EposProductService $eposProductService,
        ConfigurationService            $configurationService,
        Registry           $coreRegistry,
        LicenseService                         $licenseService
    )
    {
        $this->categoryService = $categoryService;
        $this->eposProductService = $eposProductService;
        $this->configurationService = $configurationService;
        $this->coreRegistry = $coreRegistry;
        $this->licenseService = $licenseService;
    }
    public function execute(Observer $observer)
    {
        if ($this->configurationService->getConfiguration()->getSyncProducts() && $this->licenseService->getLicenseConnected()) {
            if (!$this->coreRegistry->registry('isCreatedBySync')) {
                $product = $observer->getEvent()->getProduct();
                $requestData = new stdClass();
                $requestData->Name = $product->getName();
                $requestData->Description = $product->getName();
                if (count($product->getCategoryIds()) > 0) {
                    $requestData->CategoryId = $this->categoryService->getEposnowIdById($product->getCategoryIds()[0]);
                }
                $requestData->Sku = $product->getSku();
                $requestData->CostPrice = $product->getPrice();
                $requestData->SalePrice = $product->getPrice();
                $requestData->EatOutPrice = $product->getPrice();
                $requestData->SellOnWeb = true;
                $type_id=$product->getTypeId();
                if (!$product->getId() && $type_id!='configurable') {
                    $response = $this->eposProductService->postProduct([$requestData]);
                    $product->setEposnowId($response[0]->Id);
                } else {
                    $requestData->Id = $product->getEposnowId();
                    $response = $this->eposProductService->putProduct([$requestData]);
                }
            }
        }
    }
}
