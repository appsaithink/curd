<?php

namespace saithink\curd\controller;

use saithink\curd\Helper;
use saithink\curd\lib\Manage;

class Table extends Common
{

    public function index()
    {
        return $this->createTable(new table\Table());
    }

    public function update()
    {
        return $this->createForm(new table\Form());
    }

    /**
     * 生成菜单
     * Author: zsw zswemail@qq.com
     */
    public function menu()
    {
        $url = Helper::builder_table_url('page/index', ['_table' => input('table')], true);
        return Helper::success("页面地址:" . $url);
    }

    /**
     * 生成VUE页面+TP6页面
     * Author: sai
     */
    public function gen()
    {
        // 生成tp控制器
        // 生成tp模型
        // 生成vue列表页
        // 生成vue组件form页
        $table = input('table');

        Manage::instance()->buildController($table);
        Manage::instance()->buildModel($table);
        Manage::instance()->buildVueList($table);
        Manage::instance()->buildVueForm($table);
        Manage::instance()->buildVueAPi($table);

        return Helper::success("页面生成成功");
    }

    /**
     * 删除配置
     * Author: zsw zswemail@qq.com
     */
    public function delete()
    {
        $table = input('table');
        Manage::instance()->delete($table);
        return Helper::success("删除成功");
    }

}
