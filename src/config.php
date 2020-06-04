<?php
return [
    // 数据库类型
    'type' => 'mysql',
    // 服务器地址
    'hostname' => '127.0.0.1',
    // 数据库名
    'database' => 'fastadmin',
    // 用户名
    'username' => 'root',
    // 密码
    'password' => '',
    // 端口
    'hostport' => '3306',
    'debug' => true,
    'debug_file' => 'fastadmin.sql.log.html',
    //记录哪些动作
    'action' => array('insert', 'update', 'select')
];
?>