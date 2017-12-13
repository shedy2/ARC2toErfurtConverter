<?php
/**
 * Class A2E_Models_Minus
 */
class A2E_Models_Minus extends Erfurt_Sparql_Query2_OptionalGraphPattern
{
    /**
     * @return string
     */
    public function getSparql()
    {
        return "MINUS {\n" . Erfurt_Sparql_Query2_GroupGraphPattern::getSparql() . "}\n"; //substr is cosmetic for stripping off the last linebreak
    }
}