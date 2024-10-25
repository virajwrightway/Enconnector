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

namespace Wrightwaydigital\Enconnector\Observer;

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\EposNow\TransactionService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;

class OrderCancelObserver implements ObserverInterface
{
    /**
     * @var License
     */
    private $configurationService;
    /**
     * @var TransactionService
     */
    private $transactionService;
    public $licenseService;
    public function __construct(
        ConfigurationService            $configurationService,
        TransactionService $transactionService,
        LicenseService                         $licenseService
    )
    {
        $this->configurationService = $configurationService;
        $this->transactionService = $transactionService;
        $this->licenseService = $licenseService;
    }
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $syncOrdersToEposnow=$this->configurationService->getConfiguration()->getSyncOrderstoEposnow();
        if ($this->configurationService->getConfiguration()->getSyncOrders() && $this->licenseService->getLicenseConnected()) {
            $this->transactionService->delete($order);
        }
    }
}
