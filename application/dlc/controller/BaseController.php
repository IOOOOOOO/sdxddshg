<?php

namespace app\dlc\controller;

use think\Controller;
use think\Model;
use think\Db;
use think\Config;
use think\Request;
use think\View;
use think\Session;

class BaseController extends Controller
{
    protected static $SYS; //系统级全局静态变量
    protected static $CMS; //CMS全局静态变量
    protected static $SHOP; //Shop变量全局设置
	protected static $REQUEST; //Shop变量全局设置
    protected $uid;
    protected $role_id;
    protected $token;

    //初始化验证模块
    protected function _initialize()
    {
        //预留检测
		parent::_initialize(); 
		// TODO: Change the autogenerated stub
		self::$REQUEST = Request::instance();
        //刷新系统全局配置
        self::$SYS['set'] = $_SESSION['SYS']['set'] = $this->checkSysSet();
        self::$CMS['set']= $_SESSION['SYS']['set'];
		$request = Request::instance();
		// $action =  $request->action();
  //       $other = array('login','logout','reg','verify');
        //检测登陆状态
        $check = $this->checkLogin();
        $action =  $request->action();
        $other = array('login','logout','reg','verify');
        if(!in_array($action, $other)){
           $this ->uid = Session::get('CMS.uid');//必须保证有Userid，去掉登录功能模块↑
           $this ->user = Session::get('CMS.user');//必须保证有Userid，去掉登录功能模块↑
           $role_id = Db::name('admin') -> where(array('id' => $this ->uid))->value('roleid');
           $this->role_id = $role_id;
           $auth = Db::table('dlc_role') -> where(array('role_id' => $role_id))->value('auth');
           $role_oath = Db::table('dlc_role_oath') ->where("oath_id in (".$auth.")")->column('url','oath_id');
           foreach ($role_oath as $k => $v) {
                if($v) {
                    if(strpos($v,"?")) {
                        $role_oath[$k] = explode("?", $v)[0];
                    }
                }
            }
            $this->auth = $role_oath;
        }
    }

    //返回系统全局配置
    public function checkSysSet()
    {
        $set = model('Set')->find();

        return $set ? $set : utf8error('系统还未配置！');
    }

    //检查用户是否登陆,返回TRUE或跳转登陆
    public function checkLogin()
    {
        $passlist = array('login', 'logout', 'reg', 'verify'); //不检测登陆状态的操作
		$request = Request::instance();
		$action =  $request->action();
        $check = in_array($action, $passlist);
        if (!$check) {
            if (!Session::has('CMS.uid')) {
                $this->redirect('Dlc/Public/login');
            }
        } else {
            return true;
        }
    }

    //拼装面包导航
    public function getBread($bread)
    {
        if ($bread) {
            $this->assign('bread', $bread);
            return $this->fetch('Base_bread');
        } else {
            $this->error('请传入面包导航！');
        }
    }


    //获取单张图片
    public function getPic($id)
    {
        $m = model('Upload_img');
        $map['id'] = $id;
        $list = $m->where($map)->find();
        if ($list) {
            $list['imgurl'] = __ROOT__ . "/Upload/" . $list['savepath'] . $list['savename'];
        }
        return $list ? $list : "";
    }

    //获取图集合
    public function getAlbum($ids)
    {
        $m = model('Upload_img');
        $map['id'] = array('in', parse_str($ids));
        $list = $m->where($map)->select();
        foreach ($list as $k => $v) {
            $list[$k]['imgurl'] = "/Upload/" . $list[$k]['savepath'] . $list[$k]['savename'];
        }
        return $list ? $list : "";
    }

    //获取图集合
    public function getAlbum1($ids)
    {
        $m = model('Upload_img');
        $map['id'] = array('in', parse_str($ids));
        $list = $m->where($map)->select();
        foreach ($list as $k => $v) {
            $list[$k][] = "/Upload/" . $list[$k]['savepath'] . $list[$k]['savename'];
        }
        return $list ? $list : "";
    }

    //获取会员等级经验对称数据
    public function getlevel($exp)
    {
        $data = model('Vip_level')->order('exp')->select();
        if ($data) {
            $level = array();
            foreach ($data as $k => $v) {
                if ($k + 1 == count($data)) {
                    if ($exp >= $data[$k]['exp']) {
                        $level['levelid'] = $data[$k]['id'];
                        $level['levelname'] = $data[$k]['name'];
                    }
                } else {
                    if ($exp >= $data[$k]['exp'] && $exp < $data[$k + 1]['exp']) {
                        $level['levelid'] = $data[$k]['id'];
                        $level['levelname'] = $data[$k]['name'];
                    }
                }
            }
        } else {
            return utf8error('会员等级未定义！');
        }
        return $level;
    }
	
	/**
	 * 封装分页类
	 * @param $count 操作URL
	 * @param $psize 记录信息
	 * @param $loader 模块
	 * @param $loadername 模块名
	 * @param $searchname 搜索
	 * @param $map 
	 */
	public function getPage($count, $psize, $loader, $loadername, $searchname,$data=[]){
		if (!$count && !$psize || !$loader || !$loadername) {
				die('缺少分页参数!');
		}
		$page = new \pagecms\pagecms($count, $psize,$data); // 实例化分页类 传入总记录数和每页显示的记录数
		$page->setConfig('loader', $loader);
		$page->setConfig('loadername', $loadername);
		//绑定前端form搜索表单ID,默认为#App-search
		if ($searchname) {
			 $page->setConfig('searchname', $searchname);
		}
		$show = $page->show(); // 分页显示输出
		$this->assign('page', $show);
		return true;
	}

     /**
     * 手机号正则判断 正确返回false，错误返回true
     * [check_mobile description]
     * @param  [type] $phone [description] 手机号
     * @return [type]        [description]
     */
    protected function check_mobile($phone){
        return preg_match('#^13[\d]{9}$|^14[5,6,7,8,9]{1}\d{8}$|^15[0,1,2,3,5,6,7,8,9]{1}\d{8}$|^166[\d]{8}$|^17[0,1,2,3,4,5,6,7,8]{1}\d{8}$|^18[\d]{9}$|^19[8,9]{1}\d{8}$#', $phone);
    }
}
?>
