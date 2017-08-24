<?php
class A2E_Models_Minus extends Erfurt_Sparql_Query2_OptionalGraphPattern
{
    public function getSparql() {
        return "MINUS {\n".Erfurt_Sparql_Query2_GroupGraphPattern::getSparql()."}\n"; //substr is cosmetic for stripping off the last linebreak
    }
}