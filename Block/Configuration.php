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

namespace Wrightwaydigital\Enconnector\Block;

use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Service\EposNow\LocationService;
use Wrightwaydigital\Enconnector\Service\EposNow\TenderService;
use Wrightwaydigital\Enconnector\Service\EposNow\TaxService;
use Wrightwaydigital\Enconnector\Service\TaxService as MagentoTaxService;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Tax\Model\TaxClass\Source\Product as ProductTaxClassSource;
use Magento\Framework\UrlInterface;
use Wrightwaydigital\Enconnector\Service\License\LicenseService;
use Magento\Framework\Escaper;

class Configuration extends Template
{
    protected $_coreRegistry;
    protected $_context;
    /**
     * @var LocationService
     */
    private $locationService;
    private $tenderService;
    private $taxService;
    private $taxMatchFactory;
    /**
     * @var License
     */
    public $configurationService;
    protected $productTaxClassSource;
    protected $urlBuilder;
    public $licenseService;
    protected $escaper;
    public function __construct(
        Context                                                  $context,
        LocationService                                          $locationService,
        TaxService                                               $taxService,
        ConfigurationService                                     $configurationService,
        ProductTaxClassSource                                    $productTaxClassSource,
        \Wrightwaydigital\Enconnector\Model\TaxmatchFactory $taxMatchFactory,
        UrlInterface                                             $urlBuilder,
        TenderService                                            $tenderService,
        LicenseService                                           $licenseService,
        Escaper                                                  $escaper
    )
    {
        $this->_context = $context;
        $this->locationService = $locationService;
        $this->taxService = $taxService;
        $this->configurationService = $configurationService;
        $this->productTaxClassSource = $productTaxClassSource;
        $this->taxMatchFactory = $taxMatchFactory;
        $this->urlBuilder = $urlBuilder;
        $this->tenderService = $tenderService;
        $this->licenseService = $licenseService;
        $this->escaper = $escaper;
        parent::__construct($context);
    }
    public function execute()
    {
    }
    public function _prepareLayout()
    {
        parent::_prepareLayout();
        return $this;
    }
    public function getConfiguration()
    {
        return $this->configurationService->getConfiguration();
    }
    public function getLicense()
    {
        return $this->licenseService->getLicense();
    }
    public function getLicenseStatus()
    {
        return $this->licenseService->checkProfile();
    }
    public function getRunUrl()
    {
        return $this->_context->getUrlBuilder()->getUrl('eposnowconnector/configuration/run');
    }
    public function getLocations()
    {
        return $this->locationService->getLocations();
    }
    public function getMagentoTaxClasses()
    {
        return $this->productTaxClassSource->getAllOptions();
    }
    public function getEposnowTaxRates()
    {
        return $this->taxService->getTaxRates();
    }
    public function getMatchedTaxes()
    {
        $collection = $this->taxMatchFactory->create()->getCollection();
        $collection->addFieldToSelect('*');
        $data = $collection->getData();
        return $data;
    }
    public function getTenders()
    {
        return $this->tenderService->getTenders();
    }
    public function getSyncUrl($url)
    {
        $url = $this->urlBuilder->getUrl($url, ['_current' => true, '_use_rewrite' => true, '_nosid' => true]);
        return $url;
    }
    public function getEscaper()
    {
        return $this->escaper;
    }
}
