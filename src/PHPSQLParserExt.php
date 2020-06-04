<?php
/**
 * eg：
 * //第一步：必须配置config.php文件
 * //第二步：非必要步骤，写在index.php入口文件，用于记录访问url
 * $fileName = "sql.log";//这里的文件名要和配置文件的文件名一致
 * $message = '[<font style="color: red">URL</font>]' . strip_tags($_SERVER['REQUEST_URI']) . "\r\n<br>";
 * error_log($message, 3, $fileName . ".html");
 * //第三步：代码写在系统需要记录日志地方
 * $PHPSQLParserExt = new PHPSQLParserExt($sql);
 * $new_message = $PHPSQLParserExt->doIt();
 * //第四步：浏览器打开看看吧
 * THINKPHP5配置方法
 * thinkphp\library\think\log\driver\File.php的save方法内的foreach ($log as $type => $val) {这一行下增加即可，注意命名空间也要引入
 * call_user_func(function()use($type,$val){
if($type != 'sql'){
return false;
}
array_map(function($sql){
if(substr($sql,0,7) != '[ SQL ]'){
return false;
}
$begin_position = stripos($sql,']');
$end_position = strrpos($sql,'[');
$sql = substr($sql,($begin_position+1),($end_position-$begin_position-1));
$PHPSQLParserExt = new PHPSQLParserExt($sql);
$PHPSQLParserExt->doIt();
},$val);
});
 */

namespace PHPSQLParserExt;

use PHPSQLParser\PHPSQLParser;

class PHPSQLParserExt
{
    static private $config;
    /**
     * @var string 需要解析的sql语句
     */
    private $_sql;
    /**
     * @var 以小写字母方式返回sql语句操作动作，例如insert等
     */
    private $_sqlAction;
    /**
     * @var 表名
     */
    private $_tableName;
    /**
     * @var sql语句解析后的数组信息
     */
    private $_sqlInfo = array();//当为true表示发生异常，所有数据不通过cache处理
    private $_exception = false;
    private $_twig;
    /**
     * @var sql日志
     */
    private $_log = '';

    function __construct(string $sql)
    {
        $str = __DIR__ . DIRECTORY_SEPARATOR.'stubs/sql.html';
        $loader = new \Twig_Loader_Filesystem(__DIR__ .DIRECTORY_SEPARATOR. 'stubs');
        $this->_twig = new \Twig_Environment($loader);

        if (empty(self::$config)) {
            self::$config = include_once("config.php");
        }
        $this->__mysqli = new  \mysqli(self::$config['hostname'], self::$config['username'], self::$config['password'], self::$config['database']);

        $this->_sql = $sql;
        try {
            $this->_init();
        } catch (Exception $e) {
            $this->_exception = true;
            $this->_debugInfo(print_r($e->__toString(), true), '异常内容');
        }
    }

//    function __destruct()
//    {
//        echo $this->_twig->render('sql.html', $this->_render);

//    }

    private function _init()
    {
        #格式化sql
        $this->_formate();
        #解析sql
        $parse = new \PHPSQLParser\PHPSQLParser($this->_sql);
        $this->_sqlInfo = $parse->parsed;
        #获取动作
        $this->_getActionFromSql();
        #获取表名
        $this->getTableName();
    }

    private function _formate()
    {
        return $this->_sql = trim(str_replace(PHP_EOL, '', $this->_sql));
    }

    #首尾去空格并去除回车和换行符

    /**
     * 选取第一个单词作为动作
     * @return unknown
     * @throws Exception
     * @example select * from table //select
     *          INSERT INTO tbl_name (col1,col2) VALUES(15,col1*2); //insert
     *          update table set name='rose'//update
     */
    private function _getActionFromSql()
    {
        $current_key = key($this->_sqlInfo);
        $this->_sqlAction = strtolower($current_key);
    }

    function getTableName()
    {
        $current = current($this->_sqlInfo);
        if ($this->_sqlAction == 'insert') {
            $tmpTableName = $current[1]['no_quotes']['parts'][0];
        } elseif ($this->_sqlAction == 'show') {
            $tmpTableName = current($this->_sqlInfo)[2]['table'];
        } elseif ($this->_sqlAction == 'update') {
            $tmpTableName = $this->_sqlInfo['UPDATE'][0]['table'];
        } else {
            $tmpTableName = $this->_sqlInfo['FROM'][0]['table'];
        }

        $this->_tableName[] = trim(trim($tmpTableName), "`");
        return $this->_tableName;
    }

    private function _debugInfo($var, $tips = "")
    {
        self::$config['debug'] && error_log($tips . print_r($var, true) . "\n<br>", 3,__DIR__ . DIRECTORY_SEPARATOR.self::$config['debug_file']);
    }

    function getAction()
    {
        return $this->_sqlAction;
    }
    protected function stub(){
        $str = __DIR__ . '/stubs/sql.html';
        return $str;
    }

    function doIt()
    {
        if ($this->_sqlAction == 'update') {
            $log = $this->parseUpdate();
            $echo = $this->_twig->render('sql.html', $log);
        } elseif ($this->_sqlAction == 'insert') {
            $log = $this->parseInsert();
            $echo = $this->_twig->render('sql.html', $log);
        } elseif ($this->_sqlAction == 'select') {
            $log = $this->parseSelect();
            $echo = $this->_twig->render('sql.html', $log);
        } else {
            $log = $this->_sql;
        }
        if (in_array($this->_sqlAction, self::$config['action'])) {
            if(isset($echo)){
                $this->_debugInfo($echo);
            }
        }
        return $log;
    }

    function parseUpdate()
    {
        foreach ($this->_sqlInfo['SET'] as $k => $v) {
            //字段名
            $param_name = $v['sub_tree'][0]['no_quotes']['parts'][0];
            //字段值
            $param_value = $v['sub_tree'][2]['base_expr'];
            $update[$param_name] = $param_value;
        }
        $tmp = $this->getCommentNew($this->_tableName[0]);

        $comment = $this->getCommentNew($this->_tableName[0]);
        $return = [];
        foreach ($comment['list'] as $k => $v){
            $return[$k]['param'] = $v['param'];
            $return[$k]['comment'] = $v['comment'];
            $return[$k]['default'] = $v['default'];
            if(isset($update[$v['param']])){
                $return[$k]['update_value'] = $update[$v['param']];
            }else{
                $return[$k]['update_value'] = "";
            }
        }
        return ['list'=>$return,'comment'=>$comment['comment'],'sql'=>$this->_sql];
    }

    private function getCreateTableSql($tableName)
    {
        $sql = "SHOW CREATE TABLE " . $tableName ;
        $queryObj = $this->__mysqli->query($sql);
        $row = (array)$queryObj->fetch_object();
        return $row;
    }

    function parseInsert()
    {
        $sqlInfo = $this->_sqlInfo;
        $paramArr = call_user_func(function () use ($sqlInfo) {
            $arr = explode(",", trim($this->_sqlInfo['INSERT'][2]['base_expr'], "()"));
            $arr = array_map(function ($a) {
                return trim(trim($a), "`");
            }, $arr);
            return $arr;
        });
        $valueArr = $this->_sqlInfo['VALUES'][0]['data'];
        $valueArr_New = array_map(function ($value){
            return $value['base_expr'];
        },$valueArr);
        $new = array_combine($paramArr,$valueArr_New);

        $comment = $this->getCommentNew($this->_tableName[0]);
        $return = [];
        foreach ($comment['list'] as $k => $v){
            $return[$k]['param'] = $v['param'];
            $return[$k]['comment'] = $v['comment'];
            $return[$k]['default'] = $v['default'];
            if(isset($new[$v['param']])){
                $return[$k]['insert_value'] = $new[$v['param']];
            }else{
                $return[$k]['insert_value'] = "";
            }
        }
        return ['list'=>$return,'comment'=>$comment['comment'],'sql'=>$this->_sql];
    }

    function parseSelect(){
        //可能多个表
        $tableName = $this->_sqlInfo['FROM'][0]['table'];
        $tableName = trim($tableName,"`");
        $commentNew=$this->getCommentNew($tableName);
        return $commentNew+['sql'=>$this->_sql];
    }

    function getCommentNew($tableName)
    {
        $database=self::$config['database'];

        $comm = [['param'=>"uid",'comment'=>'会员ID','default'=>null]];
        $sql1 = "select COLUMN_NAME,COLUMN_DEFAULT,COLUMN_COMMENT from information_schema.`COLUMNS` where TABLE_SCHEMA='{$database}' and TABLE_NAME ='{$tableName}'";
        $queryObj = $this->__mysqli->query($sql1);
        $row1 = (array)$queryObj->fetch_all();

        $list = array_map(function ($v){
            $return = [];
            $return['param'] = $v[0];
            $return['default'] = $v[1];
            $return['comment'] = $v[2];
            return $return;
        },$row1);

        $sql2 = "select TABLE_COMMENT from information_schema.`TABLES` where TABLE_SCHEMA='{$database}' and TABLE_NAME='{$tableName}'";
        $queryObj = $this->__mysqli->query($sql2);
        $row2 = (array)$queryObj->fetch_array();
        return ['comment' => $row2['TABLE_COMMENT'], 'list' => $list];
    }
}