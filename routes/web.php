<?php

declare(strict_types=1);

use EzPhp\Http\Request;
use EzPhp\Routing\Router;

/** @var Router $router */
$router->get('/', fn (Request $r) => 'Hello from ez-php!');
