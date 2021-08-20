<?php

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;

class EditTicketPrinter extends \FacturaScripts\Core\Lib\ExtendedController\EditController
{
    /**
     * @return string
     */
    public function getAppKey(): string
    {
        return $this->getModel()->apikey;
    }

    /**
     * @return string
     */
    public function getAppUrl(): string
    {
        return 'https://megacity20.com' . \FS_ROUTE;
    }

    /**
     * @return string
     */
    public function getModelClassName()
    {
        return "TicketPrinter";
    }

    /**
     * @return array
     */
    public function getPageData()
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
        $this->setTabsPosition('bottom');
        $this->addHtmlView('app', 'Tab/DownloadPrinterApp', 'TicketPrinter', 'app', 'fas fa-desktop');
        $this->createViewsTicket();
    }

    /**
     * @param string $viewName
     */
    protected function createViewsTicket(string $viewName = "ListTicket")
    {
        $this->addListView($viewName, "Ticket", "tickets", "fas fa-receipt");
        $this->views[$viewName]->addOrderBy(["id"], "id");
        $this->views[$viewName]->addOrderBy(["title"], "title");
        $this->views[$viewName]->addOrderBy(["creationdate"], "date", 2);
        $this->views[$viewName]->addSearchFields(["title"]);

        /// disable printer column
        $this->views[$viewName]->disableColumn('printer');

        /// disable buttons
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