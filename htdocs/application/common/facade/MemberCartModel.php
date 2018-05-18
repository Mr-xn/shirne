<?php
/**
 * Created by IntelliJ IDEA.
 * User: shirne
 * Date: 2018/5/14
 * Time: 0:00
 */

namespace app\common\facade;

use extcore\SimpleFacade;

/**
 * Class MemberCartModel
 * @package app\common\facade
 * @see \app\common\model\MemberCartModel
 * @method bool addCart($product,$sku_id,$count,$member_id) static 添加购物车
 * @method bool updateCart($sku_id,$count,$member_id) static 更新购物车
 * @method array getCart($member_id,$sku_ids='') static 获取购物车
 * @method bool delCart($sku_ids,$member_id) static 删除购物车
 * @method bool clearCart($member_id) static 清空购物车
 */
class MemberCartModel extends SimpleFacade
{
    protected static function getFacadeClass(){
        return \app\common\model\MemberCartModel::class;
    }
}