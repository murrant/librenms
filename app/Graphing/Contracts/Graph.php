<?php

namespace App\Graphing\Contracts;

use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Exceptions\GraphSubjectNotFound;
use App\Graphing\GraphQuery;
use App\Graphing\GraphSubject;

/**
 * A graph implemented as a class. Register it in GraphServiceProvider::GRAPHS.
 * Graphs describe their data, they do not draw it and do not authorize it.
 */
interface Graph
{
    /**
     * Resolve the entities this graph is about. Constructors must not do any I/O, do it here.
     *
     * @throws GraphSubjectNotFound
     */
    public function subject(GraphQuery $query): GraphSubject;

    /**
     * The policy ability checked against GraphSubject::authorizable() for user access
     */
    public function ability(): string;

    public function title(GraphSubject $subject): string;

    public function define(GraphSubject $subject, GraphQuery $query): GraphDefinition;

    /**
     * Validation rules for graph specific options, common graph input is validated by GraphRequest
     *
     * @return array<string, mixed>
     */
    public function rules(): array;
}
