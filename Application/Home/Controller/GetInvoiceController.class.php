<?php
/**
 * Created by IntelliJ IDEA.
 * User: apple
 * Date: 16/4/19
 * Time: 下午5:08
 * 送旧的发票导入发票数据
 * demo:/Applications/XAMPP/xamppfiles/bin/php  /Applications/XAMPP/htdocs/rmsdaemon/index.php Home/GetInvoice/bjGetInvoice
 */

namespace Home\Controller;
use Think\Controller;

class GetInvoiceController extends Controller{
    /**
     * 从北京导入
     */
    public function bjGetInvoice(){
        $url = "http://fapiao.lihua.com/getInvoice.php";
        $resp = curl_get($url);

        //转换成数组
        $fapiao = json_decode($resp,true);

        $connect_str = C('RMS_CONNECTSTR');
        //发票表
        $invoice_model = M('invoice', 'rms_', $connect_str);
        //保存发票数据
        foreach($fapiao as $value){

            $data = $value;
            //var_dump($data);
            $invoice_model->create();
            $invoice_model->add($data);

            //确认发票已经获取
            $this->bjSetInvoice($data['ordersn']);
        }
    }

    /**
     * 确认收到北京的发票数据
     */
    private function bjSetInvoice($ordersn){
        $url = "http://fapiao.lihua.com/setInvoice.php?ordersn=".$ordersn;
        var_dump($url);
        $resp = curl_get($url);
    }
}