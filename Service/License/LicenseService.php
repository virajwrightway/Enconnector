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

namespace Wrightwaydigital\Enconnector\Service\License;

use Wrightwaydigital\Enconnector\Model\License;
use Wrightwaydigital\Enconnector\Model\LicenseFactory;
use Wrightwaydigital\Enconnector\Service\RestService;
use Wrightwaydigital\Enconnector\Service\WebhookConfigurationService;
use Wrightwaydigital\Enconnector\Model\Configuration;
use Magento\Store\Model\StoreManager;


class LicenseService
{
    /**
     * @var LicenseFactory\
     */
    private $licenseFactory;
    /**
     * @var License
     */
    private $license = false;
    private $restservice;
    protected $messageManager;
    protected $storeManager;
    protected $webhookConfigurationService;
    public $configurationService;
    public $configuration;
    public function __construct(
        LicenseFactory                              $licenseFactory,
        RestService                                 $restservice,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        WebhookConfigurationService                 $webhookConfigurationService,
        Configuration                               $configuration,
        StoreManager                                $storeManager
    )
    {
        $this->licenseFactory = $licenseFactory;
        $this->restservice = $restservice;
        $this->messageManager = $messageManager;
        $this->webhookConfigurationService = $webhookConfigurationService;
        $this->configuration = $configuration;
        $this->storeManager = $storeManager;
    }
    /**
     * @return $license
     */
    public function getLicense()
    {
        if ($this->license == null) {
            $this->license = $this->loadLicense();
        }
        return $this->license;
    }
//    public function saveConfiguration(Configuration $config)
//    {
//        $config->save();
//        $this->configuration = $config;
//    }
    /**
     * @return false
     */
    private function loadLicense()
    {
        if ($model = $this->licenseFactory->create()->load(1)) {
            return $model;
        } else {
            return false;
        }
    }
    public function generateKeySecret($name)
    {
        $api_token = $this->getLicense()->getEposnowToken();
        $decodedToken = base64_decode($api_token);
        $tokenParts = explode(':', $decodedToken);
        $decodedData = [];
        if (count($tokenParts) == 2) {
            $decodedData['apiKey'] = $tokenParts[0];
            $decodedData['apiSecret'] = $tokenParts[1];
            return $decodedData[$name];
        } else {
            return false;
        }
    }
    public function generateHashByTable()
    {
        $licence_key = $this->getLicense()->getLicenseKey();
        $api_token = $this->getLicense()->getEposnowToken();
        $domain = $this->getLicense()->getDomain();
        $moduleVersion = $this->getLicense()->getAppVersion();
        $systemVersion = '8.1.3';
        $hash_date = date("d-m-y");
        $string = $licence_key . $api_token . $domain . $moduleVersion . $systemVersion . $hash_date;
        $hash_code = hash('ripemd160', $string);
        return $hash_code;
    }
    public function generateHashByValues($string)
    {
        $hash_date = date("d-m-y");
        $hash_code = hash('ripemd160', $string);
        return $hash_code;
    }
    function validateLicense($post)
    {
        $licenseKey = $post['LicenseKey'];
        $decode_licence = base64_decode($licenseKey);
        $licence_arr = explode(":", $decode_licence);
        $elements_count = count($licence_arr);
        $ch_lenth = isset($licence_arr[1]) ? strlen($licence_arr[1]) : 0;
        $isSpecial = false;
        foreach ($licence_arr as $eliment) {
            if (preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $eliment)) {
                $isSpecial = true;
            }
        }
        if (($elements_count == 3) && ($ch_lenth == 32) && !$isSpecial) {
            $app_name = 'magento';
            $domain_chars = rtrim($this->storeManager->getStore()->getBaseUrl(), "/");
            $domain_name = $this->sanitizeDomain($domain_chars);
            $module_version = '1.0.0';
            $systemVersion = '2.4.6';
            $post = [
                'app' => $app_name,
                'domain' => $domain_name,
                'appVersion' => $module_version,
                'coreVersion' => $systemVersion,
            ];
            $post = json_encode($post);
            $result = $this->restservice->requestLicenseValid($licenseKey, $post);
            if (isset($result->success) && $result->success == 1) {
                $licenseModal = $this->licenseFactory->create();
                if (isset($result->apiToken)) {
                    $licenseModal->setId(1);
                    $licenseModal->setData('eposnow_token', $result->apiToken);
                    $licenseModal->setData('domain', $domain_name);
                    $licenseModal->setData('app_version', $module_version);
                    $licenseModal->setData('license_key', $licenseKey);
                    $hash_date = date("d-m-y");
                    $hash_code = hash('ripemd160', $licenseKey . $result->apiToken . $domain_name . $module_version . $systemVersion . $hash_date);
                    $licenseModal->setData('hash', $hash_code);
                    $licenseModal->save();
                    $this->webhookConfigurationService->configureWebhooks($this->configuration);
                    $this->messageManager->getMessages(true);
                    $this->messageManager->
                    addSuccessMessage("License Key Validated");
                } else {
                    $this->messageManager->getMessages(true);
                    $this->messageManager->
                    addSuccessMessage("This license is already in use in another domain");
                }
                return true;
            } else {
                $this->messageManager->getMessages(true);
                $this->messageManager->
                addErrorMessage("License Validation Failed");
                return false;
            }
//            $codeReturn = $api->validateLicenceKey('POST', $post, $appKey);
        } else {
            $this->messageManager->getMessages(true);
            $this->messageManager->
            addErrorMessage("Invalid License Key");
            return false;
        }
    }
    function checkProfile()
    {
        $licenseHash = $this->getLicense()->getHash();
        $generatedHash = $this->generateHashByTable();
        $return = false;
        if ($licenseHash) {
            $currentDomain = $this->getDomain();
            $app_name = 'magento';
            $licence_key = $this->getLicense()->getLicenseKey();
            $domain = $this->getLicense()->getDomain();
            $app_version = $this->getLicense()->getAppVersion();
            $systemVersion = '2.4.6';
            if ($currentDomain != $domain) {
                $this->messageManager->getMessages(true);
                $this->messageManager->
                addSuccessMessage("This license is already in use in another domain");
                return false;
            }
            $post = [
                'app' => $app_name,
                'domain' => $domain,
                'appVersion' => $app_version,
                'coreVersion' => $systemVersion,
            ];
            $post = json_encode($post);
            $validate_obj = $this->restservice->requestProfile($licence_key, $post);
            if ($validate_obj->status == 201 && isset($validate_obj->success) && $validate_obj->success == 1) {
                $licenseModal = $this->licenseFactory->create();
                $licenseModal->setId(1);
                $licenseModal->setData('hash', $generatedHash);
                $licenseModal->save();
                if ($validate_obj->isLatest != 1) {
                    $this->messageManager->getMessages(true);
                    $this->messageManager->
                    addSuccessMessage("New version exist");
                }
                return true;
            }
            if ($validate_obj->status == 201 && isset($validate_obj->success) && $validate_obj->success == 0) {
                if (isset($validate_obj->domain) && $validate_obj->domain != $domain) {
                    $licenseModal = $this->licenseFactory->create();
                    $licenseModal->setId(1);
                    $licenseModal->setData('eposnow_token', '');
                    $licenseModal->save();
                    $this->messageManager->getMessages(true);
                    $this->messageManager->
                    addErrorMessage("Please disconnect and connect domain again");
                }
                return false;
            }
            if ($validate_obj->status == 400) {
                $this->messageManager->getMessages(true);
                $this->messageManager->
                addErrorMessage("Invalid Token or License validation failed");
                return false;
            }
            if ($validate_obj->status == 401 || $validate_obj->status == 403) {
                $this->messageManager->getMessages(true);
                $this->messageManager->
                addErrorMessage("The token is no longer valid, 401/403 error");
                return false;
            }
        }
        return false;
    }
    function resetLicense()
    {
        $app_name = 'magento';
        $token = $this->getLicense()->getEposnowToken();
        $domain = $this->getLicense()->getDomain();
        $app_version = $this->getLicense()->getAppVersion();
        $systemVersion = '2.4.6';
        $post = [
            'app' => $app_name,
            'domain' => $domain,
            'appVersion' => $app_version,
            'coreVersion' => $systemVersion,
        ];
        $post = json_encode($post);
        $validate_obj = $this->restservice->resetKey($token, $post);
        if ($validate_obj->status == 201) {
            $licenseModal = $this->licenseFactory->create();
            $licenseModal->setId(1);
            $licenseModal->setData('license_key', $validate_obj->token);
            $licenseModal->save();
        }
        return true;
    }
    private function sanitizeDomain($domain_chars)
    {
        if (preg_match('/^(https)/', $domain_chars)) {
            $domain_chars = str_replace("https:", "", $domain_chars);
            $domain_chars = str_replace("//", "", $domain_chars);
        }
        if (preg_match('/^(http)/', $domain_chars)) {
            $domain_chars = str_replace("http:", "", $domain_chars);
            $domain_chars = str_replace("//", "", $domain_chars);
        }
        $url_arr = explode('/', $domain_chars);
        $domain_name = is_array($url_arr) ? $url_arr[0] : $url_arr;
        return $domain_name;
    }
    private function getDomain()
    {
        $domain_chars = rtrim($this->storeManager->getStore()->getBaseUrl(), "/");
        return $this->sanitizeDomain($domain_chars);
    }
    public function getLicenseConnected()
    {
        if ($this->getLicense()->getEposnowToken() != 0) {
            return true;
        } else {
            return false;
        }
    }

}
