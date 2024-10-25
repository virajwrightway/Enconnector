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

namespace Wrightwaydigital\Enconnector\Model;

use Wrightwaydigital\Enconnector\Api\WebhookInterface;
use Wrightwaydigital\Enconnector\Logger\Logger;
use Wrightwaydigital\Enconnector\Service\AuthorizationService;
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Wrightwaydigital\Enconnector\Service\ProductService;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;
use Wrightwaydigital\Enconnector\Service\StockService;
use Wrightwaydigital\Enconnector\Service\RestService;
use Zend_Controller_Request_Exception;

//use \Magento\Framework\HTTP\LaminasClient;
use \Magento\Framework\App\RequestInterface;

class Webhook implements WebhookInterface
{
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var StockService
     */
    private $stockService;
    /**
     * @var CategoryService
     */
    private $categoryService;
    /**
     * @var ProductService
     */
    private $productService;
    /**
     * @var License
     */
    private $configurationService;
    private $licenseService;
    /**
     * @var AuthorizationService
     */
    private $authorizationService;
    /**
     * @var RestService
     */
    private $restService;
    private $request;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    public function __construct(
        Logger               $logger,
        ConfigurationService $configurationService,
        StockService         $stockService,
        CategoryService      $categoryService,
        ProductService       $productService,
        AuthorizationService $authorizationService,
        RestService          $restService,
        RequestInterface     $request,
        LicenseService       $licenseService
    )
    {
        $this->logger = $logger;
        $this->stockService = $stockService;
        $this->categoryService = $categoryService;
        $this->productService = $productService;
        $this->configurationService = $configurationService;
        $this->authorizationService = $authorizationService;
        $this->restService = $restService;
        $this->request = $request;
        $this->licenseService = $licenseService;
    }
    /**
     * Parses epos now webhook
     *
     * @return mixed return
     * @throws Zend_Controller_Request_Exception
     * @api
     */
    public function parse()
    {
        $object = $this->request->getHeader('Epos-Object');
        $action = $this->request->getHeader('Epos-Action');
        $token = $this->request->getHeader('Authorization');
        $modelJson = $this->request->getContent();
        $model = json_decode($modelJson, true, 512, JSON_OBJECT_AS_ARRAY);
        $model = (object)$model;
        $this->logger->info("Webhook received" . ':' . $action . ':' . print_r($model, 1));
        if (!$this->authorizationService->matchToken($this->licenseService->getLicense()->getEposnowToken(), $token)) {
            $this->logger->info("Token" . ':' . $action . ':' . print_r($token, 1));
            return false;
        }
        switch ($object) {
            case 'ProductStockDetail':
                if ($this->configurationService->getConfiguration()->getSyncStock()) {
                    $this->stockService->updateStock($model);
                    $response = $this->restService->get($this->apiBaseUrl . "/api/V2/ProductComposition?masterproductid=" . $model->ProductId);
                    foreach ($response as $productComp) {
                        $this->productService->updateStock($productComp->PurchasedProductID);
                    }
                }
                break;
            case 'Category':
                if ($this->configurationService->getConfiguration()->getSyncCategories()) {
                    switch ($action) {
                        case 'Create':
                            $parentId = $this->categoryService->getIdByEposnowId($model->ParentId);
                            if ($parentId != null) {
                                $this->categoryService->createCategory($model, $this->categoryService->getIdByEposnowId($model->ParentId));
                            } else {
                                $this->categoryService->createCategory($model, 2);
                            }
                            break;
                        case 'Update':
                            $this->categoryService->updateCategory($model);
                            break;
                        case 'Delete':
                            $this->categoryService->deleteCategory($this->categoryService->getIdByEposnowId($model->Id));
                            break;
                    }
                }
                break;
            case 'Product':
                if ($this->configurationService->getConfiguration()->getSyncProducts()) {
                    switch ($action) {
                        case 'Create':
                            $this->productService->createProduct($model);
                            break;
                        case 'Update':
                            if ($this->productService->productExists($model->Id)) {
                                return $this->productService->updateProduct($model);
                            } else {
                                $this->productService->createProduct($model);
                            }
                            break;
                        case 'Delete':
                            $this->productService->deleteProduct($this->productService->getIdByEposnowId($model->Id));
                            break;
                    }
                }
                break;
        }
        return true;
    }
}
