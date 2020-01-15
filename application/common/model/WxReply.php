<?php
namespace app\common\model;

use think\Model;

class WxReply extends Model
{
	protected $resultSetType = 'collection';
	protected $autoWriteTimestamp = 'timestamp';
	// 定义时间戳字段名
    protected $createTime = 'ctime';
    protected $updateTime = 'updated_at';
    
	public function file()
    {
        return $this->hasOne('File','id','file_id');
    }


}