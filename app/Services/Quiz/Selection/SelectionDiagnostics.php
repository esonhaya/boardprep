<?php
declare(strict_types=1);
final class SelectionDiagnostics
{
    public static function summarize(SelectionResult $result): array
    {
        return ['count'=>$result->count(),'shortage'=>$result->shortage()];
    }
}
