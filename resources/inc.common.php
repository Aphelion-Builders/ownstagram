<?php
session_start();

include_once dirname(__FILE__).'/class.template.php';
include_once 'inc.var.php';
include_once dirname(__FILE__).'/class.db.php';
if(!defined('siteName')) define('siteName', 'ownStaGram');
if(!defined('mytimezone')) define('mytimezone', "Europe/Berlin");

date_default_timezone_set(mytimezone);

function vd($X) {
	echo "<pre>";
	print_r($X);
	echo "</pre>";
}

function me() {
	return getS("user", "u_pk");
}
function now() {
	return date("Y-m-d H:i:s");
}
function setS($name, $value) {
	$_SESSION[$name] = $value;
}
function getS($name, $field="") {
	if(!isset($_SESSION[$name])) return "";
	if($field!="") {
		if(!isset($_SESSION[$name][$field])) return "";
		return $_SESSION[$name][$field];
	}
	return $_SESSION[$name];
}
function jump2($action='') {
	header("location: index.php?action=".$action);
	exit;
}
function blurredZoom($u_fk=-1) {
	$zoom = 16;
	 if(me()<=0 || $u_fk==-1) {
		 $zoom = 13;
	 } else if(me()==$u_fk) {
	 } else {
		 $zoom = 15;
	 }
	 return $zoom;
}
function blurred($u_fk=-1) {
	if(me()<=0 || $u_fk==-1) {
		 $add = 0.0012*(rand(0,1000)/1000-0.5);
	 } else if(me()==$u_fk) {
		 $add = 0;
	 } else {
		 $add = 0.004*(rand(0,1000)/1000-0.5);
	 }
	 return $add;
}

function str_bis($haystack, $needle) {
	$s = substr($haystack, 0, strpos($haystack, $needle) );
	return($s);
}

function str_nach($haystack, $needle) {
	$s = substr($haystack, strpos($haystack, $needle)+strlen($needle) );
	return($s);
}

function str_zwischen($haystack, $needle1, $needle2) {
	$s = str_nach($haystack, $needle1);
	$s = str_bis($s, $needle2);
	return($s);
}

class ownStaGram {
	public $DC;
	public $VERSION = "1.9.8";
	public function __construct() {
		$this->DC = new DB(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_CHARACTERSET);
		if($this->DC->res!=1) {
			echo "<hr/>";
			echo("error connecting to database...");
			echo "<hr/>";
			exit;
		}
		
		if(!file_exists(projectPath.'/data')) {
			mkdir(projectPath.'/data', 0775);
			chmod(projectPath.'/data', 0775);
		}
		if(!file_exists(projectPath.'/data/cache')) {
			mkdir(projectPath.'/data/cache', 0775);
			chmod(projectPath.'/data/cache', 0775);
		}
		
		if(!is_writable(projectPath.'/data')) {
			echo "<hr/>";
			echo("data-folder not writable!");
			echo "<hr/>";
			exit;
		}
		if(!is_writable(projectPath.'/data/cache')) {
			echo "<hr/>";
			echo("data/cache-folder not writable!");
			echo "<hr/>";
			exit;
		}
		
		if(!file_exists(projectPath.'/data/index.html')) {
			touch(projectPath.'/data/index.html');
		}
		if(!file_exists(projectPath.'/data/cache/index.html')) {
			touch(projectPath.'/data/cache/index.html');
		}
		
	}
	
	public function getProfile($u_pk=0) {
		if($u_pk==0) $u_pk = me();
		$Q = "SELECT * FROM ost_user WHERE u_pk='".$u_pk."' ";
		$P = $this->DC->getByQuery($Q);
		return $P;
	}
	public function setProfile($post) {
		$U = array(
				"u_nickname" => htmlspecialchars($post['u_nickname']),
				"u_country" => htmlspecialchars($post['u_country']),
				"u_city" => htmlspecialchars($post['u_city']),
			);
		$this->DC->update($U, "ost_user", me(), "u_pk");
	}
	
	public function getSettings() {
		$Q = "SELECT * FROM ost_settings";
		$S = $this->DC->getByQuery($Q);
		if($S=="") {
			$S = array('s_allowregistration'=>1,
					"s_instance" => md5(microtime(true))
					);
			$this->DC->insert($S, "ost_settings");
			
		}
		$this->settings = $S;
		return $S;
	}
	
	public function setSettings() {
		$S = $this->getSettings();
		$data = array("s_subtitle" => $_POST["setting_title"],
			      "s_title" => $_POST["setting_maintitle"],
			      "s_allowregistration" => $_POST["setting_allow_register"],
			      "s_allowfriendsstreams" => $_POST["setting_allow_upload"],
			      "s_imprint" => $_POST["setting_imprint"],
			      "s_privacy" => $_POST["setting_privacy"],
			      "s_osm" => $_POST["setting_enable_osm"],
			      "s_homecontent" => $_POST["setting_homecontent"],
			      "s_style" => $_POST["setting_style"],
			      "s_global" => (int)$_POST["setting_global_active"],
			      "s_watermark" => $_POST["setting_watermark"]
			      );
		foreach($data as $key => $val) {
			$data[$key] = stripslashes(htmlspecialchars($val));
		}
		#vd($S);
		#vd($data);
		if(isset($S['s_pk'])) {
			$this->DC->update($data, 'ost_settings', $S['s_pk'], 's_pk');
		} else {
			$this->DC->insert($data, 'ost_settings');
		}
		
		if((int)$_POST["setting_global_active"]!=$S['s_global']) {
			file_get_contents("http://www.ow"."nst"."ag"."ram.de/global/index.php?action=setGlobal&instance=".urlencode($S["s_instance"])."&host=".urlencode($this->getServerUrl())."&global=".(int)$_POST["setting_global_active"]);
		}
		
		$res = array("result" => 1);
		return $res;
	}
	public function confirm() {
		$Q = "SELECT * FROM ost_user WHERE md5(concat('skfbvwezguzjndcbv76qwdqwef', u_email, u_password))='".addslashes($_GET['id'])."' ";
		$user = $this->DC->getByQuery($Q);
		if($user=="") die("Error!");
		
		$this->DC->sendQuery("UPDATE ost_user SET u_confirmed=now() WHERE u_confirmed='0000-00-00 00:00:00' AND u_pk='".(int)$user['u_pk']."' ");
		$this->login($user['u_email'], $user['u_password']);
		header('location: index.php?action=confirmed');
		exit;
	}
	
	public function register($nickname, $email, $pass) {

		$C = $this->DC->countByQuery("SELECT count(*) FROM ost_user WHERE lcase(u_email)='".strtolower(addslashes($email))."' OR lcase(u_nickname)='".strtolower(addslashes($nickname))."' ");
		if($C>0) {
			$res = array("result" => 0);
			return $res;
		}
		
		$data = array('u_email' => $email,
				'u_nickname' => htmlspecialchars($nickname),
				'u_password' => $pass,
				'u_registered' => now()
				);
		if(ownStaGramAdmin==$email) {
			$data["u_confirmed"] = now();
		}
		
		$this->DC->insert($data, 'ost_user');

		if(ownStaGramAdmin==$email) {
			$res = array("result" => 2);
		} else {
	
			$M = "You registered at ".$_SERVER['HTTP_HOST']." for an ".siteName."-account.\n";
			$M .= "Follow this link to confirm your registration.\n\n";
			
			$M .= "http://".$_SERVER['HTTP_HOST'].str_replace("app.php", "index.php", $_SERVER["PHP_SELF"])."?action=confirm&id=".md5('skfbvwezguzjndcbv76qwdqwef'.$email.$pass);
			mail($email, siteName." - Registration", $M, "FROM:".ownStaGramAdmin);
			$res = array("result" => 1);
		}
		
		
		return $res;
	}
	public function forgot($email) {
		$user = $this->DC->getByQuery("SELECT * FROM ost_user WHERE u_email='".addslashes($email)."' AND u_confirmed!='0000-00-00 00:00:00' ");
		if($user!="") {
			$res = array("result" => 1);

			$P = substr(md5(uniqid(microtime(true)).rand()),0,5);
			$PW = array("u_password" => md5('a4916ab01df010a042c612eb057b4ac23e79530d555354c4a92e1b24301b964f0f7ecd66143c4093ea1470efcfa33042'.$P));
			$this->DC->update($PW, "ost_user", $user["u_pk"], "u_pk");
			
			$M = "You are a member at ".$_SERVER['HTTP_HOST']." with an ".siteName."-account.\n";
			$M .= "This is your new password: ".$P;
			
			mail($user["u_email"], siteName." - New password", $M, "FROM:".ownStaGramAdmin);
			
			
		} else {
			$res = array("result" => 0);
		}
		return $res;
	}
	public function getServerUrl() {
		$S = "http".(isset($_SERVER["HTTPS"])?'s':'')."://".$_SERVER["HTTP_HOST"].dirname($_SERVER["PHP_SELF"]);
		return $S;
	}
	
	public function loginAtRemote($remotekey, $remoteserver) {
		$S = $this->getSettings();
		$data = array("id" => md5($this->user['u_pk'].$this->user['u_registered'].$S['s_instance']),
				"email" => $this->user['u_email'],
				"nickname" => $this->user['u_nickname'],
				"country" => $this->user['u_country'],
				"city" => $this->user['u_city'],
				"server" => $this->getServerUrl(),
				"key" => $remotekey
				);
		
		$rurl = $remoteserver.'/app.php?action=loginfromremote&data='.urlencode(json_encode($data));
		$remoteRes = file_get_contents($rurl );
		$res = json_decode($remoteRes, true);
		$res['remote'] = 1;
		
		if($res['result']==2) {
			$R = $this->DC->getByQuery("SELECT * FROM ost_remotes WHERE r_u_fk='".$this->user['u_pk']."' AND r_server='".addslashes($remoteserver)."' ");
			if($R=="") {
				$R = array("r_u_fk" => $this->user['u_pk'],
					   "r_server" => $remoteserver);
				$this->DC->insert($R, "ost_remotes");
			}
		}
			
		return $res;
	}
	
	public function rlogin($key) {
		
		$S = $this->getSettings();
		if( isset($S['s_allowregistration']) && $S['s_allowregistration']==1 ) { 
		
			if(stristr($key,'.') || stristr($key,'/') ) die("error.");
			if(file_exists(projectPath.'/data/cache/'.$key.'.rlogin')) {
				$data = json_decode(file_get_contents(projectPath.'/data/cache/'.$key.'.rlogin'), true);
				
				$res = $this->login($data['email'], $data['id']);
				#vd($res);exit;
				if($res['result']==1) {
					jump2('overview');
				} else {
					$reg = array(
						'u_email' => $data['email'],
						'u_password' => $data['id'],
						'u_registered' => now(),
						'u_confirmed' => now(),
						'u_nickname' => $data['nickname'],
						'u_remoteserver' => $data['server'],
						'u_country' => $data['country'],
						'u_city' => $data['city']
						);
					$reg['u_pk'] = $this->DC->insert($reg, 'ost_user');
					$res = $this->login($data['email'], $data['id']);
					jump2('overview');
				}
			}
		} else {
			jump2('login');
		}
	}
	
	public function login($email, $pass) {
		$this->user = $user = $this->DC->getByQuery("SELECT * FROM ost_user WHERE u_email='".addslashes($email)."' AND u_password='".addslashes($pass)."' AND u_confirmed!='0000-00-00 00:00:00' ");
		if($this->user!="") {
			if($user['u_remoteserver']!='') $user['u_email'] .= ' @ '.$user['u_remoteserver'];
			setS("user", $user); 
			$res = array("result" => 1);
			setCookie('ownStaGram', md5('sdkfb2irzsidfz8edtfwuedfgwjehfwje'.$this->user['u_pk']), time()+60*60*24*365);
		} else {
			$res = array("result" => 0);
		}
		return $res;
	}
	public function loginCookie($key) {
		$this->user = $user = $this->DC->getByQuery("SELECT * FROM ost_user WHERE md5(concat('sdkfb2irzsidfz8edtfwuedfgwjehfwje', u_pk))='".addslashes($key)."' AND u_confirmed!='0000-00-00 00:00:00' ");
		if($user!="") {
			setS("user", $user); 
			$res = array("result" => 1);
		} else {
			$res = array("result" => 0);
		}
		return $res;
	}
	public function logout() {
		setS("user", "");
		setCookie('ownStaGram', '', time()+60*60*24*365);
		$res = array("result" => 1);
		return $res;
	}
	
	public function uploadapp() {
		$res = $this->login($_POST['email'], $_POST["password"]);
		if($res["result"] == 1) {
			$u_pk = $this->user["u_pk"];
			$path = (int)$u_pk.'/'.date('Ymd');
			if(!file_exists('data/'.$path)) {
				mkdir('data/'.$path, 0777, true);
				chmod('data/'.$path, 0777);
			}
			$fn = $path.'/'.microtime(true).'.jpg';
			
			$M = $_POST["img"];
			$M= str_replace(" ", "+", $M);
			$M = base64_decode($M);
			file_put_contents("data/".$fn, $M);
			
			$data = array('i_u_fk' => (int)$u_pk,
				'i_created' => now(),
				'i_date' => now(),
				'i_file' => $fn,
				'i_changed' => now(),
				'i_key' => $this->newImageKey()
			);
			$pk = $this->DC->insert($data, 'ost_images');
			$this->DC->sendQuery("UPDATE ost_images SET i_key=md5(concat(i_file,i_pk,i_date)) WHERE i_key='' ");
			
			$G = $this->getGroupList();
			$G2 = array();
			for($i=0;$i<count($G);$i++) {
				$G2[] = array("gid" => $G[$i]["g_pk"],
						"name" => $G[$i]["g_name"]
						);
			}
			
			$res = array("result" => 1, "id" => $data["i_key"], "imgid" => md5($data['i_date'].$data['i_file']), "groups" => $G2 );
			return $res;
		}
	}
	
	public function savesetting() {
		$data = $this->getDetail($_POST['ownid']);
		if($data!="") {
			$S = array("i_public" => (int)$_POST['public'],
				   "i_title" => htmlspecialchars(stripslashes($_POST['title'])),
				   "i_g_fk" => (int)$_POST["group"],
				   "i_lat" => $_POST["lat"],
				   "i_lng" => $_POST["lng"],
				   "i_location" => $_POST["location"],
				   'i_changed' => now()
				   );
			$this->DC->update($S, "ost_images", $data['i_pk'], 'i_pk');
			$res = array("result" => 1);
		} else {
			$res = array("result" => 0);
		}
		return $res;
	}
	
	public function newset($setname) {
		$SE = array("se_u_fk" => me(),
			    "se_date" => now(),
			    "se_name" => htmlspecialchars(stripslashes(trim($setname)))
			);
		$se_pk = $this->DC->insert($SE, "ost_sets");
		return $se_pk;
	}
	
	public function upload($files, $u_pk) {
		
		$path = (int)$u_pk.'/'.date('Ymd');
		if(!file_exists('data/'.$path)) {
			mkdir('data/'.$path, 0777, true);
			chmod('data/'.$path, 0777);
		}
		
		$se_pk = 0;
		if(isset($_POST["sameset"])) {
			if($_POST["sameset"]==1) {
				if(trim($_POST["setname"])!="") {
					$se_pk = $this->newset($_POST["setname"]);
				}
			} else if($_POST["sameset"]==2) {
				if((int)$_POST["knownset"]>0) {
					$se_pk = $this->DC->getByQuery("SELECT se_pk FROM ost_sets WHERE se_pk='".(int)$_POST["knownset"]."' AND se_u_fk='".me()."' ", "se_pk");
				}
			}
		}
		
		$subnr = 0;
		for($i=0;$i<count($files['img']['tmp_name']);$i++) {
		
			$fn = $path.'/'.microtime(true).($subnr>0 ? '_'.$subnr : '').'.jpg';
			
			move_uploaded_file($files['img']['tmp_name'][$i], 'data/'.$fn);
			
			$T = trim($_POST['title']);
			if($T!="") {
				$T .= ($subnr>0 ? ' ('.$subnr.')' : '');
			}
			
			$datum = now();
			
			/*$datum = substr($files['img']['name'][$i],0,8);
			$datum = substr($datum,0,4)."-".substr($datum,4,2)."-".substr($datum,6,2);*/
			
			
			$data = array('i_u_fk' => (int)$u_pk,
					'i_created' => $datum,
					'i_date' => $datum,
					'i_file' => $fn,
					'i_title' => htmlspecialchars(stripslashes($T)),
					'i_public' => (int)$_POST['public'],
					'i_set' => $se_pk,
					'i_square' => (int)$_POST['format'],
					'i_changed' => now(),
					'i_key' => $this->newImageKey()
				);
			
			
			$pk = $this->DC->insert($data, 'ost_images');
			$this->DC->sendQuery("UPDATE ost_images SET i_key=md5(concat(i_file,i_pk,i_date)) WHERE i_key='' ");
			#$res = array("result" => 1, "id" => md5($fn.$pk.$data['i_date']));
			$res = array("result" => 1, "id" => $data['i_key']);
			$subnr++;
		}
		
		
		return $res;
		                             
	}
	
	private function newImageKey() {
		$C = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
		$len = 5;
		do {
			$key = "";
			for($i=0;$i<$len;$i++) {
				$key .= substr($C,rand(0,strlen($C)),1);
			}
			$Q = "SELECT count(i_pk) FROM  ost_images WHERE i_key='".$key."' ";
			$test = $this->DC->countByQuery($Q);
		} while($test>0);
		return $key;
	}
	
	public function delete($data) {
		unlink('data/'.$data["i_file"]);
		$this->DC->sendQuery("DELETE FROM ost_images WHERE i_pk='".(int)$data['i_pk']."' ");
	}
	
	public function getScaled($fn, $w, $h, $rot=0, $square=1, $watermark=true) {
		
		if(rand(0,100)>80) $this->unlinkOld();
		
		$ext = strtolower(substr(projectPath.'/data/'.$fn, strrpos(projectPath.'/data/'.$fn,".")));
		if($ext==".jpg" || $ext==".png") {
			$orig = imageCreateFromString(file_get_contents(projectPath.'/data/'.$fn));
		/*} else if($ext==".png") {
			$orig = imageCreateFromPng(projectPath.'/data/'.$fn);
		*/ } else die("Wrong extension.");
		
		$Wo = $w;
		$f = $w/imageSx($orig);
		$Ho = imageSy($orig)*$f;
		
		if($square==1) $Ho = $Wo;
		
		$W = (int)$Wo;if($W<10) $W = 100;
		$H = (int)$Ho;if($H<10) $H = 100;
		
		
		$im = imageCreateTrueColor($W,$H);
		
		
		
		$wh = imageSx($orig);
		$w2 = imageSx($orig);
		$h2 = imageSy($orig);
		
		if($square==1) {
			if($w2>$h2) $w2 = $h2;
			else $h2 = $w2;
		}
		
		$cn = 'data/cache/'.md5($fn.$w.$h).".jpg";
		imagecopyresampled($im, $orig, 0,0, imageSx($orig)/2-$w2/2, imageSy($orig)/2-$h2/2, $Wo, $Ho, $w2, $h2);
		
		if((int)$rot!=0) $im = imagerotate($im, $rot*(-90), 0);

		if($watermark) $this->addWatermark($im);
		
		imageJpeg($im, projectPath.'/'.$cn, 90);
		return $cn;
		
	}
	
	public function addWatermark(&$im) {
		
		if(trim($this->settings['s_watermark'])=="") return;
		
		$size = 5;
		do {
			$size+=2;
			$bbox = $this->imagettfbbox_fixed($size, 0, projectPath.'/resources/KunKhmer.ttf', $this->settings['s_watermark']);
			$w = $bbox[4]-$bbox[0];
			$h = $bbox[5]-$bbox[1];
		} while($w<imageSx($im)*0.9 && $size<100);
		
		$color = "CCCCCC";
		$opacity = 30;
		$alpha_color = imagecolorallocatealpha(
			      $im,
			      hexdec( substr( $color, 0, 2 ) ),
			      hexdec( substr( $color, 2, 2 ) ),
			      hexdec( substr( $color, 4, 2 ) ),
			      127 * ( 100 - $opacity ) / 100
			    );
		imagettftext( $im, $size, 0, imageSx($im)/2-$w/2,imageSy($im)/2-$h/2, $alpha_color, projectPath.'/resources/KunKhmer.ttf', $this->settings['s_watermark'] );
		imageLine($im, 0,0, imageSx($im), imageSy($im), $alpha_color);
		imageLine($im, imageSx($im),0, 0,imageSy($im), $alpha_color);
		
		imageLine($im, 0,0, imageSx($im), imageSy($im)/2, $alpha_color);
		imageLine($im, 0,0, imageSx($im)/2, imageSy($im), $alpha_color);
	}
	
	private function imagettfbbox_fixed( $size, $rotation, $font, $text )
	  {
	    $bb = imagettfbbox( $size, 0, $font, $text );
	    $aa = deg2rad( $rotation );
	    $cc = cos( $aa );
	    $ss = sin( $aa );
	    $rr = array( );
	    for( $i = 0; $i < 7; $i += 2 )
	    {
	      $rr[ $i + 0 ] = round( $bb[ $i + 0 ] * $cc + $bb[ $i + 1 ] * $ss );
	      $rr[ $i + 1 ] = round( $bb[ $i + 1 ] * $cc - $bb[ $i + 0 ] * $ss );
	    }
	    return $rr;
	  }	
	
	
	public function unlinkOld() {
		$G = glob(projectPath.'/data/cache/*.jpg');
		for($i=0;$i<count($G);$i++) {
			if(filemtime($G[$i])<time()-60*60*24*30) {
				if(file_Exists($G[$i]) && is_file($G[$i])) unlink($G[$i]);
			}
		}
	}

	public function getDetail($id) {
		// md5(concat(i_file,i_pk,i_date))  
		$Q = "SELECT ost_images.*,i_key as id, ost_user.u_nickname, ost_user.u_country, ost_user.u_city  
			FROM ost_images 
			INNER JOIN ost_user ON i_u_fk=u_pk
			WHERE i_key='".addslashes($id)."' ";
		$data = $this->DC->getByQuery($Q);
		return $data;
	}
	public function updateDetails($id, $data) {
		$detail = $this->getDetail($id);
		if($detail['i_u_fk']!=me()) die("no access!");
		
		if((int)$data['set']==-1) {
			$data['set'] = 0;
			if(trim($_POST['newsetname'])!='') {
				$data['set'] = $this->newset($_POST['newsetname']);
			}
		}
		
		$new = array(
				'i_date' => date("Y-m-d", strtotime($data['date'])),
				'i_title' => htmlspecialchars(stripslashes($data['title'])),
				'i_location' => htmlspecialchars(stripslashes($data['location'])),
				'i_public' => (int)$data['public'],
				'i_g_fk' => (int)$data['group'],
				'i_set' => (int)$data['set'],
				'i_square' => (int)$data['format'],
				'i_changed' => now()
				);
		$this->DC->update($new, "ost_images", $detail["i_pk"], "i_pk");
	}
	public function hitPhoto($u_fk, $data) {
		
		if(me()==0) {
			$this->DC->sendQuery("UPDATE ost_images SET i_views=i_views+1 WHERE i_pk='".(int)$data['i_pk']."' ");
		}
		if(me()>0 && me()!=$data['i_u_fk'] && getS('user', 'u_email')!=ownStaGramAdmin) {

			$Q = "SELECT * FROM ost_views WHERE v_u_fk='".(int)$u_fk."' AND v_i_fk='".(int)$data["i_pk"]."' ";
			$V = $this->DC->getByQuery($Q);
			if($V=="") {
				$V = array("v_u_fk" => (int)$u_fk,
					   "v_i_fk" => (int)$data['i_pk'],
					   "v_date" => date("Y-m-d H:i:s")
					   );
				$this->DC->insert($V, "ost_views");
			}
			
		}
	}
	
	public function picturesForUser($u_pk) {
		$Q = "SELECT count(*) FROM ost_images WHERE i_u_fk='".(int)$u_pk."' ";
		return $this->DC->countByQuery($Q);
	}
	
	public function picturesForGroups($g_pk) {
		$Q = "SELECT count(*) FROM ost_images WHERE i_g_fk='".(int)$g_pk."' ";
		return $this->DC->countByQuery($Q);
	}
	
	public function getCollected($from) {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT *, i_key as id FROM ost_images
		INNER JOIN ost_views ON v_i_fk=i_pk 
		WHERE v_u_fk='".(int)$from."' ";
		$Q .= " ORDER BY i_date DESC ";
		
		$data = $this->DC->getAllByQuery($Q);
		$data = $this->fillExtraData($data);
		return $data;
	}
	
	public function getList($from, $filter='') {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT *, i_key as id FROM ost_images WHERE i_u_fk='".(int)$from."' ";
		if($filter=="fav") $Q .= " AND i_star=1 ";
		$Q .= " ORDER BY i_date DESC ";
		
		$data = $this->DC->getAllByQuery($Q);
		$data = $this->fillExtraData($data);
		return $data;
	}
	
	private function fillExtraData($data) {
		for($i=0;$i<count($data);$i++) {
			$data[$i]["views"] = $this->DC->countByQuery("SELECT count(*) FROM ost_views WHERE v_i_fk='".(int)$data[$i]["i_pk"]."'  ");
			$data[$i]["comments"] = $this->DC->countByQuery("SELECT count(*) FROM ost_comments WHERE co_i_fk='".(int)$data[$i]["i_pk"]."'  ");
		}
		return $data;
	}
	
	public function listgallery($email, $pass) {
		 $u = $this->login($email, $pass);
		 if($u["result"]==1) {
		 	 $L = $this->getList((int)$this->user['u_pk']);
		 	 for($i=0;$i<count($L);$i++) {
		 	 	 $L[$i]["imgid"] = md5($L[$i]['i_date'].$L[$i]['i_file']);
		 	 }
		 	 return array("result" => 1, "list" => $L);
		 }
		 return $u;
	}
	
	public function getNextImages($data) {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT *,i_key as id FROM ost_images WHERE i_u_fk='".$data["i_u_fk"]."' AND i_date>'".$data["i_date"]."' ORDER BY i_date LIMIT 3";
		$D = $this->DC->getAllByQuery($Q);
		$D = array_reverse($D);
		return $D;
	}
	public function getPrevImages($data) {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT *,i_key as id FROM ost_images WHERE i_u_fk='".$data["i_u_fk"]."' AND i_date<'".$data["i_date"]."' ORDER BY i_date DESC LIMIT 3";
		$D = $this->DC->getAllByQuery($Q);
		return $D;
	}
	public function getComments($i_pk) {
		$Q = "SELECT * FROM ost_comments
			INNER JOIN ost_user ON u_pk=co_u_fk
			WHERE co_i_fk='".(int)$i_pk."' ORDER BY co_date";
		$data = $this->DC->getAllByQuery($Q);
		return $data;
	}
	public function getimagedetails($id) {
		$data = $this->getDetail($id);
		if($data["i_pk"]>0) {
			$res = array("result" => 1, "image" => $data);
			return $res;
		}
	}
	public function addComment($id, $comment) {
		$data = $this->getDetail($id);
		if($data["i_pk"]>0) {
			$C = array("co_i_fk" => $data["i_pk"],
				   "co_u_fk" => me(),
				   "co_date" => now(),
				   "co_comment" => htmlspecialchars(stripslashes($comment))
				);
			$this->DC->insert($C, "ost_comments");
			$res = array("result" => 1);
			return $res;
		}
	}
	public function setStar($id, $star) {
		$res = array("result" => 0);
		$data = $this->getDetail($id);
		if($data["i_pk"]>0 && $data['i_u_fk']==me()) {
			$this->DC->sendQuery("UPDATE ost_images SET i_star='".(int)$star."' WHERE i_pk='".$data['i_pk']."' AND i_u_fk='".me()."' ");
			$res = array("result" => 1);
		}
		return $res;
	}
	public function rotate($id, $rotation) {
		$res = array("result" => 0);
		$data = $this->getDetail($id);
		if($data["i_pk"]>0 && $data['i_u_fk']==me()) {
			$R = $data["i_rotation"]+$rotation;
			if($R<0) $R = 3;
			if($R>3) $R = 0;
			$this->DC->sendQuery("UPDATE ost_images SET i_rotation='".(int)$R."' WHERE i_pk='".$data['i_pk']."' AND i_u_fk='".me()."' ");
			$res = array("result" => 1, "img" => md5($data['i_date'].$data['i_file']));
		}
		return $res;
	}
	public function findImage($img) {
		$Q = "SELECT * FROM ost_images WHERE md5(concat(i_date,i_file))='".addslashes($img)."' ";
		$img = $this->DC->getByQuery($Q);
		return $img;
	}
	
	public function getUserList($page=0) {
		//$pp = 20;
		$Q = "SELECT * FROM ost_user ORDER BY u_email "; // LIMIT ".$page.",".$pp;
		$U = $this->DC->getAllByQuery($Q);
		return $U;
	}
	public function getUserData($u_pk) {
		$Q = "SELECT * FROM ost_user WHERE u_pk='".(int)$u_pk."' ";
		$data = $this->DC->getByQuery($Q);
		return $data;
	}
	public function setUserData($u_pk, $data) {
		$user = array("u_email" => $data["email"]);
		if($data['password']!="" && $data['password']==$data['password2']) {
			$user["u_password"] = md5('a4916ab01df010a042c612eb057b4ac23e79530d555354c4a92e1b24301b964f0f7ecd66143c4093ea1470efcfa33042'.$data['password']);
		}
		if(isset($data['confirm'])) $user['u_confirmed '] = now();
		else $user['u_confirmed '] = "0000-00-00 00:00:00";
		if($u_pk==-1) {
			$user["u_registered"] = now();
			$this->DC->insert($user, "ost_user");
		} else {
			$this->DC->update($user, "ost_user", $u_pk, "u_pk");
		}
	}
	
	
	
	public function getGroupList($page=0) {
		//$pp = 20;
		$Q = "SELECT * FROM ost_groups WHERE g_u_fk='".me()."' ORDER BY g_name "; // LIMIT ".$page.",".$pp;
		$U = $this->DC->getAllByQuery($Q);
		return $U;
	}
	public function getSetList($page=0) {
		//$pp = 20;
		$Q = "SELECT * FROM ost_sets WHERE se_u_fk='".me()."' ORDER BY se_pk DESC "; // LIMIT ".$page.",".$pp;
		$U = $this->DC->getAllByQuery($Q);
		return $U;
	}
	public function getOtherSetImages($set) {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT *,i_key as id FROM ost_images
			INNER JOIN ost_sets ON se_pk=i_set
			WHERE i_set='".(int)$set."' ORDER BY i_pk "; // LIMIT ".$page.",".$pp;
		$U = $this->DC->getAllByQuery($Q);
		return $U;
	}
	public function getGroupData($g_pk) {
		$Q = "SELECT * FROM ost_groups WHERE g_pk='".(int)$g_pk."' AND g_u_fk='".me()."' ";
		$data = $this->DC->getByQuery($Q);
		return $data;
	}
	public function setGroupData($g_pk, $data) {
		$group = array("g_name" => $data["groupname"], "g_u_fk" => me());
		if($g_pk==-1) {
			$this->DC->insert($group, "ost_groups");
		} else {
			$GD = $this->getGroupData($g_pk);
			if($GD["g_u_fk"]!=me()) die("no access!");
			$this->DC->update($group, "ost_groups", $g_pk, "g_pk");
		}
	}	
	
	public function getPublics($u_pk=0, $limit=20, $order='rand()') {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT *,i_key as id FROM ost_images WHERE i_public=1 ";
		$Q .= " AND i_u_fk!='".(int)$u_pk."' AND i_u_fk!='".me()."' ";
		$Q .= " ORDER BY ".$order." LIMIT ".$limit;
		$data = $this->DC->getAllByQuery($Q);
		for($i=0;$i<count($data);$i++) {
			$data[$i]["views"] = $this->DC->countByQuery("SELECT count(*) FROM ost_views WHERE v_i_fk='".(int)$data[$i]["i_pk"]."'  ");
			$data[$i]["comments"] = $this->DC->countByQuery("SELECT count(*) FROM ost_comments WHERE co_i_fk='".(int)$data[$i]["i_pk"]."'  ");
		}
		
		return $data;
	}
	public function getNewPublics($since) {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT i_location,i_lat,i_lng,i_title,i_date,i_changed,i_key as id,md5(concat(i_date,i_file)) as imgid  FROM ost_images WHERE i_public=1 ";
		$Q .= " AND i_changed>'".addslashes($since)."'  ";
		$Q .= " ORDER BY i_pk LIMIT 50";
		$data = $this->DC->getAllByQuery($Q);
		for($i=0;$i<count($data);$i++) {
			if($data[$i]['i_lng']!=0) $data[$i]['i_lng'] += blurred();
			if($data[$i]['i_lat']!=0) $data[$i]['i_lat'] += blurred();
		}
		return $data;
	}
	
	public function getPublicRemotes($limit=100) {
		$Q = "SELECT * FROM ost_global_images
		INNER JOIN ost_global ON gl_pk=gi_gl_fk
			WHERE 1 ORDER BY gi_pk DESC LIMIT ".(int)$limit;
		$data = $this->DC->getAllByQuery($Q);
		return $data;
	}
	public function getDetailGlobal($id) {
		$x = explode('-', $id);
		$Q = "SELECT * FROM ost_global_images
			INNER JOIN ost_global ON gl_pk=gi_gl_fk
			WHERE gi_gl_fk='".(int)$x[0]."'
			AND gi_id='".addslashes($x[1])."'
			";
		$data = $this->DC->getByQuery($Q);
		return $data;
	}
	
	public function getOthers($u_pk, $limit=20) {
		// md5(concat(i_file,i_pk,i_date))
		$Q = "SELECT *,i_key as id FROM ost_images WHERE i_public=1 AND i_u_fk='".(int)$u_pk."' ORDER BY rand() LIMIT ".(int)$limit;
		$I = $this->DC->getAllByQuery($Q);
		return $I;
	}
	
	public function twitterconnect() {
		$S = $this->getSettings();
		$P = $this->getProfile();
		$res = array("result" => 0);
		
		$url = "http://www.ow"."nst"."ag"."ram.de/twitter/connect.php?instance=".$S["s_instance"]."&uid=".md5($P['u_pk'].$P['u_email'])."&site=".urlencode($this->getServerUrl());
		$res = json_decode(file_get_contents($url), true);
		
		
		return $res;
	}
	
	public function socialpost() {
		$S = $this->getSettings();
		$P = $this->getProfile();
		if($_POST['type']=="twitter") {
			$url = "http://www.ow"."nst"."ag"."ram.de/twitter/connect.php?";
			$url .= "instance=".$S["s_instance"]."&";
			$url .= "uid=".md5($P['u_pk'].$P['u_email'])."&";
			$url .= "pid=".urlencode($_POST['id'])."&";
			$url .= "src=".urlencode($_POST['src'])."&";
			$url .= "title=".urlencode($_POST['title']);
			$res = json_decode(file_get_contents($url), true);
		}
		return $res;
	}
	public function addemailin() {
		if(trim($_POST['email'])=="") {
			return array("result" => 0);
		}
		$S = $this->getSettings();
		$P = $this->getProfile();
		$url = "http://www.ow"."nst"."ag"."ram.de/emailin/index.php?";
		$url .= "action=add&";
		$url .= "instance=".$S["s_instance"]."&";
		$url .= "uid=".md5($P['u_pk'].$P['u_email'])."&";
		$url .= "site=".urlencode($this->getServerUrl())."&";
		$url .= "email=".urlencode($_POST['email']);
		
		$res = json_decode(file_get_contents($url), true);
		
		$EI = array("ei_u_fk" => me(),
				"ei_email" => stripslashes($_POST['email']),
			);
		$this->DC->insert($EI, "ost_emailin");
		$res["ei"] =  $this->getEmailin();
		return $res;
	}
	
	public function getEmailin($u_pk=0) {
		if($u_pk==0) $u_pk = me();
		$Q = "SELECT ei_email, ei_key FROM ost_emailin 
			WHERE ei_u_fk='".(int)$u_pk."' 
			";
		return $this->DC->getAllByQuery($Q);
	}
	
	public function updateSendmailins($S) {
		foreach($S as $email => $key) {
			$this->DC->sendQuery("UPDATE ost_emailin SET ei_key='".addslashes($key)."' WHERE ei_u_fk='".me()."' AND ei_email='".addslashes($email)."' ");
		}
	}
	
	public function checkemailinForUser($uid) {
		
		$Q = "SELECT * FROM ost_user WHERE md5(concat(u_pk,u_email)) = '".addslashes($uid)."' ";
		$U = $this->DC->getByQuery($Q);
		
		if($U!="") {
			$this->checkemailin($U["u_pk"]);
		}
	}
	
	public function checkemailin($u_pk=0) {
		$S = $this->getSettings();
		$P = $this->getProfile($u_pk);
		
		$EI = $this->getEmailin($u_pk);
		
		$count = 0;
		
		for($i=0;$i<count($EI);$i++) {
			$url = "http://www.ow"."nst"."ag"."ram.de/emailin/index.php?";
			$url .= "action=get&";
			$url .= "instance=".$S["s_instance"]."&";
			$url .= "uid=".md5($P['u_pk'].$P['u_email'])."&";
			$url .= "site=".urlencode($this->getServerUrl())."&";
			$url .= "email=".urlencode($EI[$i]['ei_email'])."&";
			$url .= "key=".urlencode($EI[$i]['ei_key']);
			
			$res = json_decode(file_get_contents($url), true);
			if($res['result']==1) {
				for($j=0;$j<count($res["EI"]);$j++) {
					
					#error_reporting(-1);
					$img = file_get_contents("http://www.ow"."nst"."ag"."ram.de/emailin/index.php?dl=".$res['EI'][$j]['EIKEY']);
					
					$fn = $P['u_pk'].'/'.date('Ymd').'/'.microtime(true).".".$res["EI"][$j]['extension'];
					
					$path = projectPath.'/data/'.$P['u_pk'].'/'.date('Ymd');
					if(!file_exists($path)) {
						mkdir($path, 0777, true);
						chmod($path, 0777);
					}
					
					file_put_contents(projectPath.'/data/'.$fn, $img);
					
					$data = array("i_u_fk" => $P['u_pk'],
							'i_created' => $res["EI"][$j]["imagedate"],
							"i_date" => $res["EI"][$j]["imagedate"],
							"i_file" => $fn,
							"i_title" => $res["EI"][$j]["title"],
							"i_key" => $this->newImageKey(),
							"i_square" => 0,
							'i_changed' => now()
						);
					$this->DC->insert($data, "ost_images");
					$count++;
				}
			}
		}
		$res = array("result" => 1, "count" => $count);
		return $res;
		
	}
	
	public function removeEIkey($email) {
		
		$Q = "UPDATE ost_emailin set ei_u_fk=-ei_u_fk WHERE ei_u_fk='".me()."' AND ei_email='".addslashes($email)."' ";
		$this->DC->sendQuery($Q);
		
		$res = array("result" => 1);
		return $res;
	}
	
	public function updateGlobal() {
		$S = $this->getSettings();
		if($S["s_global"]==1) {
			
			$this->DC->sendQuery("UPDATE ost_settings SET s_global_lastcheck=now()");
			
			$last = $this->DC->getByQuery("SELECT * FROM ost_global ORDER BY gl_changed DESC LIMIT 1", "gl_changed");
			
			$res = file_get_contents("http://www.ow"."nst"."ag"."ram.de/global/index.php?action=getGlobal&instance=".urlencode($S["s_instance"]).'&last='.urlencode($last));
			
			$res = json_decode($res, true);
			if($res['result']==1) {
				$G = $res['globals'];
				for($i=0;$i<count($G);$i++) {
					$Q = "SELECT * FROM ost_global WHERE gl_host='".addslashes($G[$i]["g_host"])."' ";
					$Gx = $this->DC->getByQuery($Q);
					
					$data = array("gl_host" => $G[$i]["g_host"],
						      "gl_changed" => $G[$i]["g_changed"],
						      );
					
					if($Gx=="") {
						if($G[$i]["g_deleted"]=='0000-00-00 00:00:00') {
							$this->DC->insert($data, "ost_global");
						}
					} else {
						if($G[$i]["g_deleted"]=='0000-00-00 00:00:00') {
							$this->DC->update($data, "ost_global", $Gx["gl_pk"], "gl_pk");
						} else {
							$this->DC->sendQuery("DELETE FROM ost_global WHERE gl_pk='".(int)$Gx["gl_pk"]."' ");
						}
					}
				}
					
			}
		}
		$res = array("result" => 1);
		return $res;
		
	}
	
	public function getGlobalPix() {
		$S = $this->getSettings();
		$res = array("result" => 0);
		if($S["s_global"]==1) {
			$last = $this->DC->getByQuery("SELECT * FROM ost_global WHERE gl_last_checked<'".date("Y-m-d H:i:s", time()-60*60)."' ORDER BY gl_last_checked LIMIT 1");
			
			$new = file_get_contents($last['gl_host']."/app.php?action=getGlobalPixNew&host=".urlencode($this->getServerUrl()).'&since='.urlencode($last['gl_last_checked']));
			
			$data = json_decode($new, true);
			
			if($data['result']==1) {
				$res = array("result" => 1);
				
				for($i=0;$i<count($data['pix']);$i++) {
					$D = $data['pix'][$i];
					
					$Q = "SELECT * FROM ost_global_images 
						WHERE gi_gl_fk='".$last["gl_pk"]."'
						AND gi_id='".$D['id']."'
						AND gi_imgid='".$D['imgid']."'
						";
					$P = $this->DC->getByQuery($Q);
					
					$new = array(
						"gi_gl_fk" => $last["gl_pk"],
						"gi_changed" => $D['i_changed'],
						"gi_date" => $D['i_date'],
						"gi_title" => strip_tags($D['i_title']),
						"gi_location" => strip_tags($D['i_location']),
						"gi_lat" => $D['i_lat'],
						"gi_lng" => $D['i_lng'],
						"gi_id" => $D['id'],
						"gi_imgid" => $D['imgid'],
						);
					
					if(isset($P['gi_pk'])) {
						$this->DC->update($new, "ost_global_images", $P['gi_pk'], "gi_pk");
					} else {
						$this->DC->insert($new, "ost_global_images");
					}
					$this->DC->sendQuery("UPDATE ost_global SET gl_last_checked='".addslashes($D['i_changed'])."' WHERE gl_pk='".$last['gl_pk']."' ");
				}
				
			}
			
			
		}
		
		
		return $res;
	}
	
	public function getGlobalPixNew() {
		$S = $this->getSettings();
		$res = array("result" => 0);
		if($S["s_global"]==1) {
			$res = array("result" => 1, "pix" => array());
			
			$res["pix"] = $this->getNewPublics($_GET["since"]);
			
			
		}
		return $res;
	}
}

$own = new ownStaGram();

$update_fn = projectPath.'/data/cache/update.log';
$doUpdate = false;
if(!file_exists($update_fn)) $doUpdate = true;
else if(filemtime($update_fn)<filemtime(projectPath.'/resources/inc.update.php')) $doUpdate = true;
if($doUpdate == true) {
	touch($update_fn);
	include_once(dirname(__FILE__).'/inc.update.php');
}

if(me()<=0) {
	if(isset($_COOKIE['ownStaGram']) && $_COOKIE['ownStaGram']!='') {
		$own->loginCookie($_COOKIE['ownStaGram']);
	}
	
}
