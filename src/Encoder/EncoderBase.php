<?php

// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

namespace Abigail\Encoder;

class EncoderBase
{
    /**
     * Set header Content-Length $pMessage.
     *
     * @param $pMessage
     */
    public static function setContentLength($pMessage)
    {
        if (php_sapi_name() !== 'cli') {
            header('Content-Length: ' . strlen($pMessage));
        }
    }
}
