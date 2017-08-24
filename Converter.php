<?php
class A2E_Converter
{
    private $parser;
    private $targetModel;

    function __construct(){
        $this->parser = ARC2::getSPARQLParser();
        $this->targetModel = new Erfurt_Sparql_Query2();
    }

    public function convert($query){
        $this->parse($query);
        $this->convertPrefixes();
        $this->convertQueryType();
        $this->convertResultVars();
        $this->convertWhere();
        return $this->targetModel;
    }

    public function getParser(){
        return $this->parser;
    }

    function parse($query){
        $this->parser->parse($query);

        if(count($this->parser->getErrors())){
            throw new Exception(implode(';',$this->parser->getErrors()));
        }
    }

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

    function convertFroms(){

        if(isset($this->parser->r['query']['dataset'])) {
            foreach ($this->parser->r['query']['dataset'] as $from) {
                $this->targetModel->addFrom($from['graph'], $from['named']);
            }
        }
    }

    function convertResultVars(){

        foreach($this->parser->r['query']['result_vars'] as $result_var){
            switch($result_var['type']){
                case 'var':
                    $this->targetModel->addProjectionVar(new Erfurt_Sparql_Query2_Var($result_var['value']));
                    break;
                default:
                    throw new Exception("Unknown var type");
            }
        }
    }

    function convertPrefixes(){

        if(isset($this->parser->r['prefixes'])) {
            foreach ($this->parser->r['prefixes'] as $name => $uri) {
                $this->targetModel->addPrefix(new Erfurt_Sparql_Query2_Prefix(str_replace(':', '', $name), $uri));
            }
        }
    }

    function convertWhere(){

        if(isset($this->parser->r['query']['pattern']['patterns']) and count($this->parser->r['query']['pattern']['patterns'])) {
            $ggp = new Erfurt_Sparql_Query2_GroupGraphPattern();
            $this->resolvePatterns($ggp, $this->parser->r['query']['pattern']['patterns']);
            $this->targetModel->setWhere($ggp);
        }
    }

    function resolvePatterns(&$ggp,$patterns){
        
        foreach($patterns as $subPattern){
            
            switch($subPattern['type']) {
                case 'triples':
                    foreach ($subPattern['patterns'] as $pattern) {
                        $ggp->addElement($this->getTriple($pattern));
                    }
                    break;
                    
                case 'optional':
                    $sub_ggp = new Erfurt_Sparql_Query2_GroupGraphPattern();
                    $this->resolvePatterns($sub_ggp, $subPattern['patterns']);
                    $optional = new Erfurt_Sparql_Query2_OptionalGraphPattern($sub_ggp);
                    $ggp->addElement($optional);
                    break;

                case 'minus':
                    $sub_ggp = new Erfurt_Sparql_Query2_GroupGraphPattern();
                    $this->resolvePatterns($sub_ggp, $subPattern['patterns']);
                    $optional = new Core_Query2_Minus($sub_ggp);
                    $ggp->addElement($optional);
                    break;
                    
                case 'filter':
                    $envloppedExpression = $this->getConstraint($subPattern['constraint']);
                    $filter = new Erfurt_Sparql_Query2_Filter(new Erfurt_Sparql_Query2_BrackettedExpression($envloppedExpression));
                    $ggp->addElement($filter);
                    break;

                case 'bind':
                    $bind = new Core_Query2_Bind($this->getConstraint($subPattern['constraint']),new Erfurt_Sparql_Query2_Var($subPattern['var']));
                    $ggp->addElement($bind);
                    break;

                default:
                    throw new Exception("Unknown pattern");
            }
        }
    }

    function getConvertedVar($value,$type){
        
        switch($type) {
            case 'var':
                return new Erfurt_Sparql_Query2_Var($value);
                break;
            case 'literal':
                return new Erfurt_Sparql_Query2_RDFLiteral($value);
                break;
            case 'uri':
                return new Erfurt_Sparql_Query2_IriRef($value);
                break;
            default:
                throw new Exception("Unknown triples part type");
        }
    }

    function getConvertedArguments($arguments){
        $converted = array();
        foreach($arguments as $argument) {
            $converted[] = $this->getConvertedArgument($argument);
        }
        return $converted;
    }

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

    function getTriple($triple){
        $subject = $this->getConvertedVar($triple['s'],$triple['s_type']);
        $verb = $this->getConvertedVar($triple['p'],$triple['p_type']);
        $object = $this->getConvertedVar($triple['o'],$triple['o_type']);
        $objList = new Erfurt_Sparql_Query2_ObjectList(array($object));
        $properties = new Erfurt_Sparql_Query2_PropertyList(array(array('verb'=>$verb,'objList'=>$objList)));
        return new Erfurt_Sparql_Query2_TriplesSameSubject($subject,$properties);
    }

    function getEnveloppedFilterElement($element){
        $multiplicativeExpression = new Erfurt_Sparql_Query2_MultiplicativeExpression();
        $multiplicativeExpression->addElement('*',$element);
        $additiveExpression = new Erfurt_Sparql_Query2_AdditiveExpression();
        $additiveExpression->addElement('+',$multiplicativeExpression);
        return $additiveExpression;
    }

    function getSameTermExpression($arg1,$arg2){
        $element1 = new Erfurt_Sparql_Query2_ConditionalOrExpression(
            array(
                new Erfurt_Sparql_Query2_ConditionalAndExpression(
                    array($this->getEnveloppedFilterElement($this->getConvertedArgument($arg1)))
                )
            )
        );
        $element2 = new Erfurt_Sparql_Query2_ConditionalOrExpression(
            array(
                new Erfurt_Sparql_Query2_ConditionalAndExpression(
                    array($this->getEnveloppedFilterElement($this->getConvertedArgument($arg2)))
                )
            )
        );
        return new Erfurt_Sparql_Query2_sameTerm($element1,$element2);
    }

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
                    case 'sameterm':
                        $sametermExpression = $this->getSameTermExpression($constraint['args'][0], $constraint['args'][1]);
                        return new Erfurt_Sparql_Query2_ConditionalOrExpression(array(new Erfurt_Sparql_Query2_ConditionalAndExpression(array($this->getEnveloppedFilterElement($sametermExpression)))));
                    case 'concat':
                        return new Core_Query2_Concat($this->getConvertedArguments($constraint['args']));
                    default:
                        throw new Exception("Unknown filter call type");
                }
                break;
            default:
                throw new Exception("Unknown filter constraint type");

        }
    }

    function getEnvloppedOrAndExpression($type,$patterns){
        switch ($type) {
            case 'or':
                $patternEnvloppedToAnd = array();

                foreach($patterns as $pattern){
                    $patternEnvloppedToAnd[] = new Erfurt_Sparql_Query2_ConditionalAndExpression(array($pattern));
                }
                return new Erfurt_Sparql_Query2_ConditionalOrExpression($patternEnvloppedToAnd);
                break;
            case 'and':
                return new Erfurt_Sparql_Query2_ConditionalOrExpression(array(new Erfurt_Sparql_Query2_ConditionalAndExpression($patterns)));
                break;
            default:
                throw new Exception("Unknown or/and expression type");
        }
    }
}