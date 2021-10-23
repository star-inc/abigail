<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)

namespace Test\Synthetic;

use Abigail\Server;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Test\Controller\MyRoutes;

class CollectTest extends TestCase
{
    /**
     * @var Server
     */
    private Server $abigail;

    public function setUp(): void
    {
        $this->abigail = Server::create('/', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->collectRoutes();
    }

    /**
     * @throws ReflectionException
     */
    public function testNonPhpDocMethod(): void
    {
        $response = $this->abigail->simulateCall('/method-without-php-doc');
        $this->assertEquals(["status" => 200, "data" => "hi"], json_decode($response, true));
    }

    /**
     * @throws ReflectionException
     */
    public function testUrlAnnotation(): void
    {
        $response = $this->abigail->simulateCall('/stats');
        $this->assertEquals(["status" => 200, "data" => "Stats for 1"], json_decode($response, true));

        $response = $this->abigail->simulateCall('/stats/23');
        $this->assertEquals(["status" => 200, "data" => "Stats for 23"], json_decode($response, true));
    }

    /**
     * @throws ReflectionException
     */
    public function testOwnController(): void
    {
        $response = $this->abigail->simulateCall('/login', 'post');

        $this->assertEquals(
            [
                "status" => 400,
                "error" => "MissingRequiredArgumentException",
                "message" => "Argument 'username' is missing."
            ],
            json_decode($response, true)
        );

        $response = $this->abigail->simulateCall('/login?username=bla', 'post');

        $this->assertEquals(
            [
                "status" => 400,
                "error" => "MissingRequiredArgumentException",
                "message" => "Argument 'password' is missing."
            ],
            json_decode($response, true)
        );

        $response = $this->abigail->simulateCall('/login?username=peter&password=pwd', 'post');

        $this->assertEquals(["status" => 200, "data" => true], json_decode($response, true));

        $response = $this->abigail->simulateCall('/login?username=peter&password=pwd');

        $this->assertEquals(
            [
                "status" => 400,
                "error" => "RouteNotFoundException",
                "message" => "There is no route for 'login'."
            ],
            json_decode($response, true)
        );
    }
}
