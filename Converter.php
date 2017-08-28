<?php
class A2E_Converter
{
    /**
     * @var ARC2_SPARQLParser obj
     */
    private $parser;

    /**
     * @var Erfurt_Sparql_Query2 obj
     */
    private $targetModel;

    /**
     * $var A2E_Models_Factory obj
     */
    private $mf;

    /**
     * A2E_Converter constructor.
     *
     * Format example
     * <code>
     * $array = array(
     *   'ef' => 'Erfurt_Sparql_Query2', // model=>class
     * );
     * </code>
     * @param array[string]string $factory_config
     */
    function __construct($factory_config=array()){
        $this->mf = new A2E_Models_Factory($factory_config);
        $this->parser = ARC2::getSPARQLParser();
        $this->targetModel = $this->mf->ef();
    }

    /**
     * Convert sparqle query to Erfurt object
     *
     * @param string $query
     * @return Erfurt_Sparql_Query2
     */
    public function convert($query){
        $this->parse($query);
        $this->convertPrefixes();
        $this->convertQueryType();
        $this->convertResultVars();
        $this->convertWhere();
        return $this->targetModel;
    }

    /**
     * @return ARC2_SPARQLParser
     */
    public function getParser(){
        return $this->parser;
    }

    /**
     * Parse sparqle query
     *
     * @param string $query
     * @throws Exception
     */
    function parse($query){
        $this->parser->parse($query);

        if(count($this->parser->getErrors())){
            throw new Exception(implode(';',$this->parser->getErrors()));
        }
    }

    /**
     * set Erfurt_Sparql_Query2 model's query type (SELECT, etc)
     * @throws Exception
     */
    function convertQueryType(){

        if(!isset($this->parser->r['query']['type']))
            throw new Exception('Unknown var type');

        switch($this->parser->r['query']['type']) {
            case 'select':
                $this->targetModel->setQueryType('SELECT');
                if(isset($this->parser->r['query']['distinct']) and $this->parser->r['query']['distinct']==1)
                    $this->targetModel->setDistinct(true);
                break;
            default:
                throw new Exception('Unknown query type');
        }
    }

    /**
     * set Erfurt_Sparql_Query2 model's FROM blocks
     */
    function convertFroms(){

        if(isset($this->parser->r['query']['dataset'])) {
            foreach ($this->parser->r['query']['dataset'] as $from) {
                $this->targetModel->addFrom($from['graph'], $from['named']);
            }
        }
    }

    /**
     * set Erfurt_Sparql_Query2 model's vars
     * @throws Exception
     */
    function convertResultVars(){

        foreach($this->parser->r['query']['result_vars'] as $result_var){
            switch($result_var['type']){
                case 'var':
                    $this->targetModel->addProjectionVar($this->mf->ef_var($result_var['value']));
                    break;
                default:
                    throw new Exception("Unknown var type");
            }
        }
    }

    /**
     * set Erfurt_Sparql_Query2 model's prefixes
     * @throws Exception
     */
    function convertPrefixes(){

        if(isset($this->parser->r['prefixes'])) {
            foreach ($this->parser->r['prefixes'] as $name => $uri) {
                $this->targetModel->addPrefix($this->mf->ef_prefix(str_replace(':', '', $name), $uri));
            }
        }
    }

    /**
     * set Erfurt_Sparql_Query2 model's WHERE blocks
     */
    function convertWhere(){

        if(isset($this->parser->r['query']['pattern']['patterns']) and count($this->parser->r['query']['pattern']['patterns'])) {
            $ggp = $this->mf->ef_groupgraph();
            $this->resolvePatterns($ggp, $this->parser->r['query']['pattern']['patterns']);
            $this->targetModel->setWhere($ggp);
        }
    }

    /**
     * Recursively handle patterns in WHERE clauses
     *
     * @param Erfurt_Sparql_Query2_GroupGraphPattern $ggp
     * @param array $patterns
     * @throws Exception
     */
    function resolvePatterns(&$ggp,$patterns){
        
        foreach($patterns as $subPattern){
            
            switch($subPattern['type']) {
                case 'triples':
                    foreach ($subPattern['patterns'] as $pattern) {
                        $ggp->addElement($this->getTriple($pattern));
                    }
                    break;
                    
                case 'optional':
                    $sub_ggp = $this->mf->ef_groupgraph();
                    $this->resolvePatterns($sub_ggp, $subPattern['patterns']);
                    $optional = $this->mf->ef_optional($sub_ggp);
                    $ggp->addElement($optional);
                    break;

                case 'minus':
                    $sub_ggp = $this->mf->ef_groupgraph();
                    $this->resolvePatterns($sub_ggp, $subPattern['patterns']);
                    $optional = $this->mf->ef_minus($sub_ggp);
                    $ggp->addElement($optional);
                    break;
                    
                case 'filter':
                    $envloppedExpression = $this->getConstraint($subPattern['constraint']);
                    $filter = $this->mf->ef_filter($this->mf->ef_bracketted($envloppedExpression));
                    $ggp->addElement($filter);
                    break;

                case 'bind':
                    $bind = $this->mf->ef_bind($this->getConstraint($subPattern['constraint']),$this->mf->ef_var($subPattern['var']));
                    $ggp->addElement($bind);
                    break;

                default:
                    throw new Exception("Unknown pattern");
            }
        }
    }

    /**
     * Convert var to Erfurt format
     *
     * @param string $value
     * @param string $type
     * @throws Exception
     */
    function getConvertedVar($value,$type){
        
        switch($type) {
            case 'var':
                return $this->mf->ef_var($value);
                break;
            case 'literal':
                return $this->mf->ef_literal($value);
                break;
            case 'uri':
                return $this->mf->ef_iri($value);
                break;
            default:
                throw new Exception("Unknown triples part type");
        }
    }

    /**
     * Convert argument to Erfurt format
     *
     * @param string $value
     * @param string $type
     * @return mixed
     * @throws Exception
     */
    function getConvertedArgument($arg){

        switch($arg["type"]) {
            case 'var':
                $value = $arg['value'];
                break;
            case 'literal':
                $value = $arg['value'];
                break;
            case 'uri':
                $value = $arg['uri'];
                break;
            case 'built_in_call':
                return $this->getConstraint($arg);
                break;
            default:
                throw new Exception("Unknown same term arg type");
        }
        return $this->getConvertedVar($value,$arg["type"]);
    }

    /**
     * get triple converted to Erfurt format
     * @param array $triple
     * @return Erfurt_Sparql_Query2_TriplesSameSubject
     */
    function getTriple($triple){
        $subject = $this->getConvertedVar($triple['s'],$triple['s_type']);
        $verb = $this->getConvertedVar($triple['p'],$triple['p_type']);
        $object = $this->getConvertedVar($triple['o'],$triple['o_type']);
        $objList = $this->mf->ef_objectlist(array($object));
        $properties = $this->mf->ef_propertylist(array(array('verb'=>$verb,'objList'=>$objList)));
        return $this->mf->ef_triple($subject,$properties);
    }

    /**
     * get filter expression envlopped in effurt format
     * @param mixed $element
     * @return Erfurt_Sparql_Query2_Expression
     */
    function getEnveloppedFilterElement($element){
        $multiplicativeExpression = $this->mf->ef_multiplicative();
        $multiplicativeExpression->addElement('*',$element);
        $additiveExpression = $this->mf->ef_additive();
        $additiveExpression->addElement('+',$multiplicativeExpression);
        return $additiveExpression;
    }

    /**
     * Recursively handle patterns in FILTER or BIND expression
     * @param $constraint
     * @return mixed
     * @throws Exception
     */
    function getConstraint($constraint)
    {
        switch ($constraint['type']) {
            case 'expression':
                $patterns = array();
                foreach ($constraint['patterns'] as $pattern) {
                    $patterns[] = $this->getConstraint($pattern);
                }
                return $this->getEnvloppedOrAndExpression($constraint['sub_type'], $patterns);
            case 'built_in_call':
                switch ($constraint['call']) {
                    //sameTerm function
                    case 'sameterm':
                        $element1 = $this->mf->ef_or(array($this->mf->ef_and(array($this->getEnveloppedFilterElement($this->getConvertedArgument($constraint['args'][0]))))));
                        $element2 = $this->mf->ef_or(array($this->mf->ef_and(array($this->getEnveloppedFilterElement($this->getConvertedArgument($constraint['args'][1]))))));
                        $sametermExpression = $this->mf->ef_sameterm($element1,$element2);
                        return $this->mf->ef_or(array($this->mf->ef_and(array($this->getEnveloppedFilterElement($sametermExpression)))));
                     //CONCAT function
                    case 'concat':
                        $converted = array();
                        foreach($this->getConvertedArguments($constraint['args']) as $argument) {
                            $converted[] = $this->getConvertedArgument($argument);
                        }
                        return $this->mf->ef_concat($converted);
                    default:
                        throw new Exception("Unknown filter call type");
                }
                break;
            default:
                throw new Exception("Unknown filter constraint type");

        }
    }

    /**
     * get filter AND/OR expression envlopped in effurt format
     * @param string $type
     * @param array $patterns
     * @return mixed
     * @throws Exception
     */
    function getEnvloppedOrAndExpression($type,$patterns){
        switch ($type) {
            case 'or':
                $patternEnvloppedToAnd = array();

                foreach($patterns as $pattern){
                    $patternEnvloppedToAnd[] = $this->mf->ef_and(array($pattern));
                }
                return $this->mf->ef_or($patternEnvloppedToAnd);
                break;
            case 'and':
                return $this->mf->ef_or(array($this->mf->ef_and($patterns)));
                break;
            default:
                throw new Exception("Unknown or/and expression type");
        }
    }
}