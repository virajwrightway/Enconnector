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

namespace Wrightwaydigital\Enconnector\Service;

use Wrightwaydigital\Enconnector\Logger\Logger;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;

//use Wrightwaydigital\Enconnector\Service\License\LicenseService;
use Wrightwaydigital\Enconnector\Model\LicenseFactory;

class RestService
{
    private $curl;
    private $apiKey;
    private $apiSecret;
    private $licenseFactory;
    private $configurationService;
    /**
     * @var Logger
     */
    private $logger;
    protected $messageManager;
    private $timeout_code = 403;
    public function __construct(
        Curl                                        $curl,
        ConfigurationService                        $configurationService,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        Logger                                      $logger,
        LicenseFactory                              $licenseFactory
    )
    {
        $this->curl = $curl;
        $this->licenseFactory = $licenseFactory;
        $this->apiKey = $this->generateKeySecret('apiKey');
        $this->apiSecret = $this->generateKeySecret('apiSecret');
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->configurationService = $configurationService;
    }
    public function generateKeySecret($name)
    {
        $model = $this->licenseFactory->create()->load(1);
        $api_token = $model->getEposnowToken();
        if (isset($api_token)) {
            $decodedToken = base64_decode($api_token);
            $tokenParts = explode(':', $decodedToken);
            $decodedData = [];
            if (count($tokenParts) == 2) {
                $decodedData['apiKey'] = $tokenParts[0];
                $decodedData['apiSecret'] = $tokenParts[1];
                return $decodedData[$name];
            } else {
                return false;
            }
        }
        return false;
    }
    public function get($url, $isAssociative = false)
    {
        $this->curl->setCredentials($this->apiKey, $this->apiSecret);
        $this->curl->get($url);
        if ($this->curl->getStatus() == $this->timeout_code) {
            $this->configurationService->getConfiguration()->setData('api_locked', 1)->save();
        }
        $result = $this->curl->getBody();
        $result = json_decode($result, $isAssociative);
        $this->logRequest('get', $url . ' response status: ' . $this->curl->getStatus(), null, $this->curl->getBody());
        return $result;
    }
    public function put($url, $data, $isAssociative = false)
    {
        $this->curl->setCredentials($this->apiKey, $this->apiSecret);
        $this->curl->put($url, $data);
        $result = $this->curl->getBody();
        if ($this->curl->getStatus() == $this->timeout_code) {
            $this->configurationService->getConfiguration()->setData('api_locked', 1)->save();
        }
        $this->logRequest('put', $url, [], $result);
        return json_decode($result);
    }
    public function patch($url, $data, $isAssociative = false)
    {
        $this->curl->setCredentials($this->apiKey, $this->apiSecret);
        $this->curl->patch($url, $data);
        $result = $this->curl->getBody();
        if ($this->curl->getStatus() == $this->timeout_code) {
            $this->configurationService->getConfiguration()->setData('api_locked', 1)->save();
        }
        $this->logRequest('patch', $url, $data, $result);
        return json_decode($result, $isAssociative);
    }
    public function post($url, $data, $isAssociative = false)
    {
        $this->curl->setCredentials($this->apiKey, $this->apiSecret);
        $this->curl->post($url, $data);
        $result = $this->curl->getBody();
        if ($this->curl->getStatus() == $this->timeout_code) {
            $this->configurationService->getConfiguration()->setData('api_locked', 1)->save();
        }
        $this->logRequest('post', $url, $data, $result);
        if ($result = json_decode($result, $isAssociative)) {
            return $result;
        }
        return false;
    }
    public function delete($url, $data, $isAssociative = false)
    {
        $this->curl->setCredentials($this->apiKey, $this->apiSecret);
        $this->curl->delete($url, $data);
        $result = $this->curl->getBody();
        if ($this->curl->getStatus() == $this->timeout_code) {
            $this->configurationService->getConfiguration()->setData('api_locked', 1)->save();
        }
        $this->logRequest('delete', $url, $data, $result);
        return json_decode($result, $isAssociative);
    }
    private function logRequest($method, $url, $data, $result)
    {
        $this->logger->info('Rest Request ,Method:' . $method . "URL:" . $url . json_encode($data));
    }
    public function requestLicenseValid($license, $data)
    {
        $apiUrl = 'https://ehub.wrightwaysystems.com/api/econn/connect/';
        $this->curl->addHeader("Authorization", "Basic {$license}");
        $this->curl->post($apiUrl, $data);
        $result = $this->curl->getBody();
        $result = json_decode($result);
//        var_dump($result);die();
        $this->logRequest('post', $apiUrl, $data, $result);
        return $result;
    }
    public function requestProfile($license, $data)
    {
        $apiUrl = 'https://ehub.wrightwaysystems.com/api/econn/profile/';
        $this->curl->addHeader("Authorization", "Basic {$license}");
        $this->curl->post($apiUrl, $data);
        $result = $this->curl->getBody();
        if ($result = json_decode($result)) {
            $result->status = $this->curl->getStatus();
            $this->logRequest('post', $apiUrl, $data, $result);
            return $result;
        } else {
            return false;
        }
    }
    public function resetKey($license, $data)
    {
        $apiUrl = 'https://ehub.wrightwaysystems.com/api/econn/disconnect/';
        $this->curl->addHeader("Authorization", "Basic {$license}");
        $this->curl->post($apiUrl, $data);
        $result = $this->curl->getBody();
        $result = json_decode($result);
        $result->status = $this->curl->getStatus();
        $this->logRequest('post', $apiUrl, $data, $result);
        return $result;
    }
    public function checkApiLock($url)
    {
        $this->curl->setCredentials($this->apiKey, $this->apiSecret);
        $this->curl->get($url);
        $status = $this->curl->getStatus();
        if ($status == $this->timeout_code) {
            return true;
        } else {
            return false;
        }
    }

    public function getTokenInformation(){

    }
}
