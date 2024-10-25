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

use Wrightwaydigital\Enconnector\Service\RestService;

class EposProductService
{
    /**
     * @var RestService
     */
    private $restService;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    public function __construct(
        RestService $restService
    )
    {
        $this->restService = $restService;
    }
    public function getProduct()
    {
        $fullResponse = array();
        $pageNumber = 1;
        do {
            $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Product?page=' . $pageNumber++);
            if (is_array($response)) {
                $fullResponse = array_merge($fullResponse, $response);
            }
        } while (!empty($response) || $response == "You have reached your maximum API limit.");
        return $fullResponse;
    }
    public function getProductByPage($page)
    {
        $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Product?page=' . $page);
        return $response;
    }
    public function deleteProduct($data)
    {
        $response = $this->restService->delete($this->apiBaseUrl . '/api/v4/Product', $data);
        return $response;
    }
    public function postProduct($data)
    {
        $response = $this->restService->post($this->apiBaseUrl . '/api/v4/Product', $data);
        return $response;
    }
    public function putProduct($data)
    {
        $response = $this->restService->put($this->apiBaseUrl . '/api/v4/Product', $data);
        return $response;
    }
    public function getEposProductStats()
    {
        $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Product/Stats');
        return $response;
    }
    public function getEposColor()
    {
        $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Colour');
        return $response;
    }
    public function postProducts($data)
    {
        $response = [];
        if (isset($data['post'])) {
            $post = $this->postProduct($data['post']);
            if (isset($post) && is_array($post)) {
                $response = array_merge($response, $post);
            }
        }
        if (isset($data['put'])) {
            $put = $this->putProduct($data['put']);
            if (isset($put) && is_array($put)) {
                $response = array_merge($response, $put);
            }
        }
        return $response;
    }
}
