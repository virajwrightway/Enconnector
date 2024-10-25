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

namespace Wrightwaydigital\Enconnector\Cron;

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\SyncService;
use Wrightwaydigital\Enconnector\Logger\Logger;
use Wrightwaydigital\Enconnector\Service\RestService;
use Magento\Framework\Controller\ResultFactory;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;
use Magento\Cron\Model\ScheduleFactory;

// use Psr\Log\LoggerInterface;
class CronSync
{
    protected $configurationService;
    private $syncService;
    private $logger;
    private $restService;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    public $licenseService;
    protected $scheduleFactory;
    public function __construct(
        ConfigurationService $configurationService,
        Logger               $logger,
        SyncService          $syncService,
        RestService          $restService,
        LicenseService       $licenseService,
        ScheduleFactory      $scheduleFactory
    )
    {
        $this->configurationService = $configurationService;
        $this->logger = $logger;
        $this->syncService = $syncService;
        $this->restService = $restService;
        $this->licenseService = $licenseService;
        $this->scheduleFactory = $scheduleFactory;
    }
    public function execute()
    {
        $should_cron = $this->getShouldRunCron();
        $is_cron = $this->configurationService->getRawConfiguration('is_cron');
        $is_locked = $this->configurationService->getRawConfiguration('api_locked');
        $direction = $this->configurationService->getConfiguration()->getSyncDirection();
        $lastSync = $this->configurationService->resumeSync($direction);
        $this->logger->Info('cron execute');
        if (!$this->licenseService->checkProfile()) {
            return false;
        }
        if ($is_locked) {
            if ($this->restService->checkApiLock($this->apiBaseUrl . '/api/v4/Tokeninfo/')) {
                $this->configurationService->getConfiguration()->setData('should_cron', 0);
                $this->logger->Error('Api Limit has been reached.Syncronization paused');
                return $this;
            } else {
                $this->configurationService->getConfiguration()->setData('api_locked', 0)->save();
                $this->configurationService->getConfiguration()->setData('should_cron', 1)->save();
                $this->configurationService->getConfiguration()->setData('is_cron', 0)->save();
                $this->configurationService->getConfiguration()->setData('is_ajax', 0)->save();
                $this->logger->info('Api unlocked');
            }
        }
        $is_ajax = $this->configurationService->getRawConfiguration('is_ajax');
        if (isset($lastSync['status']) && $lastSync['status'] == 1) {
            return false;
        } elseif ($should_cron && !$is_ajax) {
            $syncComplete = false;
            while ($syncComplete == false) {
                $is_ajax = $this->configurationService->getRawConfiguration('is_ajax');
                if ($is_ajax) {
                    return false;
                }
                try {
                    $this->configurationService->getConfiguration()->setData('is_cron', 1)->save();
                    $data = $this->sync();
                    $this->configurationService->getConfiguration()->setData('is_cron', 0)->save();
                    if ($data['result'] === 'done') {
                        $this->logger->info('cron complete');
                        $syncComplete = true;
                    } elseif ($data['result'] === 403) {
                        $this->configurationService->getConfiguration()->setData('api_locked', 1)->save();
                        $this->configurationService->getConfiguration()->setData('should_cron', 1)->save();
                        $this->configurationService->getConfiguration()->setData('is_cron', 0)->save();
                        $this->logger->info('cron stopped due to API limit reached');
                        return $this;
                    }
                } catch (Exception $e) {
                    $this->logger->error('An error occurred when syncing: ' . $e->getMessage());
                    $this->configurationService->getConfiguration()->setData('is_cron', 0)->save();
                }
            }
            $this->logger->info('end while : ' . $data['result']);
            $this->configurationService->getConfiguration()->setData('should_cron', 0)->save();
            $this->configurationService->getConfiguration()->setData('is_cron', 1)->save();
            return $this;
        } else {
            $this->logger->info('Another syncing process running');
            return true;
        }
    }
    private function sync()
    {
        $direction = $this->configurationService->getConfiguration()->getSyncDirection();
        $data = [];
        $lastSync = $this->configurationService->resumeSync($direction);
        $this->logger->info("Resume Sync");
        if (isset($lastSync['status']) && $lastSync['status'] == 1) {
            $this->logger->info("Sync Complete");
            $data['result'] = 'done';
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
        }
        return $data;
    }
    public function getShouldRunCron()
    {
        $collection = $this->scheduleFactory->create()->getCollection()
            ->addFieldToFilter('job_code', 'Wrightwaydigital_Enconnector_CronSync')
            ->setOrder('schedule_id', 'DESC')
            ->setPageSize(1)
            ->setCurPage(2);
        if ($collection->getSize() > 1) {
            $collection->getFirstItem();
            $secondToLastSchedule = $collection->getLastItem();
            $status = $secondToLastSchedule->getStatus();
            if ($status == 'success' || $status == 'error' || $status=='missed') {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }
}
