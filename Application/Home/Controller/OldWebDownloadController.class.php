<?php
/**
 * Created by zhangwh1234.
 * User: apple
 * Date: 16/3/11
 * Time: 上午11:32
 * 从旧版网站下载订单到服务器中
 * 测试命令: /Applications/XAMPP/xamppfiles/bin/php  /Applications/XAMPP/htdocs/rmsdaemon/index.php Home/OldWebDownload/getOldShop
 */

namespace Home\Controller;
use Think\Controller;

class OldWebDownloadController extends Controller
{

    /**
     * 获取常州
     */
    public function getCzOrder(){
            $this->rmsOrder(1);
    }

    /*
     * 获取广州的订单
     */
    public function getGzOrder(){

        //while(1){
            $this->rmsOrder(9);
        //    usleep(100000);
        //}

    }

    /**
     * 获取南京订单
     */
    public function getNjOrder(){
        $this->rmsOrder(6);
    }

    /**
     * 保存到rms表中
     */
    function rmsOrder($suppliers_id){
        $connect_str = C('RMS_CONNECTSTR');
        //订单表
        $orderform_model = M('orderform', 'rms_', $connect_str);
        //订货表
        $ordergoods_model = M('orderproducts', 'rms_', $connect_str);
        //活动表
        $orderactivity_model = M('orderactivity', 'rms_', $connect_str);
        //支付表
        $orderpayment_model = M('orderpayment', 'rms_', $connect_str);
        //状态表
        $orderstate_model = M('orderstate', 'rms_', $connect_str);
        //日志表action中
        $orderaction_model =  M('orderaction', 'rms_', $connect_str);
        //营收状态表
        $orderyingshouexchangeModel = M('Orderyingshouexchange', 'rms_', $connect_str);

        $order_arr = $this->getOldShop($suppliers_id);

        foreach($order_arr as $order ){

            //保存到rms_orderform表中
            $orderform_model->create();
            $orderformid = $orderform_model->add($order);

            //保存到订货表中rms_orderproducts
            $ordertxt = ''; //生成产品简述
            $goodsmoney = 0; //商品金额
            $goods_arr = $order['goods'];
            foreach($goods_arr as $goods){
                $goods['orderformid'] = $orderformid;
                $goods['domain'] = $this->getDomain($suppliers_id);
                $ordergoods_model->create();
                $ordergoodsid = $ordergoods_model->add($goods);
                $ordertxt .= $goods['number']  .'×'. $goods['name']; //生成产品简述
                $goodsmoney = $goodsmoney + $goods['number']  * $goods['price'];
            }

            //保存到活动表rms_orderactivity
            $activitymoney = 0;
            $activity_arr = $order['activity'];
            foreach($activity_arr as $activity){
                $activity['orderformid'] = $orderformid;
                $orderactivity_model->create();
                $activityid = $orderactivity_model->add($activity);
                $activitymoney = $activitymoney + $activity['money'];
            }

            //保存到支付表rms_orderpayment
            $paymentmoney = 0;
            $payment_arr = $order['payment'];
            foreach($payment_arr as $payment){
                $payment['orderformid'] = $orderformid;
                $orderpayment_model->create();
                $paymentid = $orderpayment_model->add($payment);
                $paymentmoney = $paymentmoney + $payment['money'];
            }

            $data = array();
            //计算应收金额
            $data['shouldmoney'] = $order['totalmoney'] - $paymentmoney - $activitymoney;
            //已付金额
            $data['paidmoney'] = $paymentmoney;
            $data['ordertxt'] = $ordertxt;
            $data['goodsmoney'] = $goodsmoney;
            $where = array();
            $where['orderformid'] = $orderformid;
            $orderform_model->where($where)->save($data);

            // 写入到状态表中
            $data = array();
            $data ['create'] = 1;
            $data ['createtime'] = date('Y-m-d H:i:s');
            $data ['createcontent'] = '网站输入';
            $data ['orderformid'] = $orderformid;
            $data ['ordersn'] = $order['ordersn'];
            $data ['domain'] = $this->getDomain($suppliers_id);
            $orderstate_model->create();
            $orderstate_model->add($data);

            // 记入操作到action中
            $action ['orderformid'] = $orderformid; // 订单号
            $action ['ordersn'] = $order['ordersn'];
            $action ['action'] = '网站' . ' 新建 ' . $order ['address'] . ' ' . $ordertxt .
                '分:' . $order['company'] . ' ' . $order['beizhu'];
            $action ['logtime'] = date('H:i:s');
            $action ['domain'] = $this->getDomain($suppliers_id);
            $orderaction_model->create();
            $result = $orderaction_model->add($action);

            // 写入到营收状态表
            $data = array();
            $data ['orderformid'] = $orderformid;
            $data ['ordersn'] = $order['ordersn'];
            $data ['status'] = 0;
            $data ['domain'] = $this->getDomain($suppliers_id);
            $orderyingshouexchangeModel->create();
            $orderyingshouexchangeModel->add($data);

            //如果下载的定的中有分公司，说明已经是自动分配
            if(!empty($order['company'])){
                // 同时写入日志中
                // 记入操作到action中
                $action = array();
                $action ['orderformid'] = $orderformid; // 订单号
                $action ['ordersn'] = $order['ordersn']; // 订单号
                $company = $data ['company'];
                $action ['action'] = "订单分配给" . $order['company'] . "配送点";
                $action ['logtime'] = date ( 'H:i:s' );
                $orderaction_model->create ();
                $result = $orderaction_model->add ( $action );

                // 写入到状态表中
                $data = array ();
                $data ['distribution'] = 1;
                $data ['distributiontime'] = date ( 'Y-m-d H:i:s' );
                $data ['distributioncontent'] =  $order['company'];
                $where = array ();
                $where ['orderformid'] = $orderformid;
                $orderstate_model->where ( $where )->save ( $data );

            }

            //rms_state = 1
            $this->setRmsState($order['ordersn']);
        }

    }

    /**
     * 置rms_state = 1，已经获取
     */
    function setRmsState($ordersn){
        $connect_str = C('SHOP_CONNECTSTR');
        //订单表
        $orderform_model = M('order_info', 'ecs_', $connect_str);
        $where = array();
        $where['order_sn'] = $ordersn;
        $data = array();
        $data['rms_state'] = 1;
        $orderform_model->where($where)->save($data);
    }

    /**
     * 从旧版网站数据库取出数据，返回数据组
     */
    public function getOldShop($shop_id = 0)
    {

        $connect_str = C('SHOP_CONNECTSTR');
        //订单表
        $orderform_model = M('order_info', 'ecs_', $connect_str);
        //订货表
        $ordergoods_model = M('order_goods', 'ecs_', $connect_str);
        //活动表
        $orderactivity_model = M('order_activities', 'ecs_', $connect_str);
        //支付表
        $orderpayment_model = M('order_payment', 'ecs_', $connect_str);

        //定义订单数值
        $orderform_arr = array();

        //查询表
        $where = array();
        $where['rms_state'] = 0;
        $where['suppliers_id'] = $shop_id;
        $where [] = 'unix_timestamp() - add_time > 5';
        $where [] = '(pay_id=3 or  pay_id = 22  or pay_id = 24 or (pay_status<>3 and pay_status=2))';
        $orderform = $orderform_model->where($where)->limit(20)->select();
        foreach ($orderform as $orderValue) {
            $order_arr = array();
            $order_arr = $this->parseOrderInfo($orderValue);
            $ordersn = $orderValue['order_sn'];
            //查询订货表
            $where = array();
            $where['order_sn'] = $ordersn;
            $ordergoods = $ordergoods_model->where($where)->select();
            $order_arr['goods'] = $this->parseOrderGoods($ordergoods,$ordersn);
            //查询活动表
            $where = array();
            $where['order_sn'] = $ordersn;
            $orderactivity = $orderactivity_model->where($where)->select();
            $order_arr['activity'] = $this->parseOrderActivity($orderactivity,$ordersn);
            //支付表
            $where = array();
            $where['order_sn'] = $ordersn;
            $orderpayment = $orderpayment_model->where($where)->select();
            $order_arr['payment'] = $this->parseOrderPayment($orderpayment,$ordersn);

            //加入数组中
            $orderform_arr[] = $order_arr;
        }
        return $orderform_arr;
    }

    /**
     * 转换order_info为rms需要的字段
     */
    function parseOrderInfo($arr)
    {
        $orderform_array = array();
        $orderform_array ['ordersn'] = $arr['order_sn'];
        $orderform_array['clientname'] = $arr['consignee'];

        $orderform_array ['address'] = $this->ReMoveChar($arr['address']);

        $orderform_array ['telphone'] = $this->ReMoveChar($arr['tel']);

        if (!empty ($arr['shipping_time'])) { // 如果有配送日期，就输入日期
            $orderform_array ['custdate'] = Date("Y-m-d", $arr['shipping_time']);
        } else {
            $orderform_array ['custdate'] = Date("Y-m-d", time()); // 没有配送日期，就选当前日期
        }
        $orderform_array ['custtime'] = $arr['best_time'];

        if (trim($arr['referer']) == '本站') {
            $orderform_array ['telname'] = '网络';
        } else {
            $orderform_array ['telname'] = trim($arr['referer']);
        }

        $orderform_array ['recdate'] = date('Y-m-d');
        $orderform_array ['rectime'] = Date("H:i:s");
        $orderform_array ['beizhu'] = $this->ReMoveChar($arr['postscript']);
        //订单总金额
        $orderform_array ['totalmoney'] = $arr['order_amount']; // $value['goods_amount']+$value['shipping_fee'];
        //送餐费
        $orderform_array ['shippingmoney'] = $arr['shipping_fee'];

        //发票
        $orderform_array ['billheader'] = $arr['inv_payee'];
        $orderform_array ['billbody'] = $arr['inv_content'];

        //分公司
        $orderform_array ['company'] = $arr['company'];
        $orderform_array ['ordertxt'] = '';
        $orderform_array ['state'] = '订餐';

        // 根据suppliers_id来判断,输入domain
        $orderform_array ['domain'] = $this->getDomain($arr['suppliers_id']);

        // 根据送餐时间来判断
        if (intval(substr($arr['best_time'], 0, 2)) >= 15)
            $cAp = '下午';
        else
            $cAp = '上午';
        $orderform_array ['ap'] = $cAp;

        //订单来源
        $orderform_array ['origin'] = $arr['referer'];

        return $orderform_array;
    }

    /**
     * 转换ordergoods到rms
     */
    function parseOrderGoods($goods,$ordersn){
        foreach($goods as $arr){
            if($arr['goods_price'] > 0 ) {
                $goods_tmp = array();
                $goods_tmp ['ordersn'] = $ordersn;
                $goods_tmp ['name'] = $arr['goods_name'];
                $goods_tmp ['shortname'] = $arr['goods_name'];
                $goods_tmp ['number'] = $arr['goods_number'];
                $goods_tmp ['price'] = $arr['goods_price'];
                $goods_tmp ['money'] = $arr['goods_number'] * $arr['goods_price'];
                $goods_arr[] = $goods_tmp;
            }
        }
        return $goods_arr;
    }

    /**
     * 转换orderactivity到rms
     */
    function parseOrderActivity($activity,$ordersn){
        foreach($activity as $arr){
            $activity_tmp = array();
            $activity_tmp ['ordersn'] = $ordersn;
            if(empty($arr['activities_id'])){
                $arr['activities_id'] = 0;
            }
            $activity_tmp ['activityid'] = $arr['activities_id'];
            $activity_tmp ['name'] = $arr['activities_name'];
            $activity_tmp ['money'] = $arr['discount'];
            $activity_arr[] = $activity_tmp;
        }
        return $activity_arr;
    }

    /**
     * 转换orderpayment 到rms
     */
    function parseOrderPayment($payment,$ordersn){
        foreach($payment as $arr){
            $payment_tmp = array();
            $payment_tmp ['ordersn'] = $ordersn;
            if(empty($arr['payment_id'])){
                $arr['payment_id'] = 0;
            }
            $payment_tmp ['paymentid'] = $arr['payment_id'];
            $payment_tmp ['name'] = $arr['payment_name'];
            $payment_tmp ['money'] = $arr['discount'];
            $payment_arr[] = $payment_tmp;
        }
        return $payment_arr;
    }

    /**
     * 获取标识
     * @param $text
     * @return string
     */
    function getDomain($supperliers_id){
        switch($supperliers_id){
            case 1:
                $domain = 'cz.lihuaerp.com';
                break;
            case 4:
                $domain = 'cz.lihuaerp.com';
                break;
            case 6:
                $domain = 'nj.lihuaerp.com';
                break;
            case 9:
                $domain = 'gz.lihuaerp.com';
                break;
        }
        return $domain;
    }

    // 删除特殊的字符
    function ReMoveChar($text)
    {
        $text = str_replace("`", "", $text);
        $text = str_replace("'", "", $text);
        $text = str_replace("~", "", $text);
        $text = str_replace('"', "", $text);
        $text = str_replace('　', " ", $text);
        $text = str_replace('，', "", $text);
        $text = str_replace(',', "", $text);
        $text = str_replace('.', '元', $text);
        $text = str_replace('.', '<', $text);
        $text = str_replace('.', '>', $text);

        for ($i = 0; $i < 32; $i++) {
            $text = str_replace(chr($i), "", $text);
        }
        return htmlspecialchars($text, ENT_QUOTES);
    }
}