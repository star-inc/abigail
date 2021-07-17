<?php
// PHPUnit bootstrap for Abigail
$loader = include __DIR__ . '/../vendor/autoload.php';
$loader->setPsr4('Test\\', __DIR__ . '/Test');
