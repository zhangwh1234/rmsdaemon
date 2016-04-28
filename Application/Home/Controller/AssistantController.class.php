<?php
/**
 * Created by zhangwh1234.
 * User: lihua
 * Date: 16/3/18
 * Time: 上午11:42
 * 从rms中获取数据，保存到assis小助手中
 * 测试命令:/Applications/XAMPP/xamppfiles/bin/php  /Applications/XAMPP/htdocs/rmsdaemon/index.php Home/Assistant/getGzMsg
 * 运行名利:/alidata/server/php/bin/php /home/rmsdaemon/index.php Home/Assistant/getGzMsg
 */

namespace Home\Controller;
use Think\Controller;

class AssistantController extends Controller{

    public function getCzOrder(){
        $this->logFile = C('LOG_PATH') . 'czOrderAssis-' . date('y_m_d') . '.log';
        \Think\Log::write('开始获取常州的数据', \Think\Log::INFO, '', $this->logFile);
        while(1){
            $this->getOrderForm(4);
            usleep(3000000);
        }

    }
    //南京
    public function getNjOrder(){
        $this->getOrderForm(6);
    }
    //广州
    public function getGzOrder(){
        $this->getOrderForm(9);
    }

    // 取得订单
    function getOrderForm($shopid) {

        // 抓取的url
        $url = 'http://nj.lihuaerp.com/index.php/InterfaceServer/assisGetOrderForm/token/lihua1/domain/' . $shopid;
        // 启动curl去抓取
        $resp = curl ( $url );
        \Think\Log::write('获取的数据'.$resp, \Think\Log::INFO, '', $this->logFile);
        // 数组化
        $orderformArray = json_decode ( $resp, true );

        // 取得订单数组
        $orderform = $orderformArray ['result'];

        foreach ( $orderform as $key => $value ) {

            $this->saveOrderForm ( $value  , $shopid);
            $this->saveOrderGoods ( $value , $shopid );
            $this->saveOrderActivity($value,$shopid);
            $this->saveOrderPayment($value,$shopid);

            // 确认订单
            $confirmUrl = 'http://nj.lihuaerp.com/index.php/InterfaceServer/assisSetOrderForm/orderformid/' . $key . '.html';
            \Think\Log::write('确认数据'.$url, \Think\Log::INFO, '', $this->logFile);
            $resp = curl ( $confirmUrl );
            \Think\Log::write('确认回复'.$resp, \Think\Log::INFO, '', $this->logFile);
        }
    }


    // 保存订单表
    function saveOrderForm($orderform_array,$shopid) {

        $data = array();
        //id
        $data['order_id'] = $orderform_array['orderformid'];
        $data['sn'] = $orderform_array['ordersn'];
        //地址
        $data['address'] = $orderform_array['address'];
        //电话
        $data['tel'] = $orderform_array['telphone'];
        //要餐时间
        $data['time'] = $orderform_array['custtime'];
        //备注
        $data['remark'] = $orderform_array['beizhu'];
        //发票抬头
        $data['inv_pagee'] = $orderform_array['invoiceheader'];
        //发票内容
        $data['inv_content']= $orderform_array['invoicebody'];
        //订单总额
        $data['amount'] = $orderform_array['totalmoney'];
        //应收金额
        $data['getmoney'] = empty($orderform_array['shouldmoney'])?0:$orderform_array['shouldmoney'];
        //已收金额
        $data['money_paid'] = empty($orderform_array['paidmoney']) ? 0 : $orderform_array['paidmoney'] ;
        //来源
        $data['refer'] = empty($orderform_array['origian'])? $orderform_array['telname'] : $orderform_array['origian'];
        //下单时间
        $data['add_time'] = date('Y-m-d H:i:s');
        //经度
        $data['latitude'] = empty($orderform_array['latitude']) ? 0 : $orderform_array['latitude'] ;
        //经度
        $data['longitude'] = empty($orderform_array['longitude']) ? 0 :$orderform_array['longitude'];
        //送餐员
        $data ['employee'] = empty($orderform_array['sendname']) ? '' : $orderform_array['sendname'];
        //分公司
        $data['company'] = $orderform_array['company'];
        //地区
        $data['area'] = $this->getArea($shopid);
        //午别
        $data['ap'] = $orderform_array['ap'];

        $data['send_status'] = 0;

        // 查询订单是否已经存在
        $where = array ();
        $where ['sn'] = $orderform_array['ordersn'];

        $connect_str = C('ASSISTANT_CONNECTSTR');
        $kforderformModel =  M('order', 'sp_', $connect_str);
        $result = $kforderformModel->where ( $where )->find ();

        if (! empty ( $result )) {
            $where = array ();
            $where ['sn'] = $orderform_array['ordersn'];
            $kforderformModel->where ( $where )->save ( $data );
            \Think\Log::write('插入order:'.$kforderformModel->getLastSql(), \Think\Log::INFO, '', $this->logFile);
        } else {
            $kforderformModel->create ();
            $kforderformModel->add ( $data );
            \Think\Log::write('插入order:'.$kforderformModel->getLastSql(), \Think\Log::INFO, '', $this->logFile);

        }
    }

    // 保存订单产品表
    function saveOrderGoods($orderform_array,$shopid) {
        $where = array ();
        $where ['order_id'] = $orderform_array ['orderformid'];

        $connect_str = C('ASSISTANT_CONNECTSTR');
        $kfgetgoodsModel =  M('order_good', 'sp_', $connect_str);
        $kfgetgoodsModel->where ( $where )->delete ();
        $ordergoodsform_product_array = $orderform_array ['orderproducts'];

        foreach ( $ordergoodsform_product_array as $products_value ) {
            $data = array ();
            $data ['goods_id'] = $products_value ['orderproductsid'];
            $data ['order_id'] = $orderform_array ['orderformid'];
            $data ['name']     = $products_value ['name'];
            $data ['price']    = $products_value ['price'];
            $data ['num']      = $products_value ['number'];
            $data ['sn']       = $products_value ['ordersn'];
            $data ['area']     = $this->getArea($shopid);

            $kfgetgoodsModel->create ();
            $kfgetgoodsModel->add ( $data );
            \Think\Log::write('插入goods:'.$kfgetgoodsModel->getLastSql(), \Think\Log::INFO, '', $this->logFile);

        }
    }

    // 保存活动表
    function saveOrderActivity($orderform_array,$shopid){
        $where = array ();
        $where ['order_id'] = $orderform_array ['orderformid'];

        $connect_str = C('ASSISTANT_CONNECTSTR');
        $orderactivityModel =  M('order_activity', 'sp_', $connect_str);
        $orderactivityModel->where ( $where )->delete ();
        $ordergoodsform_activity_array = $orderform_array ['orderactivity'];

        foreach ( $ordergoodsform_activity_array as $activity_value ) {
            $data = array ();
            $data ['orderactivity_id'] = $activity_value ['orderactivityid'];
            $data ['order_id'] = $activity_value ['orderformid'];
            $data ['name']     = $activity_value ['name'];
            $data ['price']    = $activity_value ['price'];
            $data ['num']      = $activity_value ['number'];
            $data ['sn']       = $activity_value ['ordersn'];
            $data ['area']     = $this->getArea($shopid);

            $orderactivityModel->create ();
            $orderactivityModel->add ( $data );
            \Think\Log::write('插入活动表:'.$orderactivityModel->getLastSql(), \Think\Log::INFO, '', $this->logFile);

        }
    }

    //保存支付表
    function saveOrderPayment($orderform_array,$shopid){
        $where = array ();
        $where ['order_id'] = $orderform_array ['orderformid'];

        $connect_str = C('ASSISTANT_CONNECTSTR');
        $orderpaymentModel = M('order_payment', 'sp_', $connect_str);
        $orderpaymentModel->where ( $where )->delete ();
        $ordergoodsform_payment_array = $orderform_array ['orderpayment'];

        foreach ( $ordergoodsform_payment_array as $payment_value ) {
            $data = array ();
            $data ['orderactivity_id'] = $payment_value ['orderactivityid'];
            $data ['order_id'] = $payment_value ['orderformid'];
            $data ['name']     = $payment_value ['name'];
            $data ['price']    = $payment_value ['price'];
            $data ['num']      = $payment_value ['number'];
            $data ['sn']       = $payment_value ['ordersn'];
            $data ['area']     = $this->getArea($shopid);

            $orderpaymentModel->create ();
            $orderpaymentModel->add ( $data );
            \Think\Log::write('插入支付表:'.$orderpaymentModel->getLastSql(), \Think\Log::INFO, '', $this->logFile);
        }
    }



    /**
     * 获取常州消息
     */
    public function getCzMsg()
    {
        $this->logFile = C('LOG_PATH') . 'czMsgAssis-' . date('y_m_d') . '.log';
        \Think\Log::write('开始获取常州的数据', \Think\Log::INFO, '', $this->logFile);
        while(1){
            $this->getMsg(4);
            usleep(3000000);
        }

    }

    /**
     * 获取南京
     */
    public function getNjMsg(){
        $this->getMsg(6);
    }

    /**
     * 获取广州的消息
     */
    public function getGzMsg()
    {
        $this->getMsg(9);
    }

    /**
     * 获取消息
     * @param $shopid
     */
    function getMsg($shopid)
    {
        $connect_str = C('ASSISTANT_CONNECTSTR');
        $ordermsg_model = M('order_msg', 'sp_', $connect_str);
        $url = 'http://nj.lihuaerp.com/index.php/InterfaceServer/assisGetMsg/token/lihua1/domain/' . $shopid;
        $result = curl($url);
        \Think\Log::write('获取数据'.$result, \Think\Log::INFO, '', $this->logFile);
        if (empty($result)) {
            return;
        }
        $smsmgr = json_decode($result, true);
        foreach ($smsmgr as $value) {
            $data = array();
            $data['msg_id'] = $value['smsmgrid'];
            $data['content'] = $value['content'];
            $data['add_time'] = date('Y-m-d H:i:s');
            $data['name'] = $value['sendname'];
            $data['company'] = $value['company'];
            $data['area'] = $this->getArea($shopid);
            $ordermsg_model->create();
            $ordermsg_model->add($data);
            \Think\Log::write('SQL语句:'.$ordermsg_model->getLastSql(), \Think\Log::INFO, '', $this->logFile);
            //确认消息
            $this->setMsg($value['smsmgrid']);
        }

    }

    /**
     * 确认消息
     */
    function setMsg($smsmgrid)
    {
        $url = 'http://nj.lihuaerp.com/index.php/InterfaceServer/assisSetMsg/token/lihua1/smsmgrid/' . $smsmgrid;
        \Think\Log::write('确认:'.$url, \Think\Log::INFO, '', $this->logFile);
        $result = curl($url);
        \Think\Log::write('确认结果:'.$result, \Think\Log::INFO, '', $this->logFile);
    }

    /**
     * 获得地区
     */
    function getArea($shopid)
    {
        switch ($shopid) {
            case 4:
                return '常州';
                break;
            case 6:
                return '南京';
                break;
            case 9:
                return '广州';
                break;

        }
    }
}