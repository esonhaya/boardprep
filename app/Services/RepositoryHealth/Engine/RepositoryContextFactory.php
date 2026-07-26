<?php

class RepositoryContextFactory
{
    public static function create(): RepositoryContext
    {
        $context = new RepositoryContext();

        $context->questions = QuestionRepository::all();

        if (class_exists("BoardRepository")) {
            $context->boards = BoardRepository::all();
        }

        if (class_exists("SubjectRepository")) {
            $context->subjects = SubjectRepository::all();
        }

        if (class_exists("BlueprintRepository")) {
            $context->blueprints = BlueprintRepository::all();
        }

        $taxonomy = [];

        if (file_exists("database/taxonomy/domains.json")) {
            $taxonomy["domains"] =
                Storage::read("database/taxonomy/domains.json");
        }

        if (file_exists("database/taxonomy/topics.json")) {
            $taxonomy["topics"] =
                Storage::read("database/taxonomy/topics.json");
        }

        if (file_exists("database/taxonomy/concepts.json")) {
            $taxonomy["concepts"] =
                Storage::read("database/taxonomy/concepts.json");
        }

        $context->taxonomy = $taxonomy;

        return $context;
    }
}
