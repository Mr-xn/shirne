<?php
/**
 * Created by IntelliJ IDEA.
 * User: shirn
 * Date: 2016/9/10
 * Time: 16:13
 */

namespace app\index\controller;


use app\common\validate\MemberAddressValidate;
use app\common\validate\MemberCardValidate;
use app\common\validate\MemberValidate;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use think\Db;

/**
 * Class MemberController
 * @package app\index\controller
 */
class MemberController extends AuthedController
{
    public function initialize()
    {
        parent::initialize();
        $this->assign('navmodel','member');
    }

    /**
     * 会员中心
     */
    public function index(){
        $this->initLevel();

        $this->assign('userLevel',$this->userLevel);
        return $this->fetch();
    }

    /**
     * 个人资料
     */
    public function profile(){
        if($this->request->isPost()){
            $data=$this->request->only(['realname','email','mobile','gender','birth','qq','wechat','alipay'],'post');
            if(!empty($data['birth']) && $data['birth']!='') {
                $data['birth'] = strtotime($data['birth']);
            }else{
                unset($data['birth']);
            }
            $validate=new MemberValidate();
            $validate->setId($this->userid);
            if(!$validate->scene('edit')->check($data)){
                $this->error($validate->getError());
            }else{
                $data['id']=$this->userid;
                Db::name('Member')->update($data);
                user_log($this->userid,'addressadd',1,'修改个人资料');
                $this->success('保存成功',url('index/member/profile'));
            }
        }

        return $this->fetch();
    }

    public function password(){
        if($this->request->isPost()){
            $password=$this->request->post('password');
            if(!compare_password($this->user,$password)){
                $this->error('密码输入错误');
            }

            $newpassword=$this->request->post('newpassword');
            $salt=random_str(8);
            $data=array(
                'password'=>encode_password($newpassword,$salt),
                'salt'=>$salt
            );
            Db::name('Member')->where('id',$this->userid)->update($data);
            $this->success('密码修改成功',url('index/member/index'));
        }

        return $this->fetch();
    }

    /**
     * 修改头像
     */
    public function avatar(){
        if($this->request->isPost()){
            $data=[];
            $uploaded=$this->upload('avatar','upload_avatar');
            if(empty($uploaded)){
                $this->error('请选择文件');
            }
            $data['avatar']=$uploaded['url'];
            $result=Db::name('Member')->where('id',$this->userid)->update($data);
            if($result){
                if(!empty($this->user['avatar']))delete_image($this->user['avatar']);
                user_log($this->userid, 'avatar', 1, '修改头像');
                $this->success('更新成功',url('index/member/avatar'));
            }else{
                $this->error('更新失败');
            }
        }
        return $this->fetch();
    }


    /**
     * 安全中心
     */
    public function security(){
        return $this->fetch();
    }

    public function address(){
        if($this->request->isPost()){
            $data=$this->request->only('id','post');
            $result=Db::name('MemberAddress')->where('member_id',$this->userid)
                ->whereIn('address_id',idArr($data['id']))->delete();
            if($result){
                user_log($this->userid,'addressdel',1,'删除收货地址:'.$data['id']);
                $this->success('删除成功！');
            }else{
                $this->error('删除失败！');
            }
        }
        $addressed=Db::name('MemberAddress')->where('member_id',$this->userid)->select();
        $this->assign('addressed',$addressed);
        return $this->fetch();
    }

    public function addressAdd($id=0){
        if($this->request->isPost()){
            $data=$this->request->only('recive_name,mobile,province,city,area,address,code,is_default','post');
            $data['is_default']=empty($data['is_default'])?0:1;
            $validate=new MemberAddressValidate();
            if(!$validate->check($data)){
                $this->error($validate->getError());
            }else{
                if($id>0){
                    $result=Db::name('MemberAddress')->where('member_id',$this->userid)
                        ->where('address_id',$id)->update($data);
                    if($result){
                        user_log($this->userid,'addressedit',1,'修改收货地址:'.$id);
                        $this->success('修改成功',url('index/member/address'));
                    }else{
                        $this->error('修改失败');
                    }
                }else{
                    $data['member_id']=$this->userid;
                    $id=Db::name('MemberAddress')->insert($data,false,true);
                    if($id){
                        user_log($this->userid,'addressadd',1,'添加收货地址:'.$id);
                        $this->success('添加成功',url('index/member/address'),Db::name('MemberAddress')->find($id));
                    }else{
                        $this->error('添加失败');
                    }
                }
            }

        }
        if($id>0) {
            $address = Db::name('MemberAddress')
                ->where('member_id',$this->userid)
                ->where('address_id',$id)->find();
        }else{
            $address=[];
            $count=Db::name('MemberAddress')->where('member_id',$this->userid)->count();
            if($count<1){
                $address['is_default']=1;
            }
        }

        $this->assign('address',$address);
        return $this->fetch();
    }

    public function cards(){
        $cards=Db::name('MemberCard')->where('member_id',$this->userid)->select();

        $this->assign('cards',$cards);
        return $this->fetch();
    }
    public function cardEdit($id=0){
        if($id>0) {
            $card = Db::name('MemberCard')->where('id' , $id)
                ->where('member_id',$this->userid)->find();
        }else{
            $card=array();
        }
        if($this->request->isPost()){
            $card=$this->request->only('cardno,bankname,cardname,bank,is_default','post');
            $card['is_default']=empty($card['is_default'])?0:1;
            $validate=new MemberCardValidate();

            if(!$validate->check($card)){
                $this->error($validate->getError());
            }else {
                if ($id > 0) {
                    Db::name('MemberCard')->where('id' , $id)->update($card);
                } else {
                    $card['member_id'] = $this->userid;
                    $id = Db::name('MemberCard')->insert($card,false,true);
                }
                if ($card['is_default']) {
                    Db::name('MemberCard')->where('id' , 'NEQ', $id)
                        ->where( 'member_id' , $this->userid)
                        ->update(array('is_default' => 0));
                }
                $this->success('保存成功',url('index/member/cards'));
            }
        }

        $this->assign('card',$card);
        $this->assign('banklist',banklist());
        return $this->fetch();
    }
    public function cashList(){
        $model=Db::name('memberCashin')->where('member_id',$this->userid);

        $cashes = $model->paginate(15);

        $this->assign('page',$cashes->render());
        $this->assign('cashes',$cashes);
        return $this->fetch();
    }
    public function cash(){
        $hascash=Db::name('memberCashin')->where(array('member_id'=>$this->userid,'status'=>0))->count();
        if($hascash>0){
            $this->error('您有提现申请正在处理中',url('index/member/index'));
        }
        $cards=Db::name('MemberCard')->where('member_id',$this->userid)->select();
        if($this->request->isPost()){
            $amount=$_POST['amount']*100;
            $bank_id=intval($_POST['card_id']);
            $card=Db::name('MemberCard')->where(array('member_id'=>$this->userid,'id'=>$bank_id))->find();
            $data=array(
                'member_id'=>$this->userid,
                'amount'=>$amount,
                'real_amount'=>$amount-$amount*$this->config['cash_fee']*.01,
                'create_at'=>time(),
                'bank_id'=>$bank_id,
                'bank'=>$card['bank'],
                'bank_name'=>$card['bankname'],
                'card_name'=>$card['cardname'],
                'cardno'=>$card['cardno'],
                'status'=>0,
                'remark'=>$_POST['remark']
            );
            if(empty($data['amount']) || $data['amount']<$this->config['cash_limit']){
                $this->error('提现金额填写错误');
            }
            if($this->config['cash_power']>0 && $data['amount']%$this->config['cash_power']>0){
                $this->error('提现金额必需是'.$this->config['cash_power'].'的倍数');
            }
            if($data['amount']>$this->user['money']){
                $this->error('可提现金额不足');
            }
            $addid=Db::name('memberCashin')->insert($data);
            if($addid) {
                money_log($this->userid,-$data['amount'],'提现申请扣除','cash');
                $this->success('提现申请已提交',url('index/member/index'));
            }else{
                $this->error('申请失败');
            }
        }
        $this->assign('cards',$cards);
        $this->assign('banklist',banklist());
        return $this->fetch();
    }

    public function moneyLog($type='',$field=''){
        $model=Db::view('MemberMoneyLog mlog','*')
        ->view('Member m',['username','level_id'],'m.id=mlog.from_member_id','LEFT')
        ->where('mlog.member_id',$this->userid);
        if(!empty($type) && $type!='all'){
            $model->where('mlog.type',$type);
        }else{
            $type='all';
        }
        if(!empty($field) && $field!='all'){
            $model->where('mlog.field',$field);
        }else{
            $field='all';
        }

        $logs = $model->order('mlog.id DESC')->paginate(10);

        $types=getLogTypes();
        $fields=getMoneyFields();
        $this->assign('type',$type);
        $this->assign('types',$types);
        $this->assign('field',$field);
        $this->assign('fields',$fields);
        $this->assign('page',$logs->render());
        $this->assign('logs',$logs);
        return $this->fetch();
    }


    public function shares(){
        if(!$this->user['is_agent']){
            $this->error('您还不是代理，请先下单购买');
        }

        $shareurl=url('index/login/register',array('agent'=>$this->user['agentcode']),true,true);

        $qrurl='/uploads/qrcode/'.($this->userid % 32).'/'.$this->user['agentcode'].'.png';
        if(!file_exists('.'.$qrurl)) {
            $dir='.'.dirname($qrurl);
            if(!file_exists($dir))@mkdir($dir,0777,true);
            $qrCode = new QrCode($shareurl);
            $qrCode->setSize(300);
            $qrCode->setWriterByName('png')
                ->setMargin(10)
                ->setEncoding('UTF-8')
                ->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH)
                ->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0])
                ->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255])
                //->setLabel('Scan the code', 16, __DIR__.'/../assets/noto_sans.otf', LabelAlignment::CENTER)
                ->setLogoPath('./static/images/qrlogo.png')
                ->setLogoWidth(150)
                ->setValidateResult(false);
            $qrCode->writeFile('.' . $qrurl);
        }
        $this->assign('qrurl',$qrurl);
        $this->assign('shareurl',$shareurl);

        return $this->fetch();
    }

    public function team($pid=0){
        $levels=getMemberLevels();
        $curLevel=$levels[$this->user['level_id']];
        if($pid==0){
            $pid=$this->userid;
        }elseif($pid!=$this->userid){
            $member=Db::name('Member')->find($pid);
            if(empty($member)){
                $this->error('会员不存在');
            }
            $paths=[$member];
            $maxlayer=$curLevel['commission_layer'];
            while($member['id']!=$this->userid){
                $member=Db::name('Member')->find($member['referer']);
                $paths[]=$member;
                if(count($paths)>$maxlayer){
                    $this->error('您只能查看'.$maxlayer.'层的会员');
                }
            }
            $paths=array_reverse($paths);
            $this->assign('paths',$paths);
        }
        $users=Db::name('Member')->where('referer',$pid)->paginate(10);
        $uids=array_column($users->items(),'id');
        $soncounts=[];
        if(!empty($uids)) {
            $sondata = Db::name('Member')->where('referer' ,'in', $uids)
                ->group('referer')->field('referer,COUNT(id) as count_member')->select();
            $soncounts=[];
            foreach ($sondata as $row){
                $soncounts[$row['referer']]=$row['count_member'];
            }
        }

        $this->assign('levels',$levels);
        $this->assign('users',$users);
        $this->assign('page',$users->render());
        $this->assign('soncounts',$soncounts);
        return $this->fetch();
    }

    /**
     * 订单管理
     * @param int $status
     */
    public function order($status=0){
        //
        $model=Db::name('Order')->where('member_id',$this->userid)
            ->where('delete_time',0);
        if($status>0){
            $model->where('status',$status-1);
        }
        $orders =$model->order('status ASC,create_time DESC')->paginate();
        if(!empty($orders) && !$orders->isEmpty()) {
            $order_ids = array_column($orders->items(), 'order_id');
            $products = Db::view('OrderProduct', '*')
                ->view('Product', ['id' => 'orig_product_id', 'update_time' => 'orig_product_update'], 'OrderProduct.product_id=Product.id', 'LEFT')
                ->view('ProductSku', ['sku_id' => 'orig_sku_id', 'price' => 'orig_product_price'], 'ProductSku.sku_id=OrderProduct.sku_id', 'LEFT')
                ->whereIn('OrderProduct.order_id', $order_ids)
                ->select();
            $products=array_index($products,'order_id',true);
            $orders->each(function($item) use ($products){
                $item['products']=isset($products[$item['order_id']])?$products[$item['order_id']]:[];
                return $item;
            });
        }

        $countlist=Db::name('Order')->where('member_id',$this->userid)
            ->group('status')->field('status,count(order_id) as order_count')->paginate(10);
        $counts=[0,0,0,0,0,0,0];
        foreach ($countlist as $row){
            $counts[$row['status']]=$row['order_count'];
        }

        $this->assign('counts',$counts);
        $this->assign('orders',$orders);
        $this->assign('status',$status);
        $this->assign('page',$orders->render());
        return $this->fetch();
    }

    public function order_detail($id){
        $order=OrderModel::get($id);
        if(empty($order) || $order['delete_time']>0){
            $this->error('订单不存在或已删除',url('index/member/order'));
        }
        $this->assign('order',$order);
        $this->assign('products',Db::name('OrderProduct')->where('order_id',$id)->select());
        return $this->fetch();
    }
    
    /**
     * 删除订单
     * @param $id
     */
    public function order_delete($id)
    {
        $model = Db::name('order');
        $result = $model->where('member_id',$this->userid)->whereIn("order_id",idArr($id))
            ->useSoftDelete('delete_time',time())->delete();
        if($result){
            //Db::name('orderProduct')->whereIn("order_id",idArr($id))->delete();
            user_log($this->userid,'deleteorder',1,'删除订单 '.$id );
            $this->success("删除成功", url('index/member/order'));
        }else{
            $this->error("删除失败");
        }
    }

    /**
     * 订单确认
     * @param $id int
     */
    public function confirm($id){
        $model=Db::name('Order')->where('order_id',$id)->find();

        if(!$model['isaudit']){
            $this->error('订单尚未审核');
        }

        if(empty($model) || $model['member_id']!=$this->userid){
            $this->error('订单不存在');
        }
        Db::name('Order')->where('order_id',$id)->update(array('status'=>3,'confirm_time'=>time()));
        $this->success('确认完成');
    }

    public function notice(){
        $notices=Db::name('notice')->order('id desc')->paginate(10);
        $this->assign('notices',$notices);
        $this->assign('page',$notices->render());
        return $this->fetch();
    }

    public function feedback(){
        $unreplyed=Db::name('feedback')->where(array('member_id'=>$this->userid,'reply_at'=>0))->count();
        if($this->request->isPost()){
            if($unreplyed>0)$this->error('您的反馈尚未回复');
            $content=$this->request->post('content');
            $data=array();
            $data['content']=htmlspecialchars($content);
            $data['member_id']=$this->userid;
            $data['type']=1;
            $data['create_time']=time();
            $data['ip']=$this->request->ip();
            $data['status']=0;
            $data['reply_at']=0;
            $feedid=Db::name('feedback')->insert($data);
            if($feedid){
                $this->success('反馈成功');
            }else{
                $this->error('系统错误');
            }
        }
        $feedbacks=Db::name('feedback')->where('member_id',$this->userid)->order('id desc')->paginate(10);

        $this->assign('feedbacks',$feedbacks);
        $this->assign('page',$feedbacks->render());
        $this->assign('unreplyed',$unreplyed);
        return $this->fetch();
    }

    public function logout(){
        $this->clearLogin();
        $this->success('退出成功',url('index/login/index'));
    }
}