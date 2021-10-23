<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)

// PHPUnit bootstrap for Abigail
$loader = include __DIR__ . '/../vendor/autoload.php';
$loader->setPsr4('Test\\', __DIR__ . '/Test');
