<?php

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\Institutional\Bootstrap;

$fileroot = OEGlobalsBag::getInstance()->get('fileroot');
$classLoader = new ModulesClassLoader($fileroot);
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\Institutional\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

$eventDispatcher = OEGlobalsBag::getInstance()->get('kernel')->getEventDispatcher();
$bootstrap = new Bootstrap($eventDispatcher);
$bootstrap->subscribeToEvents();
