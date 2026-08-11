<?php

declare(strict_types=1);

use App\Http\Application;
use App\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Application $application */
$application = require dirname(__DIR__)
    . '/bootstrap/app.php';

$request = Request::fromGlobals();

$response = $application->handle($request);

$response->emit();
