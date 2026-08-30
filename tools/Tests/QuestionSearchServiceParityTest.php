<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionQueryService;
use App\Services\Question\QuestionSearchService;
$repo=new class extends QuestionRepository {
    public function __construct() {}
    public function all(): array { return [
        ['id'=>'a','question'=>'Noun item','difficulty'=>'easy','taxonomy'=>['subject_id'=>'english','domain_id'=>'grammar','topic_id'=>'parts-of-speech','concept_id'=>'noun']],
        ['id'=>'b','question'=>'Verb item','difficulty'=>'medium','taxonomy'=>['subject_id'=>'english','domain_id'=>'grammar','topic_id'=>'verbs','concept_id'=>'verb']],
    ]; }
};
App::container()->bind(QuestionRepository::class,static fn()=>$repo);
if(QuestionSearchService::search('noun')!==QuestionQueryService::getQuestions(['search'=>'noun'])){throw new RuntimeException('search compatibility path drifted from query pipeline');}
if(QuestionSearchService::filter('grammar','easy','parts-of-speech')!==QuestionQueryService::getQuestions(['domain'=>'grammar','difficulty'=>'easy','topic'=>'parts-of-speech'])){throw new RuntimeException('filter compatibility path drifted from query pipeline');}
if(count(QuestionSearchService::bySubject('english'))!==2||count(QuestionSearchService::byTopic('verbs'))!==1){throw new RuntimeException('taxonomy compatibility helpers regressed');}
echo "[PASS] Question search compatibility services share query semantics.\n";
