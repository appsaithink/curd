<?php

namespace saithink\curd\lib\engine;

use saithink\curd\Helper;
use saithink\curd\lib\Manage;
use saithink\curd\model\Table;

/**
 * 文件引擎 配置文件保存到data/
 *
 * Class File
 *
 * @package saithink\curd\lib\engine
 * Author: zsw zswemail@qq.com
 */
class File extends Manage
{

    private $suffix = '.php';

    private $path;

    public function init()
    {
        $tablePath = $this->config['save_path'];
        if (!is_dir($tablePath)) {
            mkdir($tablePath, 0777, true);
        }
        $this->path = $tablePath;
    }

    private function getTableFilePath($table)
    {
        return $this->path . $table . $this->suffix;
    }

    private function getData($table)
    {
        $tablePath = $this->getTableFilePath($table);
        return is_file($tablePath) ? include $tablePath : [];
    }

    public function table($table): array
    {
        return $this->tables($table)[0] ?? [];
    }

    public function tables($table = ''): array
    {
        $default = $this->tablesInfo($table);
        foreach ($default as $k => $v)
        {
            $tableData = $this->getData($v['table']);
            if (!is_array($tableData) || count($tableData) < 1) {
                $this->save($v);
                $tableData = $this->getData($v['table']);
            }
            $default[$k] = array_merge($v, $tableData);
        }
        return $default;
    }

    public function field($table, $field): array
    {
        return $this->fields($table, $field)[0] ?? [];
    }

    public function fields($table, $field = ''): array
    {
        $tableField = $this->table($table)['fields'] ?? [];
        if (!$tableField) {return [];}
        $fieldInfo = $this->fieldsInfo($table, $field);
        foreach ($fieldInfo as $k => $v)
        {
            if (!isset($tableField[$v['field']])) {
                $tableField[$v['field']] = [];
            }
            $fieldInfo[$k] = array_merge($v, $tableField[$v['field']]);
            unset($tableField[$v['field']]);
        }

        // 补充自定义字段
        foreach ($tableField as $v)
        {
            if ($v['relation'] && (!$field || $field === $v['field']))
            {
                $fieldInfo[] = $v;
            }
        }

        // fields 排序
        if (count($fieldInfo) > 1) {
            $sort = array_column($fieldInfo,'weight');
            array_multisort($sort,SORT_ASC, $fieldInfo);
        }

        return $fieldInfo;
    }

    public function save($data):bool
    {
        $table = $data['table'];
        $fields = $oldFields = [];
        if (isset($data['fields'])) {
            $fields = $data['fields'];
            unset($data['fields']);
        }
        $info = $this->getData($table);
        if (isset($info['fields'])) {
            $oldFields = $info['fields'];
            unset($data['fields']);
        }

        foreach ($fields as $v) {
            if (isset($v['relation']) && $v['relation'] && count($v['option_remote_relation']) !== 7) {
                throw new \Exception(Table::$labels['option_remote_relation'] . "不能为空");
            }
        }
        foreach ($this->fieldsInfo($table) as $f) {
            $field = $f['field'];
            if (isset($oldFields[$field])) {
                $fields[$field] = Helper::extends($oldFields[$field], $fields[$field] ?? [], true);
                unset($oldFields[$field]);
            }else{
                if ($f['key'] === 'PRI') { // 主键默认值
                    $data['pk'] = $field;
                }
                if (isset($fields[$field])) {
                    $fields[$field] = array_merge($f, $fields[$field]);
                }else{
                    $fields[$field] = $f;
                }
            }
        }

        // 自定义字段补充
        foreach ($oldFields as $k => $v) {
            if ($v['relation']) {
                $fields[$k] = array_merge($v, $fields[$k] ?? []);
            }
        }

        // fields 排序
        $sort = array_column($fields,'weight');
        array_multisort($sort,SORT_ASC, $fields);

        $data = array_merge($info, $data);
        $data['fields'] = $fields;

        // 格式化字段
        $data['extend'] = $data['extend'] ? Helper::simpleOptions($data['extend']) : [];
        foreach ($data['button'] as $k => $b) {
            $b['btn_extend'] = Helper::simpleOptions($b['btn_extend']);
            $b['data_extend'] = Helper::simpleOptions($b['data_extend']);
            $data['button'][$k] = $b;
        }

        return $this->saveData($table, $data);
    }

    public function delete($table, $fields = null)
    {
        foreach ((array)$table as $t) {
            $path = $this->getTableFilePath($t);
            if ($fields) {
                $info = require $path;
                is_array($fields) || $fields = (array)$fields;
                foreach ($fields as $f) {
                    unset($info['fields'][$f]);
                    if ($default = $this->fieldsInfo($t, $f)) { // 数据库字段自动补充
                        $info['fields'][$f] = $default[0];
                    }
                }
                $this->saveData($t, $info);
            } else {
                @unlink($path);
            }
        }
    }

    private function saveData($table, array $data)
    {
        $file = $this->getTableFilePath($table);
        $string = "<?php\r\n return " . var_export($this->checkTableContent($data), true) . ';';

        if ($handle = fopen($file, 'w')) {
            fwrite($handle, $string);
            fclose($handle);
        } else {
            throw new \app\exception\BaseException(__('File {:file} does not have {:type} permission', ['file'=>$file, 'type' => 'write']));
        }

        return true;
    }

    /**
     * 生成控制器
     */
    public function buildController($table)
    {        
        $data = $this->table($table);
        $title = $data['title'];
        $pKey = $data['pk'];

        $templatePath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'template';
        
        $stub = file_get_contents($templatePath. DIRECTORY_SEPARATOR . 'tp'. DIRECTORY_SEPARATOR . 'controller.stub');

        $prefix = config('database.connections.mysql.prefix');

        $fileName = $table;

        if (substr($table,0,strlen($prefix)) == $prefix ) {
            $fileName = substr($fileName,strlen($prefix));
        }
        
        $className = ucwords(Helper::camelize($fileName));

        $modelInfo = '';        
        $fields = $this->fields($table);
        
        foreach ($fields as $key => $item) {
            if (!in_array($item['field'],[$pkey, 'is_delete', 'create_time', 'update_time'])){
                $modelInfo .= '$model->'.$item['field'].' = $request->post("'.$item['field'].'");'."\r\n        ";
            }            
        }

        $strOut = str_replace(['{%titleName%}', '{%className%}', '{%modelInfo%}', '{%pKey%}'], [
            $title,
            $className,
            $modelInfo,
            $pKey
        ], $stub);

        $outPath = $this->path . 'tp'. DIRECTORY_SEPARATOR . 'controller';
        if (!is_dir($outPath)) {
            mkdir($outPath, 0777, true);
        }
        $outFile = $outPath. DIRECTORY_SEPARATOR . $className .'.php';
        file_put_contents($outFile, $strOut);
    }

    /**
     * 生成模型
     */
    public function buildModel($table)
    {        
        $data = $this->table($table);
        $title = $data['title'];
        $pKey = $data['pk'];

        $templatePath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'template';
        
        $stub = file_get_contents($templatePath. DIRECTORY_SEPARATOR . 'tp'. DIRECTORY_SEPARATOR . 'model.stub');

        $prefix = config('database.connections.mysql.prefix');

        $fileName = $table;

        if (substr($table,0,strlen($prefix)) == $prefix ) {
            $fileName = substr($fileName,strlen($prefix));
        }
        
        $className = ucwords(Helper::camelize($fileName));

        $strOut = str_replace(['{%titleName%}', '{%className%}', '{%pKey%}'], [
            $title,
            $className,
            $pKey
        ], $stub);

        $outPath = $this->path . 'tp'. DIRECTORY_SEPARATOR . 'model';
        if (!is_dir($outPath)) {
            mkdir($outPath, 0777, true);
        }
        $outFile = $outPath. DIRECTORY_SEPARATOR . $className .'.php';
        file_put_contents($outFile, $strOut);
    }

    /**
     * 生成VUE列表
     */
    public function buildVueList($table)
    {        
        $data = $this->table($table);
        $title = $data['title'];
        $pKey = $data['pk'];

        $templatePath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'template';
        
        $stub = file_get_contents($templatePath. DIRECTORY_SEPARATOR . 'vue'. DIRECTORY_SEPARATOR . 'list.stub');

        $prefix = config('database.connections.mysql.prefix');

        $fileName = $table;

        if (substr($table,0,strlen($prefix)) == $prefix ) {
            $fileName = substr($fileName,strlen($prefix));
        }
        
        $lowerClassName = Helper::camelize($fileName);
        $className = ucwords($lowerClassName);

        $tableColumn = '';        
        $fields = $this->fields($table);
        
        foreach ($fields as $key => $item) {
            switch($item['table_type']){
                case '_':                        
                    break;
                case 'column':
                    $tableColumn .= '<el-table-column prop="'.$item['field'].'" label="'.$item['title'].'" />'."\r\n      ";
                    break;
                case 'status':
                    $tempTitle = $item['title'];
                    $field = $item['field'];
                    $tableColumn .= <<<EOT
                    <el-table-column prop="status" label="$tempTitle" align="center" width="80">
                            <template slot-scope="{row}">
                              <el-tag :type="row.$field | statusFilter">{{ row.$field == 1 ? '正常' : '禁用' }}</el-tag>
                            </template>
                          </el-table-column>
                        
                  EOT;
                    break;
                case 'tag':
                    $tempTitle = $item['title'];
                    $field = $item['field'];
                    $tableColumn .= <<<EOT
                    <el-table-column prop="status" label="$tempTitle" align="center" width="80">
                            <template slot-scope="{row}">
                              <el-tag :type="row.$field | statusTagFilter">{{ row.$field | statusFilter }} </el-tag>
                            </template>
                          </el-table-column>
                        
                    EOT;
                    break;
                default:
                    break;
            }       
        }

        $strOut = str_replace(['{%titleName%}', '{%className%}', '{%lowerClassName%}', '{%column%}', '{%pKey%}'], [
            $title,
            $className,
            $lowerClassName,
            $tableColumn,
            $pKey
        ], $stub);

        $outPath = $this->path . 'vue'. DIRECTORY_SEPARATOR . 'views';
        if (!is_dir($outPath)) {
            mkdir($outPath, 0777, true);
        }
        $outFile = $outPath. DIRECTORY_SEPARATOR . $className .'.vue';
        file_put_contents($outFile, $strOut);
    }

    /**
     * 生成VUE的表单
     */
    public function buildVueForm($table)
    {        
        $data = $this->table($table);
        $title = $data['title'];
        $pKey = $data['pk'];

        $templatePath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'template';
        
        $stub = file_get_contents($templatePath. DIRECTORY_SEPARATOR . 'vue'. DIRECTORY_SEPARATOR . 'form.stub');

        $prefix = config('database.connections.mysql.prefix');

        $fileName = $table;

        if (substr($table,0,strlen($prefix)) == $prefix ) {
            $fileName = substr($fileName,strlen($prefix));
        }
        
        $lowerClassName = Helper::camelize($fileName);
        $className = ucwords($lowerClassName);

        $formItem = ''; 
        $formField = '';        
        $fields = $this->fields($table);
        
        foreach ($fields as $key => $item) {
            switch($item['form_type']){
                case 'hidden':
                    if($item['key'] == 'PRI'){
                        $formField .= $pKey.": undefined,\r\n        ";
                    }
                    break;
                case 'input':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $formItem .= <<<EOT
                    <el-form-item label="$tempTitle" prop="$tempField">
                            <el-input v-model="form.$tempField" :style="formInputWidth" />
                          </el-form-item>
                        
                  EOT;
                    $formField .= $tempField." : '".$item['default']."',\r\n        "; 
                    break;
                case 'textarea':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $formItem .= <<<EOT
                    <el-form-item label="$tempTitle" prop="$tempField">
                            <el-input type="textarea" v-model="form.$tempField" :style="formInputWidth" />
                            </el-form-item>
                        
                    EOT;
                    $formField .= $tempField." : '".$item['default']."',\r\n        "; 
                    break;
                case 'date':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $formItem .= <<<EOT
                      <el-form-item label="$tempTitle" prop="$tempField">
                            <el-date-picker v-model="form.$tempField" :style="formInputWidth" type="date" format="yyyy-MM-dd" value-format="yyyy-MM-dd" placeholder="选择日期" />
                          </el-form-item>
                        
                  EOT;
                    $formField .= $tempField." : '',\r\n        "; 
                    break;
                case 'time':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $formItem .= <<<EOT
                      <el-form-item label="$tempTitle" prop="$tempField">
                            <el-date-picker v-model="form.$tempField" :style="formInputWidth" type="datetime" format="yyyy-MM-dd HH:mm:ss" value-format="yyyy-MM-dd HH:mm:ss" placeholder="选择时间" />
                            </el-form-item>
                        
                    EOT;
                    $formField .= $tempField." : '',\r\n        "; 
                    break;
                case 'radio':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $radioStr = '';

                    $option = $item['option_type'];
                    switch($option){
                        case 'option_lang':
                            $option_lang = $item['option_lang'];
                            $radioStr .= '<el-radio v-for="item in '.$option_lang.'" :key="item.label" :label="item.label">{{item.value}}</el-radio>';
                            break;
                        case 'option_relation':
                            $relationArr = $item['option_relation'];
                            $option_lang = str_replace($prefix,'',$relationArr[0]);
                            $radioStr .= '<el-radio v-for="item in '.$option_lang.'List" :key="item.'.$relationArr[2].'" :label="item.'.$relationArr[2].'">{{item.'.$relationArr[1].'}}</el-radio>';
                            break;
                        default:
                            $radioArr = $item['option_config'];
                            foreach ($radioArr as $key => $value) {
                                $radioStr .= '<el-radio :label="'.$key.'">'.$value.'</el-radio>';
                            }
                            break;
                    }
                    
                    $formItem .= <<<EOT
                        <el-form-item label="$tempTitle" prop="$tempField">
                              <el-radio-group v-model="form.$tempField" :style="formInputWidth">
                                $radioStr
                              </el-radio-group>
                            </el-form-item>
                          
                    EOT;
                    $formField .= $tempField." : ".$item['default'].",\r\n        "; 
                    break;
                case 'checkbox':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $checkStr = '';

                    $option = $item['option_type'];
                    switch($option){
                        case 'option_lang':
                            $option_lang = $item['option_lang'];
                            $checkStr .= '<el-checkbox v-for="item in '.$option_lang.'" :key="item.label" :label="item.label">{{item.value}}</el-checkbox>';
                            break;
                        case 'option_relation':
                            $relationArr = $item['option_relation'];
                            $option_lang = str_replace($prefix,'',$relationArr[0]);
                            $checkStr .= '<el-checkbox v-for="item in '.$option_lang.'List" :key="item.'.$relationArr[2].'" :label="item.'.$relationArr[2].'">{{item.'.$relationArr[1].'}}</el-checkbox>';
                            break;
                        default:
                            $checkArr = $item['option_config'];
                            foreach ($checkArr as $key => $value) {
                                $checkStr .= '<el-checkbox :label="'.$key.'">'.$value.'</el-checkbox>';
                            }
                            break;
                    }
                    
                    $formItem .= <<<EOT
                      <el-form-item label="$tempTitle" prop="$tempField">
                              <el-checkbox-group v-model="form.$tempField" :style="formInputWidth">
                                $checkStr
                              </el-checkbox-group>
                            </el-form-item>
                          
                    EOT;
                    $formField .= $tempField." : [".$item['default']."],\r\n        "; 
                    break;
                case 'switcher':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $formItem .= <<<EOT
                      <el-form-item label="$tempTitle" prop="$tempField">
                            <el-switch v-model="form.$tempField" :style="formInputWidth" />
                          </el-form-item>
                        
                  EOT;
                    $formField .= $tempField." : ".$item['default'].",\r\n        "; 
                    break;
                case 'select':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $selectStr = '';                    

                    $option = $item['option_type'];
                    switch($option){
                        case 'option_lang':
                            $option_lang = $item['option_lang'];
                            $selectStr .= '<el-option v-for="item in '.$option_lang.'" :key="item.value" :label="item.label" :value="item.value" />';
                            break;
                        case 'option_relation':
                            $relationArr = $item['option_relation'];
                            $option_lang = str_replace($prefix,'',$relationArr[0]);
                            $selectStr .= '<el-option v-for="item in '.$option_lang.'List" :key="item.'.$relationArr[1].'" :label="item.'.$relationArr[2].'" :value="item.'.$relationArr[1].'" />';
                            break;
                        default:
                            $selectArr = $item['option_config'];
                            foreach ($selectArr as $key => $value) {
                                $selectStr .= '<el-option label="'.$value.'" value="'.$key.'"></el-option>';
                            }
                            break;
                    }

                    
                    $formItem .= <<<EOT
                      <el-form-item label="$tempTitle" prop="$tempField">
                              <el-select v-model="form.$tempField" :style="formInputWidth">
                                $selectStr
                              </el-select>
                            </el-form-item>
                        
                    EOT;
                    $formField .= $tempField." : '".$item['default']."',\r\n        "; 
                    break;
                case 'number':
                    $tempTitle = $item['title'];
                    $tempField = $item['field'];
                    $formItem .= <<<EOT
                    <el-form-item label="$tempTitle" prop="$tempField">
                            <el-input-number v-model="form.$tempField" :style="formInputWidth" :min="1" :max="1000" />
                          </el-form-item>
                        
                    EOT;
                    $formField .= $tempField." : '".$item['default']."',\r\n        "; 
                    break;
                default:
                    break;
            }       
        }

        $strOut = str_replace(['{%titleName%}', '{%className%}', '{%lowerClassName%}', '{%formItem%}', '{%formField%}', '{%pKey%}'], [
            $title,
            $className,
            $lowerClassName,
            $formItem,
            $formField,
            $pKey
        ], $stub);

        $outPath = $this->path . 'vue'. DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components';
        if (!is_dir($outPath)) {
            mkdir($outPath, 0777, true);
        }
        $outFile = $outPath. DIRECTORY_SEPARATOR . $className .'Form.vue';
        file_put_contents($outFile, $strOut);
    }

    /**
     * 生成VUE的API
     */
    public function buildVueAPi($table)
    {        
        $data = $this->table($table);
        $title = $data['title'];
        $pKey = $data['pk'];

        $templatePath = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'template';
        
        $stub = file_get_contents($templatePath. DIRECTORY_SEPARATOR . 'vue'. DIRECTORY_SEPARATOR . 'api.stub');

        $prefix = config('database.connections.mysql.prefix');

        $fileName = $table;

        if (substr($table,0,strlen($prefix)) == $prefix ) {
            $fileName = substr($fileName,strlen($prefix));
        }
        
        $lowerClassName = Helper::camelize($fileName);
        $className = ucwords($lowerClassName);

        $strOut = str_replace(['{%titleName%}', '{%className%}', '{%lowerClassName%}', '{%pKey%}'], [
            $title,
            $className,
            $lowerClassName,
            $pKey
        ], $stub);

        $outPath = $this->path . 'vue'. DIRECTORY_SEPARATOR . 'api';
        if (!is_dir($outPath)) {
            mkdir($outPath, 0777, true);
        }
        $outFile = $outPath. DIRECTORY_SEPARATOR . $className .'.js';
        file_put_contents($outFile, $strOut);

        
    }

}
