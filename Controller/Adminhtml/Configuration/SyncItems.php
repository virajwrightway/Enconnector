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
use Wrightwaydigital\Enconnector\Cron\CronSync;
use Wrightwaydigital\Enconnector\Service\ProductService;
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Wrightwaydigital\Enconnector\Service\WebhookConfigurationService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\RequestInterface;


class SyncItems extends Action
{
    protected $configurationService;
    protected $webhookConfigurationService;
    protected $webhookSubscriptionService;
    protected $resultPageFactory;
    /**
     * @var SyncService
     */
    private $syncService;
    private $cronSync;
    private $productService;
    private $categoryservice;
    private $request;

    public function __construct(
        Context                     $context,
        PageFactory                 $resultPageFactory,
        ConfigurationService        $configurationService,
        WebhookConfigurationService $webhookConfigurationService,
        WebhookSubscriptionService  $webhookSubscriptionService,
        SyncService                 $syncService,
        ProductService              $productService,
        CategoryService             $categoryService,
        CronSync                    $cronSync,
        RequestInterface $request

    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->configurationService = $configurationService;
        $this->webhookConfigurationService = $webhookConfigurationService;
        $this->webhookSubscriptionService = $webhookSubscriptionService;
        $this->syncService = $syncService;
        $this->productService = $productService;
        $this->categoryservice = $categoryService;
        $this->cronSync = $cronSync;
        $this->request=$request;
    }
    public function execute()
    {
        $POST = $this->request->getPost();

        if ($POST['method'] == 'resync') {
            $this->configurationService->setInitialSync();
        }
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $direction = $this->configurationService->getConfiguration()->getSyncDirection();
        $this->configurationService->getConfiguration()->setData('should_cron', 0)->save();
        $this->configurationService->getConfiguration()->setData('is_ajax', 1)->save();
        $data = [];
        $lastSync = $this->configurationService->resumeSync($direction);
        if (isset($lastSync['status']) && $lastSync['status'] == 1) {
            $data['syncProcess'] = $this->configurationService->loadSyncprocessByDirection($direction);
            $data['status'] = 'done';
            $resultJson->setData($data);
            return $resultJson;
        } else {
            $type = $lastSync['type'];
            if ($direction == 'magento') {
                if ($type == 'product') {
                    $data['result'] = $this->syncService->createMagentoProducts($lastSync);
                } elseif ($type == 'category') {
                    $data['result'] = $this->syncService->createMagentoCategories($lastSync);
                } elseif ($type == 'order') {
                    $data['result'] = $this->syncService->createMagentoOrders($lastSync);
                } elseif ($type == 'stock') {
                    $data['result'] = $this->syncService->syncMagentoStock($lastSync);
                }
            } elseif ($direction == 'eposnowconnector') {
                if ($type == 'product') {
                    $data['result'] = $this->syncService->createEposproducts($lastSync);
                } elseif ($type == 'category') {
                    $data['result'] = $this->syncService->createEposCategories($lastSync);
                } elseif ($type == 'variant') {
                    $data['result'] = $this->syncService->createEposnowVariants($lastSync);
                } elseif ($type == 'order') {
                    $data['result'] = $this->syncService->createEposNowOrders($lastSync);
                } elseif ($type == 'stock') {
                    $data['result'] = $this->syncService->syncEposnowStock($lastSync);
                }
            }
            $data['syncProcess'] = $this->configurationService->loadSyncprocessByDirection($direction);
            if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                $data['status'] = 403;
            }
            $this->configurationService->getConfiguration()->setData('is_ajax', 0)->save();
            $this->configurationService->getConfiguration()->setData('should_cron', 1)->save();
            $resultJson->setData($data);
            return $resultJson;
        }
    }
    public function getProductData()
    {
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $data = 'success';
        $resultJson->setData($data);
    }
}
