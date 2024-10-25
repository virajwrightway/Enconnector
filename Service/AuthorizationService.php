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
class AuthorizationService
{
    public function generateKey()
    {
        return 'EPOSNOW';
    }
    public function generateSecret()
    {
        return substr(strtoupper(sha1($this->generateKey() . time())), 0, 32);
    }
    public function getToken($key, $secret)
    {
        return base64_encode($key . ':' . $secret);
    }
    public function matchToken($eposToken, $token)
    {
        return $eposToken == $this->getTokenString($token);
    }
    private function getTokenString($string)
    {
        if (preg_match('/Basic\s(\S+)/', $string, $matches)) {
            return $matches[1];
        }
        return $string;
    }
}
