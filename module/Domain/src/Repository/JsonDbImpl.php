<?php

/**
 * @see       https://github.com/sawarame/php-json-server for the canonical source repository
 * @copyright https://github.com/sawarame/php-json-server/blob/master/COPYRIGHT.md
 * @license   https://github.com/sawarame/php-json-server/blob/master/LICENSE.md New BSD License
 */

namespace Domain\Repository;

use Domain\Model\Data;
use Domain\Exception\JsonDbException;
use Domain\Exception\DataNotFoundException;

class JsonDbImpl implements JsonDb
{

    private $config;
    private $model;
    private $path;

    /**
     * Constructor.
     * Setup JsonDb configuration.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function load(string $schemaName): JsonDb
    {
        $path = $this->config['data_path'] . "/${schemaName}.json";
        if (! is_file($path)) {
            throw new DataNotFoundException('Json data file is not exists.' . realpath($path));
        }
        $data = json_decode(file_get_contents($path), true);
        if (is_null($data)) {
            throw new DataNotFoundException('Faild to open json data file.' . realpath($path));
        }
        $this->path = $path;
        $this->model = new Data($data);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->model->getData();
    }

    /**
     * @inheritDoc
     */
    public function insert(array $data): int
    {
        return $this->model->replace($data);
    }

    /**
     * @inheritDoc
     */
    public function find(int $id): ?array
    {
        $row = $this->model->find($id);
        if (empty($row)) {
            throw new DataNotFoundException('Data is not exists with id is ' . $id . '.');
        }
        return $row;
    }

    /**
     * @inheritDoc
     */
    public function page(array $params): int
    {
        $page = isset($params['page']) ? (int)($params['page'] - 1) : 0;
        return $page < 0 ? 0 : $page;
    }

    /**
     * @inheritDoc
     */
    public function rows(array $params): int
    {
        return isset($params['rows']) ? (int)$params['rows'] : 20;
    }

    /**
     * @inheritDoc
     */
    public function read(array $params): array
    {
        $param = [];
        $sort = [];
        $page = $this->page($params);
        $rows = $this->rows($params);
        $offset = $page * $rows;
        $data = $this->model->read($param, $sort);
        if ($offset && $offset >= count($data)) {
            throw new DataNotFoundException('Page number exceeds total pages.');
        }
        return array_slice($data, $offset, $rows);
    }

    /**
     * @inheritDoc
     */
    public function countTotal(array $params): int
    {
        $param = [];
        $sort = [];
        $data = $this->model->read($param, []);
        return count($data);
    }

    /**
     * @inheritDoc
     */
    public function update(array $data): JsonDb
    {
        if (empty($data['id'])) {
            throw new JsonDbException('Column `id` is required in update data.');
        }
        if (! $this->model->find($data['id'])) {
            throw new JsonDbException('Data is not exists with id is ' . $data['id'] . '.');
        }
        $this->model->replace($data);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): JsonDb
    {
        if (! $this->model->find($id)) {
            throw new JsonDbException('Data is not exists with id is ' . $id . '.');
        }
        $this->model->delete($id);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function permanent(): JsonDb
    {
        file_put_contents($this->path, json_encode(
            $this->model,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ));
        return $this;
    }

    public function __toString()
    {
        return json_encode($this->model);
    }
}
