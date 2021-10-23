<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail\Kernel;

class File
{
    /**
     * @param string $file
     * @param int $startLine
     * @return array|false
     */
    public static function readLines(string $file, int $startLine)
    {
        $fh = fopen($file, 'r');
        if ($fh === false) {
            return false;
        }
        $row = 1;
        $lines = array();
        while (($buffer = fgets($fh)) !== false) {
            if ($row === $startLine) {
                break;
            }
            $lines[$row] = $buffer;
            $row++;
        }
        fclose($fh);
        return $lines;
    }
}