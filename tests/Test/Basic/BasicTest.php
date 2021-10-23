<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)

namespace Test\Basic;

use Abigail\Server;
use PHPUnit\Framework\TestCase;
use Test\Controller\MyRoutes;

class BasicTest extends TestCase
{
    public function testCustomUrl()
    {
        $abigail = Server::create('/', new MyRoutes)
            ->setClient('Abigail\\InternalClient')
            ->collectRoutes();
        $response = $abigail->simulateCall('/test/test');
        $this->assertEquals(["status" => 200, "data" => "test"], json_decode($response, true));
    }
}
