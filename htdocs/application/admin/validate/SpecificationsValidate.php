<?php
/**
 * Created by IntelliJ IDEA.
 * User: shirne
 * Date: 2018/5/15
 * Time: 22:56
 */

namespace app\admin\validate;


use app\common\validate\BaseUniqueValidate;

class SpecificationsValidate extends BaseUniqueValidate
{
    protected $rule=array(
        'title'=>'require|unique:specifications,%id%',
        'data'=>'require'
    );
    protected $message=array(
        'title.require'=>'请填写规格名',
        'title.unique'=>'规格名已经存在',
        'data.require'=>'请填写规格值'
    );
}