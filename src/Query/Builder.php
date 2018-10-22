<?php

namespace Flc\Laravel\Elasticsearch\Query;

use Closure;
use Elasticsearch\Client as ElasticsearchClient;
use Flc\Laravel\Elasticsearch\Grammars\Grammar;
use Illuminate\Database\Concerns\BuildsQueries;
use InvalidArgumentException;

/**
 * Elasticsearch 查询构建类
 *
 * @author Flc <i@flc.io>
 */
class Builder
{
    use BuildsQueries;

    /**
     * Elasticsearch Client
     *
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * 索引名
     *
     * @var string
     */
    public $index;

    /**
     * 索引Type
     *
     * @var string
     */
    public $type;

    /**
     * 搜寻条件
     *
     * @var array
     */
    public $wheres = [
        'filter'   => [],
        'should'   => [],
        'must'     => [],
        'must_not' => [],
    ];

    /**
     * 排序
     *
     * @var array
     */
    public $sort = [];

    /**
     * 从X条开始查询
     *
     * @var int
     */
    public $from;

    /**
     * 获取数量
     *
     * @var int
     */
    public $size;

    // protected $aggs = [];

    /**
     * 需要查询的字段
     *
     * @var array
     */
    public $_source;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
    ];

    /**
     * 实例化一个构建链接
     *
     * @param ElasticsearchClient $client
     */
    public function __construct(ElasticsearchClient $client)
    {
        $this->client  = $client;
        $this->grammar = new Grammar();
    }

    /**
     * 指定索引名
     *
     * @param string $value
     *
     * @return $this
     */
    public function index($value)
    {
        $this->index = $value;

        return $this;
    }

    /**
     * 指定type
     *
     * @param string $value
     *
     * @return $this
     */
    public function type($value)
    {
        $this->type = $value;

        return $this;
    }

    /**
     * 指定需要查询获取的字段
     *
     * @param array|mixed $columns
     *
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->_source = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * 按自定字段排序
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->sort[] = [
            $column => strtolower($direction) == 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * offset 方法别名
     *
     * @param int $value
     *
     * @return $this
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * 跳过X条数据
     *
     * @param int $value
     *
     * @return $this
     */
    public function offset($value)
    {
        if ($value >= 0) {
            $this->from = $value;
        }

        return $this;
    }

    /**
     * limit 方法别名
     *
     * @param int $value
     *
     * @return $this
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * 设置获取的数据量
     *
     * @param int $value
     *
     * @return $this
     */
    public function limit($value)
    {
        if ($value >= 0) {
            $this->size = $value;
        }

        return $this;
    }

    /**
     * 以分页形式获取指定数量数据
     *
     * @param int $page
     * @param int $perPage
     *
     * @return $this
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * 返回新的构建类
     *
     * @return \Flc\Laravel\Elasticsearch\Query\Builder
     */
    public function newQuery()
    {
        return new static($this->client, $this->grammar);
    }

    // --- 此处开始为where

    /**
     * 增加一个条件到查询中
     *
     * @param mixed  $value 条件语法
     * @param string $type  条件类型，filter/must/must_not/should
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function addWhere($value, $type = 'filter')
    {
        if (! array_key_exists($type, $this->wheres)) {
            throw new InvalidArgumentException("Invalid where type: {$type}.");
        }

        $this->wheres[$type][] = $value;

        return $this;
    }

    /**
     * Term 查询
     *
     * @param string $column 字段
     * @param mixed  $value  值
     * @param string $type   条件类型
     *
     * @return $this
     */
    public function whereTerm($column, $value, $type = 'filter')
    {
        return $this->addWhere(
            ['term' => [$column => $value]], $type
        );
    }

    /**
     * Match 查询
     *
     * @param string $column 字段
     * @param mixed  $value  值
     * @param string $type   条件类型
     *
     * @return $this
     */
    public function whereMatch($column, $value, $type = 'filter')
    {
        return $this->addWhere(
            ['match' => [$column => $value]], $type
        );
    }

    /**
     * Range 查询
     *
     * @param string $column   字段
     * @param string $operator 查询符号
     * @param mixed  $value    值
     * @param string $type     条件类型
     *
     * @return $this
     */
    public function whereRange($column, $operator, $value, $type = 'filter')
    {
        if (! in_array($operator, ['>=', '>', '<', '<='])) {
            throw new InvalidArgumentException("Invalid operator: {$operator}.");
        }

        switch ($operator) {
            case '>':
                return $this->addWhere([
                    'range' => [
                        $column => ['gt' => $value],
                    ],
                ], $type);
                break;

            case '<':
                return $this->addWhere([
                    'range' => [
                        $column => ['lt' => $value],
                    ],
                ], $type);
                break;

            case '>=':
                return $this->addWhere([
                    'range' => [
                        $column => ['gte' => $value],
                    ],
                ], $type);
                break;

            case '<=':
                return $this->addWhere([
                    'range' => [
                        $column => ['lte' => $value],
                    ],
                ], $type);
                break;
        }

        return $this;
    }

    // ===========================================================
    // 以下未确定版本
    // ===========================================================

    // where - start

    /**
     * 增加搜索条件
     *
     * @param string|array|\Closure $column
     * @param string|null           $operator
     * @param mixed                 $value
     * @param string                $boolean
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() == 2
        );

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        $this->performWhere($column, $value, $operator);

        return $this;
    }

    /**
     * 处理搜索
     *
     * @param string $column   字段
     * @param mixed  $value    值
     * @param string $operator 符号
     *
     * @return array
     */
    protected function performWhere($column, $value, $operator)
    {
        switch ($operator) {
            case '=':
                return $this->wheres['filter'][] = [
                    'term' => [$column => $value],
                ];
                break;

            case '>':
                return $this->wheres['filter'][] = [
                    'range' => [
                        $column => [
                            'gt' => $value,
                        ],
                    ],
                ];
                break;

            case '<':
                return $this->wheres['filter'][] = [
                    'range' => [
                        $column => [
                            'lt' => $value,
                        ],
                    ],
                ];
                break;

            case '>=':
                return $this->wheres['filter'][] = [
                    'range' => [
                        $column => [
                            'gte' => $value,
                        ],
                    ],
                ];
                break;

            case '<=':
                return $this->wheres['filter'][] = [
                    'range' => [
                        $column => [
                            'lte' => $value,
                        ],
                    ],
                ];
                break;

            case '!=':
            case '<>':
                return $this->wheres['must_not'][] = [
                    'term' => [$column => $value],
                ];
                break;
        }
    }

    /**
     * Add an array of where clauses to the query.
     *
     * @param array  $column
     * @param string $boolean
     * @param string $method
     *
     * @return $this
     */
    protected function addArrayOfWheres($column, $boolean, $method = 'where')
    {
        return $this->whereNested(function ($query) use ($column, $method, $boolean) {
            foreach ($column as $key => $value) {
                $query->$method($key, '=', $value, $boolean);
            }
        }, $boolean);
    }

    /**
     * Prepare the value and operator for a where clause.
     *
     * @param string $value
     * @param string $operator
     * @param bool   $useDefault
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function prepareValueAndOperator($value, $operator, $useDefault = false)
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param string $operator
     * @param mixed  $value
     *
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        return is_null($value) && in_array($operator, $this->operators) && ! in_array($operator, ['=', '<>', '!=']);
    }

    /**
     * Determine if the given operator is supported.
     *
     * @param string $operator
     *
     * @return bool
     */
    protected function invalidOperator($operator)
    {
        return ! in_array(strtolower($operator), $this->operators, true);
    }

    /**
     * 多条件过滤
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
     *
     * @param string $column
     * @param array  $value
     *
     * @return $this
     */
    public function whereIn($column, array $value = [])
    {
        $this->wheres['filter'][] = [
            'terms' => [$column => $value],
        ];

        return $this;
    }

    /**
     * 多条件过滤（非）
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
     *
     * @param string $column
     * @param array  $value
     *
     * @return $this
     */
    public function whereNotIn($column, array $value = [])
    {
        $this->wheres['must_not'][] = [
            'terms' => [$column => $value],
        ];

        return $this;
    }

    /**
     * 区间查询
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     *
     * @param string $column
     * @param array  $value
     *
     * @return $this
     */
    public function whereBetween($column, array $value = [])
    {
        $this->wheres['filter'][] = [
            'range' => [
                $column => [
                    'gte' => $value[0],
                    'lte' => $value[1],
                ],
            ],
        ];

        return $this;
    }

    /**
     * 将嵌套语句添加到查询条件中
     *
     * @param \Closure $callback
     * @param string   $boolean
     *
     * @return $this
     */
    public function whereNested(Closure $callback, $boolean = 'and')
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * 创建一个用于嵌套条件查询实例
     *
     * @return Builder
     */
    public function forNestedWhere()
    {
        return $this->newQuery();
    }

    /**
     * 添加嵌套的查询构造条件
     *
     * @param Builder|static $query
     * @param string         $boolean
     *
     * @return $this
     */
    public function addNestedWhereQuery($query, $boolean = 'and')
    {
        if ($bool = $this->grammar->compileWheres($this)) {
            $this->addWhere(['bool' => $bool], $boolean == 'and' ? 'filter' : 'should');
        }

        return $this;
    }

    // where - end

    // =======================================================

    /*
     * 返回数据
     *
     * @return Collection
     */
    public function get()
    {
        return $this->client->search(
            $this->toParam()
        );
    }

    /**
     * 转换为请求参数
     *
     * @return array
     */
    public function toParam()
    {
        return $this->compileSelect();
    }

    /**
     * 转换为查询 body
     *
     * @return array
     */
    public function toBody()
    {
        return $this->compileSelectBody();
    }

    /**
     * ==========================
     * 语法构建
     * ==========================
     */

    /**
     * 返回查询语法
     *
     * @return array
     */
    protected function compileSelect()
    {
        $query = $this->baseQuery();

        if (! is_null($this->_source)) {
            $query['_source'] = $this->_source;
        }

        if (! is_null($this->from)) {
            $query['from'] = $this->from;
        }

        if (! is_null($this->size)) {
            $query['size'] = $this->size;
        }

        if ($body = $this->compileSelectBody()) {
            $query['body'] = $body;
        }

        print_r($query);

        return $query;
    }

    /**
     * 返回查询 body 语法
     *
     * @return array
     */
    protected function compileSelectBody()
    {
        $body = [];

        if (count($this->sort) > 0) {
            $body['sort'] = $this->sort;
        }

        if ($bool = $this->grammar->compileWheres($this)) {
            $body['query']['bool'] = $bool;
        }

        return $body;
    }

    /**
     * 返回基础公共查询
     *
     * @return array
     */
    protected function baseQuery()
    {
        $query = [];

        $query['index'] = $this->index;

        if (! is_null($this->type)) {
            $query['type'] = $this->type;
        }

        return $query;
    }
}
