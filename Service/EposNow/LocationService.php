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

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;
use Wrightwaydigital\Enconnector\Service\RestService;

class LocationService
{
    /**
     * @var RestService
     */
    private $restService;
    private $licenseService;
    /**
     * @var License
     */
    private $configurationService;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    public function __construct(
        RestService          $restService,
        ConfigurationService $configurationService,
        LicenseService       $licenseService
    )
    {
        $this->restService = $restService;
        $this->configurationService = $configurationService;
        $this->licenseService = $licenseService;
    }
    public function getLocations()
    {
        if ($this->licenseService->getLicense()->getEposnowToken() == null) {
            return null;
        }
        return $this->restService->get($this->apiBaseUrl . '/api/v4/Location');
    }
}
