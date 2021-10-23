<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail\Kernel;

use Abigail\Server;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;

final class Inspector
{
    /**
     * Describe a route or the whole controller with all routes.
     *
     * @param Server $server
     * @param string|null $pUri
     * @param boolean $pOnlyRoutes
     * @return array
     * @throws ReflectionException
     */
    public static function describe(Server $server, string $pUri = null, bool $pOnlyRoutes = false): array
    {
        $definition = array();

        if (!$pOnlyRoutes) {
            $definition['parameters'] = array(
                '_method' => array('description' => 'Can be used as HTTP METHOD if the client does not support HTTP methods.', 'type' => 'string',
                    'values' => 'GET, POST, PUT, DELETE, HEAD, OPTIONS, PATCH'),
                '_suppress_status_code' => array('description' => 'Suppress the HTTP status code.', 'type' => 'boolean', 'values' => '1, 0'),
                '_format' => array('description' => 'Format of generated data. Can be added as suffix .json .xml', 'type' => 'string', 'values' => 'json, xml'),
            );
        }

        $definition['controller'] = array(
            'entryPoint' => $server->getTriggerUrl()
        );

        foreach ($server->getRouter()->getRoutes() as $routeUri => $routeMethods) {
            $matches = array();
            if (!$pUri || (preg_match('|^' . $routeUri . '$|', $pUri, $matches))) {
                if ($matches) {
                    array_shift($matches);
                }
                $def = array();
                $def['uri'] = $server->getTriggerUrl() . '/' . $routeUri;
                foreach ($routeMethods as $method => $phpMethod) {
                    if (is_string($phpMethod)) {
                        $ref = new ReflectionClass($server->getController());
                        $refMethod = $ref->getMethod($phpMethod);
                    } else {
                        $refMethod = new ReflectionFunction($phpMethod);
                    }
                    $def['methods'][strtoupper($method)] = self::getMethodMetaData($refMethod, $matches);
                }
                $definition['controller']['routes'][$routeUri] = $def;
            }
        }

        if (!$pUri) {
            foreach ($server->getControllers() as $controller) {
                $definition['subController'][$controller->getTriggerUrl()] = $controller->describe(false, true);
            }
        }

        return $definition;
    }

    /**
     * @param Server $server
     * @param string $httpMethod
     * @param callable|string $callableMethod
     * @return ReflectionParameter[]|string
     * @throws ReflectionException
     * @throws Exception
     */
    public static function scanClassMethods(Server $server, string $httpMethod, $callableMethod)
    {
        if ($server->getController() && is_string($callableMethod)) {
            $ref = new ReflectionClass($server->getController());
            if (!method_exists($server->getController(), $callableMethod)) {
                $callableMethodClassName = get_class($server->getController());
                $reason = "There is no method '$callableMethod' in $callableMethodClassName.";
                return $server->getResponse()->sendBadRequest('MethodNotFoundException', $reason);
            }
            $reflectionMethod = $ref->getMethod($callableMethod);
        } elseif (is_callable($callableMethod)) {
            $reflectionMethod = new ReflectionFunction($callableMethod);
        } else {
            throw new Exception("Unknown");
        }

        $params = $reflectionMethod->getParameters();

        if ($httpMethod === '_all_') {
            // First parameter is $pMethod
            array_shift($params);
        }

        return $params;
    }

    /**
     * @param Server $server
     * @param array $params
     * @return array|string
     * @throws Exception
     */
    public static function collectArguments(Server $server, array $params)
    {
        $arguments = array();
        foreach ($params as $param) {
            $name = self::argumentName($param->getName());
            if ($name === '_') {
                $thisArgs = array();
                foreach ($_GET as $k => $v) {
                    if (substr($k, 0, 1) === '_' && $k != '_suppress_status_code') {
                        $thisArgs[$k] = $v;
                    }
                }
                $arguments[] = $thisArgs;
            } else {
                if (!$param->isOptional() && !isset($_GET[$name]) && is_null($server->getRequest()->getData($name))) {
                    return $server->getResponse()->sendBadRequest('MissingRequiredArgumentException', "Argument '$name' is missing.");
                }
                $arguments[] = $_GET[$name] ?? ($server->getRequest()->getData($name) ?? $param->getDefaultValue());
            }
        }
        return $arguments;
    }

    /**
     * If the name is a camel-cased one whereas the first char is lower-cased,
     * then we remove the first char and set first char to lower case.
     *
     * @param string $pName
     * @return string
     */
    public static function argumentName(string $pName): string
    {
        if (ctype_lower(substr($pName, 0, 1)) && ctype_upper(substr($pName, 1, 1))) {
            return strtolower(substr($pName, 1, 1)) . substr($pName, 2);
        }
        return $pName;
    }

    /**
     * Parse phpDoc string and returns an array.
     *
     * @param string $pString
     * @return array
     */
    public static function parsePhpDoc(string $pString): array
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

        //parse tags
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

    /**
     * Fetches all metadata information as params, return type etc.
     *
     * @param ReflectionFunctionAbstract $pMethod
     * @param array|null $pRegMatches
     * @return bool|array
     */
    public static function getMethodMetaData(ReflectionFunctionAbstract $pMethod, array $pRegMatches = null)
    {
        $file = $pMethod->getFileName();
        $startLine = $pMethod->getStartLine();

        $fh = fopen($file, 'r');
        if ($fh === false) {
            return false;
        }

        $lineNr = 1;
        $lines = array();
        while (($buffer = fgets($fh)) !== false) {
            if ($lineNr === $startLine) {
                break;
            }
            $lines[$lineNr] = $buffer;
            $lineNr++;
        }
        fclose($fh);

        $phpDoc = '';
        $blockStarted = false;
        while ($line = array_pop($lines)) {
            if ($blockStarted) {
                $phpDoc = $line . $phpDoc;
                //if start comment block: /*
                if (preg_match('/\s*\t*\/\*/', $line)) {
                    break;
                }
                continue;
            } else {
                //we are not in a comment block.
                //if class def, array def or close broken from fn comes above
                //then we don't have phpdoc
                if (preg_match('/^\s*\t*[a-zA-Z_&\s]*(\$|{|})/', $line)) {
                    break;
                }
            }

            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            //if end comment block: */
            if (preg_match('/\*\//', $line)) {
                $phpDoc = $line . $phpDoc;
                $blockStarted = true;
                //one line php doc?
                if (preg_match('/\s*\t*\/\*/', $line)) {
                    break;
                }
            }
        }

        $phpDoc = self::parsePhpDoc($phpDoc);

        $refParams = $pMethod->getParameters();
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
                if ($pRegMatches && is_array($pRegMatches) && $pRegMatches[$c]) {
                    $parameter['fromRegex'] = '$' . ($c + 1);
                }
                $parameter['required'] = !$param->isOptional();
                if ($param->isDefaultValueAvailable()) {
                    $parameter['default'] = str_replace(array("\n", ' '), '', var_export($param->getDefaultValue(), true));
                }
                $parameters[self::argumentName($phpDocParam['name'])] = $parameter;
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
}
