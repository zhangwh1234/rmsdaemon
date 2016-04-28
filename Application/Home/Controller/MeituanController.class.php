<?php
/**
 * Created by zhangwh1234
 * User: lihua.com
 * Date: 16/3/2
 * Time: 上午11:22
 * 美团外卖的订单下载接口
 * 测试命令: php /Applications/XAMPP/htdocs/rmsdaemon/index.php Home/Index/index
 */

namespace Home\Controller;

use Think\Controller;
use Think\Log;

class MeituanController extends Controller
{
    //订单表
    protected $orderform;
    //订货表
    protected $orderproducts;
    //活动表
    protected $orderactivity;
    //支付表
    protected $orderpayment;
    //订单日志表
    protected $orderaction;
    //订单状态表
    protected $orderstate;

    function _initialize()
    {
        $this->orderform = D("OrderForm");
        $this->orderproducts = D('OrderProducts');
    }

    //广州地区获取美团的订单
    public function getGzOrder()
    {
        $shops[] = array(
            'app_id' => '3',
            'app_poi_code' => 'lihua_poi_05',
            'company' => '广州',
            'isAuto' => true,  //是否自动分配
            'domain' => 'gz.lihuaerp.com',
        );

        while (1) {
            var_dump('getOrder');
            // wait for 10 seconds
            //usleep(2000000);
            foreach ($shops as $shop) {
                $this->getOrder($shop);
            }
            exit;
        }
    }


    //获取订单
    public function getOrder($shop)
    {

        $this->log('开始美团网下载');

        // 时间戳
        $timestamp = time();
        // 程序id
        $app_id = $shop ['app_id'];
        // 商店API
        $app_poi_code = $shop ['app_poi_code'];

        // 查询新订单ID的url
        $getUrl = 'http://waimaiopen.meituan.com/api/v1/order/getneworders';
        // 加密前的url
        $url = $getUrl . '?' . "app_id=$app_id&app_poi_code=$app_poi_code&timestamp=$timestamp";

        // 加密
        $signUrl = md5($url . "ddb777ba29851759f1b58cd96ba5dd52");
        // 获得url
        $url = $url . '&sig=' . $signUrl;
        // 记入日志
        $this->log($shop['name'] . '下载的url:' . $url);

        //$resp = curl_get($url);

        $resp = '{"data":[{"ctime":1456633219,"order_id":959605225,"pay_type":2,"is_third_shipping":0,"extras":[{"reduce_fee":8.0,"act_detail_id":null,"remark":"满25.0元减8.0元。","type":2}],"app_order_code":"","wm_poi_name":"丽华快餐(圆明园)","wm_poi_address":"北京水磨西街66号,清华大学西门斜对面","wm_poi_phone":"4008800400","recipient_name":"袁京龙(先生)","recipient_phone":"18612690705","recipient_address":"金苹果幼儿园(正白旗路) (北京市海淀区上地正白旗村正白旗公寓附近成都美食旁路口往里有走左侧斜道看见第一个路口往右走看见第一个)","caution":"","shipper_phone":"","has_invoiced":0,"invoice_title":"","is_pre":0,"expect_deliver_time":0,"shipping_type":null,"detail":[{"food_discount":1.0,"quantity":3,"food_name":"冰露矿泉水","sku_id":"","app_food_code":"冰露矿泉水","unit":"瓶","box_num":0.0,"box_price":0.0,"price":2.0},{"food_discount":1.0,"quantity":1,"food_name":"20元特价套餐","sku_id":"","app_food_code":"20元特价套餐","unit":"份","box_num":1.0,"box_price":0.0,"price":20.0}],"avg_send_time":1913.0,"wm_order_id_view":4431761814289387,"original_price":32.0,"delivery_time":0,"day_seq":null,"utime":1456633219,"source_id":null,"wm_poi_id":443176,"latitude":40.020132,"longitude":116.310008,"app_poi_code":"lihua_poi_61","shipping_fee":6.0,"city_id":null,"remark":"","rider_fee":"","total":24.0,"status":2}]}';
        // 记入日志
        $this->log('取订单返回的结果:' . $resp);

        $orderArray = json_decode($resp, true); // 数组化

        if (count($orderArray ['data']) == 0) {
            $this->log('没有订单:' . date('Y-m-d H:s:i'));
            $this->log('------------------------------------              ');
        }

        foreach ($orderArray ['data'] as $orderValue) {

            /***********************************
             * 订单表处理
             ***********************************/
            $orderform_arr = array();
            //订单号
            $orderform_arr['ordersn'] = ( string )$orderValue['order_id'];

            //联系人的姓名
            $orderform_arr['clientname'] = $orderValue ['recipient_name'];

            //用户送餐地址
            $orderform_arr['address'] = $orderValue ['recipient_address'];
            $orderform_arr['address'] = $this->filter($orderform_arr['address']);

            // 收货人手机
            $orderform_arr['telphone'] = $orderValue ['recipient_phone'];
            $this->orderform = $this->filter($this->orderform);

            //订餐商品简述
            $orderform_arr['ordertxt'] = '';

            // 订单备注(附言)
            $orderform_arr['beizhu'] = $orderValue ['remark'] . $orderValue ['caution'];
            $orderform_arr['beizhu'] = $this->filter($orderform_arr['beizhu']);

            //订单总金额
            $orderform_arr['totalmoney'] = $orderValue ['original_price'];

            if ($orderValue['pay_type'] == 2) { //是在线支付

                //已付金额
                $orderform_arr['paidmoney'] = $orderValue['total'];

                //应收金额
                $orderform_arr['shouldmoney'] = 0;
            } else {//是货到付款
                //已付金额
                $orderform_arr['paidmoney'] = 0;

                //应收金额
                $orderform_arr['shouldmoney'] = $orderValue ['total'];
            }


            //送餐时间
            if ($orderValue ['delivery_time'] == 0) {
                $orderform_arr['custtime'] = date('H:i:s', time() + 60 * 30);
            } else {
                $orderform_arr['custtime'] = date('H:i:s', ( integer )$orderValue ['delivery_time']); // substr($OrderForm['deliver_time'], 11, 8);
            }

            //午别
            $orderform_arr['ap'] = getAp();

            //分公司名称（商店）
            $orderform_arr['company'] = $shop['isAuto'] ? $shop['company'] : '';

            //接单员
            $orderform_arr['telname'] = '美团网';

            //接单时间
            $orderform_arr['rectime'] = date('H:i:s');

            //接单日期
            $orderform_arr['recdate'] = date('Y-m-d');

            //发票抬头
            if ($orderValue ['has_invoiced'] == 1) {
                $orderform_arr['invoiceheader'] = $orderValue ['invoice_title'];
            } else {
                $orderform_arr['invoiceheader'] = '';
            }
            //发票内容
            $orderform_arr['invoicebody'] = '工作餐';

            //送餐费名称
            $orderform_arr['shippingname'] = '自配送';

            //送餐费金额
            $orderform_arr['shippingmoney'] = ( integer )$orderValue ['shipping_fee'];

            //订单坐标维度
            $orderform_arr['longitude'] = (string)$orderValue['longitude'];

            //订单坐标经度
            $orderform_arr['latitude'] = (string)$orderValue['latitude'];

            //订单来源
            $orderform_arr['origin'] = '美团网';

            //配送地区标识
            $orderform_arr['domain'] = $shop['domain'];

            $orderformid = 111;
            /******************************************
             * 订货表处理
             */
            // 商品的下载数据GROUP，主要的餐
            $meiOrderGoods = $orderValue ['detail'];
            $ordertxt = '';
            foreach ($meiOrderGoods as $orderGoods) {
                // 定义商品数组
                $orderproducts_arr = array();
                //订单号
                $orderproducts_arr['ordersn'] = ( string )$orderValue['order_id'];

                //订单id
                $orderproducts_arr['orderformid'] = $orderformid;

                //产品code
                $orderproducts_arr['code'] = D('Products')->getProductsCode($orderGoods ['food_name'], $shop['domain']);  //返回产品代码

                //产品名称
                $orderproducts_arr['name'] = $orderGoods ['food_name'];

                //产品简称
                $orderproducts_arr['shortname'] = D('Products')->getProductsShortname($orderGoods ['food_name'], $shop['domain']);  //返回产品代码);

                //产品单价
                $orderproducts_arr['price'] = $orderGoods ['price'];

                //产品数量
                $orderproducts_arr['number'] = $orderGoods ['quantity'];

                //产品金额
                $orderproducts_arr['money'] = $orderGoods ['quantity'] * $orderGoods ['price'];

                //配送地区标识
                $orderproducts_arr['domain'] = $shop['domain'];

                // 商品简述
                $ordertxt .= $orderproducts_arr['number'] . '×' . $orderproducts_arr ['name'] . ' ';

            }

            /********************************************************************
             * 活动表
             */
            $extraGoods = $orderValue ['extras'];
            foreach ($extraGoods as $extra) {
                $orderactivity_arr = array();
                //订单号
                $orderactivity_arr['ordersn'] = ( string )$orderValue['order_id'];

                //订单id
                $orderactivity_arr['orderformid'] = $orderformid;

                //活动id
                $orderactivity_arr['activityid'] = $extra ['type'];

                //活动名称
                $orderactivity_arr['name'] = $extra ['remark'];

                //活动金额
                $orderactivity_arr['money'] = $extra ['reduce_fee'];

                //活动备注
                $orderactivity_arr['note'] = '';
            }


            /********************************************************************
             * 支付表
             */
            $orderpayment_arr = array();
            if ($orderValue['pay_type'] == 2) {
                //订单号
                $orderpayment_arr['ordersn'] = ( string )$orderValue['order_id'];

                //订单id
                $orderpayment_arr['orderformid'] = $orderformid;

                //支付ID
                $orderpayment_arr['paymentid'] = $orderValue ['act_detail_id'];;

                //支付名称
                $orderpayment_arr['name'] = '美支付';

                //支付金额
                $orderpayment_arr['money'] = $orderValue ['original_price'];

                //支付备注
                $orderpayment_arr['note'] = '';
            }


            /**********************************************************************
             * 订单日志表
             */
            $orderction_arr = array();
            //订单号
            $orderction_arr['ordersn'] = ( string )$orderValue['order_id'];

            //订单id
            $orderction_arr['orderformid']= $orderformid;

            //日志内容
            $orderction_arr['action'] = '下载订单';

            //日志时间
            $orderction_arr['logtime'] = date('H:i:s');

            //标识
            $orderction_arr['domain'] = $shop['domain'];

            /**********************************************************************
             * 订单状态表
             */
            $orderstate_arr = array();
            //订单号
            $orderstate_arr['ordersn'] = ( string )$orderValue['order_id'];

            //订单id
            $orderstate_arr['orderformid'] = $orderformid;

            //订单创建
            $orderstate_arr['create'] = 1;

            //订单创建时间
            $orderstate_arr['createtime'] = date('H:i:s');

            //订单创建说明
            $orderstate_arr['createcontent'] = '订单下载';

            //订单分配
            $orderstate_arr['distribution'] = 1;

            //订单分配时间
            $orderstate_arr['distributiontime'] = date('H:i:s');

            //订单分配内容
            $orderstate_arr['distributioncontent'] = '订单分配到';


            // 确认订单
            $comfirmUrl = 'http://waimaiopen.meituan.com/api/v1/order/confirm';
            // 改订单号为long
            $order_id = $orderValue ['order_id'];

            // 时间戳
            $timestamp = time();
            // 取得数组
            // 加密前的url
            $url = $comfirmUrl . '?' . "app_id=$app_id&app_poi_code=$app_poi_code&timestamp=$timestamp&orderId=$order_id";

            // 加密
            $comfirmUrl = $comfirmUrl . '?' . "app_id=$app_id&app_poi_code=$app_poi_code&orderId=$order_id&timestamp=$timestamp" . "ddb777ba29851759f1b58cd96ba5dd52";
            $signUrl = md5($comfirmUrl);
            // 获得url
            $url = $url . '&sig=' . $signUrl;

            $respArray = json_decode($resp, true); // 数组化


        }
    }


    //删除特殊的字符
    private function filter($text)
    {
        $text = str_replace("`", "", $text);
        $text = str_replace("'", "", $text);
        $text = str_replace("~", "", $text);
        $text = str_replace('"', "", $text);
        $text = str_replace('　', " ", $text);
        $text = str_replace('发票', "", $text);
        return htmlspecialchars($text, ENT_QUOTES);
    }

    //简化日志方法
    private function log($msg)
    {
        // 定义日志文件
        $LogFile = C('LOG_PATH') . 'Meituan_' . date('Y_m_d') . ".log";

        // 记入日志
        LOG::write($msg, LOG::INFO, '', $LogFile);
    }

    //空方法
    public function _empty()
    {
        var_dump('empty');
    }
}