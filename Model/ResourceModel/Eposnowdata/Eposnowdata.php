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

namespace Wrightwaydigital\Enconnector\Model\ResourceModel\Eposnowdata;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Eposnowdata extends AbstractCollection
{
//    protected $_idFieldName = 'id';
//    protected $_eventPrefix = 'eposnow_configuration_configuration_collection';
//    protected $_eventObject = 'configuration_collection';
    protected function _construct()
    {
        $this->_init(\Wrightwaydigital\Enconnector\Model\Eposnowdata::class, \Wrightwaydigital\Enconnector\Model\ResourceModel\Eposnowdata::class);
    }


}
