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

namespace Wrightwaydigital\Enconnector\Service\EposNow;

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\RestService;

class StockService
{
    /**
     * @var RestService
     */
    private $restService;
    /**
     * @var License
     */
    private $configurationService;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    public function __construct(
        RestService          $restService,
        ConfigurationService $configurationService
    )
    {
        $this->restService = $restService;
        $this->configurationService = $configurationService;
    }

    public function getStockByProductId($eposId)
    {
        $stock = 0;
        $response = $this->restService->get($this->apiBaseUrl . '/api/V4/ProductStock/product/' . $eposId);
        if (!empty($response)) {
            foreach ($response as $location) {
                $defaultLocation = (int)$this->configurationService->getConfiguration()->getEposnowStoreLocation();
                $eposLocation = $location->LocationId;
                if ($eposLocation == $defaultLocation) {
                    $batches = $location->ProductStockBatches;
                    if (count($batches) > 0) {
                        foreach ($batches as $batch) {
                            $stock += $batch->CurrentStock;
                        }
                    }
                }
            }
        }
        $stock += $this->getStockFromMasterProduct($eposId);
        return $stock;
    }
    public function addStock($eposId, $amount)
    {
        $request = array(
            'ProductId' => $eposId,
            'LocationId' => $this->configurationService->getConfiguration()->getEposnowStoreLocation(),
            'ChangeInStock' => $amount
        );
        $response = $this->restService->post($this->apiBaseUrl . '/api/v4/ProductStock/Add', $request);
        return $response;
    }
    public function removeStock($eposId, $amount)
    {
        $request = array(
            'ProductId' => $eposId,
            'LocationId' => $this->configurationService->getConfiguration()->getEposnowStoreLocation(),
            'ChangeInStock' => $amount
        );
        $response = $this->restService->post($this->apiBaseUrl . '/api/v4/ProductStock/Remove', $request);
        return $response;
    }
    public function updateStock($eposId, $qty)
    {
        if (isset($eposId) && $eposId > 0) {
            $current = $this->getStockByProductId($eposId);
            $difference = $qty - $current;
            if ($difference > 0) {
                $this->addStock($eposId, $difference);
            } elseif ($difference < 0) {
                $this->removeStock($eposId, -$difference);
            }
        }
    }
    public function formatStockItem($eposId, $qty)
    {
        if (isset($eposId) && $eposId > 0) {
            $current = $this->getStockByProductId($eposId);
            $difference = $qty - $current;
            $request = array(
                'ProductId' => $eposId,
                'LocationId' => $this->configurationService->getConfiguration()->getEposnowStoreLocation(),
                'ChangeInStock' => $difference
            );
            return $request;
        } else {
            return false;
        }
    }
    public function bulkPostStock($request)
    {
        $response = $this->restService->post($this->apiBaseUrl . '/api/v4/ProductStock/Add', $request);
        return $response;
    }
    private function getStockFromMasterProduct($eposId)
    {
        $response = $this->restService->get($this->apiBaseUrl . '/api/V2/ProductComposition?PurchasedProductID=' . $eposId);
        $masterStocks = [];
        if ($response != null) {
            foreach ($response as $composition) {
                $masterStock = $this->getStockByProductId($composition->MasterProductID);
                $masterResponse = $this->restService->get($this->apiBaseUrl . '/api/V2/ProductStock?productId=' . $composition->MasterProductID);
                $masterProduct = $this->restService->get($this->apiBaseUrl . '/api/v4/Product/' . $composition->MasterProductID);
                if (!empty($masterResponse->data)) {
                    array_push($masterStocks, ($masterResponse[0]->CurrentVolume + ($masterProduct->VolumeOfSale * $masterStock)) / $composition->Amount);
                } else {
                    array_push($masterStocks, ($masterProduct->VolumeOfSale * $masterStock) / $composition->Amount);
                }
            }
        }
        if (empty($masterStocks)) {
            return 0;
        }
        return min($masterStocks);
    }
}
