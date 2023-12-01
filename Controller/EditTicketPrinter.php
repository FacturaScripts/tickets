<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditTicketPrinter extends EditController
{
    public function getAppKey(): string
    {
        return $this->getModel()->apikey;
    }

    public function getAppUrl(): string
    {
        $url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $url .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        return substr($url, 0, strrpos($url, '/'));
    }

    public function getModelClassName(): string
    {
        return "TicketPrinter";
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["menu"] = "admin";
        $pageData["title"] = "printer";
        $pageData["icon"] = "fas fa-print";
        return $pageData;
    }

    protected function createViews()
    {
        parent::createViews();

        // desactivamos el botón de opciones
        $this->setSettings($this->getMainViewName(), 'btnOptions', false);

        $this->setTabsPosition('bottom');
        $this->createViewsDownloadApp();
        $this->createViewsTicket();
    }

    protected function createViewsDownloadApp(string $viewName = "app"): void
    {
        $this->addHtmlView($viewName, 'Tab/DownloadPrinterApp', 'TicketPrinter', 'app', 'fas fa-desktop');
    }

    protected function createViewsTicket(string $viewName = "ListTicket"): void
    {
        $this->addListView($viewName, "Ticket", "tickets", "fas fa-receipt");
        $this->views[$viewName]->addOrderBy(["id"], "id");
        $this->views[$viewName]->addOrderBy(["title"], "title");
        $this->views[$viewName]->addOrderBy(["creationdate"], "date", 2);
        $this->views[$viewName]->addSearchFields(["title"]);

        // disable printer column
        $this->views[$viewName]->disableColumn('printer');

        // disable buttons
        $this->setSettings($viewName, 'btnNew', false);
    }

    /**
     * Load view data.
     *
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

            case "ListTicket":
                $idprinter = $this->views[$mvn]->model->primaryColumnValue();
                $where = [new DataBaseWhere('idprinter', $idprinter)];
                $view->loadData('', $where);
                break;
        }
    }
}