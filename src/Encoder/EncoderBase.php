<?php

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
