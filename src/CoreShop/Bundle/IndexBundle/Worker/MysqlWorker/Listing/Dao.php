<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2017 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
*/

namespace CoreShop\Bundle\IndexBundle\Worker\MysqlWorker\Listing;

use CoreShop\Bundle\IndexBundle\Worker\MysqlWorker;
use CoreShop\Component\Index\Listing\ListingInterface;
use Doctrine\DBAL\Query\QueryBuilder;
use Pimcore\Db;

class Dao
{
    /**
     * @var \Pimcore\Db\Connection
     */
    private $database;

    /**
     * @var MysqlWorker\Listing
     */
    private $model;

    /**
     * @var int
     */
    private $lastRecordCount;

    /**
     * Resource constructor.
     *
     * @param MysqlWorker\Listing $model
     */
    public function __construct(MysqlWorker\Listing $model)
    {
        $this->model = $model;
        $this->database = Db::get();
    }

    /**
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this->database);
    }

    /**
     * Load objects.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return array
     */
    public function load(QueryBuilder $queryBuilder)
    {
        $queryBuilder->from($this->model->getQueryTableName(), 'q');

        if ($this->model->getVariantMode() == ListingInterface::VARIANT_MODE_INCLUDE_PARENT_OBJECT) {
            if (!is_null($queryBuilder->getQueryPart('orderBy'))) {
                $queryBuilder->select('DISTINCT o_virtualObjectId as o_id');
                $queryBuilder->addGroupBy('o_virtualObjectId');
            } else {
                $queryBuilder->select('DISTINCT o_virtualObjectId as o_id');
            }
        } else {
            $queryBuilder->select('DISTINCT o_id');
        }

        $result = $this->database->executeQuery($queryBuilder->getSQL());
        $this->lastRecordCount = $result->rowCount();

        return $result->fetchAll();
    }

    /**
     * Load Group by values.
     *
     * @param QueryBuilder $queryBuilder
     * @param $fieldName
     * @param bool $countValues
     *
     * @return array
     */
    public function loadGroupByValues(QueryBuilder $queryBuilder, $fieldName, $countValues = false)
    {
        $queryBuilder->from($this->model->getQueryTableName(), 'q');
        $queryBuilder->groupBy($fieldName);
        $queryBuilder->orderBy($fieldName);

        if ($countValues) {
            if ($this->model->getVariantMode() == ListingInterface::VARIANT_MODE_INCLUDE_PARENT_OBJECT) {
                $queryBuilder->select($fieldName . ' as value, count(DISTINCT o_virtualObjectId) as count');
            } else {
                $queryBuilder->select($fieldName . ' as value, count(*) as count');
            }

            $stmt = $this->database->executeQuery($queryBuilder->getSQL());
            $result = $stmt->fetchAll();

            return $result;
        } else {
            $queryBuilder->select($fieldName);

            $stmt = $this->database->executeQuery($queryBuilder->getSQL());
            $queryResult = $stmt->fetchAll();

            $result = [];

            foreach ($queryResult as $row) {
                if ($row[$fieldName]) {
                    $result[] = $row[$fieldName];
                }
            }

            return $result;
        }
    }

    /**
     * Load Grouo by Relation values.
     *
     * @param QueryBuilder $queryBuilder
     * @param $fieldName
     * @param bool $countValues
     *
     * @return array
     */
    public function loadGroupByRelationValues(QueryBuilder $queryBuilder, $fieldName, $countValues = false)
    {
        $queryBuilder->from($this->model->getRelationTablename(), 'q');

        if ($countValues) {
            if ($this->model->getVariantMode() == ListingInterface::VARIANT_MODE_INCLUDE_PARENT_OBJECT) {
                $queryBuilder->select('dest as `value`, count(DISTINCT src_virtualObjectId) as `count`');
                $queryBuilder->where('fieldname = ' . $fieldName);
            } else {
                $queryBuilder->select('dest as `value`, count(*) as `count`');
                $queryBuilder->where('fieldname = ' . $fieldName);
            }

            $subQueryBuilder = new QueryBuilder($this->database);
            $subQueryBuilder->select('o_id');
            $subQueryBuilder->from($this->model->getQueryTableName(), 'q');
            $subQueryBuilder->where($queryBuilder->getQueryPart('where'));

            $queryBuilder->andWhere('src in ('.$subQueryBuilder->getSQL().') GROUP BY dest');

            $stmt = $this->database->executeQuery($queryBuilder->getSQL());
            $result = $stmt->fetchAll();

            return $result;
        } else {
            $queryBuilder->select('dest as `value`, count(DISTINCT src_virtualObjectId) as `count`');
            $queryBuilder->where('fieldname = ' . $fieldName);

            $subQueryBuilder = new QueryBuilder($this->database);
            $subQueryBuilder->select('o_id');
            $subQueryBuilder->from($this->model->getQueryTableName(), 'q');
            $subQueryBuilder->where($queryBuilder->getQueryPart('where'));

            $queryBuilder->andWhere('src in ('.$subQueryBuilder->getSQL().') GROUP BY dest');

            $stmt = $this->database->executeQuery($queryBuilder->getSQL());
            $result = $stmt->fetchColumn();

            return $result;
        }
    }

    /**
     * Get Count.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return int
     */
    public function getCount(QueryBuilder $queryBuilder)
    {
        $queryBuilder->from($this->model->getQueryTableName(), 'q');

        if ($this->model->getVariantMode() == ListingInterface::VARIANT_MODE_INCLUDE_PARENT_OBJECT) {
            $queryBuilder->select('count(DISTINCT o_virtualObjectId)');
        } else {
            $queryBuilder->select('count(*)');
        }

        $stmt = $this->database->executeQuery($queryBuilder->getSQL());
        return $stmt->fetchColumn();
    }

    /**
     * quoute value.
     *
     * @param $value
     *
     * @return mixed
     */
    public function quote($value)
    {
        return $this->database->quote($value);
    }

    /**
     * returns order by statement for similarity calculations based on given fields and object ids.
     *
     * @param $fields
     * @param $objectId
     *
     * @return string
     */
    public function buildSimilarityOrderBy($fields, $objectId)
    {
        //TODO: similarity
        /*
        try {
            $fieldString = '';
            $maxFieldString = '';

            foreach ($fields as $field) {
                if ($field instanceof AbstractSimilarity) {
                    if (!empty($fieldString)) {
                        $fieldString .= ',';
                        $maxFieldString .= ',';
                    }


                    $fieldString .= $this->db->quoteIdentifier($field->getField());
                    $maxFieldString .= 'MAX('.$this->db->quoteIdentifier($field->getField()).') as '.$this->db->quoteIdentifier($field->getField());
                }
            }

            $query = 'SELECT '.$fieldString.' FROM '.$this->model->getQueryTableName().' a WHERE a.o_id = ?;';
            $objectValues = $this->db->fetchRow($query, $objectId);

            $query = 'SELECT '.$maxFieldString.' FROM '.$this->model->getQueryTableName().' a';
            $maxObjectValues = $this->db->fetchRow($query);

            if (!empty($objectValues)) {
                $subStatement = [];

                foreach ($fields as $field) {
                    if ($field instanceof AbstractSimilarity) {
                        if ($objectValues[$field->getField()]) {
                            $subStatement[] =
                                '(' .
                                $this->db->quoteIdentifier($field->getField()) . '/' . $maxObjectValues[$field->getField()] .
                                ' - ' .
                                $objectValues[$field->getField()] / $maxObjectValues[$field->getField()] .
                                ') * ' . $field->getWeight();
                        }
                    }
                }

                if (count($subStatement) > 0) {
                    $statement = 'ABS('.implode(' + ', $subStatement).')';

                    return $statement;
                }
            } else {
                throw new \Exception('Field array for given object id is empty');
            }
        } catch (\Exception $e) {
        }*/

        return '';
    }

    /**
     * returns where statement for fulltext search index.
     *
     * @param $fields
     * @param $searchString
     *
     * @return string
     */
    public function buildFulltextSearchWhere($fields, $searchString)
    {
        $columnNames = [];

        foreach ($fields as $c) {
            $columnNames[] = $this->database->quoteIdentifier($c);
        }

        return 'MATCH ('.implode(',', $columnNames).') AGAINST ('.$this->database->quote($searchString).' IN BOOLEAN MODE)';
    }

    /**
     * get the record count for the last select query.
     *
     * @return int
     */
    public function getLastRecordCount()
    {
        return $this->lastRecordCount;
    }
}
