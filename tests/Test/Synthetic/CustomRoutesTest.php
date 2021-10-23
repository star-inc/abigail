<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Test\Synthetic;

use Abigail\Server;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Test\Controller\MyRoutes;

class CustomRoutesTest extends TestCase
{

    /**
     * @throws ReflectionException
     */
    public function testOwnController(): void
    {
        $abigail = Server::create('/', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->addPostRoute('login', 'postLogin');

        $response = $abigail->simulateCall('/login?', 'post');

        $this->assertEquals(
            [
                "status" => 400,
                "error" => "MissingRequiredArgumentException",
                "message" => "Argument 'username' is missing."
            ],
            json_decode($response, true)
        );

        $response = $abigail->simulateCall('/login?username=bla', 'post');

        $this->assertEquals(
            [
                "status" => 400,
                "error" => "MissingRequiredArgumentException",
                "message" => "Argument 'password' is missing."
            ],
            json_decode($response, true)
        );

        $response = $abigail->simulateCall('/login?username=peter&password=pwd', 'post');

        $this->assertEquals(["status" => 200, "data" => true], json_decode($response, true));

        $response = $abigail->simulateCall('/login?username=peter&password=pwd');

        $this->assertEquals(
            [
                "status" => 400,
                "error" => "RouteNotFoundException",
                "message" => "There is no route for 'login'."
            ],
            json_decode($response, true)
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testOwnControllerWithDifferentPrefix(): void
    {
        $abigail = Server::create('/v1', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->addPostRoute('login', 'postLogin');

        $response = $abigail->simulateCall('/v1/login?username=peter&password=pwd', 'post');

        $this->assertEquals(["status" => 200, "data" => true], json_decode($response, true));

        $abigail = Server::create('/v1/', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->addPostRoute('login', 'postLogin');

        $response = $abigail->simulateCall('/v1/login?username=peter&password=pwd', 'post');

        $this->assertEquals(["status" => 200, "data" => true], json_decode($response, true));

        $abigail = Server::create('v1', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->addPostRoute('login', 'postLogin');

        $response = $abigail->simulateCall('/v1/login?username=peter&password=pwd', 'post');

        $this->assertEquals(["status" => 200, "data" => true], json_decode($response, true));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testSubController(): void
    {
        $abigail = Server::create('v1', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->addPostRoute('login', 'postLogin')
            ->addSubController('sub', new MyRoutes())
            ->addPostRoute('login', 'postLogin')
            ->done();

        $response = $abigail->simulateCall('/v1/sub/login?username=peter&password=pwd', 'post');

        $this->assertEquals(["status" => 200, "data" => true], json_decode($response, true));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testSubControllerWithSlashRootParent(): void
    {
        $abigail = Server::create('/', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->addSubController('sub', new MyRoutes())
            ->addPostRoute('login', 'postLogin')
            ->done();

        $response = $abigail->simulateCall('/sub/login?username=peter&password=pwd', 'post');

        $this->assertEquals(["status" => 200, "data" => true], json_decode($response, true));
    }
}
