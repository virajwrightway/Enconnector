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
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class configure extends Template
{
    protected $_coreRegistry;
    protected $_context;
    /**
     * @var LocationService
     */
    private $locationService;
    /**
     * @var ConfigurationService
     */
    private $configurationService;
    public function __construct(
        Context $context,
        LocationService                                  $locationService,
        ConfigurationService                             $configurationService
    )
    {
        $this->_context = $context;
        $this->locationService = $locationService;
        $this->configurationService = $configurationService;
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
    public function getRunUrl()
    {
        return $this->_context->getUrlBuilder()->getUrl('eposnowconnector/configuration/run');
    }
    public function getLocations()
    {
        return $this->locationService->getLocations();
    }
}
