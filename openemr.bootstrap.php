<?php

/**
 * Bootstrap for OpenEMR Institutional Module.
 * Supports institutional settings such as E.R. and hospital environments.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 *
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2024 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Menu\MenuEvent;
use OpenEMR\Modules\Institutional\BootstrapService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\Institutional\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

/**
 * @var EventDispatcherInterface $eventDispatcher
 * @var array                    $module
 * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
 * @global                       $module          @see ModulesApplication::loadCustomModule
 */

$bootstrap = new BootstrapService($eventDispatcher, $module, $classLoader);
$bootstrap->subscribeToEvents();
