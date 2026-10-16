<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

require_once dirname(__DIR__, 5) . '/app/Services/Question/NumericalAnswerConsistencyAudit.php';

class_alias(\App\Services\Question\NumericalAnswerConsistencyAudit::class, __NAMESPACE__ . '\\NumericalAnswerConsistencyAudit');
