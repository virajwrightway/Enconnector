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

use Wrightwaydigital\Enconnector\Model\Configuration;
use Wrightwaydigital\Enconnector\Service\EposNow\WebhookEnum;
use Wrightwaydigital\Enconnector\Service\EposNow\WebhookSubscriptionService;
use Magento\Store\Model\StoreManagerInterface;

class WebhookConfigurationService
{
    private $webhookService;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    public function __construct(
        WebhookSubscriptionService $webhookService,
        StoreManagerInterface      $storeManager
    )
    {
        $this->webhookService = $webhookService;
        $this->storeManager = $storeManager;
    }
    public function configureWebhooks(Configuration $config)
    {
        // Category webhooks
        $this->configureWebhook(WebhookEnum::CreateCategory, $config->getSyncCategories());
        $this->configureWebhook(WebhookEnum::UpdateCategory, $config->getSyncCategories());
        $this->configureWebhook(WebhookEnum::DeleteCategory, $config->getSyncCategories());
        // Stock webhooks
        $this->configureWebhook(WebhookEnum::CreateProductStockDetail, $config->getSyncStock());
        $this->configureWebhook(WebhookEnum::UpdateProductStockDetail, $config->getSyncStock());
        $this->configureWebhook(WebhookEnum::DeleteProductStockDetail, $config->getSyncStock());
        // Transaction webhooks
        $this->configureWebhook(WebhookEnum::CompleteTransaction, $config->getSynOrder());
        $this->configureWebhook(WebhookEnum::CreateOrderedTransaction, $config->getSynOrder());
        $this->configureWebhook(WebhookEnum::CancelOrderedTransaction, $config->getSynOrder());
        // Products webhooks
        $this->configureWebhook(WebhookEnum::CreateProduct, $config->getSyncProducts());
        $this->configureWebhook(WebhookEnum::UpdateProduct, $config->getSyncProducts());
        $this->configureWebhook(WebhookEnum::DeleteProduct, $config->getSyncProducts());
        $this->configureWebhook(WebhookEnum::CreateProductDetail, $config->getSyncProducts());
        $this->configureWebhook(WebhookEnum::UpdateProductDetail, $config->getSyncProducts());
        $this->configureWebhook(WebhookEnum::DeleteProductDetail, $config->getSyncProducts());
        if ($config->getSyncCategories() || $config->getSyncStock() || $config->getSyncProducts()) {
            $this->webhookService->setBaseUrl(rtrim($this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), "/"));
//            $this->webhookService->setBaseUrl("http://2-4-4.server.magento.wrightwaycloud.co.uk/");
        }
    }
    private function configureWebhook($webhook, $state)
    {
        $isSubscribed = $this->webhookService->isSubscribed($webhook);
        if ($state && !$isSubscribed) {
            $this->webhookService->subscribe($webhook);
        } else if (!$state && $isSubscribed) {
            $this->webhookService->unsubscribe(array($webhook));
        }
    }
}
