<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Lib\ExportManager;

/**
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
        $this->renameTable();
    }

    private function renameTable()
    {
        $table = 'tickets';
        $dataBase = new DataBase();
        if ($dataBase->tableExists($table)) {
            $columns = $dataBase->getColumns($table);
            if (isset($columns['id']) && isset($columns['idprinter'])) {
                $dataBase->exec("RENAME TABLE " . $table . " TO " . $table . "_docs;");
            }
        }
    }
}