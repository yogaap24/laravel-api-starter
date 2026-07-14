<?php

declare(strict_types=1);

namespace Kindharika\ApiStarter\Base;

interface BaseServiceInterface
{
    public function dataTable(mixed $filter): mixed;

    public function getById(mixed $id): mixed;

    public function create(mixed $data): mixed;

    public function update(mixed $id, mixed $data): mixed;

    public function delete(mixed $id): mixed;
}
