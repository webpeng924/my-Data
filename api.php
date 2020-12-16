<?php
session_start();
//var_dump($_SESSION);
header("Access-Control-Allow-Origin:*");
header("Content-Type:text/html;charset=UTF-8");
//header('Access-Control-Allow-Methods','GET,POST,DELETE');
//header('Access-Control-Allow-Headers', "content-type");
require_once("../mysql/data.php");

$dateline=time();
$datatype = $_REQUEST['datatype'];
//别克汇  传openid
//访问地址：http://hb.rgoo.com/api/api.php?datatype=

/* 浏览记录按日期分组
* visit 日期
*/
function getStockNo($storeid,$type){
  //  $storeid = $_REQUEST['storeid'];
    //$storeid =1;
  //  $type = $_REQUEST['type'];//1入库 2出库 3盘点
    if($type==1){
        $str = substr(date('Ymd'),2);
        $str2 = $str.'0001';
        $addtime = strtotime(date('Y-m-d'));
        $endtime = $addtime+3600*24;
        $stock_no = $GLOBALS['db']->get_var("select stock_no from a_stock_into where storeid=$storeid and dateline>=$addtime and dateline<$endtime order by id desc limit 1");
        if($stock_no){
            $str2 =$stock_no+1;
        }
    }elseif($type==2){
        $str = substr(date('Ymd'),2);
        $str2 = $str.'0001';
        $addtime = strtotime(date('Y-m-d'));
        $endtime = $addtime+3600*24;
        $stock_no = $GLOBALS['db']->get_var("select stock_no from a_stock_out where storeid=$storeid and dateline>=$addtime and dateline<$endtime order by id desc limit 1");
        if($stock_no){
            $str2 =$stock_no+1;
        }
    }elseif($type==3){
        $str = substr(date('Ymd'),2);
        $str2 = $str.'0001';
        $addtime = strtotime(date('Y-m-d'));
        $endtime = $addtime+3600*24;
        $stock_no = $GLOBALS['db']->get_var("select stock_no from a_stock_pan where storeid=$storeid and dateline>=$addtime and dateline<$endtime order by id desc limit 1");
        if($stock_no){
            $str2 =$stock_no+1;
        }
    }
    return $str2;
}


function groupVisit($visit)
{
    $curyear = date('Y');
    $visit_list = [];
    foreach ($visit as $v) {
        if ($curyear == date('Y', $v['dateline'])) {
            $date = date('m月', $v['dateline']);
        } else {
            $date = date('Y年m月', $v['dateline']);
        }
        $visit_list[$date][] = $v;
    }
    return $visit_list;
}

function curl($data,$url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // 对认证证书来源的检查
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 从证书中检查SSL加密算法是否存在
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));//增加 HTTP Header（头）里的字段 : Content-Type：application/x-www-form-urlencoded
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}


//开单

//收银台列表
if($datatype=='get_orderlist'){
   // print_r($_SESSION);
  //  var_dump($_COOKIE);exit;
   // echo 111;exit;
    $storeid = $_REQUEST['storeid'];
    $date = $_REQUEST['date'];//0今天 1昨天 2前天
    $status = $_REQUEST['status'];//1服务中 2待结账 3已结账  4已作废  0全部
    $staffid = $_REQUEST['staffid'];//all,1,2,3...
    if($storeid==''){
        $res = array('code'=>2,'msg'=>'无数据', 'data'=>'');
        return $res;
    }
    $sql = "select * from a_order where storeid=$storeid and sign=0";
    if(isset($date)){
        if($date==0){
            $start = strtotime('today');
            $end = $start+24*3600;
            $sql = $sql." and dateline>$start and dateline<=$end";
        }elseif($date==1){
            $start = strtotime('yesterday');
            $end = $start+24*3600;
            $sql = $sql." and dateline>$start and dateline<=$end";
        }elseif ($date==2){
            // $start = strtotime(date('Y-m-d 0:0:0',$dateline-48*3600));
            $start = strtotime(date('Y-m-d 0:0:0',$dateline-48*3600));
            $end = $start+24*3600;
            $sql = $sql." and dateline>$start and dateline<=$end";
        }
    }

  //  echo $sql;exit;
    if($status){
        $sql = $sql." and status=$status";

    }
    /*if($staffid!=''){
        if($staffid!='all'){
            $sql = $sql." and (staff1='$staffid' or staff2='$staffid' or staff3='$staffid' or staff4='$staffid')";
        }
    }*/
    $list = $db->get_results($sql);
    if($list){
        $arr=array();
        foreach ($list as $k=>$v){
//echo "select itemname,staff1,staff2,staff2,staff4,subtotal from a_order_detail where order_no='{$v['order_no']}' and storeid={$v['storeid']}";
            if($v['member_id']>0){
                $v['member_name'] = $db->get_var("select name from fly_member where member_id={$v['member_id']} and storeid=$storeid");
            }else{
                $v['member_name']='';
            }
            $info = $db->get_results("select *,card_memberitem_id as cikaid from a_order_detail where order_no='{$v['order_no']}' and storeid={$v['storeid']}");
            if($staffid=='all'){
                $v['orderinfo'] = $info;
                //   var_dump($v);
                $arr[] = $v;
            }else{
                //echo "select id,itemname,staff1,staff2,staff3,staff4,`num`,price,subtotal,is_usecard from a_order_detail where order_no='{$v['order_no']}' and storeid={$v['storeid']} and (staff1=$staffid or staff2=$staffid or staff3=$staffid or staff4=$staffid ";exit;
                $info = $db->get_results("select *,card_memberitem_id as cikaid from a_order_detail where order_no='{$v['order_no']}' and storeid={$v['storeid']} and staff1=$staffid");
                if($info){
                    $v['orderinfo'] = $info;
                    $arr[] = $v;
                }

            }

        }
    }else{
      $arr = null;
    }
//echo $sql;exit;

    //页面右下角
    if(isset($date)){
        $statusarr = $db->get_results("select status,count(id) as `num` from a_order where storeid=$storeid and dateline>$start and dateline<=$end group by status");
    }else{
        $statusarr=null;
    }


    $res = array('code'=>1,'msg'=>'成功','data'=>$arr,'right'=>$statusarr);
    echo json_encode($res);
}


//查找会员
if($datatype=='find_member'){
    $storeid = $_REQUEST['storeid'];
    $type= $_REQUEST['type'];//1是姓名 2 是num
    $data= $_REQUEST['data'];
    if($type==1){
        $list = $db->get_results("select card_num,`name`,mobile,card_type,money,storeid,expiry_date from fly_member where `name`='$data'");
    }else{
        $list = $db->get_results("select card_num,`name`,mobile,card_type,money,storeid,expiry_date from fly_member where card_num like '%$data%' or mobile like '%$data%'");
    }
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    return json_encode($res);
}


//添加员工/编辑
if($datatype=='insert_staff'){
    $storeid = $_REQUEST['storeid'];
   // $storeid = 1;
    $type = $_REQUEST['type'];//1增加  2编辑
    $id = $_REQUEST['id'];//
    $avatar = $_REQUEST['avatar'];
    $job_no = $_REQUEST['job_no'];
    $name = $_REQUEST['name'];
    $sex = $_REQUEST['sex']; //1男 2女
    $mobile =$_REQUEST['mobile'];
    $section = $_REQUEST['section']; //部门
    $service_job = $_REQUEST['service_job'];//服务职称
    $job = $_REQUEST['job']; //行政职位
    $now_status =$_REQUEST['now_status'];// 1 在职
    //2 未在职3 离职
    $yy_status = $_REQUEST['yy_status']; //预约状态  1开启 0关闭
    $username = $_REQUEST['username'];//1开启 0关闭
    $password = $_REQUEST['password'];
    $remark = $_REQUEST['remark'];
    if($avatar){
        $avatar2=$avatar;
    }else{
        $avatar2='/upload/shop/moren.jpg';
    }


    if($username){
        $check2 = $db->get_var("select id from a_staff where username='$username'");
        if($check2){
            $res = array('code'=>2,'msg'=>'员工登录名重复');
            echo json_encode($res);exit;
        }
    }
    if($type==1){
//echo "insert into a_staff(storeid,avatar,background,job_no,`name`,sex,mobile,`section`,service_job,job,now_status,yy_status,xxxyy,tc_pro,remark,dateline) values('$storeid','$avatar','$background','$job_no','$name',$sex,$mobile,'$section','$service_job','$job','$now_status','$yy_status','$xxxyy','$tc_pro','$remark',$dateline)";exit;

        $check = $db->get_var("select id from a_staff where storeid=$storeid and job_no='$job_no'");
        if($check){
            $res = array('code'=>2,'msg'=>'员工编号重复');
            echo json_encode($res);exit;
        }
        $db->query("insert into a_staff(storeid,avatar,job_no,`name`,sex,mobile,`section`,service_job,job,now_status,yy_status,username,password,role,remark,dateline) values('$storeid','$avatar2','$job_no','$name',$sex,$mobile,'$section','$service_job','$job','$now_status','$yy_status','$username','$password',2,'$remark',$dateline)");
        //   echo $db->insert_id;exit;
        if($db->insert_id){
            $res = array('code'=>1,'msg'=>'添加成功');
            echo json_encode($res);
        }
    }else{
        //    echo "update a_staff set avatar='$avatar2',background='$background2',`name`='$name',sex=$sex,mobile=$mobile,`section`='$section',service_job='$service_job',job='$job',now_status='$now_status',yy_status='$yy_status',xxxyy='$xxxyy',tc_pro='$tc_pro',remark='$remark' where id=$id";exit;

        $db->query("update a_staff set avatar='$avatar2',background='$background',`name`='$name',sex=$sex,mobile=$mobile,`section`='$section',service_job='$service_job',job='$job',now_status='$now_status',yy_status='$yy_status',username='$username',password='$password',remark='$remark' where id=$id");
        $res = array('code'=>1,'msg'=>'更新成功');
        echo json_encode($res);
    }

}

//更多界面
if($datatype=='more'){
    $storeid = $_REQUEST['storeid'];
    $start = strtotime(date("Y-m-d"),time());
    $end = $start+24*3600;
    $check = $db->get_var("select id from a_sign where storeid=$storeid and dateline>=$start and dateline<$end");
    $row = $db->get_row("select a.*,b.typename from fly_shop as a inner join fly_shop_type as b on b.type_id=a.type_id where shop_id=$storeid");
    if($check){
        $row['is_sign']=1;
    }else{
        $row['is_sign']=0;
    }
    if($row){
        $res = array('code'=>1,'msg'=>'成功','data'=>$row);
    }else{
        $res = array('code'=>2,'msg'=>'找不到数据');
    }
    echo json_encode($res);
}


//员工列表
if($datatype=='get_staff_list'){
  //  $search = $_REQUEST['search'];
    $storeid = $_REQUEST['storeid'];
  $is_li = $_REQUEST['is_li'];//是否隐藏离职
  $is_wei = $_REQUEST['is_wei'];//是否隐藏未在职
    $search = $_REQUEST['search'];
    $search2 = $_REQUEST['search2'];
  //  $is_ting = $_REQUEST['is_ting'];
    $sql = "select id,job_no,avatar,`name`,mobile,`section`,service_job,job,now_status,yy_status,username,password,sex from a_staff where storeid=$storeid and role=2";
    if($search){
        $sql .=" and service_job='$search'";
    }
    if($search2){
        $sql .=" and (name like'%$search2%' or job_no like '%$search2%')";
    }
    if($is_li==1){
       if($is_wei==1){
           $sql .=" and now_status=1";
       }else{
           $sql .=" and now_status<3";
       }
    }else{
        if($is_wei==1){
            $sql .=" and now_status!=2";
        }
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//输入字段查询员工列表
if($datatype=='search_staff_list'){
    $storeid = $_REQUEST['storeid'];
    $search = $_REQUEST['search'];
    if($search){
     //   echo "select id,avatar,`name`,mobile,`section`,service_job,job,now_status,yy_status from a_staff where storeid=$storeid and (job_no like '%$search%' or `name` like '%$search%' or mobile like '%$search%' or `section` like '%$search%' or service_job like '%$search%' or job like '%$search%')";exit;
        $sql = "select id,avatar,`name`,mobile,`section`,service_job,job,job_no,now_status,yy_status,username,password from a_staff where storeid=$storeid  and role=2 and (job_no like '%$search%' or `name` like '%$search%' or mobile like '%$search%' or `section` like '%$search%' or service_job like '%$search%' or job like '%$search%')";
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}
//上传图片
if($datatype=='upload_img'){
   // $data=file_get_contents();
    // print_r($data);die();
    $img = $_POST['img'];
    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $img, $result)){
        $type = $result[2];
        $img2 =base64_decode(str_replace($result[1], '', $img));
        $ram=mt_rand(10000,99999);
        $file='../upload/shop/'.time().$ram.'.'.$type;
        $thumb='/upload/shop/'.time().$ram.'.'.$type;
        $a = file_put_contents($file, $img2);//返回的是字节数
        //$photo[]=$thumb;
    }
    $res = array('code'=>1,'msg'=>'成功','data'=>$thumb);
    echo json_encode($res);
}
//上传门店头像
if($datatype=='upload_shopimg'){
    $storeid =$_REQUEST['storeid'];
    $img = $_REQUEST['img'];
    $db->query("update fly_shop set avatar='$img' where shop_id=$storeid");
    $res = array('code'=>1,'msg'=>'成功','data'=>$img);
    echo json_encode($res);
}

//门店资料编辑
if($datatype=='edit_shop'){
    $storeid = $_REQUEST['storeid'];
    $shop_name = $_REQUEST['shop_name'];
    $short_name = $_REQUEST['short_name'];
    $mobile = $_REQUEST['mobile'];
    $gzh = $_REQUEST['gzh'];
    $url = $_REQUEST['url'];
    $scope = $_REQUEST['scope'];
    $kv = $_REQUEST['kv'];
    $avatar = $_REQUEST['avatar'];

    if($kv){
        $kv = json_encode($kv);
    }
    $db->query("update fly_shop set avatar='$avatar',shop_name='$shop_name',short_name='$short_name',mobile='$mobile',address='$address',gzh='$gzh',url='$url',scope='$scope',kv='$kv' where shop_id=$storeid");
    $res = array('code'=>1,'msg'=>'更新成功');
    echo json_encode($res);
}
//编辑门店收款二维码
if($datatype=='edit_code'){
    $storeid = $_REQUEST['storeid'];
    $sign = $_REQUEST['sign'];//wx/zfb/other
    $code = $_REQUEST['code'];
    if($code){
        if($sign=='wx'){
            $db->query("update fly_shop set wx_code='$code' where shop_id=$storeid");
        }elseif($sign=='zfb'){
            $db->query("update fly_shop set zfb_code='$code' where shop_id=$storeid");
        }elseif($sign=='other'){
            $db->query("update fly_shop set other_code='$code' where shop_id=$storeid");
        }
        $res = array('code'=>1,'msg'=>'更新成功');
        echo json_encode($res);
    }else{
        $res = array('code'=>2,'msg'=>'参数不能为空');
        echo json_encode($res);
    }
}
//增加项目分类

if($datatype=='insert_itemcate'){
     $storeid = $_REQUEST['storeid'];
   // $storeid = 1;
    //  $type = $_REQUEST['type'];//1增加  2编辑
    // $id = $_REQUEST['id'];//
    $title = $_REQUEST['title'];
    if($title!='' && $storeid!=0){
        $db->query("insert into a_itemcate(storeid,title) values($storeid,'$title')");
        if($db->insert_id){
            $res = array('code'=>1,'msg'=>'添加成功');
            echo json_encode($res);
        }else{
            $res = array('code'=>2,'msg'=>'添加失败');
            echo json_encode($res);
        }
    }else{
        $res = array('code'=>3,'msg'=>'参数错误');
        echo json_encode($res);
    }

}


//获取项目分类列表

if($datatype=='get_itemcate'){
    $storeid = $_REQUEST['storeid'];
   // $storeid = 1;
    $list = $db->get_results("select * from a_itemcate where storeid=0 or storeid=$storeid");
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//删除产品分类
if($datatype=='del_itemcate'){
    // $storeid = $_REQUEST['storeid'];
    //  $status = $_REQUEST['status'];//是否隐藏停用项目
    $id = $_REQUEST['id'];//编号/套餐名称
    $check = $db->get_var("select id from a_item where category_id=$id");
    if($check) {
        $res = array('code' => 2, 'msg' => '无法删除,该分类下有项目');
    }else{
        $db->query("delete from a_itemcate where id=$id");
        $res = array('code'=>1,'msg'=>'成功');
    }

    echo json_encode($res);
}


//添加项目/编辑
if($datatype=='insert_item'){
     $storeid = $_REQUEST['storeid'];
   // $storeid = 1;
    $type = $_REQUEST['type'];//1增加  2编辑
    $id = $_REQUEST['id'];//
    $img = $_REQUEST['img'];
    $item_no =$_REQUEST['item_no'];
    $name = $_REQUEST['name'];
    $price = $_REQUEST['price'];
    $category_id = $_REQUEST['category_id'];
    $belong_job =$_REQUEST['belong_job'];
    $is_stop = $_REQUEST['is_stop'];
    $remark = $_REQUEST['remark'];
    $ccard_count = $_REQUEST['ccard_count'];
    $ccard_price = $_REQUEST['ccard_price'];
    $ccard_total = $_REQUEST['ccard_total']?$_REQUEST['ccard_total']:0;

    if($type==1){
        $check = $db->get_var("select id from a_item where storeid=$storeid and item_no='$item_no' limit 1");
        if(!$check){
            $db->query("insert into a_item(storeid,img,item_no,`name`,price,category_id,`belong_job`,is_stop,remark,ccard_count,ccard_price,ccard_total,dateline) values('$storeid','$img','$item_no','$name','$price',$category_id,'$belong_job','$is_stop','$remark',$ccard_count,$ccard_price,$ccard_total,$dateline)");
            //   echo $db->insert_id;exit;
            if($db->insert_id){
                $res = array('code'=>1,'msg'=>'添加成功');
                echo json_encode($res);
            }
        }else{
            $res = array('code'=>2,'msg'=>'编号重复');
            echo json_encode($res);
        }

    }else{
        //echo "update a_staff set avatar='$avatar2',background='$background2',`name`='$name',sex=$sex,mobile=$mobile,`section`='$section',service_job='$service_job',job='$job',now_status='$now_status',yy_status='$yy_status',xxxyy='$xxxyy',tc_pro='$tc_pro',remark='$remark' where id=$id";exit;
        $db->query("update a_item set img='$img',`name`='$name',price='$price',category_id=$category_id,`belong_job`='$belong_job',is_stop=$is_stop,remark='$remark',ccard_count=$ccard_count,ccard_price=$ccard_price,ccard_total=$ccard_total where id=$id");
        $res = array('code'=>1,'msg'=>'更新成功');
        echo json_encode($res);
    }

}



//项目列表
if($datatype=='get_item_list'){
    //  $search = $_REQUEST['search'];
    $storeid = $_REQUEST['storeid'];
    $status = $_REQUEST['status'];//是否隐藏停用项目  1隐藏
    $cate = $_REQUEST['cate']; //项目分类id
    $search = $_REQUEST['search'];//编号/项目名称
    $sql = "select a.*,b.title from a_item as a left join a_itemcate as b on a.category_id=b.id where a.storeid=$storeid";
    if($status==1){ //隐藏停用=显示正常
        $sql .=" and is_stop=0";
    }
    if($cate>0){
        $sql .=" and category_id=$cate";
    }
    if($search!=''){
        $sql .=" and (item_no like '%$search%' or `name` like '%$search%')";
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}



//增加产品分类

if($datatype=='insert_goodscate'){
     $storeid = $_REQUEST['storeid'];
   // $storeid = 1;
    //  $type = $_REQUEST['type'];//1增加  2编辑
    // $id = $_REQUEST['id'];//
    $title = $_REQUEST['title'];
    if($title!='' && $storeid!=0){
        $db->query("insert into a_goodscate(storeid,title) values($storeid,'$title')");
        if($db->insert_id){
            $res = array('code'=>1,'msg'=>'添加成功');
            echo json_encode($res);
        }else{
            $res = array('code'=>2,'msg'=>'添加失败');
            echo json_encode($res);
        }
    }else{
        $res = array('code'=>3,'msg'=>'参数错误');
        echo json_encode($res);
    }

}

//获取产品分类列表

if($datatype=='get_goodscate'){
    $storeid = $_REQUEST['storeid'];
   // $storeid = 1;
    $list = $db->get_results("select * from a_goodscate where storeid=0 or storeid=$storeid");
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//删除产品分类
if($datatype=='del_goodscate'){
    // $storeid = $_REQUEST['storeid'];
    //  $status = $_REQUEST['status'];//是否隐藏停用项目
    $id = $_REQUEST['id'];//编号/套餐名称
    $check = $db->get_var("select id from a_goods where category_id=$id");
    if($check) {
        $res = array('code' => 2, 'msg' => '无法删除,该分类下有产品');
    }else{
        $db->query("delete from a_goodscate where id=$id");
        $res = array('code'=>1,'msg'=>'成功');
    }

    echo json_encode($res);
}



//添加产品/编辑
if($datatype=='insert_goods'){
     $storeid = $_REQUEST['storeid'];
   // $storeid = 1;
    $type = $_REQUEST['type'];//1增加  2编辑
    $id = $_REQUEST['id'];//
    $goods_name = $_REQUEST['goods_name'];
    $goods_no =$_REQUEST['goods_no'];//编号
    $price = $_REQUEST['price'];
 //   $discount_type = $_REQUEST['discount_type'];
    $category_id = $_REQUEST['category_id'];
    $warehouse =$_REQUEST['warehouse'];
    $goods_unit = $_REQUEST['goods_unit'];
    $supplier_id = $_REQUEST['supplier_id']?$_REQUEST['supplier_id']:0;
    $state = $_REQUEST['state'];//1 下架  0正常
    $is_stop= $_REQUEST['is_stop'];
    $bar_code = $_REQUEST['bar_code'];//条码
    $goods_spec_format= $_REQUEST['goods_spec_format'];//规格
    $remark = $_REQUEST['remark'];
    $pic = $_REQUEST['pic'];
    $in_cost = $_REQUEST['in_cost'];

    if($id){
        $oldname = $db->get_var("select goods_name from a_goods where id=$id");
        if($oldname!=$goods_name){
         //     echo "select id from a_goods where storeid=$storeid and goods_name='$goods_name' and goods_name!='' and id!=$id limit 1";exit;
            $checkname = $db->get_var("select id from a_goods where storeid=$storeid and goods_name='$goods_name' and goods_name!='' and id!=$id limit 1");

            if($checkname){
                $res = array('code'=>4,'msg'=>'已有该产品');
                echo json_encode($res);exit;
            }
        }
    }

    $checkk = $db->get_var("select id from a_goods where storeid=$storeid and bar_code='$bar_code' and bar_code!='' limit 1");
    if($type==1){
        $checkname = $db->get_var("select id from a_goods where storeid=$storeid and goods_name='$goods_name' and goods_name!='' limit 1");

        if($checkname){
            $res = array('code'=>4,'msg'=>'已有该产品');
            echo json_encode($res);exit;
        }
        $check = $db->get_var("select id from a_goods where storeid=$storeid and goods_no='$goods_no' limit 1");
        if(!$check){
					if(!$checkk){
            $db->query("insert into a_goods(storeid,goods_no,goods_name,pic,price,category_id,warehouse,goods_unit,supplier_id,state,is_stop,bar_code,in_cost,goods_spec_format,remark,dateline) values('$storeid','$goods_no','$goods_name','$pic','$price',$category_id,'$warehouse','$goods_unit','$supplier_id',$state,$is_stop,'$bar_code',$in_cost,'$goods_spec_format','$remark',$dateline)");
            $goods_id = $db->insert_id;
            $db->query("insert into a_stock_goods_sku(storeid,goods_id,goods_no,goods_name,goods_cateid,`number`,dateline,updatetime) values($storeid,$goods_id,'$goods_no','$goods_name',$category_id,0,$dateline,$dateline)");
            //   echo $db->insert_id;exit;
            if($db->insert_id){
                $res = array('code'=>1,'msg'=>'添加成功');
                echo json_encode($res);
            }
        }else{
            $res = array('code'=>3,'msg'=>'条形码重复');
            echo json_encode($res);
				}
			}else{
					$res = array('code'=>2,'msg'=>'编号重复');
					echo json_encode($res);
			}
    }else{
        //echo "update a_staff set avatar='$avatar2',background='$background2',`name`='$name',sex=$sex,mobile=$mobile,`section`='$section',service_job='$service_job',job='$job',now_status='$now_status',yy_status='$yy_status',xxxyy='$xxxyy',tc_pro='$tc_pro',remark='$remark' where id=$id";exit;
        if(!$checkk || $checkk==$id){
					$db->query("update a_goods set goods_no='$goods_no',`goods_name`='$goods_name',pic='$pic',price='$price',category_id=$category_id,goods_unit='$goods_unit',supplier_id=$supplier_id,state=$state,is_stop=$is_stop,bar_code='$bar_code',goods_spec_format='$goods_spec_format',remark='$remark',in_cost=$in_cost where id=$id");
					$db->query("update a_stock_goods_sku set goods_no='$goods_no',`goods_name`='$goods_name',goods_cateid=$category_id where storeid=$storeid and goods_id=$id");
					$res = array('code'=>1,'msg'=>'更新成功');
					echo json_encode($res);
				}else{
					$res = array('code'=>3,'msg'=>'条形码重复');
					echo json_encode($res);
				}
    }

}


//产品列表
if($datatype=='get_goods_list'){
    //  $search = $_REQUEST['search'];
    $storeid = $_REQUEST['storeid'];
    $status = $_REQUEST['status'];//是否隐藏停用项目
    $status2 = $_REQUEST['status'];//是否隐藏下架项目
    $cate = $_REQUEST['cate']; //项目分类id
    $search = $_REQUEST['search'];//编号/产品名称
    $bar_code = $_REQUEST['bar_code'];
    $sql = "select a.*,a.goods_name as `name`,a.pic as img,b.title from a_goods as a left join a_goodscate as b on a.category_id=b.id where a.storeid=$storeid";
    if($status==1){ //隐藏停用=显示正常
        $sql .=" and is_stop=0";
    }
    if($status2==1){ //隐藏下架=显示正常
        $sql .=" and state=0";
    }
    if($cate>0){
        $sql .=" and category_id=$cate";
    }
    if($search!=''){
        $sql .=" and (goods_no like '%$search%' or `goods_name` like '%$search%')";
    }
    if($bar_code){
        $sql .=" and bar_code='$bar_code'";
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}


//添加套餐编辑
if($datatype=='insert_package'){
     $storeid = $_REQUEST['storeid'];
    //$storeid = 1;
    $type = $_REQUEST['type'];//1增加  2编辑
    $id = $_REQUEST['id'];//
 //   $package_no =$_REQUEST['package_no'];//编号
    $name = $_REQUEST['name'];//
    $pay_money = $_REQUEST['pay_money'];//支付金额
    $fact_money = $_REQUEST['fact_money'];//实际到账金额
    $usetime = $_REQUEST['usetime'];
    $is_stop =$_REQUEST['is_stop'];
    $starttime = $_REQUEST['starttime'];
    $endtime = $_REQUEST['endtime'];
    $itemsinfo = $_REQUEST['itemsinfo']; //次卡
    $goodsinfo= $_REQUEST['goodsinfo'];

    //var_dump($_REQUEST);exit;
    if($itemsinfo){
        $itemsinfo = json_encode($itemsinfo,JSON_UNESCAPED_UNICODE);

    }
    if($goodsinfo){
        $goodsinfo = json_encode($goodsinfo,JSON_UNESCAPED_UNICODE);
    }
    if($type==1){
        $check = $db->get_var("select id from a_package where storeid=$storeid and package_no='$package_no' limit 1");
        if(!$check){
            $db->query("insert into a_package(storeid,`name`,pay_money,fact_money,is_stop,starttime,endtime,itemsinfo,goodsinfo,dateline) values('$storeid','$name',$pay_money,$fact_money,$is_stop,'$starttime','$endtime','$itemsinfo','$goodsinfo',$dateline)");
            //   echo $db->insert_id;exit;
            if($db->insert_id){
                $res = array('code'=>1,'msg'=>'添加成功');
                echo json_encode($res);
            }
        }else{
            $res = array('code'=>2,'msg'=>'编号重复');
            echo json_encode($res);
        }

    }else{
        //echo "update a_staff set avatar='$avatar2',background='$background2',`name`='$name',sex=$sex,mobile=$mobile,`section`='$section',service_job='$service_job',job='$job',now_status='$now_status',yy_status='$yy_status',xxxyy='$xxxyy',tc_pro='$tc_pro',remark='$remark' where id=$id";exit;
        $db->query("update a_package set `name`='$name',pay_money='$pay_money',fact_money='$fact_money',is_stop=$is_stop,starttime='$starttime',endtime='$endtime',itemsinfo='$itemsinfo',goodsinfo='$goodsinfo' where id=$id");
        $res = array('code'=>1,'msg'=>'更新成功');
        echo json_encode($res);
    }

}


//套餐列表
if($datatype=='get_package_list'){
    //  $search = $_REQUEST['search'];
    $storeid = $_REQUEST['storeid'];
    $status = $_REQUEST['status'];//是否隐藏停用项目  1停用 0正常
    $search = $_REQUEST['search'];//编号/套餐名称
    $sql = "select * from a_package where storeid=$storeid and state=0";
    if($status==1){// 只显示正常
        $sql .=" and is_stop=0"; //is_stop=1 停用 2 不停用

    }
    if($search!=''){
        $sql .=" and (id like '%$search%' or `name` like '%$search%')";
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}
//删除套uuja
if($datatype=='del_package'){
   // $storeid = $_REQUEST['storeid'];
    //  $status = $_REQUEST['status'];//是否隐藏停用项目
    $id = $_REQUEST['id'];//编号/套餐名称
    $db->query("update a_package set state=1 where id=$id");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}


//买套餐
if($datatype=='buy_package'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $id= $_REQUEST['id'];//套餐id
    $pay_type = $_REQUEST['pay_type'];//zfb，wx,cash,card,other2
    $userid = $_REQUEST['userid'];
    $username = $_REQUEST['username'];
    $order_no = $_REQUEST['order_no'];
    if(!$order_no){
        $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    $user_id = $db->get_var("select user_id from fly_member where member_id=$member_id and storeid=$storeid");
    if(!$user_id){
        $user_id= 0;
    }
    $package = $db->get_row("select * from a_package where id=$id");
   /* if($package['goodsinfo']){
        $goodsinfo = json_decode($package['goodsinfo'],true);
        echo '<pre/>';
        print_r($goodsinfo);
    }
    exit;*/

    //echo "insert into a_card_memberitem(storeid,member_id,cicard_id,itemid,itemname,first_count,rest_count,dateline,expire_date,user_id) values ($storeid,$member_id,$id,'{$cicard['itemid']}','{$cicard['itemname']}',$first_count,$first_count,$dateline,'$expire_date',$user_id)";exit;
    $db->query("start transaction");
    $balance = $db->get_var("select balance from fly_member where member_id=$member_id and storeid=$storeid");

   /* if($pay_type=='card'){
        if($balance<$package['pay_money']){
            $db->query("rollback");
            $res = array('code'=>3,'msg'=>'会员余额不够');
            echo json_encode($res); exit;
        }
    }*/
    //插入member_package表
    $db->query("insert into a_member_package(storeid,member_id,package_id,package_name,pay_money,fact_money,itemsinfo,goodsinfo,dateline,pay_type,user_id) values ($storeid,$member_id,{$package['id']},'{$package['name']}',{$package['pay_money']},{$package['fact_money']},'{$package['itemsinfo']}','{$package['goodsinfo']}',$dateline,'$pay_type',$user_id)");

    if($pay_type=='card'){
            //更新用户信息
         $db->query("update fly_member set total_pay=total_pay+{$package['pay_money']},last_time=$dateline,instore_count=instore_count+1,balance=balance-{$package['pay_money']}+{$package['fact_money']} where member_id=$member_id and storeid=$storeid");
          $db->query("update a_card_member set price=price-{$package['pay_money']} where member_id=$member_id and shop_id=$storeid");
            //插入订单表，订单明细表，消费明细表
          $new_money = $balance-$package['pay_money'];
          $db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,member_id,status,dis_total,total,dateline,user_id,pay_type,paytime,sign) values ($storeid,'$order_no',2,2,$member_id,3,{$package['pay_money']},{$package['pay_money']},$dateline,$user_id,'$pay_type',$dateline,2)");
         $order_id = $db->insert_id;
         $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,price,discount_price,subtotal,dateline) values ($storeid,$order_id,'$order_no',5,{$package['id']},'{$package['name']}','{$package['pay_money']}',{$package['pay_money']},{$package['pay_money']},$dateline)");
         $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$balance-{$package['pay_money']},'-{$package['pay_money']}','$order_no','收银',$dateline,$user_id,'$pay_type')");
         if($package['fact_money']>0){
         $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance-{$package['pay_money']},$balance-{$package['pay_money']}+{$package['fact_money']},'+{$package['fact_money']}','$order_no','充值',$dateline,$user_id,'$pay_type')");
        }
				$list = $db->get_row("select * from a_order where id=$order_id");
    }else{ //不扣会员卡
        $integral = $db->get_var("select integral from fly_member where member_id=$member_id and storeid=$storeid");
        $db->query("update fly_member set integral=integral+{$package['pay_money']},total_pay=total_pay+{$package['pay_money']},last_time=$dateline,instore_count=instore_count+1,balance=balance+{$package['fact_money']} where member_id=$member_id and storeid=$storeid");
        $db->query("insert into mini_integral_list(user_id,member_id,shop_id,charge,balance,`desc`,dateline) values ($user_id,$member_id,$storeid,'+{$package['pay_money']}',$integral+{$package['pay_money']},'购买套餐',$dateline)");
        $db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,member_id,status,dis_total,total,dateline,user_id,pay_type,paytime,sign) values ($storeid,'$order_no',2,2,$member_id,3,{$package['pay_money']},{$package['pay_money']},$dateline,$user_id,'$pay_type',$dateline,2)");

        $order_id = $db->insert_id;
				$list = $db->get_row("select * from a_order where id=$order_id");
        $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,price,discount_price,subtotal,dateline) values ($storeid,$order_id,'$order_no',5,{$package['id']},'{$package['name']}','{$package['pay_money']}',{$package['pay_money']},{$package['pay_money']},$dateline)");
        $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$balance,'-{$package['pay_money']}','$order_no','收银',$dateline,$user_id,'$pay_type')");

        if($package['fact_money']>0){
            $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$balance+{$package['fact_money']},'+{$package['fact_money']}','$order_no','充值',$dateline,$user_id,'$pay_type')");
        }
    }
    if($package['goodsinfo']){
        $goodsinfo = json_decode($package['goodsinfo'],true);
        $stock_no =getStockNo($storeid,1);
        $out_date = date('Y-m-d');
        $outnum =  count($goodsinfo);
    //  echo "insert into a_stock_out(storeid,stock_no,out_date,out_type,warehouse,`number`,get_userid,useinfo,remark,checkman,check_time,dateline) values($storeid,'$stock_no','$out_date','赠送','总仓库',$outnum,$userid,'用户购买套餐','单号：$order_no','$username',$dateline,$dateline)";exit;



        $db->query("insert into a_stock_out(storeid,stock_no,out_date,out_type,warehouse,`number`,get_userid,useinfo,remark,checkman,check_time,dateline) values($storeid,'$stock_no','$out_date','赠送','总仓库',$outnum,$userid,'用户购买套餐','单号：$order_no','$username',$dateline,$dateline)");
        $newid = $db->insert_id;
        foreach($goodsinfo as $g){
            $total_out = $g['count']*$g['in_cost'];
            $db->query("insert into a_stock_out_detail(storeid,out_id,stock_no,goods_id,goods_name,`number`,in_cost,total) values($storeid,$newid,'$stock_no','{$g['id']}','{$g['goods_name']}','{$g['count']}','{$g['in_cost']}',$total_out)");

            //改库存表
            $skunum = $db->get_var("select `number` from a_stock_goods_sku where storeid=$storeid and goods_id={$g['id']}");
            $db->query("update a_stock_goods_sku set `number`=`number`-{$g['count']} where goods_id={$g['id']}");
            $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'出库','$stock_no',{$g['id']},$skunum,$skunum-{$g['count']},'-{$g['count']}','$checkman',$dateline)");

           // $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,num,price,discount_price,subtotal,is_usecard,card_memberitem_id,dateline) values ($storeid,$order_id,'$order_no','2','{$g['id']}','{$g['goods_name']}','{$g['count']}','{$g['price']}',{$g['price']},'{$g['subtotal']}',0,0,$dateline)");
        }
    }
    if($package['itemsinfo']){
        $ciinfo = json_decode($package['itemsinfo'],true);
        foreach ($ciinfo as $v){
            if($v['typeid']==1){
                $first_count =$v['count'];
                $expire_date = date('Y-m-d');
            }elseif ($v['typeid']==2){
                $num = $v['num'];
                $expire_date = date('Y-m-d 23:59:59',strtotime("+$num month"));
                $first_count = 0;
            }elseif ($v['typeid']==3){
                $num = $v['num']*3;
                $expire_date = date('Y-m-d 23:59:59',strtotime("+$num month"));
                $first_count = 0;
            }elseif ($v['typeid']==4){
                $num = $v['num'];
                $expire_date = date('Y-m-d 23:59:59',strtotime("+$num year"));
                $first_count = 0;
            }
            $db->query("insert into a_card_memberitem(storeid,member_id,cicard_id,itemid,itemname,first_count,rest_count,dateline,expire_date,user_id) values ($storeid,$member_id,{$v['id']},'{$v['itemid']}','{$v['itemname']}',$first_count,$first_count,$dateline,'$expire_date',$user_id)");
        }
    }
    if($db->insert_id){
        $db->query("commit");
        $res = array('code' => 1, 'msg' => '成功','list'=>$list);
    }else{
        $db->query("rollback");
        $res = array('code' => 2, 'msg' => '失败');
    }
    echo json_encode($res);

}
//套餐列表
if($datatype=='get_member_package'){
    //  $search = $_REQUEST['search'];
    $storeid = $_REQUEST['storeid'];
      $member_id = $_REQUEST['member_id'];

    $sql = "select *,FROM_UNIXTIME(dateline,'%Y-%m-%d %H:%i:%s')as addtime from a_member_package where storeid=$storeid and member_id=$member_id";
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//添加 卡套餐 编辑
if($datatype=='insert_card'){
     $storeid = $_REQUEST['storeid'];
    //$storeid = 1;
    $type = $_REQUEST['type'];//1增加  2编辑
    $id = $_REQUEST['id'];//
    $card_no =$_REQUEST['card_no'];//编号
    $name = $_REQUEST['name'];//
    $img = $_REQUEST['img'];
//    $deposit_amount= $_REQUEST['deposit_amount'];//储值金额
    $recharge_money= $_REQUEST['recharge_money'];//起充金额
    $usetime =$_REQUEST['usetime'];
    $item_discount = $_REQUEST['item_discount'];
    $goods_discount = $_REQUEST['goods_discount'];
    $gift_money = $_REQUEST['gift_money'];

    $deposit_amount=$recharge_money+$gift_money;


    if($type==1){
        $check = $db->get_var("select id from a_card where storeid=$storeid and card_no='$card_no' limit 1");
        if(!$check){

            $db->query("insert into a_card(storeid,card_no,`name`,img,deposit_amount,recharge_money,usetime,item_discount,goods_discount,dateline,gift_money) values('$storeid','$card_no','$name','$img','$deposit_amount','$recharge_money','$usetime','$item_discount','$goods_discount',$dateline,$gift_money)");
            //   echo $db->insert_id;exit;
            if($db->insert_id){
                $res = array('code'=>1,'msg'=>'添加成功');
                echo json_encode($res);
            }
        }else{
            $res = array('code'=>2,'msg'=>'编号重复');
            echo json_encode($res);
        }

    }else{
        //echo "update a_staff set avatar='$avatar2',background='$background2',`name`='$name',sex=$sex,mobile=$mobile,`section`='$section',service_job='$service_job',job='$job',now_status='$now_status',yy_status='$yy_status',xxxyy='$xxxyy',tc_pro='$tc_pro',remark='$remark' where id=$id";exit;
        $db->query("update a_card set card_no='$card_no',`name`='$name',img='$img',deposit_amount='$deposit_amount',recharge_money='$recharge_money',usetime='$usetime',item_discount='$item_discount',goods_discount='$goods_discount',gift_money=$gift_money where id=$id");
        $res = array('code'=>1,'msg'=>'更新成功');
        echo json_encode($res);
    }

}


//card列表
if($datatype=='get_card_list'){
    //  $search = $_REQUEST['search'];
    $storeid = $_REQUEST['storeid'];
    //  $status = $_REQUEST['status'];//是否隐藏停用项目
    $search = $_REQUEST['search'];//编号/名称
    $sql = "select * from a_card where storeid=$storeid and state=0";
    if($search!=''){
        $sql .=" and (card_no like '%$search%' or `name` like '%$search%')";
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}
//删除card
if($datatype=='del_card'){
    // $storeid = $_REQUEST['storeid'];
    //  $status = $_REQUEST['status'];//是否隐藏停用项目
    $id = $_REQUEST['id'];//编号/套餐名称
    $db->query("update a_card set state=1 where id=$id");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

/*
 * 生成入库出库单号
 * */
if($datatype=='get_stock_no'){
    $storeid = $_REQUEST['storeid'];
    //$storeid =1;
    $type = $_REQUEST['type'];//1入库 2出库 3盘点
    if($type==1){
        $str = substr(date('Ymd'),2);
        $str2 = $str.'0001';
        $addtime = strtotime(date('Y-m-d'));
        $endtime = $addtime+3600*24;
        $stock_no = $db->get_var("select stock_no from a_stock_into where storeid=$storeid and dateline>=$addtime and dateline<$endtime order by id desc limit 1");
        if($stock_no){
            $str2 =$stock_no+1;
        }
    }elseif($type==2){
        $str = substr(date('Ymd'),2);
        $str2 = $str.'0001';
        $addtime = strtotime(date('Y-m-d'));
        $endtime = $addtime+3600*24;
        $stock_no = $db->get_var("select stock_no from a_stock_out where storeid=$storeid and dateline>=$addtime and dateline<$endtime order by id desc limit 1");
        if($stock_no){
            $str2 =$stock_no+1;
        }
    }elseif($type==3){
        $str = substr(date('Ymd'),2);
        $str2 = $str.'0001';
        $addtime = strtotime(date('Y-m-d'));
        $endtime = $addtime+3600*24;
        $stock_no = $db->get_var("select stock_no from a_stock_pan where storeid=$storeid and dateline>=$addtime and dateline<$endtime order by id desc limit 1");
        if($stock_no){
            $str2 =$stock_no+1;
        }
    }
    $res = array('code'=>1,'msg'=>'成功','data'=>$str2);
    echo json_encode($res);
}
//生成订单号
if($datatype=='get_order_no'){
    $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $res = array('code'=>1,'msg'=>'成功','data'=>$order_no);
    echo json_encode($res);
}
//添加入库单
if($datatype=='insert_into_stock'){
    $storeid = $_REQUEST['storeid'];
    $checkman = $_REQUEST['checkman'];//操作人
  //  $checkman ='admin';
    $stock_no =$_REQUEST['stock_no'];
    $into_date = $_REQUEST['into_date'];
    $into_type = $_REQUEST['into_type'];
    $warehouse = $_REQUEST['warehouse'];

    $number = $_REQUEST['number'];
    $amount =$_REQUEST['amount'];
    $into_userid = $_REQUEST['into_userid'];//如果是调拨入库的话，就是来源店的storeid
    $remark = $_REQUEST['remark'];
    $goodsinfo = $_REQUEST['goodsinfo'];

    $out_id = $_REQUEST['out_id'];
    $db->query("insert into a_stock_into(storeid,stock_no,into_date,into_type,warehouse,`number`,amount,into_userid,remark,checkman,check_time,dateline) values($storeid,'$stock_no','$into_date','$into_type','$warehouse',$number,'$amount',$into_userid,'$remark','$checkman',$dateline,$dateline)");
    $newid = $db->insert_id;
    $db->query("start transaction");
    if($newid){
        foreach ($goodsinfo as $v){
            $db->query("insert into a_stock_into_detail(storeid,into_id,stock_no,goods_id,goods_name,`number`,in_cost,total,supplier,makedate) values($storeid,$newid,'$stock_no','{$v['id']}','{$v['goods_name']}','{$v['number']}','{$v['in_cost']}','{$v['total']}','{$v['supplier']}','{$v['makedate']}')");
            $check = $db->get_row("select id,`number` from a_stock_goods_sku where goods_id='{$v['id']}' and storeid=$storeid");
            if($check){
                $db->query("update a_stock_goods_sku set `number`=`number`+{$v['number']} where id={$check['id']}");
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'入库','$stock_no',{$v['id']},{$check['number']},{$check['number']}+{$v['number']},'+{$v['number']}','$checkman',$dateline)");
            }else{
                $goods_no = $db->get_var("select goods_no from a_goods where id={$v['id']}");
                $db->query("insert into a_stock_goods_sku(storeid,goods_id,goods_no,goods_name,goods_cateid,`number`,dateline,updatetime) values($storeid,{$v['id']},'$goods_no','{$v['goods_name']}',{$v['category_id']},{$v['number']},$dateline,$dateline)");
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'入库','$stock_no',{$v['id']},0,{$v['number']},'+{$v['number']}','$checkman',$dateline)");
            }

        }
        $db->query("commit");
        $res = array('code'=>1,'msg'=>'成功');
        echo json_encode($res);
    }else{
        $res = array('code'=>2,'msg'=>'失败');
        echo json_encode($res);
    }

}

//删除入库单
if($datatype=='del_stock_into'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
    $admin_role = $_REQUEST['role'];//权限
    $checkman = $_REQUEST['checkman'];
    if($admin_role==2 ||$admin_role==''){
        $res = array('code'=>2,'msg'=>'暂无权限操作');//非店长
        echo json_encode($res);exit;
    }else{
        $detail = $db->get_results("select * from a_stock_into_detail where into_id=$id");
        $db->query("start transaction");
        foreach ($detail as $v){
            $skunum = $db->get_var("select `number` from a_stock_goods_sku where goods_id={$v['goods_id']} and storeid=$storeid");
            /*if($skunum<$v['number']){
                $db->query("rollback");
                $res = array('code'=>3,'msg'=>'库存产品不足，无法撤销入库');//非店长
                echo json_encode($res);exit;
            }else{*/
                //删除库存单
                $db->query("delete from a_stock_into where id=$id");
                $db->query("delete from a_stock_into_detail where into_id=$id");

                //修改库存
                $new_num = $skunum-$v['number'];
                $db->query("update a_stock_goods_sku set `number`=$new_num where goods_id={$v['goods_id']} and storeid=$storeid");
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'取消入库','{$v['stock_no']}',{$v['goods_id']},$skunum,$new_num,'-{$v['number']}','$checkman',$dateline)");
                $newdetailid = $db->insert_id;
                if(!$newdetailid){
                    $db->query("rollback");
                }

           // }
        }
        $db->query("commit");
    }
    $res = array('code'=>1,'msg'=>'操作成功');//非店长
    echo json_encode($res);exit;
}

//删除出库单
if($datatype=='del_stock_out'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
    $admin_role = $_REQUEST['role'];//权限
    $checkman = $_REQUEST['checkman'];
    if($admin_role==2 ||$admin_role==''){
        $res = array('code'=>2,'msg'=>'暂无权限操作');//非店长
        echo json_encode($res);exit;
    }else{
        $detail = $db->get_results("select * from a_stock_out_detail where out_id=$id");
        $db->query("start transaction");
        foreach ($detail as $v){
            $skunum = $db->get_var("select `number` from a_stock_goods_sku where goods_id={$v['goods_id']} and storeid=$storeid");

                //修改库存
                $new_num = $skunum+$v['number'];
                $db->query("update a_stock_goods_sku set `number`=$new_num where goods_id={$v['goods_id']} and storeid=$storeid");
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'取消出库','{$v['stock_no']}',{$v['goods_id']},$skunum,$new_num,'+{$v['number']}','$checkman',$dateline)");
                //删除出库单以及明细
                $db->query("delete from a_stock_out where id=$id");
                $db->query("delete from a_stock_out_detail where out_id=$id");

        }
        $db->query("commit");
    }
    $res = array('code'=>1,'msg'=>'操作成功');//非店长
    echo json_encode($res);exit;
}







//增加出库单
if($datatype=='insert_out_stock'){
    $storeid = $_REQUEST['storeid'];
    $checkman = $_REQUEST['checkman'];//操作人
   // $checkman ='admin';
    $stock_no =$_REQUEST['stock_no'];
    $out_date = $_REQUEST['out_date'];
    $out_type = $_REQUEST['out_type'];
    $warehouse = $_REQUEST['warehouse'];
    $use = $_REQUEST['useinfo'];
    $number = $_REQUEST['number'];
    $amount =$_REQUEST['amount'];
   // $get_usertype = $_REQUEST['get_usertype'];
    $get_userid = $_REQUEST['get_userid'];
    $remark = $_REQUEST['remark'];
    $goodsinfo = $_REQUEST['goodsinfo'];
    $db_status = $_REQUEST['db_status']?$_REQUEST['db_status']:0;
    /*if($out_type=='调拨出库'){
        $db_status=1;
    }else{
        $db_status=0;
    }*/
//    var_dump($goodsinfo);exit;
//echo "insert into a_stock_out(storeid,stock_no,out_date,out_type,warehouse,`number`,amount,get_userid,useinfo,remark,checkman,check_time,dateline) values($storeid,'$stock_no','$out_date','$out_type','$warehouse',$number,'$amount', $get_userid,'$use','$remark','$checkman',$dateline,$dateline)";exit;
    $db->query("start transaction");
    $db->query("insert into a_stock_out(storeid,stock_no,out_date,out_type,warehouse,`number`,amount,get_userid,useinfo,remark,checkman,check_time,dateline,db_status) values($storeid,'$stock_no','$out_date','$out_type','$warehouse',$number,'$amount', $get_userid,'$use','$remark','$checkman',$dateline,$dateline,$db_status)");
    $newid = $db->insert_id;
    if($newid){
        foreach ($goodsinfo as $v){ //出库单信息
            $skunum= $db->get_var("select `number` from a_stock_goods_sku where goods_id={$v['goods_id']} and storeid=$storeid");
            /*if($skunum<$v['number']){
                $db->query("rollback");
                $res = array('code'=>3,'msg'=>'产品数量不足，无法出库');
                echo json_encode($res);exit;
            }else{*/
                $db->query("insert into a_stock_out_detail(storeid,out_id,stock_no,goods_id,goods_name,`number`,in_cost,total,supplier,makedate) values($storeid,$newid,'$stock_no','{$v['goods_id']}','{$v['goods_name']}','{$v['number']}','{$v['in_cost']}','{$v['total']}','{$v['supplier']}','{$v['makedate']}')");
                //改库存表
                /*if($skunum<0){
                    $db->query("update a_stock_goods_sku set `number`=`number`+{$v['number']} where goods_id={$v['goods_id']}");
                    $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'出库','$stock_no',{$v['goods_id']},$skunum,$skunum+{$v['number']},'-{$v['number']}','$checkman',$dateline)");
                }else{*/
              //  if($db_status==0){ //只有非调拨出库的时候可以直接减库存
                    $db->query("update a_stock_goods_sku set `number`=`number`-{$v['number']} where goods_id={$v['goods_id']}");
                    $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'$out_type','$stock_no',{$v['goods_id']},$skunum,$skunum-{$v['number']},'-{$v['number']}','$checkman',$dateline)");
             //   }

               // }


           // }

        }
        $db->query("commit");
        $res = array('code'=>1,'msg'=>'成功');
        echo json_encode($res);
    }else{
        $res = array('code'=>2,'msg'=>'失败');
        echo json_encode($res);
    }

}
//获取库存list
if($datatype=='get_skulist_test'){
    $storeid=$_REQUEST['storeid'];
    $search =$_REQUEST['search'];
    $category_id = $_REQUEST['cate'];
    $status = $_REQUEST['status'];//1 在售 0全部
    $type = $_REQUEST['type'];//1 是要全部，-的也要
    $bar_code = $_REQUEST['bar_code'];
    if($type==1){
        $sql ="select a.*,b.price,b.in_cost,goods_unit,c.title,b.pic,b.is_stop,b.state as img from a_stock_goods_sku as a inner join a_goods as b on b.id=a.goods_id inner join a_goodscate as c on c.id=a.goods_cateid where a.`storeid`=$storeid";
    }else{
        $sql ="select a.*,b.price,b.in_cost,goods_unit,c.title,b.pic,b.is_stop,b.state as img from a_stock_goods_sku as a inner join a_goods as b on b.id=a.goods_id inner join a_goodscate as c on c.id=a.goods_cateid where a.`number`>0";
    }
    if($search){
        $sql .= " and (a.goods_no like '%$search%' or a.goods_name like '%$search%')";
    }elseif($category_id){
        $sql .= " and goods_cateid='$category_id'";
    }
    if($bar_code){
        $sql .=" and b.bar_code='$bar_code'";
    }
    if($status==1){
        $sql .= " and b.is_stop=0 and b.state=0";
    }
   echo $sql;exit;
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}


//获取库存list
if($datatype=='get_skulist'){
    $storeid=$_REQUEST['storeid'];
    $search =$_REQUEST['search'];
    $category_id = $_REQUEST['cate'];
    $status = $_REQUEST['status'];//1 在售 0全部
    $type = $_REQUEST['type'];//1 是要全部，-的也要
    $bar_code = $_REQUEST['bar_code'];
    if($type==1){
        $sql ="select a.*,b.price,b.in_cost,goods_unit,c.title,b.pic,b.is_stop,b.state as img from a_stock_goods_sku as a inner join a_goods as b on b.id=a.goods_id inner join a_goodscate as c on c.id=a.goods_cateid where a.`storeid`=$storeid";
    }else{
        $sql ="select a.*,b.price,b.in_cost,goods_unit,c.title,b.pic,b.is_stop,b.state as img from a_stock_goods_sku as a inner join a_goods as b on b.id=a.goods_id inner join a_goodscate as c on c.id=a.goods_cateid where a.`number`>0";
    }
    if($search){
        $sql .= " and (a.goods_no like '%$search%' or a.goods_name like '%$search%')";
    }elseif($category_id){
        $sql .= " and goods_cateid='$category_id'";
    }
    if($bar_code){
        $sql .=" and b.bar_code='$bar_code'";
    }
    if($status==1){
        $sql .= " and b.is_stop=0 and b.state=0";
    }
 //   echo $sql;exit;
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}
//获取一张出库单，入库单表
if($datatype=='get_one_stock'){
    $sign = $_REQUEST['sign'];//1 入库 2出库
  //  $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
    if($sign==1){
        $list = $db->get_row("select a.*,b.name from a_stock_into a left join a_staff b on a.into_userid=b.id where a.id=$id");
        if($list){
            $list['goodsinfo'] =$db->get_results("select * from a_stock_into_detail where into_id={$list['id']}");

        }else{
            $list['goodsinfo'] =array();
        }
    }else{
        $list = $db->get_row("select a.*,b.name from a_stock_out a left join a_staff b on a.get_userid=b.id where a.id=$id");
        if($list){

            $list['goodsinfo'] =$db->get_results("select a.*,b.`number` as skunum from a_stock_out_detail as a left join a_stock_goods_sku as b on b.goods_id=a.goods_id where a.out_id={$list['id']}");

        }else{
            $list['goodsinfo'] =array();
        }
    }
    if($list){
        $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    }else{
        $res = array('code'=>2,'msg'=>'暂无数据');
    }

    echo json_encode($res);
}

//获取出库单，入库单表,盘点表
if($datatype=='get_stock_list'){
    $sign = $_REQUEST['sign'];//1 入库 2出库 3盘点
    $storeid = $_REQUEST['storeid'];
    $start = $_REQUEST['start'];
    $end = $_REQUEST['end'];
    $search = $_REQUEST['search'];

    if($sign==1){ //入库
        $sql ="select id,stock_no,into_date as sdate,into_type as stype,warehouse,amount,checkman,check_time,dateline from a_stock_into where storeid=$storeid";
        if($start!='' &&$end!=''){

            $starttime = strtotime($start);
            $endtime = strtotime($end)+3600*24;
            $sql .=" and dateline>=$starttime and dateline<$endtime";
        }

        $list = $db->get_results($sql);

        if($list){
            foreach ($list as &$v){
              //  echo "select goods_name,`number`,in_cost from a_stock_into_detail where into_id={$v['id']} and goods_name like '%$search%'";
                if($search){
                    $v['goodsinfo'] =$db->get_results("select goods_name,`number`,in_cost from a_stock_into_detail where into_id={$v['id']} and goods_name like '%$search%'");
                    if($v['goodsinfo']){
                        $list_new[] = $v;
                    }

                }else{
                    $v['goodsinfo'] =$db->get_results("select goods_name,`number`,in_cost from a_stock_into_detail where into_id={$v['id']}");

                }
            }
        }
    }elseif($sign==2){
        $sql ="select id,stock_no,out_date as sdate,out_type as stype,warehouse,amount,checkman,check_time,dateline,useinfo,db_status from a_stock_out where storeid=$storeid";
        if($start!='' &&$end!=''){
            $starttime = strtotime($start);
            $endtime = strtotime($end)+3600*24;
            $sql .=" and dateline>=$starttime and dateline<$endtime";
        }

        $list = $db->get_results($sql);
        if($list){
            foreach ($list as &$v){
                if($search){
                    $v['goodsinfo'] =$db->get_results("select goods_name,`number`,in_cost from a_stock_out_detail where out_id={$v['id']} and goods_name like '%$search%'");
                    if($v['goodsinfo']){
                        $list_new[] = $v;
                    }

                }else{
                    $v['goodsinfo'] =$db->get_results("select goods_name,`number`,in_cost from a_stock_out_detail where out_id={$v['id']}");

                }

            }
        }

    }elseif($sign==3){
        $sql ="select a.id,stock_no,pan_date,FROM_UNIXTIME(a.dateline,'%H:%i') as pan_time,warehouse,b.name from a_stock_pan a left join a_staff b on b.id=a.pan_userid where a.storeid=$storeid";
        if($start!='' &&$end!=''){
            $starttime = strtotime($start);
            $endtime = strtotime($end)+3600*24;
            $sql .=" and a.dateline>=$starttime and a.dateline<$endtime";
        }
        $list = $db->get_results($sql);
        if($list){
            foreach ($list as &$v){
                if($search){
                    $v['goodsinfo'] =$db->get_results("select goods_name,old_num,new_num,`cha` as `number` from a_stock_pan_detail where pan_id={$v['id']} and goods_name like '%$search%'");
                    if($v['goodsinfo']){
                        $list_new[] = $v;
                    }
                }else{
                    $v['goodsinfo'] =$db->get_results("select goods_name,old_num,new_num,`cha` as `number` from a_stock_pan_detail where pan_id={$v['id']}");

                }
            }
        }
    }
    if($search){
        if($list_new){
            $res = array('code'=>1,'msg'=>'成功','data'=>$list_new);
        }else{
            $res = array('code'=>2,'msg'=>'暂无数据');
        }
    }else{
        if($list){
            $res = array('code'=>1,'msg'=>'成功','data'=>$list);
        }else{
            $res = array('code'=>2,'msg'=>'暂无数据');
        }
    }


    echo json_encode($res);

}


//增加盘点
if($datatype=='insert_pan_stock'){
    $storeid = $_REQUEST['storeid'];
    $stock_no =$_REQUEST['stock_no'];
    $pan_date = $_REQUEST['pan_date'];
    $pan_userid = $_REQUEST['pan_userid'];
    $warehouse = $_REQUEST['warehouse'];
    $goodsinfo = $_REQUEST['goodsinfo'];
  //exit;
//echo "insert into a_stock_out(storeid,stock_no,out_date,out_type,warehouse,`number`,amount,get_userid,useinfo,remark,checkman,check_time,dateline) values($storeid,'$stock_no','$out_date','$out_type','$warehouse',$number,'$amount', $get_userid,'$use','$remark','$checkman',$dateline,$dateline)";exit;
    $db->query("start transaction");
    $db->query("insert into a_stock_pan(storeid,stock_no,pan_date,warehouse,pan_userid,dateline) values($storeid,'$stock_no','$pan_date','$warehouse',$pan_userid,$dateline)");
    $newid = $db->insert_id;
    if($newid){
        $stock_no1 =getStockNo($storeid,1);
        $stock_no2 =getStockNo($storeid,2);
        $into_date= date('Y-m-d');
        $out_date =$into_date;

        $checkman = $db->get_var("select `name` from a_staff where id=$pan_userid");
        //预写入库单
        $db->query("insert into a_stock_into(storeid,stock_no,into_date,into_type,warehouse,into_userid,checkman,check_time,dateline) values($storeid,'$stock_no1','$into_date','盘盈入库','总仓库',$pan_userid,'$checkman',$dateline,$dateline)");
        $into_id = $db->insert_id;
        $into_num = 0;
        $into_amount = 0;
        //预写出库单
        $db->query("insert into a_stock_out(storeid,stock_no,out_date,out_type,warehouse,get_userid,checkman,check_time,dateline) values($storeid,'$stock_no2','$out_date','盘亏出库','总仓库',$pan_userid,'$checkman',$dateline,$dateline)");
        $out_id = $db->insert_id;
        $out_num = 0;
        $out_amount = 0;
        foreach ($goodsinfo as $v){ //出库单信息
             $db->query("insert into a_stock_pan_detail(storeid,pan_id,stock_no,goods_id,goods_name,old_num,new_num,cha) values($storeid,$newid,'$stock_no','{$v['goods_id']}','{$v['goods_name']}',{$v['old_num']},'{$v['new_num']}','{$v['cha']}')");
                //改库存表
            $db->query("update a_stock_goods_sku set `number`={$v['new_num']} where goods_id={$v['goods_id']}");
            $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,dateline) values ($storeid,'盘点','$stock_no',{$v['goods_id']},{$v['old_num']},{$v['new_num']},'{$v['cha']}',$dateline)");
            if(strpos($v['cha'],'-')===0){//减少--->盘点出库
                $out_num++;
                $new_cha = substr($v['cha'],1);
                $total_out = $new_cha*$v['in_cost'];
                $out_amount+=$total_out;
                $db->query("insert into a_stock_out_detail(storeid,out_id,stock_no,goods_id,goods_name,`number`,in_cost,total) values($storeid,$out_id,'$stock_no2','{$v['goods_id']}','{$v['goods_name']}',$new_cha,'{$v['in_cost']}',$total_out)");
            }else{  //盘点入库
                $into_num++;
                $total_into = $v['cha']*$v['in_cost'];
                $into_amount +=$total_into;
                $db->query("insert into a_stock_into_detail(storeid,into_id,stock_no,goods_id,goods_name,`number`,in_cost,total) values($storeid,$into_id,'$stock_no1','{$v['goods_id']}','{$v['goods_name']}','{$v['cha']}','{$v['in_cost']}',$total_into)");
            }

        }
        if($into_num){
            $db->query("update a_stock_into set `number`=$into_num,amount=$into_amount where id=$into_id");
            $db->query("update a_stock_pan set stock_into_id=$into_id where id=$newid");
        }else{
            $db->query("delete from a_stock_into where id=$into_id");
        }
        if($out_num){
            $db->query("update a_stock_out set `number`=$out_num,amount=$out_amount where id=$out_id");
            $db->query("update a_stock_pan set stock_out_id=$out_id where id=$newid");
        }else{
            $db->query("delete from a_stock_out where id=$out_id");
        }
        $db->query("commit");
        $res = array('code'=>1,'msg'=>'成功');
        echo json_encode($res);
    }else{
        $res = array('code'=>2,'msg'=>'失败');
        echo json_encode($res);
    }

}

//获取单个盘点详情
if($datatype=='get_one_stockpan'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];//盘点id
    $list = $db->get_row("select * from a_stock_pan where id=$id");
    $arr = $db->get_results("select goods_no,a.goods_name,old_num,new_num,cha from a_stock_pan_detail as a left join a_staff as b on b.id=a.pan_userid where a.pan_id=$id");
    $list['goodsinfo'] = $arr;
    $res = array('code'=>1,'msg'=>'成功','list'=>$list);
    echo json_encode($res);
}


//删除盘点--->用不上啦
if($datatype=='del_stock_pan'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
        $detail = $db->get_results("select * from a_stock_pan_detail where pan_id=$id");
        $db->query("start transaction");
        foreach ($detail as $v){
            $skunum = $db->get_var("select `number` from a_stock_goods_sku where goods_id={$v['goods_id']} and storeid=$storeid");
           // echo $skunum;exit;
            $cha = substr($v['cha'],1);
            if($v['old_num']>$v['new_num']){
                $new_num = $skunum+$cha;
                $db->query("update a_stock_goods_sku set `number`=`number`+$cha where goods_id={$v['goods_id']} and storeid=$storeid");
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,dateline) values ($storeid,'取消盘点','{$v['stock_no']}',{$v['goods_id']},$skunum,$new_num,'+$cha',$dateline)");
                if(!($db->insert_id)){
                    $db->query("rollback");
                }
            }else{
                $new_num = $skunum-$cha;
                $db->query("update a_stock_goods_sku set `number`=`number`-$cha where goods_id={$v['goods_id']} and storeid=$storeid");
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,checkman,dateline) values ($storeid,'取消盘点','{$v['stock_no']}',{$v['goods_id']},$skunum,$new_num,'-$cha',$dateline)");
                if(!($db->insert_id)){
                    $db->query("rollback");
                }
            }
            //删除盘点单以及明细
            $db->query("delete from a_stock_pan where id=$id");
            $db->query("delete from a_stock_pan_detail where pan_id=$id");

        }
        $db->query("commit");
    $res = array('code'=>1,'msg'=>'操作成功');//非店长
    echo json_encode($res);exit;
}


//获取会员列表
if($datatype=='get_memberlist'){
    $storeid=$_REQUEST['storeid'];
    $search = $_REQUEST['search'];
    $page_size = $_REQUEST['page_size'];
    $page_num= ($_REQUEST['page_num']-1)*$page_size;
    $sign = $_REQUEST['sign'];//1除了删除的全部  2只要生效的
		$datetype = $_REQUEST['datetype']; //0-全部，1-本月生日， 2-三天内生日，3-本月未消费，4-七天未消费
    //$sql = "select a.*,c.img,c.name as cardtype,FROM_UNIXTIME(b.dateline,'%Y-%m-%d') as card_addtime from fly_member as a left join a_card_member as b on b.member_id=a.member_id left join a_card as c on c.id=b.card_id where a.storeid=$storeid and b.shop_id=$storeid";
    //$sql ="select a.*,FROM_UNIXTIME(a.dateline,'%Y-%m-%d') as card_addtime from fly_member a where a.storeid=$storeid and b.shop_id=$storeid";
    $sql ="select a.*,adt as card_addtime from fly_member a where a.storeid=$storeid";
    if($sign==1){
        $sql .= " and status<9";
    }else{
        $sql .= " and status<3";
    }
    if($search){
        $sql .= " and (a.name like '%$search%' or a.mobile like '%$search%')";
    }
    if($page_size>0){
        $sql .=" order by a.member_id desc limit $page_num,$page_size";
    }
		if($datetype&&$datetype>0){
			if($datetype==1){
				 $sql .=" and (Month(birthday1)=Month(NOW()))";
			}elseif($datetype==2){
				$sql .= " and ( DATE_FORMAT(birthday1,'%m-%d') BETWEEN DATE_FORMAT(NOW(),'%m-%d')
AND DATE_FORMAT(CURDATE()+INTERVAL 3 DAY,'%m-%d'))";
			}elseif($datetype==3){
				$sql .=" and (DATE_FORMAT(FROM_UNIXTIME(last_time),'%y-%m')!=DATE_FORMAT(NOW(),'%y-%m'))";
			}elseif($datetype==4){
				$sql .=" and (!(DATE_FORMAT(FROM_UNIXTIME(last_time),'%y-%m-%d') BETWEEN DATE_FORMAT(NOW(),'%y-%m-%d') AND DATE_FORMAT(NOW()+INTERVAL 7 DAY,'%y-%m-%d')))";
			}
		};
  //  echo $sql;exit;
    $list = $db->get_results($sql);
    $sql = str_replace('select ','select count(a.member_id) as ncount,',$sql);
    $count = $db->get_row($sql);
    $count = $count['ncount'];
    $res = array('code'=>1,'msg'=>'成功','data'=>$list,'count'=>$count);
    echo json_encode($res);
}


//获取会员基础信息
if($datatype=='get_one_member'){
    $storeid=$_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $list= $db->get_row("select account,signbill,`name`,b.sex,a.mobile,last_time,card_num,remark,instore_count,total_pay,balance,b.IDcard,a.birthday1 as birthday,avatar from fly_member as a left join users_miniprogram as b on b.id=a.user_id where a.member_id=$member_id and a.storeid=$storeid");
    $list['gift_money'] = $db->get_var("select sum(gift_money) from a_member_moneydetail where member_id=$member_id and storeid=$storeid");
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//查询预约
if($datatype=='get_yylist'){
     $storeid = $_REQUEST['storeid'];
    $staffid =$_REQUEST['staffid'];
    $time = $_REQUEST['time'];//2020-07-10 11:00:00
    $expire = $dateline-3600;
    $mobile = $_REQUEST['mobile'];
    $db->query("update a_yy set status=3 where storeid=$storeid and status in(0,4) and $expire>UNIX_TIMESTAMP(yytime)");//改过期
  //  echo "update a_yy set status=3 where storeid=$storeid and status in(0,4) and UNIX_TIMESTAMP($expire)>yytime";exit;
    $db->query("update a_yy2 set status=3 where storeid=$storeid and status in(0,4) and $expire>UNIX_TIMESTAMP(yytime)");//改过期
    $sql = "select * from a_yy where storeid=$storeid";
    if($staffid!=''){
        $sql .=" and staffid=$staffid";
    }
    if($time!=''){
        $sql .=" and yytime like '$time%'";
    }
    if($mobile!=''){
        $sql .=" and mobile like '%$mobile%'";
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

if($datatype=='get_yy2_list'){
    $storeid = $_REQUEST['storeid'];
    $staffid =$_REQUEST['staffid'];
   // $time = $_REQUEST['time'];//2020-07-10 11:00:00
   // $expire = $dateline-3600;
  //  $mobile = $_REQUEST['mobile'];
  //  $db->query("update a_yy set status=3 where storeid=$storeid and status in(0,4) and $expire>UNIX_TIMESTAMP(yytime)");//改过期
    //  echo "update a_yy set status=3 where storeid=$storeid and status in(0,4) and UNIX_TIMESTAMP($expire)>yytime";exit;
   // $db->query("update a_yy2 set status=3 where storeid=$storeid and status in(0,4) and $expire>UNIX_TIMESTAMP(yytime)");//改过期
    $sql = "select * from a_yy2 where storeid=$storeid";
    if($staffid!=''){
        $sql .=" and staffid=$staffid";
    }
    $sql .=" order by id desc";
    /*if($time!=''){
        $sql .=" and yytime like '$time%'";
    }
    if($mobile!=''){
        $sql .=" and mobile like '%$mobile%'";
    }*/
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);

}
//增加预约
if($datatype=='insert_yy'){
    $type = $_REQUEST['type'];//1 预约 2占用
    $storeid = $_REQUEST['storeid'];
    $checkman = $_REQUEST['checkman'];
    $yytime = $_REQUEST['yytime'];
    $yytime2 = date('Y-m-d H:i:s',strtotime($yytime)-1800);
    $yytime3 = date('Y-m-d H:i:s',strtotime($yytime)+1800);
    if($type==1){
        $customer_type = $_REQUEST['customer_type'];//1 散客 2会员
        $member_id = $_REQUEST['member_id'];
        $staffid = $_REQUEST['staffid'];
        $staff = $_REQUEST['staff'];

        $itemid = $_REQUEST['itemid'];
        $itemname = $_REQUEST['itemname'];
        $price = $_REQUEST['price'];
        $remark = $_REQUEST['remark'];
        $name = $_REQUEST['name'];
        $mobile = $_REQUEST['mobile'];
        if($customer_type==2&&$member_id>0){
          //  $user_id = $db->get_var("select id from users_miniprogram where member_id=$member_id");
            $user_id = $db->get_var("select user_id from fly_member where member_id=$member_id");
            if(!$user_id){
                $user_id =0;
            }
        }else{
            $user_id =0;
        }

        $check = $db->get_var("select id from a_yy where storeid=$storeid and staffid=$staffid and yytime in('$yytime','$yytime2','$yytime3') and status in(0,4)");
        if($check){
            $res = array('code'=>3,'msg'=>'该员工时间段已被预约');
            echo json_encode($res);exit;
        }
        $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        // echo "insert into a_yy(order_no,storeid,customer_type,member_id,user_id,`name`,mobile,staffid,staff,yytime,itemid,itemname,price,remark,dateline) values('$order_no','$storeid','$customer_type','$member_id',$user_id,'$name','$mobile',$staffid,'$staff','$yytime',$itemid,'$itemname','$price','$remark',$dateline)";exit;
        $db->query("insert into a_yy(order_no,storeid,customer_type,member_id,user_id,`name`,mobile,staffid,staff,yytime,itemid,itemname,price,remark,dateline,checkman) values('$order_no','$storeid','$customer_type','$member_id',$user_id,'$name','$mobile',$staffid,'$staff','$yytime',$itemid,'$itemname','$price','$remark',$dateline,'$checkman')");
        $db->query("insert into a_yy2(order_no,storeid,customer_type,member_id,user_id,`name`,mobile,staffid,staff,yytime,itemid,itemname,price,remark,dateline,checkman) values('$order_no','$storeid','$customer_type','$member_id',$user_id,'$name','$mobile',$staffid,'$staff','$yytime',$itemid,'$itemname','$price','$remark',$dateline,'$checkman')");
        if($db->insert_id){
            $res = array('code'=>1,'msg'=>'成功');
        }else{
            $res = array('code'=>2,'msg'=>'失败');
        }
        echo json_encode($res);
    }else{
        $staffid = $_REQUEST['staffid'];
        $staff = $_REQUEST['staff'];
   //     $check = $db->get_var("select id from a_yy where storeid=$storeid and yytime='$yytime' and staffid=$staffid and status in(0,4)");
        $check = $db->get_var("select id from a_yy where storeid=$storeid and staffid=$staffid and yytime in('$yytime','$yytime2','$yytime3') and status in(0,4)");
        if($check){
            $res = array('code'=>3,'msg'=>'该员工时间段已被预约');
            echo json_encode($res);exit;
        }
        $db->query("insert into a_yy(storeid,staffid,staff,yytime,dateline,status,checkman) values('$storeid',$staffid,'$staff','$yytime',$dateline,4,'$checkman')");
        $db->query("insert into a_yy2(storeid,staffid,staff,yytime,dateline,status,checkman) values('$storeid',$staffid,'$staff','$yytime',$dateline,4,'$checkman')");
        if($db->insert_id){
            $res = array('code'=>1,'msg'=>'成功');
        }else{
            $res = array('code'=>2,'msg'=>'失败');
        }
        echo json_encode($res);

    }


}


//取消预约
if($datatype=='cancel_yy'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
    $remark = $_REQUEST['remark'];
    $db->query("update a_yy2 set status=2,remark='$remark' where id=$id");
   $db->query("delete from a_yy where id=$id");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

//查询会员的次卡信息
if($datatype=='get_card_memberitem'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $sign = $_REQUEST['sign'];//1 可用  2 用完（已用） 3过期
    $sql ="select a.*,b.typeid from a_card_memberitem as a left join a_cicard as b on b.id=a.cicard_id where a.storeid=$storeid and a.member_id=$member_id";

    if($sign){
        if($sign==1){
            $sql .=" and (rest_count>0 or UNIX_TIMESTAMP(expire_date)>$dateline)";
        }elseif ($sign==2){
            $sql .=" and rest_count=0 and b.typeid=1";
        }elseif ($sign==3){
            $sql .=" and b.typeid>1 and $dateline>UNIX_TIMESTAMP(expire_date)";
        }
    }
    //echo $sql;exit;
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//查询会员的折扣
if($datatype=='get_member_discount'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    //echo "select a.card_num,b.name as cardname,b.img,b.usetime,b.item_discount,goods_discount from a_card_member as a left join a_card as b on b.id=a.card_id where a.shop_id=$storeid and member_id=$member_id";
    $list = $db->get_row("select a.card_num,b.name as cardname,b.img,b.usetime,b.item_discount,goods_discount from a_card_member as a left join a_card as b on b.id=a.card_id where a.shop_id=$storeid and member_id=$member_id");
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//查询会员的账户信息
if($datatype=='get_member_moneydetail'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $type = $_REQUEST['type'];//1充值  2消费
    $sql = "select * from a_member_moneydetail where member_id=$member_id and storeid=$storeid";
    if($type){
        if($type==1){
            $sql .=" and (type like'充值%' or type='卖卡' or type like '会员卡%')";
        }else{
            $sql .=" and type='收银'";
        }
				$sql .=" order by dateline desc";
    }
    $list = $db->get_results($sql);
		if($type==2){
		    if($list){
                foreach($list as &$v){
                    $v['info'] = $db->get_results("select * from a_order_detail where order_no='{$v['order_no']}' and storeid=$storeid");
            }

		}
		}
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);

}
//开单
if($datatype=='insert_order'){
    $type = $_REQUEST['type'];//1 新建  2 编辑
    $id = $_REQUEST['id'];
    $storeid = $_REQUEST['storeid'];
    $customer_type = $_REQUEST['customer_type'];//1 散客 2会员
  //  $customer_type2 = $_REQUEST['customer_type2'];//1新客  2 老客
   // $member_id = $_REQUEST['member_id'];
    $status = $_REQUEST['status'];
    $total = $_REQUEST['total'];
    $dis_total = $_REQUEST['dis_total'];
    $remark = $_REQUEST['remark'];
    $orderinfo = $_REQUEST['orderinfo'];
    $member_id = $_REQUEST['member_id'];

  /*  echo '<pre/>';
    var_dump($orderinfo);exit;*/
    if($customer_type==1){
        $customer_type2=1;
    }elseif($customer_type==2){
            $customer_type2=2;
    }

    if($type==1){  //新建订单
        if($storeid && $orderinfo){
            $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(0000,1111),4,0,STR_PAD_LEFT);
        }
        if($customer_type==2){

         //   $user_id = $db->get_var("select id from users_miniprogram where member_id=$member_id");
            $user_id = $db->get_var("select user_id from fly_member where member_id=$member_id");
        if(!$user_id){
            $user_id=0;
        }
				$db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,member_id,status,dis_total,total,remark,dateline,user_id) values ($storeid,'$order_no','$customer_type',$customer_type2,'$member_id',$status,$dis_total,$total,'$remark',$dateline,$user_id)");
        }else{
            $user_id =0;
            $db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,status,dis_total,total,remark,dateline) values ($storeid,'$order_no','$customer_type',$customer_type2,$status,$dis_total,$total,'$remark',$dateline)");
        }
        $order_id = $db->insert_id;
        $list = $db->get_row("select * from a_order where id=$order_id");
      //  if($status==1){
            foreach($orderinfo as $v){
                // if($v['is_usecard']==1){
                //     $v['staff1']=0;
                // }
                if($v['is_usecard']==0){
                    $v['cikaid']=0;
                }

                $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,num,price,discount_price,staff1,subtotal,is_usecard,card_memberitem_id,dateline,discount) values ($storeid,$order_id,'$order_no','{$v['typeid']}','{$v['itemid']}','{$v['itemname']}','{$v['num']}','{$v['price']}','{$v['discount_price']}','{$v['staff1']}','{$v['subtotal']}',{$v['is_usecard']},{$v['cikaid']},$dateline,{$v['discount']})");
                if($v['typeid']==3){
                    $db->query("update a_yy set status=1 where id={$v['itemid']}");
                    $db->query("update a_yy2 set status=1 where id={$v['itemid']}");
                }
            }

    }elseif($type==2 &&$id>0){ //编辑
        $user_id = $db->get_var("select user_id from fly_member where member_id=$member_id");
        if(!$user_id){
            $user_id=0;
        }
        $db->query("update a_order set customer_type=$customer_type,customer_type2=$customer_type2,member_id=$member_id,status=$status,total=$total,dis_total=$dis_total,user_id=$user_id,remark='$remark' where id=$id");
       // echo "update a_order set customer_type=$customer_type,customer_type2=$customer_type2,member_id=$member_id,status=$status,total=$total,dis_total=$dis_total where id=$id";exit;
        $order_no = $db->get_var("select order_no from a_order where id=$id limit 1");
       // echo "delete from a_order_detail where order_id=$id";exit;
        $db->query("delete from a_order_detail where order_id=$id");
       // if($status==1){
        if($orderinfo){
            foreach($orderinfo as $v){
                // if($v['is_usecard']==1){
                //     $v['staff1']=0;
                // }
                if($v['is_usecard']==0){
                    $v['cikaid']=0;
                }


                $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,num,price,discount_price,staff1,subtotal,is_usecard,card_memberitem_id,dateline,discount) values ($storeid,$id,'$order_no','{$v['typeid']}','{$v['itemid']}','{$v['itemname']}','{$v['num']}','{$v['price']}','{$v['discount_price']}','{$v['staff1']}','{$v['subtotal']}',{$v['is_usecard']},{$v['cikaid']},$dateline,{$v['discount']})");
                if($v['typeid']==3){
                    $db->query("update a_yy set status=1 where id={$v['itemid']}");
                    $db->query("update a_yy2 set status=1 where id={$v['itemid']}");
                }
            }
        }

        $list = $db->get_row("select * from a_order where id=$id");
    }

    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);

}
//更新订单详情
if($datatype=='update_orderitem_detail'){
    $storeid = $_REQUEST['storeid'];
    $id =$_REQUEST['id'];
    $staff1=intval($_REQUEST['staff1']);
    $num = $_REQUEST['num'];
    $subtotal = $_REQUEST['subtotal'];
    $old=$db->get_row("select order_no,subtotal from a_order_detail where id=$id");
    $db->query("update a_order_detail set staff1=$staff1,num=$num,subtotal=$subtotal where id=$id");
    if($subtotal>$old['subtotal']){
        $cha = $subtotal-$old['subtotal'];
 //       echo "update a_order set total=total+$cha where storeid=$storeid and order_no='{$old['order_no']}'";
        $db->query("update a_order set total=total+$cha where storeid=$storeid and order_no='{$old['order_no']}'");
    }elseif($subtotal<$old['subtotal']){//减少消费
        $cha = $old['subtotal']-$subtotal;
        $db->query("update a_order set total=total-$cha where storeid=$storeid and order_no='{$old['order_no']}'");
    }
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}
//更新订单备注
if($datatype=='update_order_remark'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];//order表id
    //  $customer_type2 = $_REQUEST['customer_type2'];//1新客  2 老客
    $remark = $_REQUEST['remark'];
    $old_order = $db->query("update a_order set remark='$remark' where id=$id");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

//更新订单备注
if($datatype=='update_member_remark'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['member_id'];//order表id
    $remark = $_REQUEST['remark'];
    $old_member = $db->query("update fly_member set remark='$remark' where member_id=$id");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}


//更新折扣价格
if($datatype=='update_order_distotal'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];//order表id
    $dis_total = $_REQUEST['dis_total'];
    $old_order = $db->query("update a_order set dis_total=$dis_total where id=$id");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

//取消订单
if($datatype=='cancel_order'){
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];//order表id
    //  $customer_type2 = $_REQUEST['customer_type2'];//1新客  2 老客
    $old_order = $db->query("update a_order set status=4 where id=$id");
 //  $db->query("delete from a_order where id=$id");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

if($datatype=='get_one_order'){  //
//    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['order_id'];//order表id
    //  $customer_type2 = $_REQUEST['customer_type2'];//1新客  2 老客
    $order = $db->get_row("select * from a_order where id=$id or order_no='$id'");
    $arr = $db->get_results("select * from a_order_detail where order_id={$order['id']}");
    foreach($arr as &$v){
        if($v['typeid']==2){
            $v['in_cost'] = $db->get_var("select in_cost from a_goods where id={$v['itemid']}");
        }
    }
    $order['info']  = $arr;
    //  $db->query("delete from a_order where id=$id");
    $res = array('code'=>1,'msg'=>'成功','list'=>$order);
    echo json_encode($res);
}

if($datatype=='get_one_orderByNo'){  //
		$storeid = $_REQUEST['storeid'];
    $order_no = $_REQUEST['order_no'];//order表id
    //  $customer_type2 = $_REQUEST['customer_type2'];//1新客  2 老客
    $order = $db->get_row("select * from a_order where order_no='$order_no' and storeid=$storeid");
    $arr = $db->get_results("select * from a_order_detail where order_id={$order['id']}");
    // foreach($arr as &$v){
    //     if($v['typeid']==2){
    //         $v['in_cost'] = $db->get_var("select in_cost from a_goods where id={$v['itemid']}");
    //     }
    // }
    $order['info']  = $arr;
    //  $db->query("delete from a_order where id=$id");
    $res = array('code'=>1,'msg'=>'成功','list'=>$order);
    echo json_encode($res);
}

//结账
if($datatype=='pay_order'){
    $storeid = $_REQUEST['storeid'];
    $order_id = $_REQUEST['order_id'];
    $pay_type = $_REQUEST['pay_type'];

		//混合支付
    $full_price = $_REQUEST['full_price'];//会员使用了非会员支付方式
    $v_amount = $_REQUEST['v_amount'];//抵用券金额
    $v_id = $_REQUEST['v_id'];//member_抵用券id
		
		$mixedinfo_json= $_REQUEST['mixedinfo'];//混合支付详细
		$mixedinfo=json_decode($mixedinfo_json,true);
    $order = $db->get_row("select * from a_order where id=$order_id");
    $detailarr = $db->get_results("select * from a_order_detail where order_id=$order_id");

    if($order['customer_type']==1 && $order['member_id']==0){  //非会员
     //   echo "update a_order set status=3,pay_type='$pay_type',paytime=$dateline where id=$order_id";exit;
			$db->query("update a_order set status=3,pay_type='$pay_type',mixedinfo='$mixedinfo_json',paytime=$dateline where id=$order_id");
			if($pay_type=='mixed'){
				foreach($mixedinfo as $k=>$v){
					$money=round($v, 2);
					if($money>0){
						$sql="insert into a_order_pay_type (order_id,pay_type,pay_money) values ($order_id, '{$k}','{$money}')";
						$db->query($sql);
					}
					
				}				
			}
			//die();
        foreach ($detailarr as $v){
            if($v['typeid']==2){//产品
                $goods_sku= $db->get_var("select `number` from a_stock_goods_sku where storeid=$storeid and goods_id={$v['itemid']}");
                /*if($goods_sku<$v['num']){
                    $res = array('code'=>4,'msg'=>'产品库存不足');
                    echo json_encode($res); exit;
                }*/
                //改库存表
                $db->query("update a_stock_goods_sku set `number`=`number`-{$v['num']} where goods_id={$v['itemid']}");
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,dateline) values ($storeid,'出库','{$order['order_no']}',{$v['itemid']},$goods_sku,$goods_sku-{$v['num']},'-{$v['num']}',$dateline)");
            }
        }

    }else{ //会员
			$balance = $db->get_var("select balance from fly_member where member_id={$order['member_id']} and storeid=$storeid");
        $user_id = $db->get_var("select user_id from fly_member where member_id={$order['member_id']} and storeid=$storeid");
        if(!$user_id){
            $user_id=0;
        }
			if($pay_type=='mixed'){
				foreach($mixedinfo as $k=>$v){
					$money=round($v, 2);
					if($money>0){
						$sql="insert into a_order_pay_type (order_id,pay_type,pay_money) values ($order_id, '{$k}','{$money}')";
						$db->query($sql);
					}
				}
			}else{
				
        if($pay_type=='card'){
            if($balance<$order['dis_total']){
                $res = array('code'=>3,'msg'=>'会员余额不够');
                echo json_encode($res); exit;
            }
        }
			}
        

    //    $db->query("start transaction");
        //查询次卡次数
        $detailarr = $db->get_results("select * from a_order_detail where order_id=$order_id");
        $count =0;
        foreach ($detailarr as $v){
            if($v['typeid']==2){//产品
                $goods_sku= $db->get_var("select `number` from a_stock_goods_sku where storeid=$storeid and goods_id={$v['itemid']}");
                /*if($goods_sku<$v['num']){
                    $res = array('code'=>4,'msg'=>'产品库存不足');
                    echo json_encode($res); exit;
                }*/
             //   echo "update a_stock_goods_sku set `number`=`number`-{$v['num']} where goods_id={$v['itemid']}";exit;
                //改库存表
                $db->query("update a_stock_goods_sku set `number`=`number`-{$v['num']} where goods_id={$v['itemid']}");
            //    echo "insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,dateline) values ($storeid,'出库','{$order['order_no']}',{$v['itemid']},$goods_sku,$goods_sku-{$v['num']},'-{$v['num']}',$dateline)";
                $db->query("insert into a_stock_goods_skudetail(storeid,`type`,stock_no,goods_id,old_num,new_num,`change`,dateline) values ($storeid,'出库','{$order['order_no']}',{$v['itemid']},$goods_sku,$goods_sku-{$v['num']},'-{$v['num']}',$dateline)");
            }
            if($v['is_usecard']==1){//次卡
                $cicard = $db->get_row("select a.rest_count,b.typeid from a_card_memberitem a left join a_cicard b on b.id=a.cicard_id where a.id={$v['card_memberitem_id']}");
                if($cicard['typeid']==1){
                    if($cicard['rest_count']<$v['num']){
                        $res = array('code'=>2,'msg'=>'会员次卡次数不够');
                        echo json_encode($res); exit;
                    }else{  //使用会员卡并且剩下次数充足

                        $old_ci = $cicard['rest_count'];

                        $new_ci = $old_ci-$v['num'];
                     //   echo "update a_card_memberitem set rest_count=rest_count-{$v['num']} where id={$v['card_memberitem_id']}";
                    //    echo "------insert into a_member_cidetail(storeid,member_id,itemid,old_ci,new_ci,`change`,order_no,`type`,dateline,user_id) values($storeid,{$order['member_id']},{$v['itemid']},$old_ci,$new_ci,'-{$v['num']}','{$order['order_no']}','收银',$dateline,$user_id)";exit;
                        $db->query("update a_card_memberitem set rest_count=rest_count-{$v['num']} where id={$v['card_memberitem_id']}");
                        $db->query("insert into a_member_cidetail(storeid,member_id,itemid,old_ci,new_ci,`change`,order_no,`type`,dateline,user_id) values($storeid,{$order['member_id']},{$v['itemid']},$old_ci,$new_ci,'-{$v['num']}','{$order['order_no']}','收银',$dateline,$user_id)");
                    }
                }

            }
        }
        $db->query("update a_order set status=3,pay_type='$pay_type',mixedinfo='$mixedinfo_json',paytime=$dateline where id=$order_id");
				if($pay_type=='mixed'){
					foreach($mixedinfo as $k=>$v){
						$paytype=$k;
						$new_price=round($v, 2);
						if($new_price>0){
							if($paytype=='signbill'){  //签单
									$db->query("start transaction");
									
									$db->query("insert into a_member_signbill_list(storeid,member_id,order_no,money,dateline) values({$order['storeid']},{$order['member_id']},'{$order['order_no']}','{$new_price}',$dateline)");
									if($db->insert_id){
											$db->query("update fly_member set total_pay=total_pay+$new_price,last_time=$dateline,instore_count=instore_count+1,signbill=signbill+$new_price where member_id={$order['member_id']} and storeid=$storeid");
											$db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$balance,$balance,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'signbill')");
											$db->query("commit");
									}else{
											$db->query("rollback");
									}

							}elseif($paytype=='card'){
									if($v_id && $v_amount){ //只有会员卡支付才能有抵用券
											//if($v_amount>$order['dis_total']){
											//		$v_amount=$order['dis_total'];
											//}
											$db->query("update a_member_voucher set order_id=$order_id,status=2 where id=$v_id");
											$db->query("update a_order set dis_total=dis_total-$v_amount where id=$order_id");
									}
									//$new_price = $db->get_var("select dis_total from a_order where id=$order_id");
									$db->query("update fly_member set total_pay=total_pay+$new_price,last_time=$dateline,instore_count=instore_count+1,balance=balance-$new_price where member_id={$order['member_id']} and storeid=$storeid");
									$db->query("update a_card_member set price=price-$new_price where member_id={$order['member_id']} and shop_id=$storeid");


									 $old_money = $balance;

									$new_money = $old_money-$new_price;
									//echo "insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$old_money,$new_money,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'card')";
									$db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$old_money,$new_money,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'card')");

							}else{  //其他方式结账
									if($full_price){
											$db->query("update a_order set dis_total=$full_price where id=$order_id");
									}
									//$new_price = $db->get_var("select dis_total from a_order where id=$order_id");
									$integral = $db->get_var("select integral from fly_member where member_id={$order['member_id']} and storeid=$storeid");
									$db->query("update fly_member set integral=integral+$new_price,total_pay=total_pay+$new_price,last_time=$dateline,instore_count=instore_count+1 where member_id={$order['member_id']} and storeid=$storeid");
									$db->query("insert into mini_integral_list(user_id,member_id,shop_id,charge,balance,`desc`,dateline) values ($user_id,{$order['member_id']},$storeid,'+$new_price',$integral+$new_price,'门店消费',$dateline)");
									$db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$balance,$balance,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'$paytype')");
							}
						}
					}
				}else{

					if($pay_type=='signbill'){  //签单
							$db->query("start transaction");
							if($full_price){
									$db->query("update a_order set dis_total=$full_price where id=$order_id");
							}
							$new_price = $db->get_var("select dis_total from a_order where id=$order_id");
							$db->query("insert into a_member_signbill_list(storeid,member_id,order_no,money,dateline) values({$order['storeid']},{$order['member_id']},'{$order['order_no']}',$new_price,$dateline)");
							if($db->insert_id){
									$db->query("update fly_member set total_pay=total_pay+$new_price,last_time=$dateline,instore_count=instore_count+1,signbill=signbill+$new_price where member_id={$order['member_id']} and storeid=$storeid");
									$db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$balance,$balance,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'signbill')");
									$db->query("commit");
							}else{
									$db->query("rollback");
							}

					}elseif($pay_type=='card'){
							if($v_id && $v_amount){ //只有会员卡支付才能有抵用券
									if($v_amount>$order['dis_total']){
											$v_amount=$order['dis_total'];
									}
									$db->query("update a_member_voucher set order_id=$order_id,status=2 where id=$v_id");
									$db->query("update a_order set dis_total=dis_total-$v_amount where id=$order_id");
							}
							$new_price = $db->get_var("select dis_total from a_order where id=$order_id");
							$db->query("update fly_member set total_pay=total_pay+$new_price,last_time=$dateline,instore_count=instore_count+1,balance=balance-$new_price where member_id={$order['member_id']} and storeid=$storeid");
							$db->query("update a_card_member set price=price-$new_price where member_id={$order['member_id']} and shop_id=$storeid");


							 $old_money = $balance;

							$new_money = $old_money-$new_price;
							//echo "insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$old_money,$new_money,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'card')";
							$db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$old_money,$new_money,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'card')");

					}else{  //其他方式结账
							if($full_price){
									$db->query("update a_order set dis_total=$full_price where id=$order_id");
							}
							$new_price = $db->get_var("select dis_total from a_order where id=$order_id");
							$integral = $db->get_var("select integral from fly_member where member_id={$order['member_id']} and storeid=$storeid");
							$db->query("update fly_member set integral=integral+$new_price,total_pay=total_pay+$new_price,last_time=$dateline,instore_count=instore_count+1 where member_id={$order['member_id']} and storeid=$storeid");
							$db->query("insert into mini_integral_list(user_id,member_id,shop_id,charge,balance,`desc`,dateline) values ($user_id,{$order['member_id']},$storeid,'+$new_price',$integral+$new_price,'门店消费',$dateline)");
							$db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,{$order['member_id']},$balance,$balance,'-$new_price','{$order['order_no']}','收银',$dateline,$user_id,'$pay_type')");


					}
				}
    }
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

// 修改密码
if($datatype=='update_password'){
	$username = $_REQUEST['username'];
	$password = $_REQUEST['password'];
	$newpass = $_REQUEST['newpass'];
	$userid = $db->get_var("select id from a_staff where username='$username' and password='$password'");
	if($username&&$password&&$userid>0){
		$db->query("update a_staff set password='$newpass' where id='$userid'");
		$res = array('code'=>1,'msg'=>'更新成功,重新登陆');
	}else{
		$res =  array('code'=>2,'msg'=>'账号或密码错误');
	}
	echo json_encode($res);
}

//登录
if($datatype=='login'){
  //  $storeid = $_REQUEST['storeid'];
    $username = $_REQUEST['username'];
    $password = $_REQUEST['password'];


    if($username==''||$password==''){
        $res =  array('code'=>2,'msg'=>'账号或密码为空');
    }else{
        $user = $db->get_row("select * from a_staff where username='$username' and password='$password' limit 1");
        if($user){
            $sign_photo = $db->get_var("select sign_photo from a_store_sign where storeid={$user['storeid']}");
            $res =  array('code'=>1,'msg'=>'登录成功','data'=>$user,'sign_photo'=>$sign_photo);
            /*if($user['username']!='admin'){
                if($user['isonline']==0 &&$user['ssid']==''){
                  //  $_SESSION['adminid'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                  //  $_SESSION['sid']=md5($user['username']);
                    $ssid = md5(rand(10000,99999));
                    $db->query("update a_staff set isonline=1,ssid='$ssid' where id={$user['id']}");
                    $res =  array('code'=>1,'msg'=>'登录成功','data'=>$user);
                }else{
                    $res =  array('code'=>4,'msg'=>'已在其他设备登录');
                }
            }else{
                $res =  array('code'=>1,'msg'=>'登录成功','data'=>$user);
            }*/


          //  setcookie('sid',md5($user['username']));
           // echo 1111;
           // var_dump($_SESSION);exit;

        }else{
            $res =  array('code'=>3,'msg'=>'账号或密码错误');
        }

    }
    echo json_encode($res);
}

if($datatype=='login1'){
    $ssid = md5(rand(10000,99999));
    $username = $_REQUEST['username'];
    $password = $_REQUEST['password'];
    if($username==''||$password==''){
        $res =  array('code'=>2,'msg'=>'账号或密码为空');
    }else{
        $user = $db->get_row("select * from a_staff where username='$username' and password='$password' limit 1");
        if($user){
            if($user['isonline']==0 &&$user['ssid']==''){
               // $_SESSION['adminid'] = $user['id'];
              //  $_SESSION['admin_name'] = $user['username'];
              //  $_SESSION['sid']=md5($user['username']);
              //  $ssid = md5(rand(10000,99999));
                $db->query("update a_staff set isonline=1,ssid='$ssid' where id={$user['id']}");
                $res =  array('code'=>1,'msg'=>'登录成功','data'=>$user);
            }else{//可能是上次直接关闭浏览器，或者在其他地方登录
                $res =  array('code'=>4,'msg'=>'已在其他设备登录');
            }

            //  setcookie('sid',md5($user['username']));
            // echo 1111;
            // var_dump($_SESSION);exit;

        }else{
            $res =  array('code'=>3,'msg'=>'账号或密码错误');
        }

    }
    echo json_encode($res);

}
if($datatype=='logout'){
    $id = $_REQUEST['id'];
    $db->query("update a_staff set isonline=0,ssid='' where id=$id");
    $res =  array('code'=>1,'msg'=>'退出成功');
}
//获取门店列表
if($datatype=='get_shoplist'){
    $search = $_REQUEST['search'];
    $sql = "select * from fly_shop";
    if($search){
        $sql .=" where shop_code='$search'";
    }
    $list = $db->get_results($sql);
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//日结
if($datatype=='day_money_index'){
    $storeid = $_REQUEST['storeid'];
    $start = $_REQUEST['start'];
    $end = $_REQUEST['end'];
    $starttime = strtotime($start);
    $endtime = strtotime($end)+3600*24;

    $arr1 = $db->get_results("select sum(dis_total) as stotal,pay_type from a_order where storeid=$storeid and status=3 and dateline>=$starttime and dateline<$endtime group by pay_type");
    $list['arr1']= $arr1;

    $zong = $db->get_var("select count(id) from a_order where storeid=$storeid and status=3 and dateline>=$starttime and dateline<$endtime");
    $customer_type = $db->get_results("select count(customer_type) as ccount,customer_type,sum(dis_total) as sum_total from a_order where storeid=$storeid and status=3 and dateline>=$starttime and dateline<$endtime group by customer_type");
    $customer_type2 = $db->get_results("select count(customer_type2) as ccount,customer_type2 from a_order where storeid=$storeid and status=3 and dateline>=$starttime and dateline<$endtime group by customer_type2");
    $list['zong']=$zong;
    $list['signbill'] = $db->get_var("select sum(money) from a_member_signbill_list where storeid=$storeid and status=0 and dateline>=$starttime and dateline<$endtime");
    $list['customer1']= $customer_type;
    $list['customer2']= $customer_type2;

    $list['member']= $db->get_results("select `type`,count(id) as ccount,sum(`change`) as total from a_member_moneydetail where storeid=$storeid and dateline>=$starttime and dateline<$endtime group by `type`");
    $all = 0;
    if($list['member']){
        // foreach($list['member'] as $v){
        //     if($v['type']!='收银'){
        //         $all += $v['total'];
        //     }
        // }
				foreach($list['member'] as $v){
				    if($v['type']=='充值' ||$v['type']=='会员卡'){
				        $all += $v['total'];
				    }
				}
        foreach($list['member'] as &$v){
            if($v['type']=='充值' ||$v['type']=='会员卡'){
                if($all){
                    $v['point'] = round($v['total']/$all,2)*100;
                }else{
                    $v['point'] =0;
                }

            }
        }
    }

    $list['goods'] =$db->get_results("select a.status,b.itemid,count(b.id) as ccount,b.itemname,b.price from a_order as a left join a_order_detail as b on b.order_id=a.id where a.storeid=$storeid and a.status=3 and b.dateline>=$starttime and b.dateline<$endtime and b.typeid=2 GROUP BY b.itemid ORDER BY ccount desc limit 5");
    //echo "select itemid,count(id) as ccount,itemname,price from a_order_detail where storeid=$storeid and dateline>=$starttime and dateline<$endtime and typeid=2 GROUP BY itemid ORDER BY ccount desc limit 5";
    $list['item'] =$db->get_results("select a.status,b.itemid,count(b.id) as ccount,b.itemname,b.price from a_order as a left join a_order_detail as b on b.order_id=a.id where a.storeid=$storeid and a.status=3 and b.dateline>=$starttime and b.dateline<$endtime and b.typeid=1 GROUP BY b.itemid ORDER BY ccount desc limit 5");
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}
//营业明细
if($datatype=='get_store_moneydetail') {
    $storeid = $_REQUEST['storeid'];
    $type = $_REQUEST['type'];//1 充值 2 售卡（卖卡） 3收银
    $start = $_REQUEST['start'];
    $end = $_REQUEST['end'];
    $search = $_REQUEST['search'];//名字或者手机号
    $itemname = $_REQUEST['itemname'];
    $starttime = strtotime($start);
    $endtime = strtotime($end) + 3600 * 24;
    if ($type == 1) {
     //   echo "select `type`,pay_sn,a.dateline,b.mobile,b.sex,b.name,a.`change` from a_member_moneydetail as a left join fly_member as b on b.member_id=a.member_id where a.storeid=$storeid and `type`='充值' and a.dateline>=$starttime and a.dateline<$endtime";die();
        $sql = "select `type`,a.order_no,pay_sn,a.dateline,b.mobile,b.sex,b.name,a.`change` from a_member_moneydetail as a left join fly_member as b on b.member_id=a.member_id where a.storeid=$storeid and `type`='充值' and a.dateline>=$starttime and a.dateline<$endtime";
        $newlist = $db->get_results($sql);
    } elseif ($type == 2) {
        $sql = "select `type`,a.order_no,pay_sn,a.dateline,b.mobile,b.sex,b.name,a.`change` from a_member_moneydetail as a left join fly_member as b on b.member_id=a.member_id where a.storeid=$storeid and a.`type`='会员卡' and a.dateline>=$starttime and a.dateline<$endtime";
        $newlist = $db->get_results($sql);
    } elseif ($type == 3) {
        // $sql = "select `type`,order_no as pay_sn,a.dateline,b.mobile,b.sex,b.name,a.`commit` from a_member_moneydetail as a left join fly_member as b on b.member_id=a.member_id where a.storeid=$storeid and type='收银' and a.dateline>=$starttime and a.dateline<$endtime";
        $sql = "select a.id as order_id,a.order_no as pay_sn,a.paytime as dateline,a.customer_type,a.pay_type,a.dis_total,b.mobile,b.sex,b.name from a_order as a left join fly_member as b on b.member_id=a.member_id where a.storeid=$storeid and a.status=3 and sign<4 and a.dateline>=$starttime and a.dateline<$endtime order by a.dateline desc";
        if($search){
            $sql .=" and (b.mobile like '%$search%' or b.name like '%$search%')";
        }
      //  echo $sql;exit;
        $list = $db->get_results($sql);
        if($list){
                foreach ($list as $v) {
                    // $v['type'] = '收银';
                    // echo "select itemname,subtotal from a_order_detail where order_no={$v['pay_sn']} and is_usecard=0";

                    $v['type'] = '收银';
                    if($itemname){
                        $v['iteminfo'] = $db->get_results("select itemname,subtotal from a_order_detail where order_no='{$v['pay_sn']}' and is_usecard=0 and itemname like '%$itemname%'");
                        if($v['iteminfo']){
                            $newlist[]=$v;
                        }
                    }else{
                        $v['iteminfo'] = $db->get_results("select itemname,subtotal from a_order_detail where order_no='{$v['pay_sn']}' and is_usecard=0");
                        $newlist[]=$v;
                    }

                }

        }

    }


    $res = array('code' => 1, 'msg' => '成功', 'data' => $newlist, 'type' => $type);
    echo json_encode($res);
}
//次卡列表
if($datatype=='get_ci_list'){
    $storeid = $_REQUEST['storeid'];
    $state = $_REQUEST['state'];// 0 正常  1 已删除
    $itemname = $_REQUEST['itemname'];
    $typeid = $_REQUEST['typeid'];
    $sql = "select * from a_cicard where storeid=$storeid";
    if(!is_null($state)){
        if($state==0){
            $sql .= " and state=0";
        }else{
            $sql .= " and state=1";
        }
    }
    if($itemname){
        $sql .= " and itemname like '%$itemname%'";
    }
    if($typeid){
        $sql .= " and typeid=$typeid";
    }
    $arr = $db->get_results($sql);
    $res = array('code' => 1, 'msg' => '成功', 'data' => $arr);
    echo json_encode($res);
}

//退款
if($datatype=='refund'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $db->query("update fly_member set balance=0,status=3 where member_id=$member_id and storeid=$storeid");
    $res = array('code' => 1, 'msg' => '成功');
    echo json_encode($res);
}


//买次卡
if($datatype=='buy_ccard'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $id= $_REQUEST['id'];//次卡list 主键id 次卡id
    $pay_type = $_REQUEST['pay_type'];//zfb，wx,cash,card,other2
    $order_no = $_REQUEST['order_no'];
    if(!$order_no){
        $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    /*$check = $db->get_row("select * from a_card_memberitem where cicard_id=$id and member_id=$member_id and storeid=$storeid");
    if($check){
        $cicard = $db->get_row("select * from a_cicard where id=$id");
        if($cicard['typeid']==1){
            if($check['rest_count']>0){
                $res = array('code' => 2, 'msg' => '已有该次卡');
                echo json_encode($res);exit;
            }
        }else{
            if(date('Y-m-d H:i:s')<$check['expire_date']){
                $res = array('code' => 2, 'msg' => '已有该次卡');
                echo json_encode($res);exit;
            }
        }

    }*/
        $user_id = $db->get_var("select user_id from fly_member where member_id=$member_id and storeid=$storeid");
        $cicard = $db->get_row("select * from a_cicard where id=$id");
        if($cicard['typeid']==1){
            $first_count =$cicard['count'];
            $expire_date = date('Y-m-d');
        }elseif ($cicard['typeid']==2){
            $num = $cicard['num'];
            $expire_date = date('Y-m-d 23:59:59',strtotime("+$num month"));
            $first_count = 0;
        }elseif ($cicard['typeid']==3){
            $num = $cicard['num']*3;
            $expire_date = date('Y-m-d 23:59:59',strtotime("+$num month"));
            $first_count = 0;
        }elseif ($cicard['typeid']==4){
            $num = $cicard['num'];
            $expire_date = date('Y-m-d 23:59:59',strtotime("+$num year"));
            $first_count = 0;
        }
        //echo "insert into a_card_memberitem(storeid,member_id,cicard_id,itemid,itemname,first_count,rest_count,dateline,expire_date,user_id) values ($storeid,$member_id,$id,'{$cicard['itemid']}','{$cicard['itemname']}',$first_count,$first_count,$dateline,'$expire_date',$user_id)";exit;
    $db->query("start transaction");
        $db->query("insert into a_card_memberitem(storeid,member_id,cicard_id,itemid,itemname,first_count,rest_count,dateline,expire_date,user_id) values ($storeid,$member_id,$id,'{$cicard['itemid']}','{$cicard['itemname']}',$first_count,$first_count,$dateline,'$expire_date',$user_id)");
        if($db->insert_id){
         //   $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $balance = $db->get_var("select balance from fly_member where member_id=$member_id and storeid=$storeid");
            if($pay_type=='card'){

               // $user_id = $db->get_var("select user_id from fly_member where member_id={$order['member_id']} and storeid=$storeid");
                if($balance<$cicard['price']){
                    $db->query("rollback");
                    $res = array('code'=>3,'msg'=>'会员余额不够');
                    echo json_encode($res); exit;
                }else{
                    $db->query("update fly_member set total_pay=total_pay+{$cicard['price']},last_time=$dateline,instore_count=instore_count+1,balance=balance-{$cicard['price']} where member_id=$member_id and storeid=$storeid");
                    $db->query("update a_card_member set price=price-{$cicard['price']} where member_id=$member_id and shop_id=$storeid");

                    $new_money = $balance-$cicard['price'];
                    $db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,member_id,status,dis_total,total,dateline,user_id,pay_type,paytime,sign) values ($storeid,'$order_no',2,2,$member_id,3,{$cicard['price']},{$cicard['price']},$dateline,$user_id,'$pay_type',$dateline,1)");
                    $order_id = $db->insert_id;
                    $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,price,discount_price,subtotal,dateline) values ($storeid,$order_id,'$order_no',4,$id,'{$cicard['cardname']}','{$cicard['price']}',{$cicard['price']},{$cicard['price']},$dateline)");
                    $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$new_money,'-{$cicard['price']}','$order_no','售次卡',$dateline,$user_id,'$pay_type')");

                }
							$list = $db->get_row("select * from a_order where id=$order_id");
            }else{ //不扣会员卡
    $integral = $db->get_var("select integral from fly_member where member_id=$member_id and storeid=$storeid");

                $db->query("update fly_member set integral=integral+{$cicard['price']},total_pay=total_pay+{$cicard['price']},last_time=$dateline,instore_count=instore_count+1 where member_id=$member_id and storeid=$storeid");
                //增加积分明细
                $db->query("insert into mini_integral_list(user_id,member_id,shop_id,charge,balance,`desc`,dateline) values ($user_id,$member_id,$storeid,'+{$cicard['price']}',$integral+{$cicard['price']},'购买次卡',$dateline)");

                $db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,member_id,status,dis_total,total,dateline,user_id,pay_type,paytime,sign) values ($storeid,'$order_no',2,2,$member_id,3,{$cicard['price']},{$cicard['price']},$dateline,$user_id,'$pay_type',$dateline,1)");
                $order_id = $db->insert_id;
                $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,price,discount_price,subtotal,dateline) values ($storeid,$order_id,'$order_no',4,$id,'{$cicard['cardname']}','{$cicard['price']}',{$cicard['price']},'{$cicard['price']}',$dateline)");
                $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$balance,'-0','$order_no','售次卡',$dateline,$user_id,'$pay_type')");

								$list = $db->get_row("select * from a_order where id=$order_id");
            }

				

            if($db->insert_id){
                $db->query("commit");
                $res = array('code' => 1, 'msg' => '成功','list'=>$list);
            }else{
                $db->query("rollback");
                $res = array('code' => 2, 'msg' => '失败');
            }
        }

        echo json_encode($res);

}

//签单明细表
if($datatype=='get_signbill_list'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
   $list = $db->get_results("select * from a_member_signbill_list where storeid=$storeid and member_id=$member_id");
	 foreach($list as &$v){
	   $v['info'] = $db->get_results("select * from a_order_detail where order_no='{$v['order_no']}' and storeid=$storeid");
	 }
    $res = array('code' => 1, 'msg' => '成功','list'=>$list);
    echo json_encode($res);
}

//还款
if($datatype=='repayment'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $ids = $_REQUEST['id'];//欠款列表id
    $pay_type = $_REQUEST['pay_type'];
    $sum = 0;
    if($ids){
			
        foreach($ids as $v){
            $money = $db->get_var("select money from a_member_signbill_list where id=$v");
            $sum +=$money;
        }
				
				//判断会员卡余额
				if($pay_type=='card'){
					$balance = $db->get_var("select balance from fly_member where member_id={$member_id} and storeid=$storeid");
					  $user_id = $db->get_var("select user_id from fly_member where member_id={$member_id} and storeid=$storeid");
					  if(!$user_id){
					      $user_id=0;
					  }
					if($balance<$sum){
					    $res = array('code'=>3,'msg'=>'会员余额不够');
					    echo json_encode($res); exit;
					}else{
						//还所有的历史订单款
						foreach($ids as $v){
						    $money = $db->get_var("select money from a_member_signbill_list where id=$v");
						    $db->query("update a_member_signbill_list set status=1,repay_time=$dateline,pay_type='$pay_type' where id=$v");
						}
						//插入会员资金变动
						$order_no = 'HK'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
						$db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$balance-{$sum},'-{$sum}','$order_no','还款',$dateline,$user_id,'$pay_type')");
						
						//改变会员卡余额
						$db->query("update fly_member set total_pay=total_pay+$sum,last_time=$dateline,instore_count=instore_count+1,balance=balance-{$sum} where member_id={$member_id} and storeid=$storeid");
					}
				}else{
					//还所有的历史订单款
					foreach($ids as $v){
					    $money = $db->get_var("select money from a_member_signbill_list where id=$v");
					    $db->query("update a_member_signbill_list set status=1,repay_time=$dateline,pay_type='$pay_type' where id=$v");
					}
				}
				
				
				
        $db->query("update fly_member set signbill=signbill-$sum where member_id=$member_id and storeid=$storeid");
        $res = array('code' => 1, 'msg' => '成功');
        echo json_encode($res);
    }else{
        $res = array('code' =>2, 'msg' => '请选择还款项目');
        echo json_encode($res);
    }

}


//会员充值
if($datatype=='recharge'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $money = $_REQUEST['money'];
    $gift_money = $_REQUEST['gift_money'];
    $order_no = $_REQUEST['order_no'];
    if(!$order_no){
        $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    $info = $db->get_row("select user_id,balance,integral from fly_member where member_id=$member_id and storeid=$storeid");
    if(!$info['user_id']){
        $info['user_id']=0;
    }
//    $integral = $db->get_var("select integral from fly_member where member_id=$member_id and storeid=$storeid");

    $db->query("update fly_member set integral=integral+$money,balance=balance+$money+$gift_money where member_id=$member_id and storeid=$storeid");
    $db->query("insert into mini_integral_list(user_id,member_id,shop_id,charge,balance,`desc`,dateline) values ({$info['user_id']},$member_id,$storeid,'+$money',{$info['integral']}+$money,'会员卡充值',$dateline)");

    //$order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
  // echo "insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,type,dateline,user_id) values($storeid,$member_id,{$info['balance']},{$info['balance']}+$money,'+$money','$order_no','充值',$dateline,{$info['user_id']})";exit;
    $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,type,dateline,user_id) values($storeid,$member_id,{$info['balance']},{$info['balance']}+$money,'+$money','$order_no','充值',$dateline,{$info['user_id']})");
    $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,type,dateline,user_id,gift_money) values($storeid,$member_id,{$info['balance']}+$money,{$info['balance']}+$money+$gift_money,'+$gift_money','$order_no','充值赠送',$dateline,{$info['user_id']},$gift_money)");
		
    if($db->insert_id){
        $res = array('code' => 1, 'msg' => '成功','data'=>$order_no);
        echo json_encode($res);
    }else{
        $res = array('code' => 2, 'msg' => '失败');
        echo json_encode($res);
    }

}

if($datatype=='make_tree_img'){
    $type=$_REQUEST['type'];//1 项目 2产品
    $storeid = $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
    $start = strtotime(date('Y0101'));
    if($type==1){ //项目

        $arr = $db->get_results("select sum(num) month_num,FROM_UNIXTIME(dateline,'%Y%m') months from a_order_detail where storeid=$storeid and typeid=1 and dateline>=$start group by months order by months asc");
        $res = array('code' => 1, 'msg' => '成功','data'=>$arr);
        echo json_encode($res);
    }elseif($type==2){
        $arr = $db->get_results("select sum(num) month_num,FROM_UNIXTIME(dateline,'%Y%m') months from a_order_detail where storeid=$storeid and typeid=2 and dateline>=$start group by months order by months asc");
        $res = array('code' => 1, 'msg' => '成功','data'=>$arr);
        echo json_encode($res);
    }elseif($type==3){ //卖卡
        $arr = $db->get_results("select count(id) month_num,FROM_UNIXTIME(dateline,'%Y%m') months from a_member_moneydetail where storeid=$storeid and type='卖卡' and dateline>=$start group by months order by months asc");
        $res = array('code' => 1, 'msg' => '成功','data'=>$arr);
        echo json_encode($res);
    }
}

//上传用户对比图

if($datatype=='insert_member_photo') {

    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $itemcate_id = $_REQUEST['item_id'];//项目分类id
    $desc = $_REQUEST['desc'];
    $img = $_REQUEST['img'];
    $user_id = $db->get_var("select user_id from fly_member where member_id=$member_id and storeid=$storeid");
    if(!$user_id){
        $user_id= 0;
    }
    $db->query("insert into a_member_photo(storeid,member_id,itemcate_id,img,`desc`,user_id,dateline) values($storeid,$member_id,$itemcate_id,'$img','$desc',$user_id,$dateline)");
   if($db->insert_id){
       $res = array('code' => 1, 'msg' => '成功');

   }else{
       $res = array('code' => 2, 'msg' => '失败');
   }
    echo json_encode($res);
}


//删除用户图片
if($datatype=='del_member_photo'){
    $id = $_REQUEST['id'];
    $img = $db->get_var("select img from a_member_photo where id=$id");
   // echo $_SERVER['DOCUMENT_ROOT'];exit;
    unlink($_SERVER['DOCUMENT_ROOT'].$img);
    $db->query("delete from a_member_photo where id=$id");
    $res = array('code' => 1, 'msg' => '成功');
    echo json_encode($res);
}

//获取单个用户的所有图片
if($datatype=='get_photo_list'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $itemcate_id = $_REQUEST['item_id']; //类别id
    if($storeid==''||$member_id==''){
        $res = array('code' => 2, 'msg' => '数据错误');
        echo json_encode($res);exit;
    }
    if($itemcate_id){
        $list = $db->get_results("select id,storeid,member_id,img,`desc` from a_member_photo where storeid=$storeid and member_id=$member_id and itemcate_id=$itemcate_id");
    }else{
        $list = $db->get_results("select id,storeid,member_id,img,`desc` from a_member_photo where storeid=$storeid and member_id=$member_id");
    }

    if($list){
        $res = array('code' => 1, 'msg' => '成功','list'=>$list);

    }else{
        $res = array('code' => 3, 'msg' => '暂无数据');
    }
    echo json_encode($res);
}

//添加次卡
if($datatype=='insert_cicard'){
    $storeid = $_REQUEST['storeid'];
    $itemid = $_REQUEST['itemid'];
    $itemname = $_REQUEST['itemname'];
    $typeid = $_REQUEST['typeid']; //1次卡  2月卡 3季卡  4年卡
    $num = $_REQUEST['num'];
    $price = $_REQUEST['price'];
    if($typeid==1){
        $count = $num;
        $cardname = $itemname.'-'.$count.'次卡';
        $db->query("insert into a_cicard(storeid,cardname,itemid,itemname,typeid,`count`,price,dateline) values($storeid,'$cardname',$itemid,'$itemname',$typeid,$count,$price,$dateline)");
    }else{
        if($typeid==2){
            $str = '月卡';
        }elseif($typeid==3){
            $str = '季卡';
        }elseif($typeid==2){
            $str = '年卡';
        }
        $db->query("insert into a_cicard(storeid,cardname,itemid,itemname,typeid,`num`,price,dateline) values($storeid,'$itemname-$num$str',$itemid,'$itemname',$typeid,$num,$price,$dateline)");
    }
    if($db->insert_id){
        $res = array('code' => 1, 'msg' => '成功');
    }else{
        $res = array('code' => 2, 'msg' => '失败');
    }

    echo json_encode($res);
}

//是否启用次卡
if($datatype=='change_cicard_state'){
    $id = $_REQUEST['id'];
    $state = $_REQUEST['state'];
    if($id){
        $db->query("update a_cicard set state=$state where id=$id");
        $res = array('code' => 1, 'msg' => '成功');
    }else{
        $res = array('code' => 2, 'msg' => '参数错误');
    }
    echo json_encode($res);
}


//增加抵用券

if($datatype=='insert_voucher'){
    $id = $_REQUEST['id'];
    $storeid = $_REQUEST['storeid'];
    $sign = $_REQUEST['sign'];//1增加  2编辑
    $type = $_REQUEST['type'];//1现金券 3亲友券 2免费券
    $amount = $_REQUEST['amount'];
    $starttime = strtotime($_REQUEST['starttime']);
    $endtime = strtotime($_REQUEST['endtime']) + 3600*24-1;
    $is_stop = $_REQUEST['is_stop'];//1停用 0启用
    $content =  $_REQUEST['content'];
    $remark = $_REQUEST['remark'];
    $name = $_REQUEST['name'];
    if($storeid==''||$amount==''||$starttime==''||$endtime==''){
        $res = array('code'=>2,'msg'=>'数据错误');
        echo json_encode($res);exit;
    }
    if($sign==1){

        $db->query("insert into a_voucher(storeid,`type`,`name`,amount,starttime,endtime,is_stop,content,remark) values($storeid,'$type','$name',$amount,$starttime,$endtime,$is_stop,'$content','$remark')");
        if($db->insert_id){
            $res = array('code'=>1,'msg'=>'成功');
            echo json_encode($res);
        }else{
            $res = array('code'=>3,'msg'=>'失败');
            echo json_encode($res);
        }
    }else{
        $db->query("update a_voucher set `type`='$type',`name`='$name',amount=$amount,starttime=$starttime,endtime=$endtime,is_stop=$is_stop,content='$content',remark='$remark' where id=$id");
        $res = array('code'=>1,'msg'=>'成功');
        echo json_encode($res);
    }


}

//抵用券列表
if($datatype=='voucher_list'){
    $storeid = $_REQUEST['storeid'];
    $status =  $_REQUEST['status'];  //1 选择隐藏停用=正常列表


    $sql = "select * from a_voucher where storeid=$storeid";
   /* if(is_null($status)){
        echo '没传';
    }elseif ($status==0){
        echo '0';
    }
    exit;*/
        if($status==1){  //停用的
            $sql .=" and is_stop=0";
        }


    $list = $db->get_results($sql);


    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);

}
//用户抵用券
if($datatype=='get_member_voucher'){
    $storeid = $_REQUEST['storeid'];
    $member_id =  $_REQUEST['member_id'];
    $status=$_REQUEST['status'];
    $db->query("update a_member_voucher set status=3 where v_endtime<=$dateline and storeid=$storeid and member_id=$member_id");
    if($status==3){  //3已过期
        $sql = "select a.*,b.type,b.remark as v_remark from a_member_voucher a inner join a_voucher b on b.id=a.voucher_id where a.storeid=$storeid and a.member_id=$member_id and a.status=3";
    }elseif ($status==2){   //2已使用
        $sql = "select a.*,b.type,b.remark as v_remark from a_member_voucher a inner join a_voucher b on b.id=a.voucher_id where a.storeid=$storeid and a.member_id=$member_id and a.status=2";
    }else{ //1已领取
       $sql = "select a.*,b.type,b.remark as v_remark from a_member_voucher a inner join a_voucher b on b.id=a.voucher_id where a.storeid=$storeid and a.member_id=$member_id and a.status=1";
    }
    $list = $db->get_results($sql);
    if($list){
        $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    }else{
        $res = array('code'=>1,'msg'=>'成功','data'=>null);
    }

    echo json_encode($res);
}
//快速支付
if($datatype=='quick_pay'){
    $storeid = $_REQUEST['storeid'];
    $dis_total = $_REQUEST['dis_total'];
    $pay_type =$_REQUEST['pay_type'];
    $order_no = $_REQUEST['order_no'];
    if(!$order_no){
        $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
//    $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $db->query("insert into a_order(storeid,order_no,status,dis_total,total,dateline,pay_type,paytime,sign) values ($storeid,'$order_no',3,$dis_total,$dis_total,$dateline,'$pay_type',$dateline,3)");
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

//根据id取商品信息
if($datatype=='get_goodsinfo_byid'){
    $storeid= $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
    $list = $db->get_row("select a.*,b.number,c.title from a_goods as a left join a_stock_goods_sku as b on b.goods_id=a.id left join a_goodscate as c on c.id=a.category_id where a.storeid=$storeid&&a.id=$id");
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}

//根据id取项目信息
if($datatype=='get_item_byid'){
    $storeid= $_REQUEST['storeid'];
    $id = $_REQUEST['id'];
    $list = $db->get_row("select a.*,c.title from a_item as a left join a_itemcate as c on c.id=a.category_id where a.storeid=$storeid&&a.id=$id");
    $res = array('code'=>1,'msg'=>'成功','data'=>$list);
    echo json_encode($res);
}
//更新门店配置表
if($datatype=='edit_store_config'){
    $storeid= intval($_REQUEST['storeid']);
    $menu_name = $_REQUEST['menu_name']; //名称英文：如goods_list
    $info = $_REQUEST['info'];//中文释义：如产品列表
    $value = $_REQUEST['value'];//值
    $parameter = $_REQUEST['parameter']?$_REQUEST['parameter']:'';//参数：如：1=>停用，0=>正常
    if($storeid==0){
        $res = array('code'=>2,'msg'=>'参数错误');
        echo json_encode($res);exit;
    }
    $check = $db->get_var("select id from a_store_config where storeid=$storeid and menu_name='$menu_name'");
    if($check){
        $db->query("update a_store_config set `value`='$value' where id=$check");
    }else{
        $db->query("insert into a_store_config(storeid,menu_name,info,`value`,parameter) values($storeid,'$menu_name','$info','$value','$parameter')");
    }
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

//查询门店配置表
if($datatype=='get_store_config'){
    $storeid= intval($_REQUEST['storeid']);
    $menu_name = $_REQUEST['menu_name']; //名称英文：如goods_list
    if($storeid==0){
        $res = array('code'=>2,'msg'=>'参数错误');
        echo json_encode($res);exit;
    }
    $data = $db->get_row("select id,`value` from a_store_config where storeid=$storeid and menu_name='$menu_name'");
    if($data){
        $res = array('code'=>1,'msg'=>'成功','data'=>$data);
    }else{
        $res = array('code'=>3,'msg'=>'无数据');
    }

    echo json_encode($res);
}
//签到
if($datatype=='insert_sign'){
    $storeid= intval($_REQUEST['storeid']);
    $start = strtotime(date("Y-m-d"),time());
    $end = $start+24*3600;
    $check = $db->get_var("select id from a_sign where storeid=$storeid and dateline>=$start and dateline<$end");
    if($check){
        $res = array('code'=>2,'msg'=>'已签到');
    }else{
        $date = date("Y-m-d");
        $integral = $db->get_var("select integral from pos_integral_setting where title='签到积分' and $date<=period");
        $db->query("insert into a_sign(storeid,integral,dateline) values ($storeid,$integral,$dateline)");
				
				//添加积分值到门店
				//$integral=$db->get_var("select integral from pos_integral_setting where id=1");
				// $db->query("update fly_shop set integral=integral+$integral where shop_id=$storeid");
				$db->query("update fly_shop set integral_sum=integral_sum+$integral where shop_id=$storeid");
        $res = array('code'=>1,'msg'=>'成功');
    }
    echo json_encode($res);
}
//获取签到列表
if($datatype=='get_sign_list'){
    $storeid= intval($_REQUEST['storeid']);
    $month = $_REQUEST['month'];//2020-09
   // echo "select * from a_sign where storeid=$storeid and FROM_UNIXTIME(dateline,'%Y-%m')=$month";
    $list = $db->get_results("select * from a_sign where storeid=$storeid and FROM_UNIXTIME(dateline,'%Y-%m')='$month'");
    $new=[];
    if($list){
        foreach ($list as $v){
            $arr['date']=date('Y-m-d',$v['dateline']);

            $arr['integral']=$v['integral'];
            $new[]=$arr;
        }
    }
    // $sum = $db->get_var("select sum(integral) from a_sign where storeid=$storeid");
		$sum = $db->get_var("select sum(integral_sum) from fly_shop where shop_id=$storeid");
    $count = $db->get_var("select count(id) from a_sign where storeid=$storeid");
    $res = array('code'=>1,'msg'=>'成功','data'=>$new,'count'=>$count,'sum'=>$sum);
    echo json_encode($res);
}
//获取门店二维码
if($datatype=='get_qrcode'){
    $storeid = $_REQUEST['storeid'];
    $type_id = $db->get_var("select type_id from fly_shop where shop_id=$storeid");
    if($type_id==1){
        require_once('mini/get_accessToken.php');  //直营店
        $qrcode = get_qrcode($storeid);
    }else{
        require_once('mini/get_accessToken_jiameng.php');   //加盟店
        $qrcode = get_qrcode($storeid);
    }
    $res = array('code'=>1,'msg'=>'成功','data'=>$qrcode);
    echo json_encode($res);
}
//select count(id) FROM a_stock_goods_skudetail where storeid=1 and type='出库' and stock_no like 'POS%';
//上传签名接口
if($datatype=='insert_sign_photo'){
    $storeid= $_REQUEST['storeid'];
    $sign_photo = $_REQUEST['sign_photo'];
    $check = $db->get_var("select id from a_store_sign where sign_photo='$sign_photo'");
    if($check){
        $res = array('code'=>2,'msg'=>'已有签名');
    }else{
        $db->query("insert into a_store_sign(storeid,sign_photo,dateline) values ($storeid,'$sign_photo',$dateline)");
        $res = array('code'=>1,'msg'=>'成功');
    }
    echo json_encode($res);
}
//POS后台增加会员
if($datatype=='insert_member'){
    $storeid = $_REQUEST['storeid'];
    $name = $_REQUEST['name'];
    $birthday = $_REQUEST['birthday'];
    $mobile = $_REQUEST['mobile'];
    $sex = $_REQUEST['sex'];
    $ID_card = $_REQUEST['ID_card'];

    $time=explode(' ',microtime());

    $b= base_convert(($time[1].($time[0]*100000000)),10,32).mt_rand(0,9999);
    $openid =sha1($b);
    $check_mini = $db->get_var("select id from users_miniprogram where mobile=$mobile limit 1");
    if($check_mini){
        $userid = $check_mini;
    }else{
        $db->query("insert into users_miniprogram(openid,mobile,birthday,realname,sex,IDcard,`source`) values('$openid',$mobile,'$birthday','$realname',$sex,'IDcard',1)");
        $userid = $db->insert_id;
    }
    $check = $db->get_var("select member_id from fly_member where mobile=$mobile and storeid=$storeid and is_delete=0 limit 1");
    if($check){
        $res = array('code'=>2,'msg'=>'已经是该门店会员');
    }else{
        $today=date('Y-m-d H:i:s',$dateline);

        $db->query("insert into fly_member (`name`,storeid,birthday1,user_id,mobile,balance,integral,last_time,adt,status) values ('$name',$storeid,'$birthday',$userid,'$mobile','0',0,$dateline,'$today',1)");
        $member_id = $db->insert_id;
        $account = str_pad ($today, 12, 0, STR_PAD_RIGHT ) + $member_id;

        $db->query("update fly_member set account='$mobile',card_num='$account' where member_id='$member_id'");
        $db->query("update users_miniprogram set member_id='$member_id' where id='$userid'");
        $res = array('code'=>1,'msg'=>'成功');
    }
    echo json_encode($res);


}
//设置门店目标
if($datatype=='insert_store_target'){
    $storeid = $_REQUEST['storeid'];
    $month = $_REQUEST['month'];
    $value = $_REQUEST['value'];
    $check = $db->get_var("select id from a_store_target where storeid=$storeid and month='$month'");
    if($check){
        $db->query("update a_store_target set `value`='$value' where id=$check");
    }else{
        $db->query("insert into a_store_target(storeid,month,`value`) values($storeid,'$month','$value')");
    }
    $res = array('code'=>1,'msg'=>'成功');
    echo json_encode($res);
}

//查询门店目标
if($datatype =='get_store_target'){
    $storeid = $_REQUEST['storeid'];
    $month = $_REQUEST['month'];
    $value = $db->get_var("select `value` from a_store_target where storeid=$storeid and month='$month'");
    if(!$value){
        $value=0;
    }
    $res = array('code'=>1,'msg'=>'成功','data'=>$value);
    echo json_encode($res);
}

//
if($datatype=='buy_member_card'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $id= $_REQUEST['card_id'];//会员卡list 主键id
    $pay_type = $_REQUEST['pay_type'];//zfb，wx,cash,card,other2
    /*$check = $db->get_row("select * from a_card_memberitem where cicard_id=$id and member_id=$member_id and storeid=$storeid");
    if($check){
        $cicard = $db->get_row("select * from a_cicard where id=$id");
        if($cicard['typeid']==1){
            if($check['rest_count']>0){
                $res = array('code' => 2, 'msg' => '已有该次卡');
                echo json_encode($res);exit;
            }
        }else{
            if(date('Y-m-d H:i:s')<$check['expire_date']){
                $res = array('code' => 2, 'msg' => '已有该次卡');
                echo json_encode($res);exit;
            }
        }

    }*/
    $user_id = $db->get_var("select user_id from fly_member where member_id=$member_id and storeid=$storeid");
    $card = $db->get_row("select * from a_card where id=$id");
    if(!$user_id){
        $user_id = 0;
    }
    //echo "insert into a_card_memberitem(storeid,member_id,cicard_id,itemid,itemname,first_count,rest_count,dateline,expire_date,user_id) values ($storeid,$member_id,$id,'{$cicard['itemid']}','{$cicard['itemname']}',$first_count,$first_count,$dateline,'$expire_date',$user_id)";exit;
    $db->query("start transaction");
    $card_num =  date('Ymd').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
   // echo "insert into a_card_member(card_num,user_id,member_id,card_id,shop_id,price,dateline) values ('$card_num',$user_id,$member_id,$id,$storeid,'{$card['deposit_amount']}',$dateline)";exit;
    $db->query("insert into a_card_member(card_num,user_id,member_id,card_id,shop_id,price,dateline) values ('$card_num',$user_id,$member_id,$id,$storeid,'{$card['deposit_amount']}',$dateline)");
    if($db->insert_id){
  //      $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $order_no = $_REQUEST['order_no'];
        if(!$order_no){
            $order_no = 'POS'.date('YmdHis').str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        $balance = $db->get_var("select balance from fly_member where member_id=$member_id and storeid=$storeid");
        /*if($pay_type=='card'){

            // $user_id = $db->get_var("select user_id from fly_member where member_id={$order['member_id']} and storeid=$storeid");
            if($balance<$cicard['price']){
                $db->query("rollback");
                $res = array('code'=>3,'msg'=>'会员余额不够');
                echo json_encode($res); exit;
            }else{
                $db->query("update fly_member set total_pay=total_pay+{$cicard['price']},last_time=$dateline,instore_count=instore_count+1,balance=balance-{$cicard['price']} where member_id=$member_id and storeid=$storeid");
                $db->query("update a_card_member set price=price-{$cicard['price']} where member_id=$member_id and shop_id=$storeid");

                $new_money = $balance-$cicard['price'];
                $db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,member_id,status,dis_total,total,dateline,user_id,pay_type,paytime,sign) values ($storeid,'$order_no',2,2,$member_id,3,{$cicard['price']},{$cicard['price']},$dateline,$user_id,'$pay_type',$dateline,1)");
                $order_id = $db->insert_id;
                $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,price,discount_price,subtotal,dateline) values ($storeid,$order_id,'$order_no',4,$id,'{$cicard['cardname']}','{$cicard['price']}',{$cicard['price']},{$cicard['price']},$dateline)");
                $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,`type`,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$new_money,'-{$cicard['price']}','$order_no','售次卡',$dateline,$user_id,'$pay_type')");

            }

        }else{*/ //不扣会员卡
            $integral = $db->get_var("select integral from fly_member where member_id=$member_id and storeid=$storeid");

            $db->query("update fly_member set integral=integral+{$card['recharge_money']},balance=balance+{$card['deposit_amount']},last_time=$dateline,instore_count=instore_count+1,card_num='$card_num' where member_id=$member_id and storeid=$storeid");
            //增加积分明细
            $db->query("insert into mini_integral_list(user_id,member_id,shop_id,charge,balance,`desc`,dateline) values ($user_id,$member_id,$storeid,'+{$card['recharge_money']}',$integral+{$card['recharge_money']},'购买会员卡',$dateline)");

            $db->query("insert into a_order(storeid,order_no,customer_type,customer_type2,member_id,status,dis_total,total,dateline,user_id,pay_type,paytime,sign) values ($storeid,'$order_no',2,2,$member_id,3,{$card['recharge_money']},{$card['recharge_money']},$dateline,$user_id,'$pay_type',$dateline,4)");
            $order_id = $db->insert_id;

            $db->query("insert into a_order_detail(storeid,order_id,order_no,typeid,itemid,itemname,price,discount_price,subtotal,dateline) values ($storeid,$order_id,'$order_no',6,$id,'{$card['name']}',{$card['recharge_money']},{$card['recharge_money']},'{$card['recharge_money']}',$dateline)");

        $gift_money = $card['deposit_amount']-$card['recharge_money'];

        $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,type,dateline,user_id,pay_type) values($storeid,$member_id,$balance,$balance+{$card['recharge_money']},'+{$card['recharge_money']}','$order_no','会员卡',$dateline,$user_id,'$pay_type')");
        $db->query("insert into a_member_moneydetail(storeid,member_id,old_money,balance,`change`,order_no,type,dateline,user_id,gift_money,pay_type) values($storeid,$member_id,$balance+{$card['recharge_money']},$balance+{$card['deposit_amount']},'+$gift_money','$order_no','会员卡赠送',$dateline,$user_id,$gift_money,'$pay_type')");
            $list = $db->get_row("select * from a_order where id=$order_id");
        /*}*/




        $db->query("commit");
        $res = array('code' => 1, 'msg' => '成功','list'=>$list);

    }else{
        $db->query("rollback");
        $res = array('code' => 2, 'msg' => '失败');
    }

    echo json_encode($res);
}

//查询用户有么有会员卡
if($datatype=='check_member_cardinfo'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    if($storeid=='' ||$member_id==''){
        $res = array('code' => 2, 'msg' => '参数错误');
        echo json_encode($res);die();
    }
    $check = $db->get_var("select id from a_card_member where member_id=$member_id and shop_id=$storeid");
    if($check){
        $res = array('code' => 3, 'msg' => '已有会员卡');

    }else{
        $res = array('code' => 1 , 'msg' => '没有会员卡');
    }
    echo json_encode($res);
}


/*//短信发送
if($datatype=='sendsms'){
    require_once '../ChuanglanSmsHelper/ChuanglanSmsApi.php';
    $mobile=intval($_REQUEST['mobile']);
    $userid=$_REQUEST['userid'];
    $event=$_REQUEST['event'];
    if(!is_numeric($userid)){
        $userid = filterWords($userid);
    }
    // echo $userid;exit;
    $is_user = $db->get_var("select id from users where id=".$userid);
    if($mobile==""||$is_user==""){
        echo 2;
    }else{
        $num=rand(1000,9999);
        if($event!=""){
            $msg="【上汽通用别克】感谢你参加别克推荐汇活动，活动验证码：".$num;
        }else{
            $msg="【上汽通用别克】感谢你参加别克推荐汇欢乐试驾活动，活动验证码：".$num;
        }

        $clapi  = new ChuanglanSmsApi();
        $result = $clapi->sendSMS($mobile, $msg);
        //var_dump("insert into mobile_sms (userid,mobile,num,dateline) values ($userid,'$mobile',$num,$dateline)");
        $db->query("insert into mobile_sms (userid,mobile,num,dateline) values ($userid,'$mobile',$num,$dateline)");
        if(!is_null(json_decode($result))){

            $output=json_decode($result,true);
            if(isset($output['code'])  && $output['code']=='0'){
                //echo '短信发送成功！' ;//发送成功
                echo 1;
            }else{
                echo $output['errorMsg'];
            }
        }else{
            echo $result;
        }

    }

}*/

//查询短信模板
if($datatype=='get_store_smstemp'){
    $storeid = $_REQUEST['storeid'];
    $list = $db->get_results("select * from a_sms_template where storeid=$storeid");
    $res = array('code' => 1 , 'msg' => '成功','data'=>$list);
    echo json_encode($res);
}
//查询短信数量
if($datatype=='get_store_smscount'){
    $storeid = $_REQUEST['storeid'];
    $smscount = $db->get_var("select sms_count from fly_shop where shop_id=$storeid");
    $res = array('code' => 1 , 'msg' => '成功','data'=>$smscount);
    echo json_encode($res);
}

//查询门店短信列表
if($datatype=='get_store_smsinfo'){
    $storeid = $_REQUEST['storeid'];
    $smslist = $db->get_results("select * from a_sms where storeid=$storeid");
    $res = array('code' => 1 , 'msg' => '成功','data'=>$smslist);
    echo json_encode($res);
}
//改变会员状态
if($datatype=='set_member_status'){
    $storeid = $_REQUEST['storeid'];
    $member_id = $_REQUEST['member_id'];
    $status = $_REQUEST['status'];
    $db->query("update fly_member set status=$status,user_id=0 where storeid= $storeid and member_id = $member_id");
    $res = array('code' => 1 , 'msg' => '成功');
    echo json_encode($res);
}
//发送短信
if($datatype=='sendsms'){
    $storeid = $_REQUEST['storeid'];
    $member_arr = $_REQUEST['ids'];//会员号数组
    $temp_id = $_REQUEST['temp_id'];//模板id

    if($temp_id){
        $msg = $db->get_var("select content from a_sms_template where id=$temp_id");
    }else{
        $msg='善真门店系统短信测试';
    }
    $check = $db->get_var("select sms_count from fly_shop where shop_id=$storeid");
    if($check<count($member_arr)){
        $res = array('code' =>2 , 'msg' => '门店短信余额不足');
        echo json_encode($res);
    }
    for($i=0;$i<count($member_arr);$i++){
        $mobile = $db->get_var("select mobile from fly_member where member_id={$member_arr[$i]}");
        $res = sendSms($mobile,$storeid,$member_arr[$i],$msg);
        if($res==1){
            $db->query("update fly_shop set sms_count=sms_count-1 where shop_id=$storeid");
						$res = array('code' => 1 , 'msg' => '发送成功');
        }
    }
    
    echo json_encode($res);

}
//查询门店是否有未接收的调拨数据
if($datatype=='get_db_count'){
    $storeid = $_REQUEST['storeid'];
    $count = $db->get_var("select count(id) from a_stock_out where out_type='调拨出库' and get_userid=$storeid and db_status=1");
    $res = array('code' => 1 , 'msg' => '成功','data'=>$count);
    echo json_encode($res);
}
//获取调拨的数据
if($datatype=='get_db_onestock'){
   // $sign = $_REQUEST['sign'];//1 入库 2出库
      $storeid = $_REQUEST['storeid'];
      $list = $db->get_row("select a.*,b.shop_name from a_stock_out a left join fly_shop b on b.shop_id=a.storeid where out_type='调拨出库' and get_userid=$storeid and db_status=1 order by a.id asc limit 1");
      if($list){
          $list['goodsinfo'] =$db->get_results("select a.*,b.goods_no,b.category_id from a_stock_out_detail a left join a_goods b on b.id=a.goods_id where out_id={$list['id']}");

      }else{
          $list['goodsinfo'] =array();
      }
    $res = array('code' => 1 , 'msg' => '成功','data'=>$list);
    echo json_encode($res);
}
if($datatype=='update_db_status'){
    $storeid = $_REQUEST['storeid'];
    $out_id =$_REQUEST['out_id'];
    $db->query("update a_stock_out set db_status=3 where id=$out_id");
    $res = array('code' => 1 , 'msg' => '成功');
    echo json_encode($res);
}

if($datatype=='get_goodslist_byname'){
    $goods_name = $_REQUEST['goods_name'];
    $list = $db->get_results("select a.goods_name,a.code as goods_no,a.price,a.goods_unit,a.defaultpic as pic,a.cost_price as in_cost from fly_goods a left join fly_goods_category b on b.category_id=a.category_id where b.parent_id=2 and a.goods_name like '%$goods_name%'");
    $res = array('code'=>1,'msg' =>'成功','data'=>$list);
    echo json_encode($res);
}
function sendSms($mobile,$storeid,$member_id,$msg){

    require_once '../ChuanglanSmsHelper/ChuanglanSmsApi.php';
    $clapi  = new ChuanglanSmsApi();
    $result = $clapi->sendSMS($mobile, $msg,true);
		$dateline= time();
    if(!is_null(json_decode($result))){
        $output=json_decode($result,true);
        if(isset($output['code'])  && $output['code']=='0'){
            //echo '短信发送成功！' ;//发送成功
            $GLOBALS['db']->query("insert into a_sms(storeid,member_id,mobile,msg,dateline) values ($storeid,$member_id,'$mobile','$msg',$dateline)");
            return 1;
        }else{
            //	echo $result;
            echo $output['errorMsg'];
        }
    }else{
        echo $result;
    }
}



//搜索门店