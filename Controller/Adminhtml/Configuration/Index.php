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

use Wrightwaydigital\Enconnector\Model\ConfigurationFactory;
use Wrightwaydigital\Enconnector\Model\LicenseFactory;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;
use Wrightwaydigital\Enconnector\Service\WebhookConfigurationService;
use Wrightwaydigital\Enconnector\Service\SyncService;
use Wrightwaydigital\Enconnector\Service\TaxService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;
use Wrightwaydigital\Enconnector\Service\RestService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;

class Index extends Action
{
    protected $resultPageFactory;
    protected $configurationService;
    protected $coreRegistry;
    /**
     * @var ConfigurationFactory
     */
    private $configurationFactory;
    private $licenseFactory;
    /**
     * @var WebhookConfigurationService
     */
    private $webhookConfigurationService;
    private $syncService;
    private $taxService;
    private $licenseService;
    private $request;
    private $restservice;
    public $apiBaseUrl = 'https://api.eposnowhq.com';
    public function __construct(
        Context                     $context,
        PageFactory                 $resultPageFactory,
        ConfigurationService        $configurationService,
        ConfigurationFactory        $configurationFactory,
        LicenseFactory              $licenseFactory,
        WebhookConfigurationService $webhookConfigurationService,
        SyncService                 $syncService,
        TaxService                  $taxService,
        RestService                 $restservice,
        LicenseService              $licenseService,
        RequestInterface $request
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->configurationService = $configurationService;
        $this->configurationFactory = $configurationFactory;
        $this->licenseFactory = $licenseFactory;
        $this->webhookConfigurationService = $webhookConfigurationService;
        $this->syncService = $syncService;
        $this->taxService = $taxService;
        $this->restservice = $restservice;
        $this->licenseService = $licenseService;
        $this->request=$request;
    }
    public function execute()
    {
        $GET = $this->request->getParams();
        $POST = $this->request->getPost();
        if (isset($GET['submit'])) {
            $model = $this->configurationFactory->create();
            $model->setId(1);
            if (isset($GET['EposnowStoreLocation'])) {
                $model->setEposnowStoreLocation($GET['EposnowStoreLocation']);
            }
            $model->setSyncStock($GET['SyncStock']);
            $model->setSyncProducts($GET['SyncProducts']);
            $model->setSyncCategories($GET['SyncCategories']);
            if (isset($GET['SyncOrdersToMagento'])) {
                $model->setData('sync_orderstoMagento', $GET['SyncOrdersToMagento']);
            }
            if (isset($GET['SyncOrdersToEposnow'])) {
                $model->setData('sync_orderstoEposnow', $GET['SyncOrdersToEposnow']);
            }
            if (isset($GET['SyncTitle'])) {
                $model->setSyncTitle($GET['SyncTitle']);
            }
            if (isset($GET['SyncDesc'])) {
                $model->setSyncDesc($GET['SyncDesc']);
            }
            $model->setDescType($GET['descType']);
            if (isset($GET['SyncCatTitle'])) {
                $model->setSyncCattitle($GET['SyncCatTitle']);
            }
            if (isset($GET['SyncCatDesc'])) {
                $model->setSyncCatdesc($GET['SyncCatDesc']);
            }
            if (isset($GET['DefaultTender'])) {
                $model->setDefaultTender($GET['DefaultTender']);
            }
            if (isset($GET['SyncPrice'])) {
                $model->setSyncPrice($GET['SyncPrice']);
            }
            if (isset($GET['LoopTimeout'])) {
                $model->setLoopTimeout($GET['LoopTimeout']);
            }
            $model->setSyncOrders($GET['SyncOrders']);
            $model->setsyncDirection($GET['syncDirection']);
            if (isset($GET['DefaultEposnowTax'])) {
                $model->setDefaultEposnowTaxClass($GET['DefaultEposnowTax']);
            }
            if (isset($GET['DefaultMagentoTax'])) {
                $model->setDefaultMagentoTaxClass($GET['DefaultMagentoTax']);
            }
            $this->configurationService->saveConfiguration($model);
            $this->taxService->saveTaxClassMatches($GET['magento_tax'], $GET['eposnow_tax']);
            if (isset($GET['magento_tax']) && isset($GET['eposnow_tax'])) {
            }
            $this->webhookConfigurationService->configureWebhooks($this->configurationService->getConfiguration());
//            }
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('eposnowconnector/configuration');
            return $resultRedirect;
        }
        if (isset($GET['resyncStock'])) {
            $model = $this->configurationService->getConfiguration();
            if ($model->getSyncStock()) {
                $model->setId(1);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('eposnowconnector/configuration/run', ['_current' => true]);
                return $resultRedirect;
            }
        }
        if (isset($POST['validate'])) {
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $data['status'] = false;
            if ($this->licenseService->validateLicense($POST)) {
                $data['status'] = true;
            }
            $resultJson->setData($data);
            return $resultJson;
        }
        if (isset($POST['reset'])) {
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $data['status'] = false;
            if ($this->licenseService->resetLicense()) {
                $data['status'] = true;
            }
            $resultJson->setData($data);
            return $resultJson;
        }

        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}
