<?php
/**
 * Created by IntelliJ IDEA.
 * User: apple
 * Date: 16/3/11
 * Time: 下午3:28
 */

namespace Home\Model;
use Think\Model;
class OrderInfoModel extends Model{
    protected $tablePrefix = 'ecs_';
    protected $trueTableName = 'order_info';
    protected $connection = array(
        'db_type'  => 'mysql',
        'db_user'  => 'root',
        'db_pwd'   => '',
        'db_host'  => 'localhost',
        'db_port'  => '3306',
        'db_name'  => 'lihuashop',
        'db_charset' =>    'utf8',
    );

}