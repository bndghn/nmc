<?php

function strip_mq_gpc($arg)
{
  	$arg = str_replace('"',"'",$arg);
  	$arg = stripslashes($arg);
    return $arg;
}
function cleanit($text)
{
	return htmlentities(strip_tags(stripslashes($text)), ENT_COMPAT, "UTF-8");
}

function get_last_ip(){
    $lstip = get_client_ip();
    if ($lstip!="::1"){
        return trim($lstip);
    }else{
        $lstip = getLocalIP();
        return trim($lstip);
    }
}

// Function to get the client IP address
function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function getLocalIP(){
    exec("ipconfig /all", $output);
        foreach($output as $line){
            if (preg_match("/(.*)IPv4 Address(.*)/", $line)){
                $ip = $line;
                $ip = str_replace("IPv4 Address. . . . . . . . . . . :","",$ip);
                $ip = str_replace("(Preferred)","",$ip);
            }
        }
    return $ip;
}

function verify_login_admin()
{
    global $config,$conn;

    if(!isset($_SESSION['ADMIN_LOGIN'])){
        $CookieStatus = loginByCookie("1");
        if($CookieStatus!=""){
            header("location: ".$config['adminurl']."/logout.php?error=".$CookieStatus);
        }
    }
    /** for more security remove SHARPS #
     ********************************* */
  #  elseif(!adminIsValid()) {
  #     header("location: ".$config['adminurl']."/logout");
  #  }
    
        
}

function adminIsValid(){
    global $config,$conn;
    $adminID = intval($_SESSION['ADMIN_ID']);
    $sql = "SELECT `admin` FROM `users` WHERE id=".$adminID;
    $rs=$conn->execute($sql);
    if($rs->fields['admin'] === "0"){
        $_SESSION['ISADMIN'] = 0;
        $_SESSION['ADMIN_LOGIN'] = 0;
        return false;
    }elseif($rs->fields['status'] === "0"){
        $_SESSION['ADMIN_LOGIN'] = 0;
        $_SESSION['LOGIN'] = 0;
        return false;
    }else{
        return true;
    }
}

function create_remember(){
    
    $key = md5(sha1($_SESSION['USERNAME'] . get_last_ip()));
    global $conn;
    $username = $conn->qStr($_SESSION['USERNAME']);
    $sql="update `users` set remember_time='".date('Y-m-d H:i:s')."', remember_key='".$key."' WHERE username=".$username;
    $conn->execute($sql);
    echo $conn->errorMsg();
    setcookie('remember', gzcompress(serialize(array($_SESSION['USERNAME'], $key)), 9), time()+60*60*24*30);
}

function destroy_remember($username) {
    if (strlen($username) > 0) {
            global $conn;
            $conn->qStr($username);
            $sql="update `users` set `remember_time`=NULL,`remember_key`=NULL WHERE `username`='".$username."'";
            
            $conn->execute($sql);
           echo $conn->errorMsg();
    }
    setcookie ("remember", "", time() - 3600);
}

function loginByCookie($isAdmin="0"){
    global $config,$conn;
    $error="";
    if (!isset($_SESSION["USERNAME"]) && isset($_COOKIE['remember'])) 
    {
        $sql="update `users` set `remember_time`=NULL and `remember_key`=NULL WHERE `remember_time`<'".date('Y-m-d H:i:s', mktime(0, 0, 0, date("m")-1, date("d"),   date("Y")))."'";
        $conn->execute($sql); 
        //echo $conn->errorMsg();
        list($username, $key) = @unserialize(gzuncompress(stripslashes($_COOKIE['remember'])));
        if (strlen($username) > 0 && strlen($key) > 0)
        {
            $conn->qStr($username);
            $conn->qStr($key);
            
            $sql="SELECT * FROM `users` WHERE `username`= '".$username."' and `remember_key`='".$key."'";
            
            $rs=$conn->execute($sql);
            //echo $conn->errorMsg();
            if($rs->recordCount()<1)
            {
                $error = '26';
            }
            elseif($rs->fields['status'] === "0")
            {
                $error = '57';
            }
            if($isAdmin==="1"){
                if($rs->fields['admin'] === "0"){
                    $error = '25';
                    $_SESSION['ISADMIN'] = "0";
                }
            }
            if($error=="")
            {				
                
                if($isAdmin==="1"){
                    $_SESSION['ADMIN_ID']       = $rs->fields['id'];
                    $_SESSION['ADMIN_USER']     = $rs->fields['username'];
                    $_SESSION['USERNAME']       = $rs->fields['username'];
                    $_SESSION['ADMIN_PASS']     = $rs->fields['pass'];
                    $_SESSION['ADMIN_GENDER']   = $rs->fields['gender'];
                    $_SESSION['ADMIN_FNAME']    = $rs->fields['fname'];
                    $_SESSION['ADMIN_LNAME']    = $rs->fields['lname'];
                    $_SESSION['ADMIN_EMAIL']    = $rs->fields['email'];
                    $_SESSION['ADMIN_MOBILE']   = $rs->fields['mobile'];
                    $_SESSION['ISADMIN']        = "1"; //check is Admin or NOT
                    $_SESSION['ADMIN_LOGIN']    = "1"; //nessary for checking in admin page
                    $_SESSION['LOGIN']          = "1"; //nessary for checking in front page
                }else{
                    $_SESSION['ID']         = $rs->fields['id'];
                    $_SESSION['PASS']       = $rs->fields['pass'];
                    $_SESSION['USER_NAME']  = $rs->fields['username'];
                    $_SESSION['VERIFIED']   = $rs->fields['verified'];
                    $_SESSION['FNAME']      = $rs->fields['fname'];
                    $_SESSION['LNAME']      = $rs->fields['lname'];
                    $_SESSION['EMAIL']      = $rs->fields['email'];
                    $_SESSION['MOBILE']     = $rs->fields['mobile'];
                    $_SESSION['LOGIN']      = "1";
                }
                    
                
                create_remember();
            }
            else
            {
                destroy_remember($username);
                return $error;
            }
        }
    }else{
        $error = '29';
        return $error;
    }

}

function insert_get_user_list($var){
    global $conn,$config;
    if(!isset($var['user_group']) ){
        $add_sql    = " ";
    }elseif($var['user_group']=="0"){
        //$ugroup = intval($var['user_group']);
        $add_sql    = " AND NOT users.user_group = 1 ";
    }else{
        $ugroup = intval($var['user_group']);
        $add_sql    = " AND users.user_group = $ugroup ";
    }
    
    if(!isset($var['customer']) ){
        $add_sql    .= "AND user_group.isCustomer=0";
    }else{
        $add_sql    .= "AND user_group.isCustomer=1";
    }
    
    if(!isset($var['start'])){
        $limit = intval($config['limit_users']);
        $add_sql    .= " LIMIT 0,". $limit;
    }else{
        $limit  = intval($config['limit_users']);
        $start  = intval($var['limit_start']);
        $add_sql    .= " LIMIT ".$start.",". $limit;
    }
    
    
    $query ="SELECT users.*, user_group.* FROM `users`,`user_group` WHERE user_group.id= users.user_group ".$add_sql;
    
    //echo $query;
    $result = $conn->execute($query);
    if(!$users = $result->getArray()){
        echo $conn->errorMsg();
        return false;
    }else{
        return $users;
    }
    
    
    
}

function insert_get_user_group_list($gvar){
    global $conn;
    if(!isset($gvar['isCustomer']) ){
        $add_sql    = "";
    }elseif($gvar['isCustomer'] === "0"){
        $add_sql    = "WHERE `isCustomer` = 0 ";
    }else{
        $add_sql    = "WHERE `isCustomer` = 1 ";
    }
    
    $query = "SELECT * FROM `user_group` ".$add_sql;
    $result = $conn->execute($query);
    $userGroups = $result->getAll();
    
    return $userGroups;
}

function generatePass($characters) {
    $possible = '123456789@_#abcdefgikmlnoz';
    $code = '';
    $i = 0;
    while ($i < $characters) {
        $code .= substr($possible, mt_rand(0, strlen($possible)-1), 1);
        $i++;
    }
    return $code;
}


function verify_valid_email($emailtocheck){
    if(!preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $emailtocheck)) {
        return false;
    }
    else{
        return true;
    }
}

function check_tel($tel){
   if ( ! preg_match("/^09(0[1-9]|1[0-9]|2[1-9]|3[1-9]|9[0-9])-?[0-9]{3}-?[0-9]{4}$/",$tel)){
         
        return false;
    }
    else{
        return true;    
    }   
}
