<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Omines\DataTablesBundle\Adapter\Doctrine\ORM;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Omines\DataTablesBundle\DataTableState;

/**
 * SearchCriteriaProvider.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class SearchCriteriaProvider implements QueryBuilderProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(QueryBuilder $queryBuilder, DataTableState $state)
    {
        $this->processSearchColumns($queryBuilder, $state);
        $this->processGlobalSearch($queryBuilder, $state);
    }

    private function processSearchColumns(QueryBuilder $queryBuilder, DataTableState $state)
    {
        foreach ($state->getSearchColumns() as $searchInfo) {
            /** @var AbstractColumn $column */
            $column = $searchInfo['column'];
            $search = $searchInfo['search'];
            $isRegex = $searchInfo['regex'];

            if ('' !== trim($search)) {
                if (null !== ($filter = $column->getFilter())) {
                    if (!$filter->isValidValue($search)) {
                        continue;
                    }
                }
                if ($isRegex) {
                    $search = $queryBuilder->expr()->literal($search);
                    $queryBuilder->andWhere($column->getField().' REGEXP '.$search);
                } elseif (str_contains($search, 'search_between_date_')) {
                    $search = str_replace('search_between_date_', '', $search);
                    $search = explode('|',$search);
                    $queryBuilder->andWhere($column->getField().' >= \''.$search[0].'\' AND '.$column->getField().' <= \''.$search[1].'\'');
                } elseif (str_contains($search, 'search_date_')) {
                    $search = str_replace('search_date_', '', $search);
                    $queryBuilder->andWhere($column->getField().' = \''.$search.'\'');
                } else {
                    $search = $queryBuilder->expr()->literal($search);
                    $queryBuilder->andWhere(new Comparison($column->getField(), $column->getOperator(), $search));
                }
            }
        }
    }

    private function processGlobalSearch(QueryBuilder $queryBuilder, DataTableState $state)
    {
        if (!empty($globalSearch = $state->getGlobalSearch())) {
            $expr = $queryBuilder->expr();
            $comparisons = $expr->orX();
            foreach ($state->getDataTable()->getColumns() as $column) {
                if ($column->isGlobalSearchable() && !empty($column->getField()) && $column->isValidForSearch(
                        $globalSearch
                    )) {
                    $comparisons->add(
                        new Comparison(
                            $column->getLeftExpr(), $column->getOperator(),
                            $expr->literal($column->getRightExpr($globalSearch))
                        )
                    );
                }
            }
            $queryBuilder->andWhere($comparisons);
        }
    }
}
