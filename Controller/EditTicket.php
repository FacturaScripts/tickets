<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditTicket extends EditController
{
    public function getModelClassName(): string
    {
        return "Ticket";
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["title"] = "Ticket";
        $pageData["icon"] = "fas fa-search";
        return $pageData;
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case $mvn:
                parent::loadData($viewName, $view);
                if (false === $view->model->exists()) {
                    $view->model->nick = $this->user->nick;
                }
                break;
        }
    }
}