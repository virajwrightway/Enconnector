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

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Wrightwaydigital\Enconnector\Service\EposNow\EposCustomerService;

//use Wrightwaydigital\Enconnector\Service\CustomerService;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use stdClass;

class CustomerSaveObserver implements ObserverInterface
{
    protected $eposCustomerService;
    public function __construct(
        EposCustomerService $eposCustomerService
    )
    {
        $this->eposCustomerService = $eposCustomerService;
    }
    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer->getId()) {
            $postData = new stdClass();
            $postData->Name = $customer->getName();
            $response = $this->eposCustomerService->postCustomer([$postData]);
        } else {
            $postData = new stdClass();
            $customer_Id = $customer->getId();
            $postData->Forename = $customer->getfirstname();
            $postData->Surname = $customer->getlastname();
            $postData->EmailAddress = $customer->getEmail();
            $response = $this->eposCustomerService->postCustomer([$postData]);
        }
        // }
    }
}
