<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail;

use Abigail\Kernel\Request;
use Abigail\Kernel\Response;
use Abigail\Kernel\Router;
use BadMethodCallException;
use Exception;
use InvalidArgumentException;
use ReflectionException;
use TypeError;

/**
 * \Abigail\Server - A REST server class for RESTful APIs.
 * @method Server setDescribeRoutes(bool $pDescribeRoutes)
 * @method Server collectRoutes()
 * @method Server addRoute(string $pUri, $pCb, string $pHttpMethod = '_all_')
 * @method Server addGetRoute(string $pUri, $pCb)
 * @method Server addPostRoute(string $pUri, $pCb)
 * @method Server addPutRoute(string $pUri, $pCb)
 * @method Server addPatchRoute(string $pUri, $pCb)
 * @method Server addHeadRoute(string $pUri, $pCb)
 * @method Server addOptionsRoute(string $pUri, $pCb)
 * @method Server addDeleteRoute(string $pUri, $pCb)
 * @method Server setHttpStatusCodes(bool $pWithStatusCode)
 * @method Server setSuccessHandler(callable $pFn)
 * @method Server setExceptionHandler(callable $pFn)
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
                $this->getResponse()->setSuccessHandler($pParentController->getResponse()->getSuccessResponseWrapper());
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
    public function setClass($pClass): void
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
    protected function createControllerClass(string $pClassName): void
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
                if (get_parent_class($this->controller) === '\Abigail\Server') {
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
     * @param string|object $pControllerClass
     *
     * @return Server $this
     */
    public static function create(string $pTriggerUrl, $pControllerClass = ''): Server
    {
        $clazz = get_called_class();
        return new $clazz($pTriggerUrl, $pControllerClass);
    }

    /**
     * Magic method to call settings
     * @param string $name method name
     * @param array $arguments method arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->getRouter(), $name)) {
            return $this->getRouter()->$name(...$arguments);
        } else if (method_exists($this->getResponse(), $name)) {
            return $this->getResponse()->$name(...$arguments);
        } else {
            throw new BadMethodCallException();
        }
    }

    /**
     * Alias for getParentController()
     *
     * @return Server|null
     */
    public function done(): ?Server
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
     * @param string|object $pControllerClass A class name (autoloader required) or an instance of a class.
     *
     * @return Server new created Server. Use done() to switch the context back to the parent.
     * @throws Exception
     */
    public function addSubController(string $pTriggerUrl, $pControllerClass = ''): Server
    {
        Kernel\Utils::normalizeUrl($pTriggerUrl);

        $base = $this->triggerUrl;
        if ($base === '/') {
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
     * @throws ReflectionException|Exception
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
     * Searches the method and Send the data to the client.
     *
     * @return false|string
     * @throws Exception
     */
    public function run()
    {
        // Check sub controller
        foreach ($this->controllers as $controller) {
            if ($result = $controller->run()) {
                return $result;
            }
        }

        $requestedUrl = $this->getClient()->getUrl();
        Kernel\Utils::normalizeUrl($requestedUrl);
        if (!Kernel\Utils::startsWith($requestedUrl, $this->triggerUrl)) {
            return false;
        }

        $apiBaseUri = substr($requestedUrl, $this->triggerUrl === '/' ? 1 : strlen($this->triggerUrl) + 1);
        if ($apiBaseUri === false) {
            $apiBaseUri = '';
        }

        $arguments = array();
        $requiredMethod = $this->getClient()->getMethod();

        // Does the requested uri exist?
        list($callableMethod, $regexArguments, $method) = $this->getRouter()->findRoute($apiBaseUri, $requiredMethod);

        // Return a summary of one route or all routes through OPTIONS
        if ((!$callableMethod || $method != 'options') && $requiredMethod == 'options') {
            $description = Kernel\Inspector::describe($this, $apiBaseUri);
            return $this->getResponse()->send($description);
        }

        if (empty($callableMethod)) {
            return $this->fireParentController($apiBaseUri);
        }

        if ($method === '_all_') {
            $arguments[] = $method;
        }

        if (is_array($regexArguments)) {
            $arguments = array_merge($arguments, $regexArguments);
        }

        // Open class and scan methods
        $scannedParams = Kernel\Inspector::scanClassMethods($this, $method, $callableMethod);
        if (!is_array($scannedParams)) {
            return $scannedParams;
        }

        // Remove regex arguments
        $regexArgumentsLength = count($regexArguments);
        for ($i = 0; $i < $regexArgumentsLength; $i++) {
            array_shift($scannedParams);
        }

        // Read data from request body
        $this->getRequest()->readDataFromBody();

        // Collect arguments
        $collectedArguments = Kernel\Inspector::collectArguments($this, $scannedParams);
        if (!is_array($collectedArguments)) {
            return $collectedArguments;
        }
        if (count($collectedArguments) > 0) {
            $arguments = array_merge($arguments, $collectedArguments);
        }

        // Check Access
        if ($this->checkAccessFn) {
            $this->fireCheckAccessFn($arguments);
        }

        // Fire method
        $object = $this->controller;
        return $this->fireMethod($callableMethod, $object, $arguments);
    }

    /**
     * @return false|string
     * @throws Exception
     */
    public function fireParentController(string $apiBaseUri)
    {
        if (!$this->getParentController()) {
            if ($this->getRouter()->getFallbackMethod()) {
                $m = $this->getRouter()->getFallbackMethod();
                return $this->getResponse()->send($this->controller->$m());
            } else {
                $reason = "There is no route for '$apiBaseUri'.";
                return $this->getResponse()->sendBadRequest('RouteNotFoundException', $reason);
            }
        } else {
            return false;
        }
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @param array $arguments
     * @throws Exception
     */
    public function fireCheckAccessFn(array $arguments): void
    {
        try {
            $fireArguments = array($this->getClient()->getUrl(), $arguments);
            call_user_func_array($this->checkAccessFn, $fireArguments);
        } catch (Exception $e) {
            $this->getResponse()->sendException($e);
        }
    }

    /**
     * @param string|callable $pMethod
     * @param string|object $pController
     * @param array $pArguments
     * @return false|string
     * @throws Exception
     */
    public function fireMethod($pMethod, $pController, array $pArguments)
    {
        $callable = false;

        if ($pController && is_string($pMethod)) {
            if (!method_exists($pController, $pMethod)) {
                $callableMethodClassName = is_string($pController) ? $pController : get_class($pController);
                $reason = sprintf('Method %s in class %s not found.', $pMethod, $callableMethodClassName);
                return $this->getResponse()->sendError('MethodNotFoundException', $reason);
            }
            $callable = array($pController, $pMethod);
        } elseif (is_callable($pMethod)) {
            $callable = $pMethod;
        }

        if (!$callable) {
            return false;
        }

        try {
            return $this->getResponse()->send(call_user_func_array($callable, $pArguments));
        } catch (TypeError | InvalidArgumentException | Exception $e) {
            return $this->getResponse()->sendException($e);
        }
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
