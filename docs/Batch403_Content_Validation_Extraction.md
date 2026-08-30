# Batch 403 — Content Validation Extraction

Extracted the content-quality validator into focused collaborators while preserving the `ContentValidator::validate()` public boundary and existing issue semantics.

## Production path

`ContentValidator` delegates to `ContentValidationPipeline`, which reads question/explanation text, validates each field, and constructs the existing issue shape.

## Hardening

The previous short-text conditions relied on ternary precedence. The extracted `ContentLength::lessThan()` makes the comparison explicit so `mb_strlen()` is compared to the threshold rather than treated as the condition itself.

Thresholds remain:
- question text: fewer than 15 characters => `short-question` warning
- explanation: fewer than 20 characters => `short-explanation` info

Empty question and missing explanation semantics are unchanged.
