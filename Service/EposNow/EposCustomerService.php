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

class EposCustomerService
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
    public function getCustomers()
    {
        $fullResponse = array();
        $pageNumber = 1;
        do {
            $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Customer?page=' . $pageNumber++);
            if (is_array($response)) {
                $fullResponse = array_merge($fullResponse, $response);
            }
        } while (!empty($response));
        return $fullResponse;
    }
    public function getCustomer($customerId)
    {
        $fullResponse = array();
        if ($customerId > 0) {
            $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Customer/' . $customerId);
            if (is_array($response)) {
                $fullResponse = array_merge($fullResponse, $response);
            }
            return $fullResponse;
        } else {
            return '';
        }
    }
    public function deleteCustomer($data)
    {
        $response = $this->restService->delete($this->apiBaseUrl . '/api/v4/Customer', $data);
        return $response;
    }
    public function postCustomer($data)
    {
        $response = $this->restService->post($this->apiBaseUrl . '/api/v4/Customer', $data);
        return $response;
    }
    public function putCustomer($data)
    {
        $response = $this->restService->put($this->apiBaseUrl . '/api/v4/Customer', $data);
        return $response;
    }
}
