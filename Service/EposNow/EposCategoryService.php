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

class EposCategoryService
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
    public function getCategory()
    {
        $fullResponse = array();
        $pageNumber = 1;
        do {
            $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Category?page=' . $pageNumber++);
            if (is_array($response)) {
                $fullResponse = array_merge($fullResponse, $response);
            }
        } while (!empty($response));
        return $fullResponse;
    }
    public function getCategoryByPage($page)
    {
        $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Category?page=' . $page);
        return $response;
//        if (is_array($response)) {
//            return $response;
//        } else {
//            return false;
//        }
    }
    public function deleteCategory($data)
    {
        $response = $this->restService->delete($this->apiBaseUrl . '/api/v4/Category', $data);
        return $response;
    }
    public function postCategory($data)
    {
        $response = $this->restService->post($this->apiBaseUrl . '/api/v4/Category', $data);
        return $response;
    }
    public function putCategory($data)
    {
        $response = $this->restService->put($this->apiBaseUrl . '/api/v4/Category', $data);
        return $response;
    }
    public function postCategories($data)
    {
        $response = [];
        if (isset($data['post'])) {
            $post = $this->postCategory($data['post']);
            if (isset($post) && is_array($post)) {
                $response = array_merge($response, $post);
            }
        }
        if (isset($data['put'])) {
            $put = $this->putCategory($data['put']);
            if (isset($put) && is_array($put)) {
                $response = array_merge($response, $put);
            }
        }
        return $response;
    }
}
