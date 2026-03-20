<?php
/**
 * Copyright (C) 2019-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditTicket extends EditController
{
    public function getModelClassName(): string
    {
        return 'Ticket';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'Ticket';
        $data['icon'] = 'fa-solid fa-search';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();

        // desactivamos los botones de nuevo y opciones
        $mvn = $this->getMainViewName();
        $this->setSettings($mvn, 'btnNew', false);
        $this->setSettings($mvn, 'btnOptions', false);
    }
}
