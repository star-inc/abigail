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
                '_method' => array(
                    'description' => 'Can be used as HTTP METHOD if the client does not support HTTP methods.', 'type' => 'string',
                    'values' => 'GET, POST, PUT, DELETE, HEAD, OPTIONS, PATCH'
                ),
                '_suppress_status_code' => array(
                    'description' => 'Suppress the HTTP status code.', 'type' => 'boolean', 'values' => '1, 0'
                ),
                '_format' => array(
                    'description' => 'Format of generated data. Can be added as suffix .json .xml', 'type' => 'string', 'values' => 'json, xml'
                ),
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
     * Fetches all metadata information as params, return type etc.
     *
     * @param ReflectionFunctionAbstract $pMethod
     * @param array|null $pRegMatches
     * @return array|false
     */
    public static function getMethodMetaData(ReflectionFunctionAbstract $pMethod, array $pRegMatches = null)
    {
        $file = $pMethod->getFileName();
        $startLine = $pMethod->getStartLine();
        $lines = File::readLines($file, $startLine);
        if ($lines === false) {
            return false;
        }
        $phpDocText = PhpDoc::scan($lines);
        $phpDoc = PhpDoc::parse($phpDocText);
        $refParams = $pMethod->getParameters();
        return PhpDoc::fill($phpDoc, $refParams, $pRegMatches);
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
}
