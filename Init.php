<?php

namespace FacturaScripts\Plugins\Tickets;

use FacturaScripts\Dinamic\Lib\ExportManager;

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init()
    {
        ExportManager::addOption('Ticket', 'ticket', 'fas fa-receipt');
    }

    public function update()
    {
        // se ejecuta cada vez que se instala o actualiza el plugin
    }
}