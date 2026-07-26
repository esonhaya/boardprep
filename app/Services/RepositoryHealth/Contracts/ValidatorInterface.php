<?php

interface ValidatorInterface
{
    /**
     * Human-readable validator name.
     */
    public function name(): string;

    /**
     * Short description shown in the CMS.
     */
    public function description(): string;

    /**
     * Version of this validator.
     */
    public function version(): string;

    /**
     * Category this validator belongs to.
     *
     * Examples:
     * Content
     * Metadata
     * Taxonomy
     * Blueprint
     */
    public function category(): string;

    /**
     * Validate repository data.
     *
     * Returns ValidationResult.
     */
    public function validate(array $repository);

    /**
     * Whether automatic repair is supported.
     */
    public function supportsRepair(): bool;

    /**
     * Automatically repair repository.
     */
    public function repair(array &$repository): void;
}
