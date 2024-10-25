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

namespace Wrightwaydigital\Enconnector\Controller\Adminhtml\Configuration;

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\EposNow\WebhookSubscriptionService;
use Wrightwaydigital\Enconnector\Service\SyncService;
use Wrightwaydigital\Enconnector\Service\WebhookConfigurationService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Wrightwaydigital\Enconnector\Service\RestService;

class validateKeys extends Action
{
    protected $configurationService;
    protected $webhookConfigurationService;
    protected $webhookSubscriptionService;
    protected $resultPageFactory;
    private $restService;
    /**
     * @var SyncService
     */
    private $syncService;
    public function __construct(
        Context                     $context,
        PageFactory                 $resultPageFactory,
        ConfigurationService        $configurationService,
        WebhookConfigurationService $webhookConfigurationService,
        WebhookSubscriptionService  $webhookSubscriptionService,
        SyncService                 $syncService,
        RestService $restService,
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->configurationService = $configurationService;
        $this->webhookConfigurationService = $webhookConfigurationService;
        $this->webhookSubscriptionService = $webhookSubscriptionService;
        $this->syncService = $syncService;
        $this->restService=$restService;
    }
    public function execute()
    {
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response = $this->restService->get($this->apiBaseUrl . '/api/v4/TokenInfo');
        if (is_array($response)) {
            $data['status'] = true;
        } else {
            $data['status'] = true;
        }
        $resultJson->setData($data);
        return $resultJson;
    }
}
