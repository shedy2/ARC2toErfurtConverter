<?php

class A2E_ConverterTest{

    public static function test($queries){
        if(!is_array($queries) or !count($queries))
            throw new Exception('Param must be an array');

        $i = 0;
        foreach($queries as $query){
            //echo 'Запрос '.++$i.'. ';
            try {
                echo 'Тест ' . (self::testQuery($query) ? 'пройден' : 'не пройден') . '<br>';
            }
            catch (Exception $e) {
                echo $e->getMessage(). '<br>';
            }
        }
    }

    static function testQuery($query){

        $converter = new A2E_Converter();
        try {
            $erfurtParserResultStr = (string)Erfurt_Sparql_Query2::initFromString($query);
        }
        catch (Exception $e) {
            throw new Exception("Ошибка парсинга в Erfurt");
        }
        try {
            $converterResultStr = (string)$converter->convert($query);
        }
        catch (Exception $e) {
            throw new Exception("Ошибка парсинга в ARC2");
        }
        return $converterResultStr == $erfurtParserResultStr;
    }
}