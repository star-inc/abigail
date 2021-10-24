<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail\Encoder;

final class JSON extends EncoderBase implements EncoderInterface
{
    /**
     * Converts $pMessage to pretty json.
     *
     * @param $pMessage
     * @return string
     */
    public static function export($pMessage): string
    {
        if (php_sapi_name() !== 'cli') {
            header('Content-Type: application/json; charset=utf-8');
        }

        $result = json_encode($pMessage, JSON_PRETTY_PRINT);
        self::setContentLength($result);

        return $result;
    }
}
