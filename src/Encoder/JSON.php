<?php

namespace Abigail\Encoder;

class JSON extends EncoderBase implements EncoderInterface
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

        $result = self::jsonFormat($pMessage);
        self::setContentLength($result);

        return $result;
    }

    /**
     * Indents a flat JSON string to make it more human-readable.
     *
     * Original at http://recursive-design.com/blog/2008/03/11/format-json-with-php/
     *
     * @param mixed $json The original JSON string to process.
     *
     * @return string Indented version of the original JSON string.
     */
    public static function jsonFormat($json): string
    {
        if (!is_string($json)) {
            $json = json_encode($json);
        }

        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $indentStr = '    ';
        $newLine = "\n";
        $inEscapeMode = false; //if the last char is a valid \ char.
        $outOfQuotes = true;

        for ($i = 0; $i <= $strLen; $i++) {
            // Grab the next character in the string.
            $char = substr($json, $i, 1);
            // Are we inside a quoted string?
            if ($char == '"' && !$inEscapeMode) {
                $outOfQuotes = !$outOfQuotes;
                // If this character is the end of an element,
                // output a new line and indent the next line.
            } elseif (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos--;
                $result .= str_repeat($indentStr, $pos);
            } elseif ($char == ':' && $outOfQuotes) {
                $char .= ' ';
            }
            // Add the character to the result string.
            $result .= $char;
            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos++;
                }
                $result .= str_repeat($indentStr, $pos);
            }
            if ($char == '\\' && !$inEscapeMode) {
                $inEscapeMode = true;
            } else {
                $inEscapeMode = false;
            }
        }
        return $result;
    }
}
