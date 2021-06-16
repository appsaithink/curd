<?php

namespace saithink\curd;

use saithink\curd\model\Table;

/**
 * 助手类
 *
 * @package saithink\curd
 * Author: zsw zswemail@qq.com
 */
class Helper
{

    /**
     * 生成 Table Url 地址
     *
     * @param string $url
     * @param array $param
     * @param bool|string $domain
     *
     * @return string
     */
    public static function builder_table_url(string $url, array $param = [], $domain = false)
    {
        $url = '/' . config('curd.route_prefix') . '/' . trim($url, "\\/");
        $domain && $url = request()->domain() . $url;
        if ($param) {
            $url .= (strpos( $url, '?' ) === false ? '?' : '&' ) .http_build_query($param);
        }
        return $url;
    }

    /**
     * 格式化options参数
     *
     * @param array  $options
     * @param string $labelName value名称
     * @param string $valueName key名称
     *
     * @return array
     */
    public static function formatOptions(array $options, $labelName = Table::LABEL, $valueName = Table::VALUE): array
    {
        $data = [];
        foreach ($options as $k => $v) {
            array_push($data, [$labelName => $v, $valueName => $k]);
        }
        return $data;
    }

    /**
     * 格式化数组转普通数组
     *
     * @param array  $options
     * @param string $keyName   作为键的参数
     * @param string $valueName 作为值的参数
     *
     * @return array
     */
    public static function simpleOptions(array $options, $keyName = Table::KEY, $valueName = Table::VALUE): array
    {
        $data = [];
        foreach ($options as $v) {
            $data[$v[$keyName]] = $v[$valueName];
        }
        return $data;
    }

    /**
     * 数组深度合并 相同KEY值 如果是数组合并 如果是字符串覆盖
     *
     * @param array $original
     * @param array $extend
     * @param bool $main   只处理一级
     *
     * @return array
     */
    public static function extends(array $original, array $extend, $main = false):array
    {
        foreach ($extend as $k => $v) {
            $original[$k] = isset($original[$k]) && is_array($original[$k]) ? ($main ? $v : static::extends($original[$k], (array)$v)) : $v;
        }
        return $original;
    }

    public static function success($msg, $data = []){
        return json(['code' => 0, 'msg'  => $msg, 'data' => $data]);
    }

    public static function error($msg, $data){
        return json(['code' => 1, 'msg'  => $msg, 'data' => $data]);
    }

    /**
     * 下划线转驼峰
     * @param $uncamelized_words
     * @param string $separator
     * @return string
     */
    public static function camelize($uncamelized_words, $separator = '_')
    {
        $uncamelized_words = $separator . str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator);
    }

    /**
     * 驼峰转下划线
     * @param $camelCaps
     * @param string $separator
     * @return string
     */
    public static function uncamelize($camelCaps, $separator = '_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }

}


