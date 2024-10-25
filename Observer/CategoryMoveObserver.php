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
use Wrightwaydigital\Enconnector\Service\EposNow\EposCategoryService;
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;


class CategoryMoveObserver implements ObserverInterface
{
    protected $configurationService;
    protected $categoryService;
    protected $eposCategoryService;
    public $licenseService;
    public function __construct(
        ConfigurationService             $configurationService,
        CategoryService     $categoryService,
        EposCategoryService $eposCategoryService,
        LicenseService                         $licenseService

    )
    {
        $this->configurationService = $configurationService;
        $this->categoryService = $categoryService;
        $this->eposCategoryService = $eposCategoryService;
        $this->licenseService = $licenseService;

    }
    public function execute(Observer $observer)
    {
        if ($this->configurationService->getConfiguration()->getSyncCategories() && $this->licenseService->getLicenseConnected()) {
            $category = $observer->getEvent()->getCategory();
            if ($category->getEposnowId() == null) {
                $postData = [
                    [
                        "ParentId" => $this->categoryService->getEposnowIdById($category->getParentCategory()->getId()),
                        "Name" => $category->getName()
                    ]
                ];
                $response = $this->eposCategoryService->postCategory($postData, true);
                $category->setEposnowId($response[0]->Id);
            } else {
                $putData = [
                    [
                        "Id" => $category->getEposnowId(),
                        "ParentId" => $this->categoryService->getEposnowIdById($category->getParentCategory()->getId()),
                        "Name" => $category->getName()
                    ]
                ];
                $response = $this->eposCategoryService->putCategory($putData, true);
            }
        }
    }
}
