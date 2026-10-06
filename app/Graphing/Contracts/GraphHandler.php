<?php

namespace App\Graphing\Contracts;

use App\Graphing\Exceptions\GraphException;
use App\Graphing\GraphAccess;
use App\Graphing\GraphDescription;
use App\Graphing\GraphQuery;
use App\Graphing\GraphSubject;

interface GraphHandler
{
    /**
     * Resolve the entities the graph is about.
     *
     * @throws GraphException
     */
    public function subject(GraphQuery $query, GraphAccess $access): GraphSubject;

    public function authorize(GraphSubject $subject, GraphAccess $access): bool;

    public function describe(GraphSubject $subject): GraphDescription;

    /**
     * @throws GraphException
     */
    public function plan(GraphSubject $subject, GraphQuery $query): RenderPlan;

    /**
     * Graph specific validation rules, common graph input is validated by GraphRequest
     *
     * @return array<string, mixed>
     */
    public function rules(): array;
}
