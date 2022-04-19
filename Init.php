<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Core\Base\InitClass;

/**
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Init extends InitClass
{
    public function init()
    {
        ExportManager::addOption('Ticket', 'ticket', 'fas fa-receipt');
    }

    public function update()
    {
        // se ejecuta cada vez que se instala o actualiza el plugin
        $appSettings = ToolBox::appSettings();
        $appSettings->set('default', 'enable_api', true);
        $appSettings->save();
    }
}