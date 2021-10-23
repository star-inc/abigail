<?php
/*
 * (c) 2021 Star Inc. (https://starinc.xyz)
 */

namespace Abigail\Kernel;

use Abigail\Server;
use ReflectionException;
use ReflectionMethod;

class Router
{
    private Server $server;

    /**
     * List of excluded methods.
     *
     * @var array|string array('methodOne', 'methodTwo') or * for all methods
     */
    protected $collectRoutesExclude = array('__construct');

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Current routes.
     *
     * structure:
     *  array(
     *    '<uri>' => <callable>
     *  )
     *
     * @var array
     */
    protected array $routes = array();

    /**
     * If this controller can not find a route,
     * we fire this method and send the result.
     *
     * @var string
     */
    protected string $fallbackMethod = '';

    /**
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return string
     */
    public function getFallbackMethod(): string
    {
        return $this->fallbackMethod;
    }

    /**
     * Getter for describeRoutes.
     *
     * @return boolean
     */
    public function getDescribeRoutes(): bool
    {
        return $this->describeRoutes;
    }

    /**
     * Sets whether the service should serve route descriptions
     * through the OPTIONS method.
     *
     * @param boolean $pDescribeRoutes
     * @return Server  $this
     */
    public function setDescribeRoutes(bool $pDescribeRoutes): Server
    {
        $this->describeRoutes = $pDescribeRoutes;
        return $this->server;
    }

    /**
     * Sets whether the service should serve route descriptions
     * through the OPTIONS method.
     *
     * @var boolean
     */
    protected bool $describeRoutes = true;

    /**
     * Setup automatic routes.
     *
     * @return Server
     * @throws ReflectionException
     */
    public function collectRoutes(): Server
    {
        if ($this->collectRoutesExclude == '*') {
            return $this->server;
        }

        $methods = get_class_methods($this->server->getController());
        foreach ($methods as $method) {
            if (in_array($method, $this->collectRoutesExclude)) {
                continue;
            }

            $info = explode('/', preg_replace('/([a-z]*)(([A-Z]+)([a-zA-Z0-9_]*))/', '$1/$2', $method));
            $uri = Utils::camelCase2Dashes((empty($info[1]) ? '' : $info[1]));

            $httpMethod = $info[0];
            if ($httpMethod == 'all') {
                $httpMethod = '_all_';
            }

            $reflectionMethod = new ReflectionMethod($this->server->getController(), $method);
            if ($reflectionMethod->isPrivate()) {
                continue;
            }

            $phpDocs = Utils::getMethodMetaData($reflectionMethod);
            if (isset($phpDocs['url'])) {
                if (isset($phpDocs['url']['url'])) {
                    //only one route
                    $this->routes[$phpDocs['url']['url']][$httpMethod] = $method;
                } else {
                    foreach ($phpDocs['url'] as $urlAnnotation) {
                        $this->routes[$urlAnnotation['url']][$httpMethod] = $method;
                    }
                }
            } else {
                $this->routes[$uri][$httpMethod] = $method;
            }
        }

        return $this->server;
    }

    /**
     * Find and return the route for $pUri.
     *
     * @param string $pUri
     * @param string $pMethod limit to method.
     * @return array|boolean
     */
    public function findRoute(string $pUri, string $pMethod = '_all_')
    {
        if (isset($this->routes[$pUri][$pMethod]) && $method = $this->routes[$pUri][$pMethod]) {
            return array($method, array(), $pMethod, $pUri);
        } elseif ($pMethod != '_all_' && isset($this->routes[$pUri]['_all_']) && $method = $this->routes[$pUri]['_all_']) {
            return array($method, array(), $pMethod, $pUri);
        } else {
            //maybe we have a regex uri
            foreach ($this->routes as $routeUri => $routeMethods) {
                if (preg_match('|^' . $routeUri . '$|', $pUri, $matches)) {
                    if (!isset($routeMethods[$pMethod])) {
                        if (isset($routeMethods['_all_'])) {
                            $pMethod = '_all_';
                        } else {
                            continue;
                        }
                    }
                    $arguments = [];
                    array_shift($matches);
                    foreach ($matches as $match) {
                        $arguments[] = $match;
                    }
                    return array($routeMethods[$pMethod], $arguments, $pMethod, $routeUri);
                }
            }
        }
        return false;
    }

    /**
     * Adds a new route for all http methods (get, post, put, delete, options, head, patch).
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @param string $pHttpMethod If you want to limit to a HTTP method.
     * @return Server
     */
    public function addRoute(string $pUri, $pCb, string $pHttpMethod = '_all_'): Server
    {
        $this->routes[$pUri][$pHttpMethod] = $pCb;

        return $this->server;
    }

    /**
     * Same as addRoute, but limits to GET.
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @return Server
     */
    public function addGetRoute(string $pUri, $pCb): Server
    {
        $this->addRoute($pUri, $pCb, 'get');

        return $this->server;
    }

    /**
     * Same as addRoute, but limits to POST.
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @return Server
     */
    public function addPostRoute(string $pUri, $pCb): Server
    {
        $this->addRoute($pUri, $pCb, 'post');

        return $this->server;
    }

    /**
     * Same as addRoute, but limits to PUT.
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @return Server
     */
    public function addPutRoute(string $pUri, $pCb): Server
    {
        $this->addRoute($pUri, $pCb, 'put');

        return $this->server;
    }

    /**
     * Same as addRoute, but limits to PATCH.
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @return Server
     */
    public function addPatchRoute(string $pUri, $pCb): Server
    {
        $this->addRoute($pUri, $pCb, 'patch');

        return $this->server;
    }

    /**
     * Same as addRoute, but limits to HEAD.
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @return Server
     */
    public function addHeadRoute(string $pUri, $pCb): Server
    {
        $this->addRoute($pUri, $pCb, 'head');

        return $this->server;
    }

    /**
     * Same as addRoute, but limits to OPTIONS.
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @return Server
     */
    public function addOptionsRoute(string $pUri, $pCb): Server
    {
        $this->addRoute($pUri, $pCb, 'options');

        return $this->server;
    }

    /**
     * Same as addRoute, but limits to DELETE.
     *
     * @param string $pUri
     * @param callable|string $pCb The method name of the passed controller or a php callable.
     * @return Server
     */
    public function addDeleteRoute(string $pUri, $pCb): Server
    {
        $this->addRoute($pUri, $pCb, 'delete');

        return $this->server;
    }
}
