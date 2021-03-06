<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Test\Basic;

use Abigail\Server;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Test\Controller\MyRoutes;

class BasicTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testCustomUrl()
    {
        $abigail = Server::create('/', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->collectRoutes();
        $response = $abigail->simulateCall('/test/test');
        $this->assertEquals(["status" => 200, "data" => "test"], json_decode($response, true));
    }
}
