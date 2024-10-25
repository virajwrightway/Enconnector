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

namespace Wrightwaydigital\Enconnector\Controller\Index;
//use Braintree\Collection;
use Wrightwaydigital\Enconnector\Logger\Logger;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Wrightwaydigital\Enconnector\Service\EposNow\EposCategoryService;
use Wrightwaydigital\Enconnector\Service\EposNow\EposProductService;
use Wrightwaydigital\Enconnector\Service\EposNow\StockService;
use Wrightwaydigital\Enconnector\Service\ProductService;
use Wrightwaydigital\Enconnector\Service\WebhookConfigurationService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

//use Wrightwaydigital\Enconnector\Model\configurationFactory;
class Index extends Action
{
    protected $_pageFactory;
    /**
     * @var WebhookConfigurationService
     */
    private $webhookConfigurationService;
    /**
     * @var License
     */
    private $configurationService;
    private $eposCategoryService;
    private $eposProductService;
    private $categoryService;
    private $productService;
    private $stockService;
    private $configurationFactory;
    private $eposnowdataFactory;
    /**
     * @var Logger
     */
    private $logger;
    public function __construct(
        Context                                     $context,
        WebhookConfigurationService                 $webhookConfigurationService,
        ConfigurationService                        $configurationService,
        PageFactory                                 $pageFactory,
        EposCategoryService                         $eposCategoryService,
        EposProductService                          $eposProductService,
        CategoryService                             $categoryService,
        ProductService                              $productService,
        StockService                                $stockService,
        Logger                                      $logger,
        \Wrightwaydigital\Enconnector\Model\ConfigurationFactory $configurationFactory,
        \Wrightwaydigital\Enconnector\Model\EposnowdataFactory   $eposnowdataFactory
    )
    {
        $this->webhookConfigurationService = $webhookConfigurationService;
        $this->configurationService = $configurationService;
        $this->_pageFactory = $pageFactory;
        $this->eposCategoryService = $eposCategoryService;
        $this->categoryService = $categoryService;
        $this->eposProductService = $eposProductService;
        $this->productService = $productService;
        $this->stockService = $stockService;
        $this->logger = $logger;
        $this->configurationFactory = $configurationFactory;
        $this->eposnowdataFactory = $eposnowdataFactory;
        return parent::__construct($context);
    }
    public function execute()
    {
    }
}
