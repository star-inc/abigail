<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

namespace Abigail\Encoder;

class XML extends EncoderBase implements EncoderInterface
{
    /**
     * Converts $pMessage to xml.
     *
     * @param $pMessage
     * @return string
     */
    public static function export($pMessage): string
    {
        $xml = self::toXml($pMessage);
        $xml = "<?xml version=\"1.0\"?>\n<response>\n$xml</response>\n";
        self::setContentLength($xml);
        return $xml;
    }

    /**
     * @param mixed $pData
     * @param string $pParentTagName
     * @param int $pDepth
     * @return string XML
     */
    public static function toXml($pData, string $pParentTagName = '', int $pDepth = 1): string
    {
        if (is_array($pData)) {
            $content = '';
            foreach ($pData as $key => $data) {
                $key = is_numeric($key) ? $pParentTagName . '-item' : $key;
                $content .= str_repeat('  ', $pDepth)
                    . '<' . htmlspecialchars($key) . '>' .
                    self::toXml($data, $key, $pDepth + 1)
                    . '</' . htmlspecialchars($key) . ">\n";
            }
            return $content;
        } else {
            return htmlspecialchars($pData);
        }
    }
}
