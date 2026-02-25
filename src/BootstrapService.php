<?php

/**
 * Bootstrap service for the Institutional Module.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 *
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2024 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional;

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Menu\MenuEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BootstrapService
{
    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $dispatcher;

    /**
     * @var array
     */
    private array $module;

    /**
     * @var ModulesClassLoader
     */
    private ModulesClassLoader $classLoader;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        array $module,
        ModulesClassLoader $classLoader
    ) {
        $this->dispatcher  = $dispatcher;
        $this->module      = $module;
        $this->classLoader = $classLoader;
    }

    /**
     * Subscribe module event listeners to the OpenEMR event dispatcher.
     */
    public function subscribeToEvents(): void
    {
        $this->dispatcher->addListener(MenuEvent::MENU_UPDATE, [$this, 'addMenuItems']);
    }

    /**
     * Add institutional module menu items to the OpenEMR navigation.
     *
     * @param MenuEvent $event
     * @return MenuEvent
     */
    public function addMenuItems(MenuEvent $event): MenuEvent
    {
        $menu = $event->getMenu();

        $menuItem             = new \stdClass();
        $menuItem->requirement = 0;
        $menuItem->target     = 'institutional';
        $menuItem->menu_id    = 'institutional0';
        $menuItem->label      = xlt('Institutional');
        $menuItem->url        = '/interface/modules/custom_modules/oe-module-institutional/public/index.php';
        $menuItem->children   = [];
        $menuItem->acl_req    = [];

        foreach ($menu as $item) {
            if ($item->menu_id === 'admimg') {
                $item->children[] = $menuItem;
                break;
            }
        }

        $event->setMenu($menu);

        return $event;
    }
}
