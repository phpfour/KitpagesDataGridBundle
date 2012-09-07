<?php

namespace Kitpages\DataGridBundle\Model;

class Field
{
    /** @var string */
    protected $fieldName = null;

    /** @var string */
    protected $label = null;

    /** @var boolean */
    protected $sortable = false;

    /** @var boolean */
    protected $filterable = false;

    /** @var boolean */
    protected $visible = true;

    /** @var function */
    protected $formatValueCallback = null;

    /** @var boolean */
    protected $autoEscape = true;

    /** @var boolean */
    protected $translatable = false;

    public function __construct($fieldName, $optionList = array())
    {
        $this->fieldName = $fieldName;
        $this->label     = $fieldName;

        foreach ($optionList as $key => $val) {
            if (in_array($key, array(
                "label",
                "sortable",
                "filterable",
                "visible",
                "formatValueCallback",
                "autoEscape",
                "translatable"
            ))
            ) {
                $this->$key = $val;
            } else {
                throw new \InvalidArgumentException("key $key doesn't exist in option list");
            }
        }
    }

    /**
     * @param string $fieldName
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @param boolean $filterable
     */
    public function setFilterable($filterable)
    {
        $this->filterable = $filterable;
    }

    /**
     * @return boolean
     */
    public function getFilterable()
    {
        return $this->filterable;
    }

    /**
     * @param \Kitpages\DataGridBundle\Model\function $formatValueCallback
     */
    public function setFormatValueCallback($formatValueCallback)
    {
        $this->formatValueCallback = $formatValueCallback;
    }

    /**
     * @return \Kitpages\DataGridBundle\Model\function
     */
    public function getFormatValueCallback()
    {
        return $this->formatValueCallback;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param boolean $sortable
     */
    public function setSortable($sortable)
    {
        $this->sortable = $sortable;
    }

    /**
     * @return boolean
     */
    public function getSortable()
    {
        return $this->sortable;
    }

    /**
     * @param boolean $visible
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
    }

    /**
     * @return boolean
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * @param boolean $autoEscape
     */
    public function setAutoEscape($autoEscape)
    {
        $this->autoEscape = $autoEscape;
    }

    /**
     * @return boolean
     */
    public function getAutoEscape()
    {
        return $this->autoEscape;
    }

    /**
     * @param boolean $translatable
     */
    public function setTranslatable($translatable)
    {
        $this->translatable = $translatable;
    }

    /**
     * @return boolean
     */
    public function getTranslatable()
    {
        return $this->translatable;
    }

}
