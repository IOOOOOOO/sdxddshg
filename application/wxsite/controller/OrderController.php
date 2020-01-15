<?php
namespace app\wxsite\controller;
/** 
 * 马远利
 */

use \think\Loader;
use \think\Db;
use \think\Model;
// use \QRCode;

use app\apk\controller\IndexController; 

class OrderController extends BaseController
{
    public $appUrl;
    public $user_id;
    public function _initialize()
    {

        // $this->appUrl = request()->root(true);
        $this->appUrl = 'http://'.$_SERVER['HTTP_HOST'].'/';
    }

    /**
     *接口入口
     */
    public function api()
    {
        $api_name = input('api_name');

        if ($api_name) {
            switch ($api_name) {
                case 'getNetwork':
                    $this->getNetwork();     //获取10公里范围内定位的网点列表
                    break;
                case 'getNetworkDevice':
                    $this->getNetworkDevice();//获取网点设备列表
                    break;
                case 'getDeviceGoods':
                    $this->getDeviceGoods();  //获取设备商品列表
                    break;
                case 'userOpenDoor':

                    $this->userOpenDoor();    //用户开门 
                    break;
                case 'payOrder':
                    $this->payOrder();        //订单提交支付
                    break;
                default:
                    $info['code'] = 403;
                    $info['msg'] = '接口不存在';
                    return $info;
                    break;
            }
        }else{

//            $info['code'] = 404;
//            $info['msg'] = '接口不能为空';
//            $info['data'] = $api_name;
            $this->_return(404,'接口名不能为空',$api_name);
        }

    }

    /**
     * 获取10公里范围内定位的网点列表
         * @param  float   $lat     | 纬度
     * @param  float   $lng     | 经度
     * @param  string  $token   | 用户的唯一标识 
     * @param  int     $page    | 分页，默认为 1
     * @param  int     $pagesize| 每页条数，默认为10
     * @return array 
     */
    public function getNetwork()
    {
        $lat      = input('lat');
        $lng      = input('lng');
        $page     = input('page')?:1;
        $pagesize = input('pagesize')?:10;
        $user_id  = $this->get_user_id();

        if(empty($lat)) $this->_return(-1,'当前纬度不能为空',(object)array());
        if(empty($lng)) $this->_return(-1,'当前经度不能为空',(object)array());
       
        vendor("Gps.Gps");
        $gps = new \Gps();
        // 转换成百度坐标进行对比
        $lat_lng = $gps->bd_encrypt($lat,$lng);
        $lat = $lat_lng['lat'];
        $lng = $lat_lng['lon'];
        $sql = Db::name('network')->field("network_id,title,lat,lng,address,ROUND(6378.138 * 2 * ASIN(SQRT(POW(SIN(($lat * PI() / 180 - lat * PI() / 180) / 2),2) + COS($lat * PI() / 180) * COS(lat * PI() / 180) * POW(SIN(($lng * PI() / 180 - lng * PI() / 180) / 2 ),2 ))) ) as calc_range")->buildSql();
        $list = Db::name('network')->table($sql.' a')->where('calc_range < 10')->order(' calc_range asc')->page($page,$pagesize)->select();
        if($list){
            foreach ($list as $k => $v) {
                // 百度坐标转为高德坐标
                $degree = $gps->bd_decrypt($v['lat'],$v['lng']);
                $list[$k]['lat'] = $degree['lat'];
                $list[$k]['lng'] = $degree['lon'];
            }
        }
        $this->_return(1,'获取成功',$list);
    }

     /**
     * 获取网点设备列表
     * @param  int     $network_id      | 网点ID
     * @param  string  $token           | 用户的唯一标识 
     * @param  int     $page            | 分页，默认为 1
     * @param  int     $pagesize        | 每页条数，默认为10
     * @return array 
     */
    public function getNetworkDevice()
    {
        $page       = input('page')?:1;
        $pagesize   = input('pagesize')?:10;
        $network_id = input('network_id');
        $user_id = $this->get_user_id();
        if(empty($network_id)) $this->_return(-1,'网点ID不能为空',(object)array());
        $field= ['device_id','network_id','title','macno'];
        $list = Db::name('device')->where(['network_id'=>$network_id])->field($field)->order('ctime desc')->page($page,$pagesize)->select();
        if(empty($list)){
            $this->_return(1,'获取成功',$list);
        }
        foreach ($list as $key => $value) {
           $status = Db::name('favorite')->where(['device_id'=>$value['device_id']])->find();
           $list[$key]['status'] = $status ? 1:0;
           $list[$key]['favorite_id'] = $status ? $status['id']: 0;
        }
        $this->_return(1,'获取成功',$list);
    }


    /**
     * 获取设备商品列表
     * @param  string  $macno           | 设备编号
     * @param  string  $token           | 用户的唯一标识 
     * @param  int     $page            | 分页，默认为 1
     * @param  int     $pagesize        | 每页条数，默认为10
     * @return array 
     */
    public function getDeviceGoods()
    {
        $page     = input('page')?:1;
        $pagesize = input('pagesize')?:10;
        $macno    = input('macno');
        $user_id = $this->get_user_id();
        if(empty($macno)) $this->_return(-1,'设备编号不能为空',(object)array());
        //检查设备是否存在
        $device = Db::name('device')->where(['macno'=>$macno])->find();
        if(empty($device)) $this->_return(-1,'设备不存在',(object)array());
        $map['a.device_id'] = $device['device_id'];
        $map['a.inventory'] = array('<>',0);
        $field = ['a.device_goods_id','a.device_id','a.goods_id','b.title','b.img','a.price'];
        $list = Db::name('device_goods')->alias('a')
                ->join('dlc_goods b','b.goods_id=a.goods_id')
                ->where($map)
                ->field($field)
                ->order('a.ctime desc')
                ->page($page,$pagesize)
                ->select();
//        print_r( Db::name('device_goods')->alias('a')->getLastSql());
        if(empty($list)) $this->_return(1,'获取成功');
        foreach ($list as $key => $value) {
            $list[$key]['img'] = $this->appUrl.'public/'.$value['img'];
        }
        $this->_return(1,'获取成功1',$list);
    }

    /**
     * 用户开门
     * @param  string  $macno           | 设备编号
     * @param  string  $token           | 用户的唯一标识
     * @param  int     $type            | 开门方式（1：小程序 2：公众号）
     * @return array
     */

    public function userOpenDoor()
    {
        $mqtt = new IndexController();
        $macno   = input('macno');
        $type    = input('type');
        $user_id = $this->get_user_id();
        write_log('userOpenDoor',"用户: ".$user_id." H5用户购买开始请求开门: 设备号---".$macno.'时间----'.date('Y-m-d H:i:s:u',time()));
        if(empty($macno)) $this->_return(-1,'设备编号不能为空',(object)array());
        if(empty($type)) $this->_return(-1,'开门方式不能为空',(object)array());
//        $mobile= db('user')->where('user_id',$user_id)->value('mobile');
//        if(empty($mobile)){
//            $this->_return(99,'未绑定手机号，请先绑定手机号',(object)array());
//        }
        //检查设备是否存在
        Db::startTrans();
        $device = Db::name('device')->lock(true)->where(['macno'=>$macno])->find();

        if(empty($device)) $this->_return(-1,'设备不存在',(object)array());
        if ($device['htime']+60<time())$this->_return(-1,'设备不在线',(object)array());
        if($device['status'] == 1)$this->_return(-1,'设备异常，请先检测！',(object)array());
        if ($device['doorstatus']==1){//未使用
            Db::commit();// 提交事务
            $this->_return(-1,'设备正在使用中...',(object)array());
        }
        //判断用户是否被禁用
        $user = Db::name('user')->where(['user_id'=>$user_id])->find();
//        if(empty($user['mobile'])){
//            _return(110,'你还没有绑定手机号',(object)array());
//        }
        if($user['status'] == 0)_return(-1,'你已被平台禁用，请尽快联系客服！',(object)array());
		$info = [];
        //判断用户是否开通微信免密
//        if ($type == 1 ) {
//            import("WxpaymentController");
//            $wxpayment = new WxpaymentController;
//            $user = Db::name('user')->where(array('user_id'=>$user_id))->find();
//            $check_contract = $wxpayment->querycontract($user['openid']);
//            write_log('wxpayment',"查询是否开通免密支付：".json_encode($check_contract));
//            if($check_contract){
//                $this->_return(88,'您还未开通微信免密支付');
//            }
//        }
        import("WxpaymentController");
        $wxpayment = new WxpaymentController;
//        $find= db('shop_wxset')->where('shop_id',$device['shop_id'])->find();
//        $wxdata=[];
//        if($find){
//            $wxdata['appid']=$find['wxappid'];
//            $wxdata['appsecret']=$find['wxappsecret'];
//            $wxdata['mchid']=$find['wxmchid'];
//            $wxdata['mchsecret']=$find['wxmchkey'];
//            $wxdata['plan_id']=$find['wxplanid'];
//            $wxpayment->loadWxpayConfig($wxdata);
//        }
           $wxpayment->loadWxpayConfig();
        $user = Db::name('user')->where(array('user_id'=>$user_id))->find();
        $check_contract = $wxpayment->querycontract($user['openid']);
        write_log('wxpayment',"查询是否开通免密支付1111".json_encode($check_contract));
        if($check_contract){
            $contract_code = mt_rand(100000,999999).$user_id;
            $request_serial = 10000+$user_id;
            $info['url'] = $wxpayment->contractorder($contract_code,$request_serial,$device['shop_id']);
            write_log('OpenDoor',"您还未开通微信免密支付: ");
            $this->_return(88,'您还未开通微信免密支付',$info);
        }

        //先检查是否有不是当用户的在开门中的订单,有的话就删除

//        $deleteMap['user_id']   = ['neq',$user_id];      //用户ID
//        $deleteMap['status']    = 1;                     //订单状态
//        // $deleteMap['device_id'] = $device['device_id'];  //设备ID
//        $deleteOrder = Db::name('order')->where($deleteOrder)->select();
//        if($deleteOrder){
//            Db::name('order')->where($deleteMap)->update(array('status'=>-1));
//        }
        //删除掉用户空的订单
        db('order')->where(array('user_id'=>$user_id,'num'=>0))->update(array('status'=>-1,'reason'=>'空订单'));

        $findorder = Db::name('order')->where(array('device_id'=>$device['device_id'],'status'=>1))->find();
        write_log("order","正在开门中订单:".json_encode($findorder,true));
        //判断设备状态
        if($findorder){
            if($findorder['user_id'] == $user_id){
                $info['order_id'] = $findorder['order_id'];//订单ID
                $info['oid']      = $findorder['order_number'];//订单编号
                $this-> _return(4,'你还有正在进行中的订单',$info);
                /*write_log("result",json_encode($result));
                if($result['code'] == 0){
                    _return(-1,$result['msg'],(object)array());
                }else{
                    if($result['status'] == 1){
                        _return(-1,"设备正在使用，请稍后再购买",(object)array());
                    }elseif($result['status'] == 2){ //处理设备开关门订单状态
                        $update_device['doorstatus']  = 0;
                        $update_device['open_status']  = 3;
                        Db::name("device")->where(array('macno'=>$macno))->update($update_device);
                        Db::name("order")->where(array('order_number'=>$order['order_number']))->update(array('status'=>-1));
                    }
                }*/
            }else{
                if($findorder['status'] == 2){//处理设备开关门订单状态
                    $update_device['doorstatus']  = 0;
                    $update_device['open_status']  = 3;
                    Db::name("device")->where(array('macno'=>$macno))->update($update_device);
                }
                //Db::name("order")->where(array('order_number'=>$order['order_number']))->update(array('status'=>-1));*/

                if( $findorder['status'] == 1&&$findorder['num']!=0){
                    $list['type']=0;//0是开门 1 是关门
                    $mqtt->notifyH5(-1,'还有用户等待结算，请稍后再购买！',"sdxddshg_close_".$user_id,$list,$findorder['order_number']);

//                    $mqtt->notifyH5Message(-1,'还有用户等待结算，请稍后再购买！',"sdxddshg_".$user_id,'');
                    $this->  _return(-1,'还有用户等待结算，请稍后再购买！',(object)array());
                }
            }
        }


        //先检查用户是否有未支付的订单
        $orderMap['user_id']   = $user_id;              //用户ID
        $orderMap['status']    = 2;                     //订单状态
        // $orderMap['device_id'] = $device['device_id'];  //设备ID
        $order = Db::name('order')->where($orderMap)->order('ctime desc')->find();
        if(!empty($order)){
            $info['order_id']    = $order['order_id'];    //订单ID
            $info['order_number']= $order['order_number'];//订单编号
            $this->_return(4,'您还有未支付的订单',$info);
        }
        if($device['doorstatus'] != 0 && $device['open_status'] != 3)  $this->_return(-1,'设备开门异常，请检测！',(object)array());
        //检查是否有员工正在补货
        $satffMap['device_id'] = $device['device_id'];  //设备ID
        $satffMap['status']    = ['in','1,2'];                     //补货状态（1：开门中 2：未确认 3：已确认）
        $deviceOrder = Db::name('device_order')->where($satffMap)->order('ctime')->find();
        if($deviceOrder){
            $this->_return(-1,'有员工正在补货',(object)array());
        }
        //检查柜子里面是否还有商品
        $goodsMap['device_id'] = $device['device_id'];  //设备ID
        $goodsMap['inventory'] = ['neq',0];              //商品库存
        $deviceGoods = Db::name('device_goods')->where($goodsMap)->select();
        $mysql = Db::name('device_goods')->getLastSql();
        if(empty($deviceGoods))$this->_return(-1,'此柜子里面没有商品，请先补货',(object)array());

        //检查用户是否有开门中的订单
        $openMap['user_id']   = $user_id;              //用户ID
        $openMap['status']    = 1;                     //订单状态
        $openMap['device_id'] = $device['device_id'];  //设备ID
        $openOrder = Db::name('order')->where($openMap)->order('ctime desc')->find();

        if($openOrder){
            $data['order_number'] = 'U'.date('YmdHis').mt_rand(1000,9999);  //订单编号
            $data['ctime'] = time();//创建时间
            $result = Db::name('order')->where(['order_id'=>$order['order_id']])->update($data);
        }else{
            $data['order_number'] = 'U'.date('YmdHis').mt_rand(1000,9999);  //订单编号
            $data['user_id']      = $user_id;                               //用户ID
            $data['device_id']    = $device['device_id'];                   //设备ID
            $data['shop_id']      = $device['shop_id'];                     //所属商家ID
            $data['network_id']   = $device['network_id'];                  //网点ID
            $data['status']       = 1;                                      //订单状态（1：开门中 2：未付款 3：已付款）
            $data['type']         = $type;
            $data['ctime']        = time();                                 //创建时间
//            $data['staff_id']     = $device['user_id'];                     //员工ID
            $result = Db::name('order')->insert($data);

            $data1['user_id']=$user_id;
            $data1['shop_id']=$device['shop_id'];
            $data1['device_id']=$device['device_id'];
            $data1['network_id']   = $device['network_id'];
            $data1['ctime']=time();
            $data1['type']=2;
            db('device_open_log')->insert($data1);
        }
        if($result !== false){
            $device['doorstatus'] = 2;
            $res = Db::name('device')->update($device);
            if (!$res){
                Db::rollback();// 回滚事务
                $this->_return(-1,'还有用户正在购物，请等待其他用户购物完成在购物！',(object)array());
            }
            Db::commit();// 提交事务
            //开门日志信息
            $arr['user_id']    = $user_id;              //补货员ID
            $arr['network_id'] = $device['network_id']; //网点ID
            $arr['shop_id']    = $device['shop_id'];    //商家ID
            $arr['device_id']  = $device['device_id'];  //柜子ID
            $arr['type']       = 2;                     //开门类型（1：员工 2：用户 3：商家）
			$mqtt = new IndexController();
			$json = '{"doorOpen":"customer"}';
			$client_id = time();
			$mqtt->publish($macno,$json,0,$client_id);
            write_log('OpenDoor','发起开门时间--'.date('Y-m-d H:i:s:u',time()));
			write_log('userOpenDoor',"用户：".$user_id."设备: ".$macno."订单：".$data['order_number']);
			$info['user_id'] = $user_id;
            $this->_return(1,'正在请求开门',$info);
        }else{
            $this->_return(-1,'订单生成失败',(object)array());
        }
    }

    /**
     * 设备开门
     * @param array  $post                  | 请求硬件开门条件
     * @param string $post['macno']         | 设备编号
     * @param string $post['order_number']  | 订单编号
     * @param int    $post['type']          | 开门类型：1：售货；2：补货；3：补货重开;4：补货确认 5：清除商品
     * @param int    $type                  | 开门类型（1：用户开门 2：补货开门 3：清除商品）
     * @param array  $arr                   | 开门日志信息
     * @param int    $arr['user_id']        | 开门人ID
     * @param int    $arr['network_id']     | 网点ID
     * @param int    $arr['shop_id']        | 商家ID
     * @param int    $arr['device_id']      | 设备ID
     * @param int    $arr['type']           |开门类型（1：用户 2：员工 3：商家）
     * @return 
     */
    public function openDoor($post=array(),$type=1,$arr=array())
    {
        $re = $this->post_url('http://10.27.204.40/yzsellcage/open',$post);
        $re = json_decode($re,true);
        if($type == 1){//用户开门 
            if($re['code'] == 1){
                if($re['data'] == 1 ){
                    //新增一条开门操作日志
                    $arr['ctime'] = time();
                    Db::name('device_open_log')->insert($arr);
                    //修改开门状态
                    Db::name('device')->where(array('macno'=>$post['macno']))->update(array('doorstatus'=>1,'open_status'=>1));
                    //增加商家每日开门的次数
                    $data['open_num'] = 1;                  //开门次数
                    $data['shop_id']  = $arr['shop_id'];    //商家ID
                    $data['cdate']    = date('Ymd',time()); //统计日期
                    $where['shop_id'] = $arr['shop_id'];
                    $where['cdate']   = date('Ymd',time());
                    $this->shopCount($where,$data);
                    //增加设备开门的次数
                    Db::name('device')->where(array('macno'=>$post['macno']))->setInc('open_num',1);
                    //修改订单表的开门时间
                    Db::name('order')->where(['order_number'=>$post['order_number']])->update(['open_time'=>time()]);
                    $this->_return(1,'开门成功',array('macno'=>$post['macno'],'order_number'=>$post['order_number']));
                }else{
                    Db::name('order')->where(['order_number'=>$post['order_number']])->delete();
                    $this->_return(-1,'开门失败',(object)array()); 
                }
            }else{
                Db::name('order')->where(['order_number'=>$post['order_number']])->delete();
                $this->_return(-1,'设备不在线',(object)array()); 
            }
        }elseif($type == 2){//补货员开门/补货异常 
            if($re['code'] == 1){
                if($re['data'] == 1 ){
                    if($post['type'] == 2){
                        //新增一条开门操作日志
                        $arr['ctime'] = time();
                        Db::name('device_open_log')->insert($arr);
                        //修改开门状态
                        Db::name('device')->where(array('macno'=>$post['macno']))->update(array('doorstatus'=>1,'open_status'=>1));
                        //增加商家每日开门的次数
                        $data['open_num'] = 1;                  //开门次数
                        $data['shop_id']  = $arr['shop_id'];    //商家ID
                        $data['cdate']    = date('Ymd',time()); //统计日期
                        $where['shop_id'] = $arr['shop_id'];
                        $where['cdate']   = date('Ymd',time());
                        $this->shopCount($where,$data);
                        //增加设备开门的次数
                        Db::name('device')->where(array('macno'=>$post['macno']))->setInc('open_num',1);
                        //修改补货表的开门时间
                    }
                    Db::name('device_order')->where(['order_number'=>$post['order_number']])->update(['open_time'=>time()]);
                    $this->_return(1,'开门成功',array('macno'=>$post['macno'],'order_number'=>$post['order_number']));
                }else{
                    if($post['type'] == 2){
                        Db::name('device_order')->where(['order_number'=>$post['order_number']])->delete();
                        $this->_return(-1,'开门失败',(object)array());
                    }else{
                        $this->_return(-1,'补货异常失败',(object)array());
                    }
                }
            }else{
                if($post['type'] == 2){
                    Db::name('device_order')->where(['order_number'=>$post['order_number']])->delete();
                }
                $this->_return(-1,'设备不在线'); 
            }
        }else{
            if($re['code'] == 1){
                if($re['data'] == 1 ){
                    //新增一条开门操作日志
                    $arr['ctime'] = time();
                    Db::name('device_open_log')->insert($arr);
                    //修改开门状态
                    Db::name('device')->where(array('macno'=>$post['macno']))->update(array('doorstatus'=>1,'open_status'=>1));
                    //增加商家每日开门的次数
                    $data['open_num'] = 1;                  //开门次数
                    $data['shop_id']  = $arr['shop_id'];    //商家ID
                    $data['cdate']    = date('Ymd',time()); //统计日期
                    $where['shop_id'] = $arr['shop_id'];
                    $where['cdate']   = date('Ymd',time());
                    $this->shopCount($where,$data);
                    //增加设备开门的次数
                    Db::name('device')->where(array('macno'=>$post['macno']))->setInc('open_num',1);
                    $this->_return(1,'开门成功',array('macno'=>$post['macno'],'order_number'=>$post['order_number']));
                }else{
                    $this->_return(-1,'开门失败',(object)array()); 
                }
            }else{
                $this->_return(-1,'设备不在线',(object)array()); 
            }
        }
    }

    /**
     * 商家每日统计新增
     * @param  array/string   $where | 查询条数
     * @param  array          $data  | 要插入或修改的数据
     * @return 
     */
    public function shopCount($where= '',$data = array())
    {
        $find = Db::name('shop_count')->where($where)->find();

        if(empty($find)){
           Db::name('shop_count')->insert($data);
        }else{
            // var_dump($data);exit;
            unset($data['shop_id']);
            unset($data['cdate']); 
            if($data['order_num']){
                $data['order_num'] = $find['order_num'] + $data['order_num'];
            }
            if($data['open_num']){
                $data['open_num'] = $find['open_num'] + $data['open_num'];
            } 
            if($data['income']){
                $data['income'] = $find['income'] + $data['income'];
            }  
            $a =  Db::name('shop_count')->where($where)->update($data);//->setInc($key,$value);

        }
    }


     /**
     * 订单支付
     * @param  string   $token        | 用户标识
     * @param  int      $type         | 订单支付类型（1：微信支付 2：支付宝 3：余额）
     * @param  string   $order_id     | 订单ID
     * @param  int      $discount_id  | 优惠卷ID
     * @return 
     */
    public function payOrder()
    {
        $token = input('token');

        $user_id= $this->decodeToken($token);
//        $user_id = $this->get_user_id();
        $type = input('type');
        $order_id = input('order_id');
        $discount_id = input('discount_id');
        if(empty($type)) $this->_return(-1,'支付类型不为能空',(object)array());
        if(empty($order_id)) $this->_return(-1,'订单id不为能空',(object)array());
        $where['order_id|order_number']=$order_id;
        $where['status']=2;
        $order = Db::name('order')->where($where)->find();
        if(empty($order)) $this->_return(-1,'这个订单不存在');
//        $order = Db::name('order')->where(['order_id'=>$order_id,'status'=>1])->find();
//        if(empty($order)) $this->_return(-1,'这个订单不存在');
        $data['pay_type'] = $type;
        if($user_id){
            $openid= db('user')->where('user_id',$user_id)->value('openid');
        }
        write_log('payOrder','用户id'.$user_id);
        write_log('payOrder','支付openid'.$openid);

        if($discount_id){
            $coupon_log = Db::name('coupon_log')->where(['id'=>$discount_id])->find();
            if($coupon_log){
                $disMoney = $order['total_price'] - $coupon_log['coupon_money'];
                $data['discount_id']    = $coupon_log['id'];            //优惠ID
                $data['discount_money'] = $coupon_log['coupon_money'];  //优惠金额
                if($disMoney >0){
                    $data['pay_price'] = $disMoney;
                }else{
                    $data['pay_price'] = 0;
                }
            }
//     db('user')->where(array('user_id'=>$order['user_id']))->setInc('coupon_count',1);

        }else{
            $data['pay_price'] = $order['total_price'];
        }
        $Wxpay = new WxpayController();
        switch ($type) {
            case '1'://微信支付
                write_log('payReturn','走到这来了--------$type=1');
                write_log('payReturn','走到这来了--------$order_id'.$order_id);

                    $result = Db::name('order')->where(['order_id'=>$order_id,'status'=>2])->update($data);

                    if($result !== false){
                        write_log('payReturn','走到这来了2$result--------'.json_encode($result,true));
                        write_log('payReturn','走到这来了2$result--------'.json_encode($data,true));
                        //如果没有支付用 余额支付现在没有余额先注释掉
//                        if($data['pay_price'] == 0){
//                            write_log('payReturn','走到这来了--------'.$data['pay_price']); if($aa){}
//                            $res = $Wxpay->payReturn($order['order_number'],'',3);
//                            if($res){
//                                $this->_return(1,'支付成功',(object)array());
//                            }else{
//                                $this->_return(-1,'支付失败',(object)array());
//                            }
//                        }else{
                            write_log('payReturn','wxpay----order_number----'.$order['order_number'].'--------pay_price----'.$data['pay_price'].'----openid--'.$openid);
//                             $data['pay_price'] = 0.01;
                            $this->wxpay($order['order_number'],$data['pay_price'],$openid);

//                        }
                    }else{

                        $this->_return(-1,'订单提交支付失败',(object)array());
                    }
                break;
            case '2'://支付宝支付
                    $result = Db::name('order')->where(['order_id'=>$order_id,'status'=>2])->update($data);
                    if($result !== false){
                        if($data['pay_price'] == 0){
                            $res = $Wxpay->payReturn($order['order_number'],'',3);
                            if($res){
                                $this->_return(1,'支付成功',(object)array());
                            }else{
                                $this->_return(-1,'支付失败',(object)array());
                            }
                        }else{
                            $data['pay_price'] = 0.01;
                            $data['body'] = '支付宝消费订单'.$order['order_number'];
                            $data['out_trade_no'] = $order['order_number'];
                            $data['subject'] = '支付宝付款';
                            $data['total_amount'] = floatval($data['pay_price']);//floatval($order_money) * 100);//总金额
                            $data['notify_url'] = "http://".$_SERVER['HTTP_HOST']."/wxsite/alipay/order_notify_url";
                            $form = model('Alipay')->alipay($data);
                            echo $form;
                        }
                    }else{
                        $this->_return(-1,'订单提交支付失败',(object)array());
                    }
                break;
            case '3'://余额支付
                    $user = Db::name('user')->where(['user_id'=>$user_id])->find();
                    if($user['money'] == 0){
                        // if($result !== false){
                        //     $data['pay_price'] = 0.01;
                        //     $data['body'] = '支付宝消费订单'.$order['order_number'];
                        //     $data['out_trade_no'] = $order['order_number'];
                        //     $data['subject'] = '支付宝付款';
                        //     $data['total_amount'] = floatval($data['pay_price']);//floatval($order_money) * 100);//总金额
                        //     $data['notify_url'] = "http://".$_SERVER['HTTP_HOST']."/wxsite/alipay/order_notify_url";
                        //     $form = model('Alipay')->alipay($data);
                        //     echo $form;
                        // }else{
                        //     $this->_return(-1,'订单提交支付失败',(object)array());
                        // }
                        $this->_return(2,'余额不足，请用户支付宝支付！',(object)array());
                    }else{
                        $result = Db::name('order')->where(['order_id'=>$order_id,'status'=>2])->update($data);
                        if($result !== false){
                            $res = $Wxpay->payReturn($order['order_number'],'',3);
                            if($res){
                                $this->_return(1,'支付成功',(object)array());
                            }else{
                                $this->_return(-1,'支付失败',(object)array());
                            }
                        }else{
                            $this->_return(-1,'订单提交支付失败',(object)array());
                        }
                    }
                break;
            default:
               $this->_return(-1,'支付类型错误',(object)array());
                break;
        }
    }

     /**
     * 微信支付入口
     * @param  string   $order_number  | 订单编号
     * @param  folat    $money         | 支付金额
     * @param  string   $openid        | 用户openid
     * @return 
     */
    /*public function wxpay($order_number='',$money= 0.01 ,$openid='')
    {
        $money= 0.01;
        Vendor("WxPayPubHelper.WxPayPubHelper");
        $shop_id=db('order')->where('order_number',$order_number)->value('shop_id');//查找订单的商家id
        $config= db('shop_wxset')->where('shop_id',$shop_id)->find();//查看商家是否自己配置微信有就调取商家的微信没有就调取平台的微信
        $pay_style=1;
        if(!$config){
           $config = Db::name('wxpay')->where('id=1')->find();
           $pay_style=2;
        }
        write_log('wxpay','微信配置------'.json_encode($config,true));
        //使用支付接口
        $unifiedOrder = new \UnifiedOrder_pub($config["wxappid"], $config["wxappsecret"], $config["wxmchid"], $config["wxmchkey"]);

        //设置统一支付接口参数
        //设置必填参数
        //appid已填,商户无需重复填写
        //mch_id已填,商户无需重复填写
        //noncestr已填,商户无需重复填写
        //spbill_create_ip已填,商户无需重复填写
        //sign已填,商户无需重复填写
        $body = '微信消费订单'.$order_number;
        $unifiedOrder->setParameter("openid", $openid);//商品描述
        $unifiedOrder->setParameter("body", $body);//商品描述
        $unifiedOrder->setParameter("pay_style", $pay_style);//订单支付获取类型1 为平台 2 为商家
        //自定义订单号，此处仅作举例
        $unifiedOrder->setParameter("out_trade_no", $order_number);//商户订单号
        $unifiedOrder->setParameter("total_fee", floatval($money) * 100);//floatval($order_money) * 100);//总金额
        $unifiedOrder->setParameter("notify_url", $this->appUrl ."wxsite/Wxpay/wxNotify");//通知地址
        $unifiedOrder->setParameter("trade_type", "JSAPI");//交易类型

        //非必填参数，商户可根据实际情况选填
        $prepay_id = $unifiedOrder->getPrepayId();

        //=========步骤3：使用jsapi调起支付============
        //使用jsapi接口

        $jsApi = new \JsApi_pub($config["wxappid"], $config["wxappsecret"], $config["wxmchid"], $config["wxmchkey"]);
        $jsApi->setPrepayId($prepay_id);

        $jsApiParameters = $jsApi->getParameters();

        $this->_return(1,'微信支付信任获取成功',json_decode($jsApiParameters));
    }*/


    public function wxpay($order_number='',$money='' ,$openid='')
    {
        write_log('payReturn','调起支付1订单号$order_number='.$order_number.'----$money='.$money.'------$openid---'.$openid);
        $paydata['openid'] = $openid;
        $paydata['body'] = '商品购买支付';                          // 商品描述
        $paydata['out_trade_no'] = $order_number;                    // 订单号
//        $paydata['out_trade_no'] = 'U201810191606233730';                    // 订单号
        $paydata['total_fee'] = $money ;
        $jsapiParams = \app\common\tool\WecahtOfficialAccount::getH5PayParams($paydata);
        write_log('payReturn','$jsapiParams'.json_encode($jsapiParams,true));
        $this->_return(1, 'ok', $jsapiParams);
    }


    //生成员工端的二维码
    public function staffRQCode()
    {
        //ob_start();
        $macno = input('macno');
        Vendor("QRcode");
        $qrcode_content = 'http://'.$_SERVER['HTTP_HOST'].'/wxsite/Index/mach?macno='.$macno; //二维码内容

        $qr = new \QRCode();
        $errorCorrectionLevel = 'L'; //容错级别     
        $matrixPointSize = 10; //生成图片大小 
        error_reporting(E_ERROR);
        $file = urldecode($qrcode_content);
        $staff_file = $qr->png($file,false,$errorCorrectionLevel,$matrixPointSize,2);

        echo '<img src="'.$staff_file.'">';  
    }
   	
	public function Rqcode(){
		 $macno = input('macno');
        echo '< img src="c="http://sdxddshg.app.xiaozhuschool.com/wxsite/order/staffRQCode?macno='.$macno.'">';
	}


    /**
     * 自动确认补货
     * @param array $deviceOrderInfo  //补货的商品
     * @param array $device           //设备信息
     * @param array $deviceOrder      //补货订单
     */
    public function Retrieval($deviceOrderInfo = array(),$device = array(),$deviceOrder=array())
    {
        write_log('Retrieval','自动确认补货了:'.json_encode($orderIfno));
        if($deviceOrderInfo){
            foreach($deviceOrderInfo as $key => $value) {
                  write_log('Retrieval','自动确认补货的rfid:'.$value['rfid']);
                    //新增一条补货操作日志表
                    $goodsLog['order_id']  = $value['order_id'];    //订单ID
                    $goodsLog['device_id'] = $device['device_id'];  //设备ID
                    $goodsLog['goods_id']  = $value['goods_id'];    //商品ID
                    $goodsLog['shop_id']   = $value['shop_id'];     //商家ID
                    $goodsLog['num']       = $value['num'];         //补货数量
                    $goodsLog['rfid']      = $value['rfid'];        //商品rfid
                    $goodsLog['ctime']     = time();            
                    Db::name('device_goodslog')->insert($goodsLog);

                    //修改rfid的状态
                    $rfid['device_id'] = $device['device_id'];//设备ID
                    $rfid['shop_id']   = $value['shop_id'];   //商品ID
                    $rfid['status']    = 2;
                    $rfidWhere['rfid'] = ['in',$value['rfid']];
                    Db::name('rfid')->where($rfidWhere)->update($rfid);

                    //获取商品信息
                    $goods = Db::name('goods')->where(['goods_id'=>$value['goods_id']])->find();
                    //新增或修改设备的rfid
                    $deviceGoods = Db::name('device_goods')->where(['device_id'=>$device['device_id'],'goods_id'=>$value['goods_id']])->find();
                    if(!empty($deviceGoods)){
                        $addGoods['rfid']      = $deviceGoods['rfid'].','.$value['rfid'];
                        $addGoods['inventory'] = $deviceGoods['inventory'] + $value['num'];
                        $re1 = Db::name('device_goods')->where(['device_goods_id'=>$deviceGoods['device_goods_id']])->update($addGoods); 
                    }else{
                        $addGoods['inventory'] = $value['num'];
                        $addGoods['rfid']      = $value['rfid'];
                        $addGoods['device_id'] = $device['device_id'];
                        $addGoods['goods_id']  = $value['goods_id'];
                        $addGoods['price']     = $goods['price'];
                        $addGoods['ctime']     = time();
                        $re1 = Db::name('device_goods')->insert($addGoods);
                    }  
            }
       }
        //修改补货订单状态
        Db::name('device_order')->where(['order_id'=>$deviceOrder['order_id']])->update(['status'=>3]);
    }
	
	
    private function post_url($url, $post = '', $host = '', $referrer = '', $cookie = '', $proxy = -1, $sock = false, $useragent = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1)')
    {//'192.3.25.99:7808'
        if (empty($post) && empty($host) && empty($referrer) && empty($cookie) && ($proxy == -1 || empty($proxy)) && empty($useragent)) return @file_get_contents($url);
        $method = empty($post) ? 'GET' : 'POST';

        if (function_exists('curl_init') && empty($cookie)) {
            $ch = @curl_init();
            @curl_setopt($ch, CURLOPT_URL, $url);
            if ($proxy != -1 && !empty($proxy)) @curl_setopt($ch, CURLOPT_PROXY, 'http://' . $proxy);
            @curl_setopt($ch, CURLOPT_REFERER, $referrer);
            @curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
            @curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_FILE_PATH);
            @curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_FILE_PATH);
            @curl_setopt($ch, CURLOPT_HEADER, 0);
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            @curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

            if ($method == 'POST') {
                @curl_setopt($ch, CURLOPT_POST, 1);
                @curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            }

            $result = @curl_exec($ch);
            @curl_close($ch);
        }

        if ($result === false && function_exists('file_get_contents')) {
            $urls = parse_url($url);
            if (empty($host)) $host = $urls['host'];
            $httpheader = $method . " " . $url . " HTTP/1.1\r\n";
            $httpheader .= "Accept: */*\r\n";
            $httpheader .= "Accept-Language: zh-cn\r\n";
            $httpheader .= "Referer: " . $referrer . "\r\n";
            if ($method == 'POST') $httpheader .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $httpheader .= "User-Agent: " . $useragent . "\r\n";
            $httpheader .= "Host: " . $host . "\r\n";
            if ($method == 'POST') $httpheader .= "Content-Length: " . strlen($post) . "\r\n";
            $httpheader .= "Connection: Keep-Alive\r\n";
            $httpheader .= "Cookie: " . $cookie . "\r\n";

            $opts = array(
                'http' => array(
                    'method' => $method,
                    'header' => $httpheader,
                    'timeout' => 60,
                    'content' => ($method == 'POST' ? $post : '')
                )
            );
            if ($proxy != -1 && !empty($proxy)) {
                $opts['http']['proxy'] = 'tcp://' . $proxy;
                $opts['http']['request_fulluri'] = true;
            }
            $context = @stream_context_create($opts);
            $result = @file_get_contents($url, 'r', $context);
        }

        if ($sock && $result === false && function_exists('fsockopen')) {
            $urls = parse_url($url);
            if (empty($host)) $host = $urls['host'];
            $port = empty($urls['port']) ? 80 : $urls['port'];

            $httpheader = $method . " " . $url . " HTTP/1.1\r\n";
            $httpheader .= "Accept: */*\r\n";
            $httpheader .= "Accept-Language: zh-cn\r\n";
            $httpheader .= "Referer: " . $referrer . "\r\n";
            if ($method == 'POST') $httpheader .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $httpheader .= "User-Agent: " . $useragent . "\r\n";
            $httpheader .= "Host: " . $host . "\r\n";
            if ($method == 'POST') $httpheader .= "Content-Length: " . strlen($post) . "\r\n";
            $httpheader .= "Connection: Keep-Alive\r\n";
            $httpheader .= "Cookie: " . $cookie . "\r\n";
            $httpheader .= "\r\n";
            if ($method == 'POST') $httpheader .= $post;
            $fd = false;
            if ($proxy != -1 && !empty($proxy)) {
                $proxys = explode(':', $proxy);
                $fd = @fsockopen($proxys[0], $proxys[1]);
            } else {
                $fd = @fsockopen($host, $port);
            }
            @fwrite($fd, $httpheader);
            @stream_set_timeout($fd, 60);
            $result = '';
            while (!@feof($fd)) {
                $result .= @fread($fd, 8192);
            }
            @fclose($fd);
        }

        return $result;
    }


}