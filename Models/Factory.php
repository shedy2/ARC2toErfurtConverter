<?php

/**
 * Class A2E_Models_Factory
 */
class A2E_Models_Factory
{
    /**
     * @var array
     */
    public $config = array(
        'ef_bind' => 'A2E_Models_Bind',
        'ef_concat' => 'A2E_Models_Concat',
        'ef_minus' => 'A2E_Models_Minus',
        'ef_var' => 'W_Var',
        'ef_and' => 'Erfurt_Sparql_Query2_ConditionalAndExpression',
        'ef_or' => 'Erfurt_Sparql_Query2_ConditionalOrExpression',
        'ef_sameterm' => 'Erfurt_Sparql_Query2_sameTerm',
        'ef_additive' => 'Erfurt_Sparql_Query2_AdditiveExpression',
        'ef_multiplicative' => 'Erfurt_Sparql_Query2_MultiplicativeExpression',
        'ef_triple' => 'Erfurt_Sparql_Query2_TriplesSameSubject',
        'ef_propertylist' => 'Erfurt_Sparql_Query2_PropertyList',
        'ef_objectlist' => 'Erfurt_Sparql_Query2_ObjectList',
        'ef_literal' => 'Erfurt_Sparql_Query2_RDFLiteral',
        'ef_iri' => 'Erfurt_Sparql_Query2_IriRef',
        'ef_bracketted' => 'Erfurt_Sparql_Query2_BrackettedExpression',
        'ef_filter' => 'Erfurt_Sparql_Query2_Filter',
        'ef_groupgraph' => 'Erfurt_Sparql_Query2_GroupGraphPattern',
        'ef_optional' => 'Erfurt_Sparql_Query2_OptionalGraphPattern',
        'ef_prefix' => 'Erfurt_Sparql_Query2_Prefix',
        'ef_lang' => 'Erfurt_Sparql_Query2_Lang',
        'ef' => 'Erfurt_Sparql_Query2',
        'ef_regex' => 'Erfurt_Sparql_Query2_Regex',
    );

    /**
     * A2E_Models_Factory constructor.
     * @param array $config
     */
    public function __construct($config = array())
    {
        $this->setConfig($config);
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        foreach ($config as $model => $class) {
            if (isset($config[$model])) {
                $config[$model] = $class;
            }
        }
    }

    /**
     * @param $name
     * @param array $arguments
     * @return mixed
     * @throws Exception
     */
    public function __call($name, array $arguments)
    {
        return $this->create($name, $arguments);
    }

    /**
     * @param $model
     * @param $args
     * @return mixed
     * @throws Exception
     */
    function create($model, $args)
    {
        if (isset($this->config[$model])) {
            $class = $this->config[$model];
            return new $class(...$args);
        }
        throw new Exception('Model does not exist');
    }

    /**
     * @param $pattern
     * @return mixed
     */
    /*public function ef_var($pattern){
        $class = $this->config[__FUNCTION__];
        return new $class($pattern);
    }*/
}