<?php
/**
 * Created by IntelliJ IDEA.
 * User: apple
 * Date: 16/3/3
 * Time: 下午12:45
 */

namespace Home\Model;
use Think\Model;
class ProductsModel extends Model {
    protected $tableName = 'products';

    //返回产品代码
    public function getProductsCode($name,$domain){
        $where = array();
        $where['name'] = $name;
        $products = $this->where($where)->find();
        if($products){
            return $products['code'];
        }else{
            return '';
        }

    }

    //返回产品简称
    public function getProductsShortname($name,$domain){
        $where = array();
        $where['name'] = $name;
        $products = $this->where($where)->find();
        if($products){
            return $products['shortname'];
        }else{
            return $name;
        }
    }


}