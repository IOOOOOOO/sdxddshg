<?php
namespace app\shop\controller;
/**
 * 马远利
 */
use think\model;
use think\Db;
use think\Session;
class OrderController extends BaseController
{
    public $orderStatus = array(1=>'开门中',2=>'未付款',3=>'已付款');
    public $payType = array(1=>'微信',2=>'支付宝',3=>'余额');
    public $id;
    public $type;
    public function _initialize()
    {
        $this->type = Session::get('MMS.type');//子后台登录帐号类型（1：商家 2：员工）
        $aid=Session::get('MMS.uid');
        if($this->type == 1){
            $this->id=Db::name('shop')->where(['aid'=>$aid])->value('shop_id');//商家ID
        }else{
            $this->id = $aid;//员工ID
        }
        $this->assign('type',$this->type);
    }

    //订单管理
	public function index()
    {
       $type = input('type')? 2 : 1;
       $pages= input('p');

       if($type == 1){
            $bread = array(
                '0' => array(
                    'name' => '订单管理',
                    'url' => url('shop/Order/index'), 
                ), 
                '1' => array(
                    'name' => '订单列表',
                    'url' => url('shop/Order/index'), 
                ), 
            );
            $name = '订单列表';
            $shop_id= input('shop_id');
            if($shop_id){
                $map['a.shop_id'] = $shop_id;
                $this->assign('shop_id',$shop_id);
            }
       }else{
             $bread = array(
                '0' => array(
                    'name' => '帐号管理',
                    'url' => url('shop/User/index'), 
                ), 
                '1' => array(
                    'name' => '商家列表' ,
                    'url' => url('shop/User/shop'), 
                ), 
                '2' => array(
                    'name' => '商家订单列表',
                    'url' => url('shop/Order/index'), 
                ), 
            );
            $map['a.shop_id'] = input('id');
            $name = '商家订单列表';
       }
      
        $this->assign('breadhtml', $this->getBread($bread));
        $order_number  = trim(input('order_number'));
        $macno  = trim(input('macno'));
        $username= trim(input('username'));
        $status = input('status');
        $pay_type= input('pay_type');
        $page   = input('page')? : 1;
        $size   = self::$MMS['set']['pagesize']?:20;
        $map    = [];
        if($order_number){
            $map['a.order_number'] = ['like','%'.$order_number.'%'];
            $this->assign('order_number',$order_number);
        }
        if($username){
            $map['e.nickname'] = ['like','%'.$username.'%'];
            $this->assign('username',$username);
        }
        if($macno){
            $map['d.macno'] = ['like','%'.$macno.'%'];
            $this->assign('macno',$macno);
        }
        if($pay_type){
            $map['a.pay_type'] = $pay_type;
            $this->assign('pay_type',$pay_type);
        }
        if($status){
            $map['a.status'] = $status;
            $this->assign('status',$status);
        }
        $join = [['network b','b.network_id = a.network_id','left'],
                 ['shop c','c.shop_id=a.shop_id','left'],
                 ['device d','d.device_id=a.device_id','left'],
                 ['user e','e.user_id=a.user_id','left']
                ];
        $field= ['a.*,b.address,c.user_name,d.macno,e.nickname,d.title'];
        $order= 'a.ctime desc';

        if($this->type==1){
            $map['a.shop_id']=$this->id;
        }else{
            $device_id = Db::name('device')->where(['user_id'=>$this->id])->column('device_id');
            $device_ids = implode(',',$device_id);
            $map['a.device_id'] = ['in',$device_ids];
        }
//        $map['a.num']=['<>',0];
		$result = Db::name('order')->alias('a')
                ->join($join)->where($map)
                ->order($order)->page($page,$size)
                ->field($field)->select();

        $count = Db::name('order')->alias('a')
                ->join($join)->where($map)
                ->count();
        $this->getPage($count, $size, 'App-loader', $name, 'App-search');
        $this->assign('empty','<tr><td colspan="16" style="line-height:32px;text-align:center;">暂无数据！</td></tr>');
        $this->assign('type',$type);
        $this->assign('types',$this->type);
        $this->assign('result',$result);
        $this->assign('pages',$pages);
		echo $this->fetch('');
    }

    //订单详情
    public function info()
    {
       $type = input('type');
       $pages= input('pagess');
       if($type == 1){
            $bread = array(
                '0' => array(
                    'name' => '订单管理',
                    'url' => url('shop/Order/index'),
                ),
                '1' => array(
                    'name' => '订单详情',
                    'url' => url('shop/Order/info'),
                ),
            );
       }else{
             $bread = array(
                '0' => array(
                    'name' => '帐号管理',
                    'url' => url('shop/User/index'),
                ),
                '1' => array(
                    'name' => '商家列表' ,
                    'url' => url('shop/User/shop'),
                ),
                '2' => array(
                    'name' => '商家订单列表',
                    'url' => url('shop/Order/index'),
                ),
                '3' => array(
                    'name' => '商家订单详情',
                    'url' => url('shop/Order/info'),
                ),
            );
            $shop_id = input('id');
            $this->assign('id',$shop_id);
       }

        $this->assign('breadhtml', $this->getBread($bread));
        $id = input('order_id');
        $p= input('p');
        $result = Db::name('order')->alias('a')
                 ->join('user b','b.user_id=a.user_id','left')
                 ->join('device c','c.device_id=a.device_id','left')
                 ->join('shop d','d.shop_id=a.shop_id','left')
                 ->join('network e','e.network_id=a.network_id','left')
                 ->where(['a.order_id'=>$id])
                 ->field('a.*,b.username,c.macno,d.user_name,e.title,e.address')
                 ->find();
        $goods = DB::name('order_info')->alias('a')
                 ->join('goods b','b.goods_id=a.goods_id','left')
                 ->field('a.*,b.title,b.img,b.cost,b.price as s_price')->where('order_id',$result['order_id'])->select();
        $result['goods'] = $goods;
        $this->assign('result',$result);
        $this->assign('p',$p);
        echo $this->fetch('');
    }

    //设备新增或修改
    public function set()
    {
        $id = input('device_id');
        $p  = input('p');
        $bread = array(
            '0' => array(
                'name' => '设备管理',
                'url' => url('shop/Device/index'), 
            ), 
            '1' => array(
                'name' => $id ? '设备编辑' : '设备列表',
                'url' => url('shop/Device/index?device_id='.$id), 
            ), 
        );
        $this->assign('breadhtml', $this->getBread($bread));
        if($_POST){
            $post = input('post.');
            if($id){
                unset($post['device_id']);
                $post['qrcode'] = 'http://' . $_SERVER['HTTP_HOST'] . '/wxsite/order/staffRQCode?macno='.$post['macno'];
                $res = Db::name('device')->where(['device_id'=>$id])->update($post);
            }else{
                $post['qrcode'] = 'http://' . $_SERVER['HTTP_HOST'] . '/wxsite/order/staffRQCode?macno='.$post['macno'];
                $post['ctime'] = time();
                $res = Db::name('device')->insert($post);
            }
            if (FALSE !== $res) {
                $info['status'] = 1;
                $info['msg'] = '设置成功！';
            } else {
                $info['status'] = 0;
                $info['msg'] = '设置失败！';
            }
            return($info);
        }
        $shop = Db::name('shop')->select();
        $result = Db::name('device')->alias('a')
                ->join('shop_shop b ','b.shop_id=a.shop_id','left')
                ->join('shop_network c','c.network_id = a.network_id')
                ->where(['a.device_id'=>$id])
                ->field('a.*,c.title as network_name')->find();
                // var_dump($result);
                //  var_dump($id);
        $this->assign('result',$result);
        $this->assign('device_id',$id);
        $this->assign('p',$p);
        $this->assign('shop',$shop);
        $this->assign('type',$type);
        $this->assign('id',$id);
        $this->assign('pages',$pagess);
        echo $this->fetch('set'); 
    }   
	
    //设备删除
    public function del()
    {
        $id = input('device_id');
        if(empty($id)){
            $info['status'] = 0;
            $info['msg']    = 'ID不能为空';
            return($info);
        }
        $res1 = Db::name('device')->where(['device_id'=>['in',$id]])->delete();
        $res2 = Db::name('device_goods')->where(['device_id'=>['in',$id]])->delete();
        if($res1 !== false && $res2 !== false){
            $info['status'] = 1;
            $info['msg']    = '删除设备成功';
        }else{
            $info['status'] = 0;
            $info['msg']    = '删除设备失败';
        }
        return($info);
    }


    //设备所属商品列表
    public function goods()
    {
        $id = input('device_id');
        $p  = input('page');
        $bread = array(
            '0' => array(
                'name' => '设备管理',
                'url' => url('shop/Device/index'), 
            ), 
            '1' => array(
                'name' => '所属商品',
                'url' => url('shop/Device/goods'), 
            ), 
        );
        $page = input('p') ? : 1;
        $size = 20;
        $title= input('title');
        if($title){
            $map['c.title'] = ['like','%'.$title.'%'];
            $this->assign('title',$title);
        }
        $this->assign('breadhtml', $this->getBread($bread));
        $map['a.device_id'] = $id;
        $result = Db::name('device_goods')->alias('a')
                  ->join('shop_goods c','c.goods_id=a.goods_id','left')
                  ->where($map)
                  ->field('a.*,c.img,c.title')
                  ->page($page,$size)->select();
        $count = Db::name('device_goods')->alias('a')
                  ->join('shop_goods c','c.goods_id=a.goods_id','left')
                  ->where($map)->count();
        $shop = Db::name('shop')->select();
        $this->getPage($count, $size, 'App-loader', '所属商品', 'App-search');
        $this->assign('empty','<tr><td colspan="9" style="line-height:32px;text-align:center;">暂无数据！</td></tr>');
        $this->assign('result',$result);
        $this->assign('pages',$p);
        $this->assign('device_id',$id);
        echo $this->fetch();
    }


    //修改设备商品单价
    public function updateGoods()
    {
        $id = input('device_id');
        $p  = input('page');
        $device_goods_id = input('device_goods_id');
        if(empty($device_goods_id)){
            $info['status'] = 0;
            $info['msg']    = 'ID不能为空';
            return($info);
        }
        $bread = array(
            '0' => array(
                'name' => '设备管理',
                'url' => url('shop/Device/index'), 
            ), 
            '1' => array(
                'name' => '编辑设备商品单价',
                'url' => url('shop/Device/updateGoods'), 
            ), 
        );
        $this->assign('breadhtml', $this->getBread($bread));
        if($_POST){
            $post = input('post.');
            $res = Db::name('device_goods')->where('device_goods_id',$device_goods_id)->update(['price'=>$post['price']]);
            if($res !== false ){
                $info['status'] = 1;
                $info['msg']    = '编辑设备商品单价成功';
            }else{
                $info['status'] = 0;
                $info['msg']    = '编辑设备商品单价失败';
            }
            return($info);
        }
        $result = Db::name('device_goods')->where('device_goods_id',$device_goods_id)->find();
        $this->assign('result',$result);
        $this->assign('pages',$p);
        $this->assign('device_id',$id);
        $this->assign('device_goods_id',$device_goods_id);
        echo $this->fetch('updateGoods');
    }

    //设备订单列表
    public function order()
    {
        $id = input('device_id');
        $p  = input('page');
        if(empty($id)){
            $info['status'] = 0;
            $info['msg']    = 'ID不能为空';
            return($info);
        }
        $bread = array(
            '0' => array(
                'name' => '设备管理',
                'url' => url('shop/Device/index'), 
            ), 
            '1' => array(
                'name' => '设备订单列表',
                'url' => url('shop/Device/updateGoods'), 
            ), 
        );
        $this->assign('breadhtml', $this->getBread($bread));
        $page         = input('p')? : 1;
        $username     = input('username');
        $order_number = input('order_number');
        $pay_type     = input('pay_type');
        $status       = input('status');
        $size = 20;
        if($username){
            $map['a.username'] = ['like','%'.$username.'%'];
            $this->assign('username',$username);
        }
        if($order_number){
            $map['a.order_number'] = ['like','%'.$order_number.'%'];
            $this->assign('order_number',$order_number);
        }
        if($pay_type){
            $map['a.pay_type'] = $pay_type;
            $this->assign('pay_type',$pay_type);
        }
        if($status){
            $map['a.status'] = $status;
            $this->assign('status',$status);
        }
        $map['a.device_id'] = $id;
        $result = Db::name('order')->alias('a')
                 ->join('shop_device b','b.device_id=a.device_id','left')
                 ->join('shop_user c','c.user_id=a.user_id','left')
                 ->field('a.*,b.macno,c.username')
                 ->where($map)->page($page,$size)->order('a.status desc')->select();
        $count = Db::name('order')->alias('a')
                 ->join('shop_device b','b.device_id=a.device_id','left')
                 ->join('shop_user c','c.user_id=a.user_id','left')
                 ->where($map)->count();
        foreach ($result as $key => $value) {
            $goods = Db::name('order_info')->alias('a')
                    ->join('shop_goods b','b.goods_id=a.goods_id','left')
                    ->where(['order_id'=>$value['order_id']])->field('a.*,b.title')->select();
            $result[$key]['goods'] = $goods;
        }
        $this->getPage($count, $size, 'App-loader', '设备订单列表', 'App-search');
        $this->assign('empty','<tr><td colspan="15" style="line-height:32px;text-align:center;">暂无数据！</td></tr>');
        $this->assign('result',$result);
        $this->assign('pages',$p);
        $this->assign('device_id',$id);
        echo $this->fetch();
    }

    //获取网点
    public function networks()
    {   
        $id = input('id');
        if(empty($id)){
            $info['status'] = 0;
            $info['msg']    = 'ID不能为空';
           $this->ajaxReturn($info);
        }
        $network = Db::name('network')->where(['shop_id'=>$id])->select();
        return($network);
         echo $this->fetch('set'); 
    }


    public function exportSelect(){
        // $device = M('device')->field('device_id,title')->order('device_id desc')->select();
        // $user    = M('user')->field('user_id,nickname')->where(array('nickname'=>array('NEQ',"")))->order('user_id desc')->select();
        // $this->assign('device', $device);
        echo $this->fetch('exportSelect');
    }


    public function orderExport(){
        $order_number  = input('order_number');
        $macno  = input('macno');
        $username= input('username');
        $status = input('status');
        $pay_type= input('pay_type');
        $map    = [];
        if($order_number){
            $map['a.order_number'] = ['like','%'.$order_number.'%'];
            $this->assign('order_number',$order_number);
        }
        if($username){
            $map['e.username'] = ['like','%'.$username.'%'];
            $this->assign('username',$username);
        }
        if($macno){
            $map['d.macno'] = ['like','%'.$macno.'%'];
            $this->assign('macno',$macno);
        }
        if($pay_type){
            $map['a.pay_type'] = $pay_type;
            $this->assign('pay_type',$pay_type);
        }
        if($status){
            $map['a.status'] = $status;
            $this->assign('status',$status);
        }
        if($this->type==1){
            $map['a.shop_id']=$this->id;
        }else{
            $map['a.user_id']=$this->id;
        }
        $join = [['network b','b.network_id = a.network_id','left'],
            ['shop c','c.shop_id=a.shop_id','left'],
            ['device d','d.device_id=a.device_id','left'],
            ['user e','e.user_id=a.user_id','left']
        ];
        $field= ['a.order_id,a.order_number,d.macno,e.nickname,c.user_name,a.total_price,a.pay_price,a.discount_money,a.num,a.status,a.pay_type,a.pay_time,a.ctime'];
        $result = Db::name('order')->alias('a')
            ->join($join)->where($map)
            ->field($field)->select();
        foreach ($result as $k=>$v){
            if (!$v['nickname']) $result[$k]['nickname'] = '此用户已被后台删除';
            if (!$v['discount_money']) $result[$k]['discount_money'] = 0;
            $result[$k]['status'] = $this->orderStatus[$v['status']];
            $result[$k]['pay_type'] = $this->payType[$v['pay_type']];
            if ($v['pay_time']){
                $result[$k]['pay_time'] = date('Y-m-d H:i:s',$v['pay_time']);
            }else{
                $result[$k]['pay_time'] = '暂无';
            }
            $result[$k]['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
        }

        $Field = ['id','订单编号','设备编号','用户昵称','所属商家','订单总价','支付金额','订单优惠','购买数量','订单状态','支付类型','支付时间','创建时间'];
        $this ->exportexcel($result,$Field,'订单列表');
    }



    /**
     * 导出数据为excel表格
     * @param $data    一个二维数组,结构如同从数据库查出来的数组
     * @param $title   excel的第一行标题,一个数组,如果为空则没有标题
     * @param $filename 下载的文件名
     * @examlpe
     * $stu = M ('User');
     * $arr = $stu -> select();
     * exportexcel($arr,array('id','账户','密码','昵称'),'文件名!');
     */
    private function exportexcel($data = array(), $title = array(), $filename = 'report')
    {
        header("Content-type:application/octet-stream");
        header("Accept-Ranges:bytes");
        header("Content-type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename=" . $filename . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
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