<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Hyperf\Extend\Controller;

use Hyperf\Database\Model\Builder;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Extend\Compatibility\HttpRequestJsonWrapper;
use Hyperf\Extend\Model\BaseModel;

/**
 * @property string $model_class
 * @property string $export_file_name
 * @property array $export_file_header
 * @property RequestInterface $request
 * @method crudSetQueryForList(Builder $query)
 * @method crudRenderModelForList(BaseModel $model)
 * @method crudRenderModelForInfo(BaseModel $model)
 * @method crudUpdateModelAttributesBeforeSave(BaseModel $model)
 */
trait CRUDApiActions
{
    /**
     * @var int 分页条数
     */
    protected $per_page = 50;

    public function init()
    {
    }

    public function getPrimaryColumnName()
    {
        return 'id';
    }

    public function list()
    {
        $this->init();
        /** @var Builder $query */
        $query = $this->model_class::query();
        if (method_exists($this, 'crudSetQueryForList')) {
            $this->crudSetQueryForList($query);
        }
        $this->orderListQueryByDefault($query);
        $paginator = $query->paginate($this->per_page);
        if (method_exists($this, 'crudBatchRenderModelForList')) {
            $paginator = $this->crudBatchRenderModelForList($paginator);
        }
        $data = [];
        foreach ($paginator as $item) {
            if (method_exists($this, 'crudRenderModelForList')) {
                $data[] = $this->crudRenderModelForList($item);
            } else {
                $data[] = $item;
            }
        }
        return [
            'data' => $data,
            'paginator' => $this->formatPaginatorForJSONOutput($paginator),
        ];
    }

    /**
     * @param array $custom_data 自定义的数据
     * @return array
     */
    public function create(array $custom_data = [])
    {
        $this->init();
        $data = json_decode($this->request->getBody()->getContents(), true);
        $data = array_merge($data, $custom_data);
        $model = new $this->model_class();
        if (!empty($data)) {
            $this->saveModel($model, $data);
        }
        return ['status' => 'success', 'id' => $model->id];
    }

    public function info()
    {
        $this->init();
        $model = $this->findModelByRequestId();
        if (method_exists($this, 'crudRenderModelForInfo')) {
            return ['data' => $this->crudRenderModelForInfo($model)];
        }
        return ['data' => $model];
    }

    public function update()
    {
        $this->init();
        $model = $this->findModelByRequestId();
        $data = json_decode($this->request->getBody()->getContents(), true);
        if (!empty($data)) {
            $this->saveModel($model, $data);
        }
        return ['status' => 'success'];
    }

    public function getQueryOrderStrings($order)
    {
        $order_strings = [];
        foreach (explode(';', $order) as $order_str) {
            $key_value = explode(':', $order_str);
            if (!empty(trim($key_value[0])) && !empty($key_value[1])) {
                if ($key_value[1] == 'descending') {
                    $order_strings[] = trim($key_value[0]) . ' desc';
                } elseif ($key_value[1] == 'ascending') {
                    $order_strings[] = trim($key_value[0]) . ' asc';
                } else {
                    continue;
                }
            }
        }
        return $order_strings;
    }

    public function getQueryOrders($order)
    {
        $orders = [];
        foreach (explode(';', $order) as $order_str) {
            $key_value = explode(':', $order_str);
            if (!empty(trim($key_value[0])) && !empty($key_value[1])) {
                if ($key_value[1] == 'descending') {
                    $orders[trim($key_value[0])] = 'desc';
                } elseif ($key_value[1] == 'ascending') {
                    $orders[trim($key_value[0])] = 'asc';
                } else {
                    continue;
                }
            }
        }
        return $orders;
    }

    public function getQueryForExport()
    {
        /** @var Builder $query */
        $query = $this->model_class::query();
        if (method_exists($this, 'crudSetQueryForList')) {
            $this->crudSetQueryForList($query);
        }
        return $query;
    }

    public function crudBatchRenderModelForExport($paginator)
    {
        $data = [];
        if (method_exists($this, 'crudBatchRenderModelForList')) {
            $paginator = $this->crudBatchRenderModelForList($paginator);
        }
        foreach ($paginator as $item) {
            if (method_exists($this, 'crudRenderModelForExport')) {
                $item = $this->crudRenderModelForExport($item);
            }
            if (isset($this->export_file_header)) {
                $row = [];
                foreach ($this->export_file_header as $key => $name) {
                    if (is_object($item)) {
                        $row[] = $item->{$key};
                    } elseif (is_array($item)) {
                        $row[] = $item[$key];
                    }
                }
                $data[] = $row;
            } else {
                $data[] = $item;
            }
        }
        return $data;
    }

    /**
     * 默认按照主键倒序排列列表.
     */
    protected function orderListQueryByDefault(Builder $query)
    {
        $query->orderByDesc($this->getPrimaryColumnName());
    }

    /**
     * 设置分页条数.
     */
    protected function setPerPage(int $per_page)
    {
        $this->per_page = $per_page;
    }

    private function findModelByRequestId()
    {
        $id = intval($this->request->query($this->getPrimaryColumnName()));
        if (empty($id)) {
            throw new NotFoundHttpException();
        }
        $model = $this->model_class::findById($id);
        if (empty($model)) {
            throw new NotFoundHttpException();
        }
        return $model;
    }

    private function saveModel(BaseModel $model, $json)
    {
        foreach ($json as $k => $v) {
            if ($model->isFillable($k)) {
                $model->setAttribute($k, $v);
            }
        }
        if (method_exists($this, 'crudUpdateModelAttributesBeforeSave')) {
            $this->crudUpdateModelAttributesBeforeSave($model);
        }
        $return = $model->save();
        if (method_exists($this, 'crudUpdateModelAttributesAfterSave')) {
            $this->crudUpdateModelAttributesAfterSave($model);
        }
        return $return;
    }
}
