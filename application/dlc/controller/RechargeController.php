<?php
/**
 * Created by PhpStorm.
 * User: 韩令恺
 * Date: 2018/5/24 0024
 * Time: 22:15
 */

namespace app\dlc\controller;
use Think\Db;

class RechargeController extends BaseController
{
    public $url = '';
    public $authStatus = array(0 => '未认证', 1 => '已认证');
    public $userType = array(1=>'小程序用户',2=>'生活号用户');
    public $payType = array(1=>'微信',2=>'支付宝');

    public function _initialize()
    {
        parent::_initialize();
        // TODO: Change the autogenerated stub
        $this->url = request()->root(true) . '/public/';
    }



    //充值列表
    public function index(){

        $condition['a.status'] = 2;
        $pagesize = 20;
        $page = input('page')?input('page'):1;
        $nickname  = trim(input('nickname'));
        $mobile= trim(input('mobile'));
        if ($nickname){
            $condition['b.nickname'] = ['like','%'.$nickname.'%'];
            $this->assign('nickname',$nickname);
        }

        if ($mobile){
            $condition['b.mobile'] = ['like','%'.$mobile.'%'];
            $this->assign('mobile',$mobile);
        }

        if ($_GET['user_type']){
            $condition['b.user_type'] = $_GET['user_type'];
            $this->assign('userType',$_GET['user_type']);
        }

        $result = Db::name('recharge')->alias('a')->join('user b','a.user_id=b.user_id','LEFT')->where($condition)
                            ->field('a.*,b.nickname,b.mobile,b.user_type')->page($page,$pagesize)->select();

        foreach ($result as $k=>$v){
            $result[$k]['userType'] = $this->userType[$v['user_type']];
            if (!$result[$k]['userType']) $result[$k]['userType'] = '未获取到用户来源';
            $result[$k]['nickname'] = $v['nickname']?$v['nickname']:'未设置昵称';
            $result[$k]['mobile'] = $v['mobile']?$v['mobile']:'未绑定手机';
            $result[$k]['payType'] = $this->payType[$v['pay_type']];
            if ($v['pay_succeed']){
                $result[$k]['pay_succeed'] = date('Y-m-d H:i:s');
            }else{
                $result[$k]['pay_succeed'] = '未获取到支付时间';
            }
        }
        $count = Db::name('recharge')->alias('a')->join('user b','a.user_id=b.user_id')->where($condition)->count();
        $this->getPage($count, $pagesize, 'App-loader', '商品类型列表', 'App-search');
        $this->assign('result',$result);
        $this->assign('user_type',$this->userType);
        echo $this->fetch();
    }


    //充值详情
    public function detail(){
        $recharge_id = input('recharge_id');
        if (!$recharge_id){
            $info['status'] = 0;
            $info['msg'] = 'ID不能为空!';
            return($info);
        }

        $result = model('recharge')->get_row(['id'=>$recharge_id]);
        $this->assign('result',$result);
        echo $this->fetch();
    }


    public function exportSelect(){
        // $device = M('device')->field('device_id,title')->order('device_id desc')->select();
        // $user    = M('user')->field('user_id,nickname')->where(array('nickname'=>array('NEQ',"")))->order('user_id desc')->select();
        // $this->assign('device', $device);
        echo $this->fetch('exportSelect');
    }

    public function exportexcel()
    {
        header("Content-type:application/octet-stream");
        header("Accept-Ranges:bytes");
        header("Content-type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename=充值列表.xls");
        header("Pragma: no-cache");
        header("Expires: 0");


        $condition['a.status'] = 2;
        if (input('nickname')){
            $condition['b.nickname'] = ['like','%'.input('nickname').'%'];
        }
        if (input('mobile')){
            $condition['b.mobile'] = ['lick','%'.input('mobile').'%'];
        }
        if (input('user_type')){
            $condition['b.user_type'] = input('user_type');
        }
        $title = ['充值编号','用户昵称','用户手机','用户类型','支付方式','充值金额','支付时间'];
        $field = ['a.order_number','b.nickname','b.mobile','b.user_type','a.pay_type','a.money','a.pay_succeed'];
        $data = Db::name('recharge')->alias('a')->join('user b','a.user_id=b.user_id')->where($condition)->field($field)->select();
        foreach ($data as $k=>$v){
            $data[$k]['nickname'] = $v['nickname']?$v['nickname']:'未设置昵称';
            $data[$k]['mobile'] = $v['mobile']?$v['mobile']:'未绑定手机';
            $data[$k]['user_type'] = $this->userType[$v['user_type']];
            $data[$k]['pay_type'] = $this->payType[$v['pay_type']];
            $data[$k]['pay_succeed'] = date('Y-m-d H:i:s',$v['pay_succeed']);
        }


        //导出xls 开始
        if (!empty($title)) {
            foreach ($title as $k => $v) {
                $title[$k] = iconv("UTF-8", "GB2312", $v);
            }
            $title = implode("\t", $title);
            echo "$title\n";
        }
        if (!empty($data)) {
            foreach ($data as $key => $val) {
                foreach ($val as $ck => $cv) {
                    $data[$key][$ck] = (string)(' '.(string)iconv("UTF-8", "GB2312", $cv).' ');
                }
                $data[$key] = (implode("\t", $data[$key]));
            }
            echo implode("\n", $data);
        }

    }


}