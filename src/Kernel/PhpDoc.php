<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail\Kernel;

final class PhpDoc
{
    /**
     * @param array $lines
     * @return string
     */
    public static function scan(array $lines): string
    {
        $phpDoc = '';
        $blockStarted = false;
        while ($line = array_pop($lines)) {
            if ($blockStarted) {
                $phpDoc = $line . $phpDoc;
                // If start comment block:
                if (preg_match('/\s*\t*\/\*/', $line)) {
                    break;
                }
                continue;
            } else {
                // We are not in a comment block.
                // If class def, array def or close broken from fn comes above,
                // then we don't have phpdoc.
                if (preg_match('/^\s*\t*[a-zA-Z_&\s]*(\$|{|})/', $line)) {
                    break;
                }
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // If end comment block:
            if (preg_match('/\*\//', $line)) {
                $phpDoc = $line . $phpDoc;
                $blockStarted = true;
                // One line php doc?
                if (preg_match('/\s*\t*\/\*/', $line)) {
                    break;
                }
            }
        }
        return $phpDoc;
    }

    /**
     * @param array $phpDoc
     * @param array $refParams
     * @param array|null $pRegMatches
     * @return array
     */
    public static function fill(array $phpDoc, array $refParams, ?array $pRegMatches): array
    {
        $params = array();
        $fillPhpDocParam = !isset($phpDoc['param']);
        foreach ($refParams as $param) {
            $params[$param->getName()] = $param;
            if ($fillPhpDocParam) {
                $phpDoc['param'][] = array(
                    'name' => $param->getName(),
                    'type' => $param->isArray() ? 'array' : 'mixed'
                );
            }
        }
        $parameters = array();
        if (isset($phpDoc['param'])) {
            if (is_array($phpDoc['param']) && is_string(key($phpDoc['param']))) {
                $phpDoc['param'] = array($phpDoc['param']);
            }
            $c = 0;
            foreach ($phpDoc['param'] as $phpDocParam) {
                $param = $params[$phpDocParam['name']];
                if (!$param) {
                    continue;
                }
                $parameter = array(
                    'type' => $phpDocParam['type']
                );
                if (is_array($pRegMatches) && $pRegMatches[$c]) {
                    $parameter['fromRegex'] = '$' . ($c + 1);
                }
                $parameter['required'] = !$param->isOptional();
                if ($param->isDefaultValueAvailable()) {
                    $parameter['default'] = str_replace(array("\n", ' '), '', var_export($param->getDefaultValue(), true));
                }
                $parameters[Inspector::argumentName($phpDocParam['name'])] = $parameter;
                $c++;
            }
        }
        if (!isset($phpDoc['return'])) {
            $phpDoc['return'] = array('type' => 'mixed');
        }
        $result = array(
            'parameters' => $parameters,
            'return' => $phpDoc['return']
        );
        if (isset($phpDoc['description'])) {
            $result['description'] = $phpDoc['description'];
        }
        if (isset($phpDoc['url'])) {
            $result['url'] = $phpDoc['url'];
        }
        return $result;
    }

    /**
     * Parse phpDoc string and returns an array.
     *
     * @param string $pString
     * @return array
     */
    public static function parse(string $pString): array
    {
        preg_match('#^/\*\*(.*)\*/#s', trim($pString), $comment);

        if (0 === count($comment)) {
            return array();
        }

        $comment = trim($comment[1]);

        preg_match_all('/^\s*\*(.*)/m', $comment, $lines);
        $lines = $lines[1];

        $tags = array();
        $currentTag = '';
        $currentData = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (substr($line, 0, 1) === '@') {
                if ($currentTag) {
                    $tags[$currentTag][] = $currentData;
                } else {
                    $tags['description'] = $currentData;
                }

                $currentData = '';
                preg_match('/@([a-zA-Z_]*)/', $line, $match);
                $currentTag = $match[1];
            }

            $currentData = trim($currentData . ' ' . $line);
        }
        if ($currentTag) {
            $tags[$currentTag][] = $currentData;
        } else {
            $tags['description'] = $currentData;
        }

        // Parse tags
        $regex = array(
            'param' => array('/^@param\s*\t*([a-zA-Z_\\\[\]]*)\s*\t*\$([a-zA-Z_]*)\s*\t*(.*)/', array('type', 'name', 'description')),
            'url' => array('/^@url\s*\t*(.+)/', array('url')),
            'return' => array('/^@return\s*\t*([a-zA-Z_\\\[\]]*)\s*\t*(.*)/', array('type', 'description')),
        );
        foreach ($tags as $tag => &$data) {
            if ($tag === 'description') {
                continue;
            }
            foreach ($data as &$item) {
                if (isset($regex[$tag])) {
                    preg_match($regex[$tag][0], $item, $match);
                    $item = array();
                    $c = count($match);
                    for ($i = 1; $i < $c; $i++) {
                        if (isset($regex[$tag][1][$i - 1])) {
                            $item[$regex[$tag][1][$i - 1]] = $match[$i];
                        }
                    }
                }
            }
            if (count($data) === 1) {
                $data = $data[0];
            }
        }

        return $tags;
    }
}
