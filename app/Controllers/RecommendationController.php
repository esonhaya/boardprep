<?php

require_once __DIR__ . "/../Repositories/AttemptRepository.php";

class RecommendationController
{

public static function getRecommendation()
{

$attempts = AttemptRepository::all();

if(count($attempts) === 0)
{

return [

"title"=>"Take Your First Quiz",

"description"=>
"Complete a quiz to receive personalized recommendations."

];

}


$latest =
end($attempts);


if($latest["percentage"] < 50)
{

return [

"title"=>"Strengthen Your Foundation",

"description"=>
"Practice Grammar on Easy difficulty (10 questions)."

];

}


if($latest["percentage"] < 75)
{

return [

"title"=>"Keep Improving",

"description"=>
"Practice Grammar on Medium difficulty (10 questions)."

];

}


return [

"title"=>"Challenge Yourself",

"description"=>
"Try a Hard Grammar quiz or switch to Exam Mode."

];

}

}
