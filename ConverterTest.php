<?php

class A2E_ConverterTest{

    public static function test($converter,$queries){
        if(!is_array($queries) or !count($queries))
            throw new Exception('Param must be an array');

        $i = 0;
        foreach($queries as $query){
            echo 'Запрос '.++$i.'. ';
            try {
                echo 'Тест ' . (self::testQuery($converter,$query) ? 'пройден' : 'не пройден') . '<br>';
            }
            catch (Exception $e) {
                echo $e->getMessage(). '<br>';
            }
        }
    }

    static function testQuery($converter,$query){

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