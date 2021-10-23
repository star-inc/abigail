<?php

// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

namespace Abigail;

use Abigail\Kernel\Request;
use Abigail\Kernel\Response;
use Abigail\Kernel\Router;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use TypeError;

/**
 * \Abigail\Server - A REST server class for RESTful APIs.
 */
class Server
{
    /**
     * Current URL that triggers the controller.
     *
     * @var string
     */
    protected string $triggerUrl = '';

    /**
     * Contains the controller object.
     *
     * @var object
     */
    protected object $controller;

    /**
     * List of sub controllers.
     *
     * @var array
     */
    protected array $controllers = array();

    /**
     * Parent controller.
     *
     * @var Server|null
     */
    protected ?Server $parentController = null;

    /**
     * The client
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Check access function/method. Will be fired after the route has been found.
     * If it is forbidden, please throw an Exception to stop the processing.
     * Arguments: (url, route, params)
     *
     * @var callable
     */
    protected $checkAccessFn;

    /**
     * If this is true, we send file, line and backtrace if an exception has been thrown.
     *
     * @var boolean
     */
    protected bool $debugMode = false;

    /**
     * @var callable
     */
    protected $controllerFactory;

    /**
     * @var Router
     */
    private Router $router;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private Response $response;

    /**
     * Constructor
     *
     * @param string $pTriggerUrl
     * @param string|object|null $pControllerClass
     * @param Server|null $pParentController
     * @throws Exception
     */
    public function __construct(string $pTriggerUrl, $pControllerClass = null, Server $pParentController = null)
    {
        Kernel\Utils::normalizeUrl($pTriggerUrl);
        $this->request = new Request();
        $this->router = new Router($this);
        $this->response = new Response($this);
        if ($pParentController) {
            $this->parentController = $pParentController;
            $this->setClient($pParentController->getClient());
            if ($pParentController->getCheckAccess()) {
                $this->setCheckAccess($pParentController->getCheckAccess());
            }
            if ($pParentController->getDebugMode()) {
                $this->setDebugMode($pParentController->getDebugMode());
            }
            if ($pParentController->getRouter()->getDescribeRoutes()) {
                $this->getRouter()->setDescribeRoutes($pParentController->getRouter()->getDescribeRoutes());
            }
            if ($pParentController->getControllerFactory()) {
                $this->setControllerFactory($pParentController->getControllerFactory());
            }
            if ($pParentController->getResponse()->getSuccessResponseWrapper()) {
                $this->getResponse()->setSuccessResponseWrapper($pParentController->getResponse()->getSuccessResponseWrapper());
            }
            if ($pParentController->getResponse()->getExceptionHandler()) {
                $this->getResponse()->setExceptionHandler($pParentController->getResponse()->getExceptionHandler());
            }
            $this->getResponse()->setHttpStatusCodes($pParentController->getResponse()->getHttpStatusCodes());
        } else {
            $this->setClient(new Client($this));
        }
        $this->setClass($pControllerClass);
        $this->setTriggerUrl($pTriggerUrl);
    }

    /**
     * Get the current client.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Sets the client.
     *
     * @param Client|string $pClient
     * @return Server        $this
     */
    public function setClient($pClient): Server
    {
        if (is_string($pClient)) {
            $pClient = new $pClient($this);
        }

        $this->client = $pClient;
        $this->client->setupFormats();

        return $this;
    }

    /**
     * Getter for checkAccess
     * @return callable|null
     */
    public function getCheckAccess(): ?callable
    {
        return $this->checkAccessFn;
    }

    /**
     * Set the check access function/method.
     * Will fired with arguments: (url, route)
     *
     * @param callable $pFn
     * @return Server   $this
     */
    public function setCheckAccess(callable $pFn): Server
    {
        $this->checkAccessFn = $pFn;

        return $this;
    }

    /**
     * Getter for Debugger
     * @return boolean
     */
    public function getDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * If this is true, we send file, line and backtrace if an exception has been thrown.
     *
     * @param boolean $pDebugMode
     * @return Server  $this
     */
    public function setDebugMode(bool $pDebugMode): Server
    {
        $this->debugMode = $pDebugMode;

        return $this;
    }

    /**
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * @return callable|null
     */
    public function getControllerFactory(): ?callable
    {
        return $this->controllerFactory;
    }

    /**
     * @param callable $controllerFactory
     *
     * @return Server $this
     */
    public function setControllerFactory(callable $controllerFactory): Server
    {
        $this->controllerFactory = $controllerFactory;

        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Sets the controller class.
     *
     * @param string|object $pClass
     * @throws Exception
     */
    public function setClass($pClass)
    {
        if (is_string($pClass)) {
            $this->createControllerClass($pClass);
        } elseif (is_object($pClass)) {
            $this->controller = $pClass;
        } else {
            $this->controller = $this;
        }
    }

    /**
     * Set up the controller class.
     *
     * @param string $pClassName
     * @throws Exception
     */
    protected function createControllerClass(string $pClassName)
    {
        if ($pClassName != '') {
            try {
                if ($this->controllerFactory) {
                    $this->controller = call_user_func_array($this->controllerFactory, array(
                        $pClassName,
                        $this
                    ));
                } else {
                    $this->controller = new $pClassName($this);
                }
                if (get_parent_class($this->controller) == '\Abigail\Server') {
                    $this->controller->setClient($this->getClient());
                }
            } catch (Exception $e) {
                throw new Exception('Error during initialisation of ' . $pClassName . ': ' . $e, 0, $e);
            }
        } else {
            $this->controller = $this;
        }
    }

    /**
     * Factory.
     *
     * @param string $pTriggerUrl
     * @param mixed $pControllerClass
     *
     * @return Server $this
     */
    public static function create(string $pTriggerUrl, $pControllerClass = ''): Server
    {
        $clazz = get_called_class();
        return new $clazz($pTriggerUrl, $pControllerClass);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getRouter()->$name(...$arguments) ?? $this->getResponse()->$name(...$arguments);
    }

    /**
     * Alias for getParentController()
     *
     * @return Server
     */
    public function done(): Server
    {
        return $this->getParentController();
    }

    /**
     * Returns the parent controller
     *
     * @return Server|null $this
     */
    public function getParentController(): ?Server
    {
        return $this->parentController;
    }

    /**
     * Gets the current trigger url.
     *
     * @return string
     */
    public function getTriggerUrl(): string
    {
        return $this->triggerUrl;
    }

    /**
     * Set the URL that triggers the controller.
     *
     * @param $pTriggerUrl
     * @return Server
     */
    public function setTriggerUrl($pTriggerUrl): Server
    {
        $this->triggerUrl = $pTriggerUrl;

        return $this;
    }

    /**
     * Attach a sub controller.
     *
     * @param string $pTriggerUrl
     * @param mixed $pControllerClass A class name (autoloader required) or a instance of a class.
     *
     * @return Server new created Server. Use done() to switch the context back to the parent.
     * @throws Exception
     */
    public function addSubController(string $pTriggerUrl, $pControllerClass = ''): Server
    {
        Kernel\Utils::normalizeUrl($pTriggerUrl);

        $base = $this->triggerUrl;
        if ($base == '/') {
            $base = '';
        }

        $controller = new Server($base . $pTriggerUrl, $pControllerClass, $this);

        $this->controllers[] = $controller;

        return $controller;
    }

    /**
     * Simulates a HTTP Call.
     *
     * @param string $pUri
     * @param string $pMethod The HTTP Method
     * @return bool|string
     * @throws ReflectionException
     */
    public function simulateCall(string $pUri, string $pMethod = 'get')
    {
        if (($idx = strpos($pUri, '?')) !== false) {
            parse_str(substr($pUri, $idx + 1), $_GET);
            $pUri = substr($pUri, 0, $idx);
        }
        $this->getClient()->setUrl($pUri);
        $this->getClient()->setMethod($pMethod);

        return $this->run();
    }

    /**
     * Fire the magic!
     *
     * Searches the method and sends the data to the client.
     *
     * @return bool|string
     * @throws ReflectionException
     * @throws Exception
     */
    public function run()
    {
        //check sub controller
        foreach ($this->controllers as $controller) {
            if ($result = $controller->run()) {
                return $result;
            }
        }

        $requestedUrl = $this->getClient()->getUrl();
        Kernel\Utils::normalizeUrl($requestedUrl);
        //check if it's in our area
        if (strpos($requestedUrl, $this->triggerUrl) !== 0) {
            return "";
        }

        $endPos = $this->triggerUrl === '/' ? 1 : strlen($this->triggerUrl) + 1;
        $uri = substr($requestedUrl, $endPos);

        if (!$uri) {
            $uri = '';
        }

        $route = false;
        $arguments = array();
        $requiredMethod = $this->getClient()->getMethod();

        // Does the requested uri exist?
        list($callableMethod, $regexArguments, $method) = $this->getRouter()->findRoute($uri, $requiredMethod);

        if ((!$callableMethod || $method != 'options') && $requiredMethod == 'options') {
            $description = Kernel\Utils::describe($this, $uri);
            return $this->getResponse()->send($description);
        }

        if (empty($callableMethod)) {
            if (!$this->getParentController()) {
                if ($this->getRouter()->getFallbackMethod()) {
                    $m = $this->getRouter()->getFallbackMethod();
                    return $this->getResponse()->send($this->controller->$m());
                } else {
                    return $this->getResponse()->sendBadRequest('RouteNotFoundException', "There is no route for '$uri'.");
                }
            } else {
                return false;
            }
        }

        if ($method == '_all_') {
            $arguments[] = $method;
        }

        if (is_array($regexArguments)) {
            $arguments = array_merge($arguments, $regexArguments);
        }

        // Open class and scan method
        if ($this->controller && is_string($callableMethod)) {
            $ref = new ReflectionClass($this->controller);

            if (!method_exists($this->controller, $callableMethod)) {
                $callableMethodClassName = get_class($this->controller);
                return $this->getResponse()->sendBadRequest('MethodNotFoundException', "There is no method '$callableMethod' in $callableMethodClassName.");
            }

            $reflectionMethod = $ref->getMethod($callableMethod);
        } elseif (is_callable($callableMethod)) {
            $reflectionMethod = new ReflectionFunction($callableMethod);
        } else {
            throw new Exception("Unknown");
        }

        $params = $reflectionMethod->getParameters();

        if ($method == '_all_') {
            // First parameter is $pMethod
            array_shift($params);
        }

        // Remove regex arguments
        for ($i = 0; $i < count($regexArguments); $i++) {
            array_shift($params);
        }

        // Read data from request body
        $this->getRequest()->readDataFromBody();

        // Collect arguments
        foreach ($params as $param) {
            $name = Kernel\Utils::argumentName($param->getName());
            if ($name == '_') {
                $thisArgs = array();
                foreach ($_GET as $k => $v) {
                    if (substr($k, 0, 1) == '_' && $k != '_suppress_status_code') {
                        $thisArgs[$k] = $v;
                    }
                }
                $arguments[] = $thisArgs;
            } else {
                if (!$param->isOptional() && !isset($_GET[$name]) && is_null($this->getRequest()->getData($name))) {
                    return $this->getResponse()->sendBadRequest('MissingRequiredArgumentException', "Argument '$name' is missing.");
                }
                $arguments[] = $_GET[$name] ?? ($this->getRequest()->getData($name) ?? $param->getDefaultValue());
            }
        }

        if ($this->checkAccessFn) {
            $args[] = $this->getClient()->getUrl();
            $args[] = $route;
            $args[] = $arguments;
            try {
                call_user_func_array($this->checkAccessFn, $args);
            } catch (Exception $e) {
                $this->getResponse()->sendException($e);
            }
        }

        // fire method
        $object = $this->controller;
        return $this->fireMethod($callableMethod, $object, $arguments);
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @throws Exception
     */
    public function fireMethod($pMethod, $pController, $pArguments): string
    {
        $callable = false;

        if ($pController && is_string($pMethod)) {
            if (!method_exists($pController, $pMethod)) {
                return $this->getResponse()->sendError('MethodNotFoundException', sprintf('Method %s in class %s not found.', $pMethod, get_class($pController)));
            } else {
                $callable = array($pController, $pMethod);
            }
        } elseif (is_callable($pMethod)) {
            $callable = $pMethod;
        }

        if ($callable) {
            try {
                return $this->getResponse()->send(call_user_func_array($callable, $pArguments));
            } catch (TypeError | InvalidArgumentException | Exception $e) {
                return $this->getResponse()->sendException($e);
            }
        }
        return "";
    }

    /**
     * @return object
     */
    public function getController(): object
    {
        return $this->controller;
    }

    /**
     * @return array
     */
    public function getControllers(): array
    {
        return $this->controllers;
    }
}
