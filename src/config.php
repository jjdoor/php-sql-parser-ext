<?php
return [
    // 数据库类型
    'type' => 'mysql',
    // 服务器地址
//    'hostname'        => 'rm-2ze34rc7z33ss5qr45o.mysql.rds.aliyuncs.com',
    'hostname' => '127.0.0.1',
    // 数据库名
    'database' => 'mdlr',
    // 用户名
//    'username' => 'mdlr',
    'username' => 'root',
    // 密码
//    'password'        => 'MdlrLY1983',
    'password' => '',
    // 端口
    'hostport' => '3306',
    'debug' => true,
    'debug_file' => 'sql.log.detail.html',
    //记录哪些动作
    'action' => array('insert', 'update', 'select')
];
?>