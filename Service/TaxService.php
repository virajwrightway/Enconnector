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

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\RestService;

class TaxService
{
    /**
     * @var RestService
     */
    private $restService;
    private $taxMatchFactory;
    /**
     * @var License
     */
    private $configurationService;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    public function __construct(
        RestService                            $restService,
        ConfigurationService                                $configurationService,
        \Wrightwaydigital\Enconnector\Model\TaxmatchFactory $taxMatchFactory
    )
    {
        $this->restService = $restService;
        $this->configurationService = $configurationService;
        $this->taxMatchFactory = $taxMatchFactory;
    }
    function saveTaxClassMatches($magentoClasses, $eposnowClasses)
    {
        $model = $this->taxMatchFactory->create();
        $this->truncateTable('eposnow_taxmatch');
        foreach ($magentoClasses as $key => $value) {
            if (!($eposnowClasses[$key] == '0')) {
                $magentoClass = $magentoClasses[$key];
                $eposnowClass = $eposnowClasses[$key];
                $taxMatch['magento_tax_id'] = (int)$magentoClass;
                $taxMatch['eposnow_tax_id'] = (int)$eposnowClass;
                $model->setData($taxMatch);
                $model->save();
            }
        }
    }
    function truncateTable($table)
    {
        $model = $this->taxMatchFactory->create();
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
        $connection->truncateTable($tableName);
    }
    function getMatchedTaxes()
    {
        $collection = $this->taxMatchFactory->create()->getCollection();
        $data=$collection->getData();
        return $data;
    }
}
