<?php

namespace saithink\curd\controller\table;

use saithink\curd\Helper;
use saithink\curd\lib\Manage;
use surface\form\components\Arrays;
use surface\form\components\Input;
use surface\form\components\Radio;
use surface\form\components\Select;
use surface\form\components\Switcher;
use surface\helper\FormInterface;
use saithink\curd\model\Table as TableModel;

class Form implements FormInterface
{

    public function options(): array
    {
        return [
            'resetBtn' => false,
            'async' => [
                'url' => '',
            ],
        ];
    }

    public function columns(): array
    {
        $table = input('table', '');
        if ( ! $table)
        {
            return Helper::error('数据表不存在');
        }
        $model = Manage::instance()->table($table);
        if ( ! $model || count($model) < 1)
        {
            return Helper::error('参数错误');
        }
        $buttons = [];
        foreach ($model['button'] as $b) {
            $b['data_extend'] = Helper::formatOptions($b['data_extend'], TableModel::VALUE, TableModel::KEY);
            $b['btn_extend'] = Helper::formatOptions($b['btn_extend'], TableModel::VALUE, TableModel::KEY);
            $buttons[] = $b;
        }

        return [
            (new Input('table', TableModel::$labels['table'], $model['table']))->props(['readonly' => true]),
            (new Input('pk', TableModel::$labels['pk'], $model['pk'])),
            (new Input('title', TableModel::$labels['title'], $model['title'])),
            (new Input('description', TableModel::$labels['description'], $model['description'])),
            (new Switcher('page', TableModel::$labels['page'], $model['page'])),
            (new Arrays('extend', TableModel::$labels['extend'], Helper::formatOptions($model['extend'], TableModel::VALUE, TableModel::KEY)))->options(
                [
                    (new Input(TableModel::KEY, TableModel::$labels[TableModel::KEY]))->item(false),
                    (new Input(TableModel::VALUE, TableModel::$labels[TableModel::VALUE]))->item(false),
                ]
            )->marker('扩展配置'),
        ];
    }

    public function save()
    {
        $post = input();
        try
        {
            Manage::instance()->save($post);
        } catch (\Exception $e)
        {
            return false;
        }
        return true;
    }


}
