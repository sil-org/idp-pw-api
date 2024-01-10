<?php

// This is global bootstrap for autoloading

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');
require_once(__DIR__ . '/../common/config/bootstrap.php');

$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';

$config = require(__DIR__ . '/../common/config/main.php');

$config['basePath'] = dirname(__DIR__);

(new yii\web\Application($config));
