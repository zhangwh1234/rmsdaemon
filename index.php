<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用入口文件

// 检测PHP环境
if(version_compare(PHP_VERSION,'5.3.0','<'))  die('require PHP > 5.3.0 !');

// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG',True);

//定义项目路径
define('APP_PATH',dirname(__FILE__).'/Application/');

// 定义应用目录
//define('APP_PATH','./Application/');

//定义ThinkPHP核心文件路径
define('THINK_PHP',dirname(__FILE__).'/ThinkPHP/');

//加载ThinkPHP核心文件
require THINK_PHP.'ThinkPHP.php';