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
use Magento\Store\Model\StoreManager;

class WebhookSubscriptionService
{
    /**
     * @var RestService
     */
    private $restService;
    /**
     * @var StoreManager
     */
    private $storeManager;
    private $webhooks;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    public function __construct(
        RestService  $restService,
        StoreManager $storeManager
    )
    {
        $this->restService = $restService;
        $this->storeManager = $storeManager;
    }
    public function setBaseUrl($url)
    {
        $data=[];
        $data[]=$url;
        $this->restService->put($this->apiBaseUrl . '/api/v4/Webhook', $url);
    }
    public function subscribe($webhook)
    {
        $request = array(array(
            "EventTypeId" => $webhook,
            "Path" => '/rest/V4/eposnowconnector/webhook'
        ));
        $this->restService->post($this->apiBaseUrl . '/api/v4/webhook', $request);
    }
    public function unsubscribe($webhook)
    {
        $this->restService->delete($this->apiBaseUrl . '/api/v4/webhook', $webhook);
    }
    public function isSubscribed($webhook)
    {
        if (!empty($this->getWebhooks())) {
            $array = array_map(function ($value) {
                return $value->EventTypeId;
            }, $this->getWebhooks());
            return in_array($webhook, $array);
        } else {
            return false;
        }
    }
    private function getWebhooks()
    {
        if ($this->webhooks == null) {
            $this->webhooks = $this->restService->get($this->apiBaseUrl . '/api/v4/webhook');
        }
        if (!empty($this->webhooks->Triggers)) {
            return $this->webhooks->Triggers;
        }
    }
    public function isAuthorizationValid()
    {
        $this->webhooks = $this->restService->get($this->apiBaseUrl . '/api/v4/webhook');
        if (!empty($this->webhooks->Message) && $this->webhooks->Message == "Error has been logged and will be investigated.") {
            return false;
        } else {
            return true;
        }
    }
}
