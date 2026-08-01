<?php
declare(strict_types=1);

use App\Core\Application;

require dirname(__DIR__) . '/vendor/autoload.php';
$app = Application::boot(dirname(__DIR__));
require dirname(__DIR__) . '/routes/web.php';
require dirname(__DIR__) . '/routes/api.php';
$app->run();
