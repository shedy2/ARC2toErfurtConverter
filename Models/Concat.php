<?php
/**
 * Class A2E_Models_Concat
 */
class A2E_Models_Concat extends Erfurt_Sparql_Query2_ElementHelper
{
    /**
     * @var mixed
     */
    protected $elements;

    /**
     * @var mixed
     */
    protected $var;

    /**
     * A2E_Models_Concat constructor.
     * @param $elements
     */
    public function __construct($elements)
    {
        $this->elements = $elements;
        parent::__construct();
    }

    /**
     * get the string representation
     * @return string
     */
    public function getSparql()
    {
        return 'CONCAT(' . implode(',', array_map(function ($element) {
                return $element->getSparql();
            }, $this->elements)) . ')';
    }
}