<?php

namespace Kitpages\DataGridBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Doctrine\ODM\MongoDB\Query\Builder as QueryBuilder;

use Kitpages\DataGridBundle\Model\GridConfig;
use Kitpages\DataGridBundle\Model\Grid;
use Kitpages\DataGridBundle\Model\PaginatorConfig;
use Kitpages\DataGridBundle\Model\Paginator;
use Kitpages\DataGridBundle\Tool\UrlTool;
use Kitpages\DataGridBundle\KitpagesDataGridEvents;
use Kitpages\DataGridBundle\Event\DataGridEvent;

class ODMGridManager
{
    /** @var EventDispatcherInterface */
    protected $dispatcher = null;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getGrid(QueryBuilder $queryBuilder, GridConfig $gridConfig, Request $request)
    {
        // create grid object
        $grid = new Grid();
        $grid->setGridConfig($gridConfig);
        $grid->setUrlTool(new UrlTool());
        $grid->setRequestUri($request->getRequestUri());

        // create base request
        $gridQueryBuilder = clone($queryBuilder);

        // Apply filters
        $filter = $request->query->get($grid->getFilterFormName(), "");
        $this->applyFilter($gridQueryBuilder, $grid, $filter);

        // Apply sorting
        $sortField = $request->query->get($grid->getSortFieldFormName(), "");
        $sortOrder = $request->query->get($grid->getSortOrderFormName(), "");
        $this->applySort($gridQueryBuilder, $grid, $sortField, $sortOrder);

        // build paginator
        $paginatorConfig = $gridConfig->getPaginatorConfig();

        if ($paginatorConfig == null) {
            $paginatorConfig = new PaginatorConfig();
            $paginatorConfig->setCountFieldName($gridConfig->getCountFieldName());
            $paginatorConfig->setName($gridConfig->getName());
        }

        $paginator = $this->getPaginator($gridQueryBuilder, $paginatorConfig, $request);
        $grid->setPaginator($paginator);

        // calculate limits
        $gridQueryBuilder->limit($paginator->getPaginatorConfig()->getItemCountInPage());
        $gridQueryBuilder->skip(($paginator->getCurrentPage() - 1) * $paginator->getPaginatorConfig()->getItemCountInPage());

        // send event for changing grid query builder
        $event = new DataGridEvent();
        $event->set("grid", $grid);
        $event->set("gridQueryBuilder", $gridQueryBuilder);
        $event->set("request", $request);
        $this->dispatcher->dispatch(KitpagesDataGridEvents::ON_GET_GRID_QUERY, $event);

        if (!$event->isDefaultPrevented()) {
            $query = $gridQueryBuilder->getQuery();
            $event->set("query", $query);
        }

        $this->dispatcher->dispatch(KitpagesDataGridEvents::AFTER_GET_GRID_QUERY, $event);

        // hack : recover query from the event so the developer can build a new grid
        // from the gridQueryBuilder in the listener and re-inject it in the event.
        $query = $event->get("query");

        // execute the query
        $itemList = $query->execute();

        // normalize result (for request of type $queryBuilder->select("item, bp, item.id * 3 as titi"); )
        $normalizedItemList = array();

        foreach ($itemList as $document) {

            $normalizedItem = array();
            $item = $document->toArray();

            foreach ($item as $key => $val) {
                if (is_int($key)) {
                    foreach ($val as $newKey => $newVal) {
                        $normalizedItem[$newKey] = $newVal;
                    }
                } else {
                    $normalizedItem[$key] = $val;
                }
            }

            $normalizedItemList[] = $normalizedItem;
        }

        // end normalization
        $grid->setItemList($normalizedItemList);

        return $grid;
    }

    protected function applyFilter(QueryBuilder $queryBuilder, Grid $grid, $filter)
    {
        if (!$filter) {
            return;
        }

        $event = new DataGridEvent();
        $event->set("grid", $grid);
        $event->set("gridQueryBuilder", $queryBuilder);
        $event->set("filter", $filter);

        $this->dispatcher->dispatch(KitpagesDataGridEvents::ON_APPLY_FILTER, $event);

        if (!$event->isDefaultPrevented()) {

            $fieldList         = $grid->getGridConfig()->getFieldList();
            $filterRequestList = array();

            foreach ($fieldList as $field) {
                if ($field->getFilterable()) {
                    $filterRequestList[] = $queryBuilder->addOr($queryBuilder->expr()->field($field->getFieldName())->equals(new \MongoRegex('/' . $filter . '.*/i')));
                }
            }

            $grid->setFilterValue($filter);
        }

        $this->dispatcher->dispatch(KitpagesDataGridEvents::AFTER_APPLY_FILTER, $event);
    }

    protected function applySort(QueryBuilder $gridQueryBuilder, Grid $grid, $sortField, $sortOrder)
    {
        if (!$sortField) {
            return;
        }

        $event = new DataGridEvent();
        $event->set("grid", $grid);
        $event->set("gridQueryBuilder", $gridQueryBuilder);
        $event->set("sortField", $sortField);
        $event->set("sortOrder", $sortOrder);

        $this->dispatcher->dispatch(KitpagesDataGridEvents::ON_APPLY_SORT, $event);

        if (!$event->isDefaultPrevented()) {

            $sortFieldObject = null;
            $fieldList       = $grid->getGridConfig()->getFieldList();

            foreach ($fieldList as $field) {
                if ($field->getFieldName() == $sortField) {
                    if ($field->getSortable() == true) {
                        $sortFieldObject = $field;
                    }
                    break;
                }
            }

            if (!$sortFieldObject) {
                return;
            }

            if ($sortOrder != "DESC") {
                $sortOrder = "ASC";
            }

            $gridQueryBuilder->sort($sortField, ($sortOrder == 'ASC') ? 1 : -1);
            $grid->setSortField($sortField);
            $grid->setSortOrder($sortOrder);
        }

        $this->dispatcher->dispatch(KitpagesDataGridEvents::AFTER_APPLY_SORT, $event);
    }

    public function getPaginator(QueryBuilder $queryBuilder, PaginatorConfig $paginatorConfig, Request $request)
    {
        // create paginator object
        $paginator = new Paginator();
        $paginator->setPaginatorConfig($paginatorConfig);
        $paginator->setUrlTool(new UrlTool());
        $paginator->setRequestUri($request->getRequestUri());

        // get currentPage
        $paginator->setCurrentPage($request->query->get($paginatorConfig->getRequestQueryName("currentPage"), 1));

        // calculate total object count
        $countQueryBuilder = clone($queryBuilder);

        // event to change paginator query builder
        $event = new DataGridEvent();
        $event->set("paginator", $paginator);
        $event->set("paginatorQueryBuilder", $countQueryBuilder);
        $event->set("request", $request);
        $this->dispatcher->dispatch(KitpagesDataGridEvents::ON_GET_PAGINATOR_QUERY, $event);

        if (!$event->isDefaultPrevented()) {
            $query = $countQueryBuilder->getQuery();
            $event->set("query", $query);
        }
        $this->dispatcher->dispatch(KitpagesDataGridEvents::AFTER_GET_PAGINATOR_QUERY, $event);

        // hack : recover query from the event so the developer can build a new query
        // from the paginatorQueryBuilder in the listener and re-inject it in the event.
        $query = $event->get("query");

        $totalCount = $query->execute()->count();
        $paginator->setTotalItemCount($totalCount);
        
        // calculate total page count
        if ($paginator->getTotalItemCount() == 0) {
            $paginator->setTotalPageCount(0);
        } else {
            $paginator->setTotalPageCount((int)((($paginator->getTotalItemCount() - 1) / $paginatorConfig->getItemCountInPage()) + 1));
        }

        // change current page if needed
        if ($paginator->getCurrentPage() > $paginator->getTotalPageCount()) {
            $paginator->setCurrentPage(1);
        }

        // calculate nbPageLeft and nbPageRight
        $nbPageLeft  = (int)($paginatorConfig->getVisiblePageCountInPaginator() / 2);
        $nbPageRight = $paginatorConfig->getVisiblePageCountInPaginator() - 1 - $nbPageLeft;

        // calculate lastPage to display
        $maxPage = min($paginator->getTotalPageCount(), $paginator->getCurrentPage() + $nbPageRight);
        // adapt minPage and maxPage
        $minPage = max(1, $maxPage - ($paginatorConfig->getVisiblePageCountInPaginator() - 1));
        $maxPage = min($paginator->getTotalPageCount(), $minPage + ($paginatorConfig->getVisiblePageCountInPaginator() - 1));

        $paginator->setMinPage($minPage);
        $paginator->setMaxPage($maxPage);

        // calculate previousButton
        if ($paginator->getCurrentPage() == 1) {
            $paginator->setPreviousButtonPage(null);
        } else {
            $paginator->setPreviousButtonPage($paginator->getCurrentPage() - 1);
        }

        // calculate nextButton
        if ($paginator->getCurrentPage() == $paginator->getTotalPageCount()) {
            $paginator->setNextButtonPage(null);
        } else {
            $paginator->setNextButtonPage($paginator->getCurrentPage() + 1);
        }

        return $paginator;
    }
}
