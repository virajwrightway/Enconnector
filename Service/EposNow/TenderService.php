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

class TenderService
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
    public function getTenders()
    {
        $tenderTypes= $this->restService->get($this->apiBaseUrl . '/api/v4/TenderType');
        return $tenderTypes;
    }
    public function getCardTenderId()
    {
        foreach ($this->getTenders() as $tender) {
            if (strpos(strtolower($tender->Description), 'card') !== false || strpos(strtolower($tender->Name), 'card') !== false) {
                return $tender->Id;
            }
        }
        return null;
    }
}
