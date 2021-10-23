<?php

namespace Abigail\Encoder;

interface EncoderInterface
{
    public static function export($pMessage): string;
}