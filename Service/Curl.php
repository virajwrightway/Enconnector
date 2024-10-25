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

use Exception;

class Curl extends \Magento\Framework\HTTP\Client\Curl
{
    public function put($uri, $params)
    {
        $this->makeRequest('PUT', $uri, $params);
    }
    public function patch($uri, $params)
    {
        $this->makeRequest('PATCH', $uri, $params);
    }
    public function delete($uri, $params)
    {
        $this->makeRequest('DELETE', $uri, $params);
    }
    public function post($uri, $params)
    {
        $this->makeRequest('POST', $uri, $params);
    }
    /**
     * Make request
     * @param string $method
     * @param string $uri
     * @param array $params
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @throws Exception
     */
    protected function makeRequest($method, $uri, $params = [])
    {
        $this->_ch = curl_init();
        $this->curlOption(CURLOPT_URL, $uri);
        $data = json_encode($params);
        if (is_countable($params)) {
            if (count($params)) {
                $this->_headers['Content-Length'] = strlen($data);
            } else {
                unset($this->_headers['Content-Length']);
            }
        }
        if ($method == 'POST') {
            $this->curlOption(CURLOPT_POST, 1);
        } elseif ($method == 'GET') {
            $this->curlOption(CURLOPT_HTTPGET, 1);
            $this->_postFields = null;
        } else {
            $this->curlOption(CURLOPT_CUSTOMREQUEST, $method);
        }
        if ($method == 'POST' || $method == 'PUT' || $method == 'DELETE' || $method == 'PATCH') {
            $this->_headers['Content-Type'] = 'application/json';
            $this->curlOption(CURLOPT_POSTFIELDS, $data);
            if (is_string($params)) {
                $this->_postFields = $data;
            } else {
                $this->_postFields = $params;
            }
        }
        if (count($this->_headers)) {
            $heads = [];
            foreach ($this->_headers as $k => $v) {
                $heads[] = $k . ': ' . $v;
            }
            $this->curlOption(CURLOPT_HTTPHEADER, $heads);
        }
        if (count($this->_cookies)) {
            $cookies = [];
            foreach ($this->_cookies as $k => $v) {
                $cookies[] = "{$k}={$v}";
            }
            $this->curlOption(CURLOPT_COOKIE, implode(";", $cookies));
        }
        if ($this->_timeout) {
            $this->curlOption(CURLOPT_TIMEOUT, $this->_timeout);
        }
        if ($this->_port != 80) {
            $this->curlOption(CURLOPT_PORT, $this->_port);
        }
        $this->curlOption(CURLOPT_RETURNTRANSFER, 1);
        if (count($this->_curlUserOptions)) {
            foreach ($this->_curlUserOptions as $k => $v) {
                $this->curlOption($k, $v);
            }
        }
        $this->_headerCount = 0;
        $this->_responseHeaders = [];
        $info = curl_getinfo($this->_ch);
        $this->_responseBody = curl_exec($this->_ch);
        $this->_responseStatus = curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($this->_ch);
        if ($err) {
            $this->doError(curl_error($this->_ch));
        }
        curl_close($this->_ch);
    }
}
