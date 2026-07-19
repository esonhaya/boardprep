<?php

/**
 * BoardPrep Question Model
 *
 * Official Question Schema
 *
 * This model defines the standard structure for every question
 * used throughout the application.
 *
 * Current Storage:
 * - PHP arrays
 *
 * Future Storage:
 * - MySQL
 * - PostgreSQL
 * - API
 */

class Question
{

    public const SCHEMA = [

        "id",

        "exam",

        "subject",

        "topic",

        "concept",

        "difficulty",

        "question",

        "choices",

        "answer",

        "explanation",

        "tags",

        "source"

    ];

}
