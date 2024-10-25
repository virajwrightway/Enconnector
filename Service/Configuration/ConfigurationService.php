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

namespace Wrightwaydigital\Enconnector\Service\Configuration;

use Wrightwaydigital\Enconnector\Model\Configuration;
use Wrightwaydigital\Enconnector\Model\ConfigurationFactory;
use Wrightwaydigital\Enconnector\Model\SyncprocessFactory;
use mysql_xdevapi\Exception;

class ConfigurationService
{
    /**
     * @var ConfigurationFactory
     */
    private $configurationFactory;
    /**
     * @var Configuration
     */
    private $configuration;
    private $syncprocessFactory;
    public function __construct(
        ConfigurationFactory $configurationFactory,
        SyncprocessFactory   $syncprocess
    )
    {
        $this->configurationFactory = $configurationFactory;
        $this->syncprocessFactory = $syncprocess;
    }
    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        if ($this->configuration == null) {
            $this->configuration = $this->loadConfiguration();
        }
        return $this->configuration;
    }
    public function saveConfiguration(Configuration $config)
    {
        try {
            $return = $config->save();
            $this->configuration = $config;
        } catch (\Exception $e) {
            return $e;
        }
    }
    /**
     * @return Configuration
     */
    private function loadConfiguration()
    {
        $model = $this->configurationFactory->create()->load(1);
        return $model;
    }
    public function getSyncprocess()
    {
        $this->configuration = $this->loadSyncproces();
        return $this->configuration;
    }
    public function loadSyncprocess()
    {
        $model = $this->syncprocessFactory->create();
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
//        $sql = "SELECT * FROM " . $tableName . " ORDER BY id DESC LIMIT 1";
        $sql = "SELECT * FROM " . $tableName . " ORDER BY priority ASC";
        $result = $connection->fetchAll($sql);
        return $result;
    }
    public function loadSyncprocessByDirection($direction)
    {
        $model = $this->syncprocessFactory->create();
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
//        $sql = "SELECT * FROM " . $tableName . " ORDER BY id DESC LIMIT 1";
        $sql = "SELECT * FROM " . $tableName . " WHERE direction = '" . $direction . "' ORDER BY priority ASC";
        $result = $connection->fetchAll($sql);
        $data = [];
        foreach ($result as $sync) {
            $data[$sync['type']] = $sync;
        }
        if ($data['product']['direction'] == "eposnowconnector") {
            $data['product']['synced_count'] = $data['product']['synced_count'] + $data['variant']['synced_count'];
        }
        return $data;
    }
    public
    function flagSyncTypeComplete($type)
    {
        $collection = $this->syncprocessFactory->create()->getCollection();
        $collection->addFieldToFilter('type', $type);
        $collection->setData('status', 1)->save();
        return true;
    }
    public
    function updateSyncProcess($data)
    {
        //        $collection = $this->syncprocessFactory->create()->getCollection();
//        $collection->addFieldToFilter('type', $type);
//        $collection->setData($data)->save();
        $where = ['type = ?' => $data['type'], 'direction= ?' => $data['direction']];
        $model = $this->syncprocessFactory->create();
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
        $connection->update($tableName, $data, $where);
//
//        $model = $this->syncprocessFactory->create();
//        $connection = $model->getResource()->getConnection();
//        $tableName = $model->getResource()->getMainTable();
//        $sql = "UPDATE " . $tableName . " SET status = 0";
//        $connection->query($sql);
//        return $this->resumeSync();
        return true;
    }
    public
    function resumeSync($direction)
    {
        $syncs = $this->loadSyncprocess();
        $shouldSyncCat = $this->getConfiguration()->getSyncCategories();
        $shouldSyncProd = $this->getConfiguration()->getSyncProducts();
        $shouldSyncStck = $this->getConfiguration()->getSyncStock();
        $shouldSyncOrdr = $this->getConfiguration()->getSyncOrders();
        $completeCount = 0;
        $data = [];
        foreach ($syncs as $sync) {
            if ($sync['direction'] == $direction) {
                if ($sync['type'] == 'category' && $sync['status'] == 0 && $shouldSyncCat) {
                    return $sync;
                } elseif ($sync['type'] == 'product' && $sync['status'] == 0 && $shouldSyncProd) {
                    return $sync;
                } elseif ($sync['type'] == 'stock' && $sync['status'] == 0 && $shouldSyncStck) {
                    return $sync;
                } elseif ($sync['type'] == 'order' && $sync['status'] == 0 && $shouldSyncOrdr) {
                    return $sync;
                } elseif ($sync['type'] == 'variant' && $sync['status'] == 0 && $shouldSyncProd) {
                    return $sync;
                } else {
                    $completeCount++;
                }
            }
        }
        if ($completeCount == 5 && $direction == 'eposnowconnector') {
            $data['status'] = 1;
        } elseif ($completeCount == 4 && $direction == 'magento') {
            $data['status'] = 1;
        }
        return $data;
    }
    public function checkSyncStarted($direction)
    {
        $syncs = $this->loadSyncprocess();
        foreach ($syncs as $sync) {
            if ($sync['direction'] == $direction) {
                if ($sync['synced_count'] > 0) {
                    return true;
                }
            }
        }
        return false;
    }
    public function setInitialSync()
    {
        $model = $this->syncprocessFactory->create();
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
        $sql = "UPDATE " . $tableName . " SET status = 0, synced_count = 0, last_page = 0, last_id = 0, last_fetched = 0, total_count = 0";
        $connection->query($sql);
    }
    public function getRawConfiguration($field)
    {
        $model = $this->configurationFactory->create();
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
        $sql = "SELECT " . $field . " FROM " . $tableName;
        $result = $connection->fetchAll($sql);
        return $result[0][$field];
    }
    public function getLicenseConnected()
    {
        if ($this->licenseService->getLicense()) {
            return true;
        } else {
            return false;
        }
    }
}
