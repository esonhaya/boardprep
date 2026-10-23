<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionQueryService;
$repo=new class extends QuestionRepository {
    public int $reads=0;
    public function __construct() {}
    public function all(): array {
        $this->reads++;
        return [
            ['id'=>'a','question'=>'Noun item','difficulty'=>'easy','taxonomy'=>['domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'noun']],
            ['id'=>'b','question'=>'Verb item','difficulty'=>'easy','taxonomy'=>['domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'verb']],
        ];
    }
};
App::container()->bind(QuestionRepository::class,static fn()=>$repo);
$r=QuestionQueryService::getQuestions(['search'=>'noun','domain'=>'grammar','difficulty'=>'easy','topic'=>'parts-of-speech']);
if($repo->reads!==1){throw new RuntimeException('question query read repository '.$repo->reads.' times');}
if(count($r)!==1||($r[0]['id']??null)!=='a'){throw new RuntimeException('single-read production query returned wrong intersection');}
QuestionQueryService::getQuestions(['search'=>['bad'],'domain'=>new stdClass()]);
if($repo->reads!==2){throw new RuntimeException('malformed filters prevented safe production query');}
$firstPage=QuestionQueryService::getQuestionPage([],1,1);
$secondPage=QuestionQueryService::getQuestionPage([],2,1);
if($repo->reads!==4){throw new RuntimeException('paginated query did not use one repository snapshot per request');}
if(($firstPage['total']??0)!==2||($firstPage['canonical_total']??0)!==2||($firstPage['pages']??0)!==2||($firstPage['items'][0]['id']??null)!=='a'){
    throw new RuntimeException('first question-editor page did not return the expected bounded slice');
}
if(($secondPage['page']??0)!==2||($secondPage['items'][0]['id']??null)!=='b'){
    throw new RuntimeException('second question-editor page did not return the next record');
}
echo "[PASS] Question query production path uses one repository snapshot.\n";
