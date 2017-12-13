<?php
/**
 * Class A2E_Models_Bind
 */
class A2E_Models_Bind extends Erfurt_Sparql_Query2_GroupGraphPattern
{
    /**
     * @var mixed
     */
    protected $element;

    /**
     * @var Erfurt_Sparql_Query2_Var
     */
    protected $var;

    /**
     *
     * @param $element
     * @param boolean $negate - чтобы было !BOUND
     */
    public function __construct($element, Erfurt_Sparql_Query2_Var $var)
    {
        $this->element = $element;
        $this->var = $var;
        parent::__construct();
    }

    /**
     * get the string representation
     * @return string
     */
    public function getSparql()
    {
        return 'BIND(' . $this->element->getSparql() . ' AS ' . $this->var->getSparql() . ')';
    }
}