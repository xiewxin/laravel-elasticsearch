# Laravel - Elasticsearch

debug 中，请勿用于生产

## 安装

```bash
composer require flc/laravel-elasticsearch
```

## 配置

> 待整理

## 示例

```php
<?php

use Elasticsearch;

Elasticsearch::index('users')
    ->select('id', 'username', 'password', 'created_at', 'updated_at', 'status', 'deleted')
    ->whereTerm('status', 1)
    ->orWhereIn('deleted', [1, 2])
    ->whereNotExists('area')
    ->where(['status' => 1, 'closed' => 0])
    ->where(function ($query) {
        $query->where('status', '=', 1)
            ->where('closed', 1)
            ->where('username', 'like', '张三');
            ->where('username', 'match', '李四');
    })
    ->orderBy('id', 'desc')
    ->take(2)
    ->paginate(10);
    // ->get();
    // ->search();
```

## TODO

- [ ] 聚合查询
- [ ] 原生支持
- [ ] 辅助方法

## LICENSE

MIT