<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail\Kernel;

final class Utils
{
    /**
     * As known as the polyfill of startsWith
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * Normalize $pUrl. Cuts of the trailing slash.
     *
     * @param string $pUrl
     */
    public static function normalizeUrl(string &$pUrl): void
    {
        if ('/' === $pUrl) {
            return;
        }
        if (substr($pUrl, -1) === '/') {
            $pUrl = substr($pUrl, 0, -1);
        }
        if (substr($pUrl, 0, 1) != '/') {
            $pUrl = '/' . $pUrl;
        }
    }

    /**
     * @param string $pValue
     * @return string
     */
    public static function camelCase2Dashes(string $pValue): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $pValue));
    }
}
