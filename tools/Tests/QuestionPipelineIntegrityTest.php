<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Repositories\QuestionRepository;
use App\Services\Shared\QuestionValidationService;
use App\Storage\JsonStorage;

$root=sys_get_temp_dir().'/boardprep-question-pipeline-'.getmypid();
mkdir($root,0777,true);
file_put_contents($root.'/questions.json',json_encode([
    null,
    'invalid',
    ['id'=>'q1','subject'=>'English','domain'=>'Grammar','topic'=>'Nouns'],
]));
$repository=new QuestionRepository(new JsonStorage($root));
if (count($repository->all())!==1||$repository->find('q1')===null||count($repository->where(['subject'=>'English']))!==1) {
    throw new RuntimeException('repository did not isolate malformed records');
}

$legacy=[
    'id'=>'q1','board'=>'let','subject'=>'English','domain'=>'Grammar','topic'=>'Nouns','concept'=>'Common nouns',
    'difficulty'=>' EASY ','question'=>' Which word is a noun? ','choices'=>['Teacher','Quickly'],
    'answer'=>' teacher ','explanation'=>'Teacher names a person.',
];
if (!QuestionValidationService::validate($legacy)['valid']) {
    throw new RuntimeException('valid legacy runtime question was rejected');
}
$invalid=$legacy;
$invalid['id']=[];
$invalid['choices']=['Teacher',' teacher '];
$invalid['answer']='Missing';
$invalid['explanation']=[];
if (QuestionValidationService::validate($invalid)['valid']) {
    throw new RuntimeException('malformed legacy question passed validation');
}

$parser=new \QuestionImportParser();
if ($parser->parse('{bad json')!==[]||$parser->errors()===[]) {
    throw new RuntimeException('invalid import JSON was silently accepted');
}
if (count($parser->parse('[null,{"id":"q2"}]'))!==1||$parser->errors()===[]) {
    throw new RuntimeException('malformed import record was silently dropped');
}

$request=new \SelectionRequest('English','Grammar',['easy'=>1],1,'Nouns');
$selected=\QuestionSelectionService::fulfillRequest([$invalid,$legacy],$request)->questions;
if (count($selected)!==1||$selected[0]['answer']!=='Teacher') {
    throw new RuntimeException('validated stored question did not reach normalized quiz selection');
}
$specification=new \QuizSpecification('let','English','Grammar',['Nouns'],[],'easy',1,'practice',false,false,null,null);
$generated=\QuizGenerationService::generate([$invalid,$legacy],$specification);
if (count($generated->questions)!==1||$generated->questions[0]['id']!=='q1') {
    throw new RuntimeException('stored validated question did not reach generated quiz');
}
$score=\QuestionScoreEvaluator::evaluate(['choices'=>['A','B'],'answer'=>'C'],'C');
if ($score['correct']) {
    throw new RuntimeException('unrepresented malformed answer was scored correct');
}

unlink($root.'/questions.json');
rmdir($root);
echo "[PASS] stored question pipeline integrity verified.\n";
