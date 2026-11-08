<?php

declare(strict_types=1);

/**
 * Stale one supplemental row in the disposable browser fixture only.
 *
 * @param array<string,mixed> $matrix
 */
function boardPrepBrowserStaleSupplementalEvidenceVersion(array &$matrix, string $questionId): bool
{
    if (!is_array($matrix['citation_rows'] ?? null)) {
        return false;
    }

    foreach ($matrix['citation_rows'] as &$citation) {
        if (is_array($citation) && (string) ($citation['question_id'] ?? '') === $questionId) {
            $citation['question_version'] = str_repeat('0', 64);
            unset($citation);
            return true;
        }
    }
    unset($citation);

    return false;
}
