<?php

namespace CoreShop\Bundle\ResourceBundle\CoreExtension;

use CoreShop\Component\Resource\Model\ResourceInterface;
use CoreShop\Component\Resource\Repository\RepositoryInterface;
use Pimcore\Model;
use Pimcore\Model\Object;

abstract class Select extends Model\Object\ClassDefinition\Data\Select
{
    /**
     * Type for the column to query.
     *
     * @var string
     */
    public $queryColumnType = 'int(11)';

    /**
     * Type for the column.
     *
     * @var string
     */
    public $columnType = 'int(11)';

    /**
     * @var bool
     */
    public $allowEmpty = false;

    /**
     * @return RepositoryInterface
     */
    protected abstract function getRepository();

    /**
     * @return string
     */
    protected abstract function getModel();

    /**
     * @param $object
     * @param $data
     * @param array $params
     * @return string
     */
    public function preSetData($object, $data, $params = [])
    {
        if (is_int($data) || is_string($data)) {
            if (intval($data)) {
                return $this->getDataFromResource($data, $object, $params);
            }
        }

        return $data;
    }

    /**
     * @param $object
     * @param array $params
     * @return string
     */
    public function preGetData($object, $params = [])
    {
        $data = $object->{$this->getName()};

        if ($data instanceof ResourceInterface) {
            //Reload from Database, but only if available
            $tmpData = $data::getById($data->getId());

            if ($tmpData instanceof ResourceInterface) {
                return $tmpData;
            }
        }

        return $data;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataForResource
     * @param ResourceInterface $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataForResource($data, $object = null, $params = [])
    {
        if (is_a($data, $this->getModel())) {
            return $data->getId();
        }

        return null;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataFromResource
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataFromResource($data, $object = null, $params = [])
    {
        if (intval($data) > 0) {
            return $this->getRepository()->find($data);
        }

        return null;
    }

    /**
     * get data for query resource.
     *
     * @see Object\ClassDefinition\Data::getDataForQueryResource
     *
     * @param ResourceInterface $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     *
     * @return int|null
     */
    public function getDataForQueryResource($data, $object = null, $params = [])
    {
        if (is_a($data, $this->getModel())) {
            return $data->getId();
        }

        return null;
    }

    /**
     * get data for editmode.
     *
     * @see Object\ClassDefinition\Data::getDataForEditmode
     *
     * @param ResourceInterface $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     *
     * @return int
     */
    public function getDataForEditmode($data, $object = null, $params = [])
    {
        return $this->getDataForResource($data, $object, $params);
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataFromEditmode
     *
     * @param int                              $data
     * @param null|Model\Object\AbstractObject $object
     * @param array                            $params
     *
     * @return string
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        return $this->getDataFromResource($data, $object, $params);
    }

    /**
     * is empty.
     *
     * @param Model\Object\Concrete $data
     *
     * @return bool
     */
    public function isEmpty($data)
    {
        return !$data;
    }

    /**
     * get data for search index.
     *
     * @param $object
     * @param mixed $params
     *
     * @return int|string
     */
    public function getDataForSearchIndex($object, $params = [])
    {
        if ($object instanceof ResourceInterface) {
            return $object->getId();
        }

        return parent::getDataForSearchIndex($object, $params);
    }

    /**
     * @return boolean
     */
    public function isAllowEmpty()
    {
        return $this->allowEmpty;
    }

    /**
     * @param boolean $allowEmpty
     */
    public function setAllowEmpty($allowEmpty)
    {
        $this->allowEmpty = $allowEmpty;
    }
}