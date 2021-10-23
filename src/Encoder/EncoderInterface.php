<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

namespace Abigail\Encoder;

interface EncoderInterface
{
    public static function export($pMessage): string;
}
