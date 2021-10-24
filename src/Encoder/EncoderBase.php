<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail\Encoder;

abstract class EncoderBase
{
    /**
     * Set header Content-Length $pMessage.
     *
     * @param $pMessage
     */
    public static function setContentLength($pMessage): void
    {
        if (php_sapi_name() !== 'cli') {
            header('Content-Length: ' . strlen($pMessage));
        }
    }
}
