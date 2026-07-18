<?php


class AttemptRepository
{


public static function save($attempt)
{


$file =
__DIR__
. "/../../database/attempts/attempts.json";



$data = [];



if(file_exists($file))
{

$data =
json_decode(
file_get_contents($file),
true
);

}



$data[] = $attempt;



file_put_contents(
$file,
json_encode(
$data,
JSON_PRETTY_PRINT
)
);



}



public static function all()
{


$file =
__DIR__
. "/../../database/attempts/attempts.json";



if(!file_exists($file))
{

return [];

}



return json_decode(
file_get_contents($file),
true
);


}



}
