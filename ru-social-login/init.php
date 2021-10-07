<?php
/*
Plugin Name: Ru Social Login
Plugin URI:  https://run-up.co.kr
Description: Naver Kakao Social Login
Version:     1.0.0
Author:      RUNUP
Author URI:  http://www.run-up.co.kr
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

class ru_social_login{
  private $kakao_auth_url = 'https://kauth.kakao.com/oauth/authorize';
  private $kakao_access_token_url = 'https://kauth.kakao.com/oauth/token';
  private $kakao_profile_url = 'https://kapi.kakao.com/v2/user/me';

  private $naver_auth_url = 'https://nid.naver.com/oauth2.0/authorize';
  private $naver_access_token_url = 'https://nid.naver.com/oauth2.0/token';
  private $naver_profile_url = 'https://openapi.naver.com/v1/nid/me';

  private $CONFIG_STRUCTURE = array();

  public function __construct(){
    add_action( 'rest_api_init', array($this, 'ru_social_callback_route') );
    add_action( 'wp_ajax_my_action', array( $this, 'ajax_email_check') );
    add_action( 'wp_ajax_nopriv_my_action', array( $this, 'ajax_email_check') );
    
    # 체크아웃 로그인 재정의
    # remove_action의 플러그인 정의는 기본설정을 덮어쓸수 없다 테마에서 설정하거나 우커머스 설정에서 끄도록 한다
    //remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 0 );
    add_action( 'woocommerce_before_checkout_form', array($this, 'woocommerce_login_form'),  1);


    ## 관리자 메뉴 추가
    add_action( 'admin_menu', array($this, 'add_plugin_page'));
    add_action( 'admin_init', array( $this, 'page_init' ) );
  }

   public function add_plugin_page() {
                add_options_page(
                        'Ru Social Login Settiing',
                        'Ru Social Login',
                        'manage_options',
                        'ru-social-login',
                        array( $this, 'create_admin_page' )
                );
        }
   

/**
     * 관리자 페이지 생성
     */
    public function create_admin_page()
    {

        $this->load_option();


        ?>
        <div class="wrap">
            <h1>카카오/ 네이버 로그인 세팅</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'ru-social-login-setting-group' );
                do_settings_sections( 'ru-social-login-setting' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     *  관리자 페이지 세팅
     */
        public function page_init(){


                ## 옵션에 대한 구조를 생성
                $this->init_config_structure();

                ## 옵션구조에 맞게 양식 생성
                foreach($this->CONFIG_STRUCTURE as $option_key => $option_val){

                        ## 옵션등록
                        register_setting(
                                'ru-social-login-setting-group',  // Option group
                                $option_key,                      // Option name
                                array( $this, 'sanitize' )        // Sanitize
                        );

                        ## 섹션생성
                        add_settings_section(
                                $option_key,                            // ID
                                $option_val['title'],                   // Title
                                array($this, $option_val['callback']),  // Callback
                                'ru-social-login-setting'              // Page
                        );

                        ## 필드생성
                        foreach($option_val["field"] as $field_key => $field_val){

                                $input_option = "";
                                if ( isset($field_val['input_option'] ))
                                        $input_option = $field_val['input_option'];


                                add_settings_field(
                                    $field_key,
                                    $field_val['title'],
                                    array( $this, 'create_field' ),
                                    'ru-social-login-setting',
                                    $option_key,
                                    ['id'=>$field_key, 'option'=>$option_key, 'key'=>$field_key, 'desc'=>$field_val['desc'], 'type'=>$field_val['type'], 'input_option'=>$input_option]
                                );
                        }
                }


        }

    private function load_option(){
        $this->init_config_structure();
        foreach($this->CONFIG_STRUCTURE as $option_key => $option_val){
                $this->options[$option_key] = get_option( $option_key );
        }
    }


    public function sanitize( $input )
    {
        $new_input = array();
        foreach($input as $key => $val){
	    switch($key){
 		case "is_enable" :
		case "is_marketing" : 
                        $new_input[$key] = ( $input[$key] == 0 || $input[$key] == 1 )?$input[$key]:"";
                        break;
                default :
                        $new_input[$key] = sanitize_text_field( $input[$key] );
                        break;
            }
        }
        return $new_input;
    }


/**
     * 관리자 섹션 출력
     */

    public function section_print_global()
    {
        print '설정값을 입력하세요';
    }


    /**
     * 폼데이타 출력 콜백
     */
    public function create_field($args)
    {
        if($args["type"] == "text"){
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />%s',
                    $args["id"],
                    $args["option"]."[".$args["key"]."]",
                    isset( $this->options[$args["option"]][$args["key"]] ) ? esc_attr( $this->options[$args["option"]][$args["key"]]) : '',
                    isset( $args["desc"] ) ? "<p>".$args["desc"]."</p>" : ''
                );
        }else if($args["type"] == "select"){
                $select_option = "";
                foreach($args["input_option"] as $key => $val){
                        $chk="";
                        if($key == $this->options[$args["option"]][$args["key"]] ) $chk = " selected";
                        $select_option .= '<option value="'.$key.'"'.$chk.'>'.$val.'</option>';
                }
                printf(
                   '<select id="%s" name="%s">%s</select>%s',
                   $args["id"],
                   $args["option"]."[".$args["key"]."]",
                   $select_option,
                   isset( $args["desc"] ) ? "<p>".$args["desc"]."</p>" : ''
                );

        }
    }

        private function init_config_structure(){

                # 옵션의 기본구조 정의
                $this->CONFIG_STRUCTURE = array(
			"ru_social_login_global" => array(
				"title"=>"기본설설정",
                                "callback"=>"section_print_global",
                                "field"=>array(
                                        "is_marketing"  => array("type"=>"select", "default"=>"1", "title"=>"마케팅 수신동의",   "desc"=>"", "input_option"=>array("1"=>"사용","0"=>"사용안함")),
					"privacy_url"  => array("type"=>"text", "default"=>"", "title"=>"개인정보 수집동의 페이지",   "desc"=>"" ),
					"agree_url"  => array("type"=>"text", "default"=>"", "title"=>"이용약관 페이지",   "desc"=>"" )
                                )

			),
                        "ru_social_login_naver" => array(
                                "title"=>"네이버 로그인 설정",
                                "callback"=>"section_print_global",
                                "field"=>array(
					"is_enable"  => array("type"=>"select", "default"=>"1", "title"=>"사용여부",   "desc"=>"", "input_option"=>array("1"=>"예","0"=>"아니오")),
                                        "api_key"    => array("type"=>"text",   "default"=>"",  "title"=>"API 호출키", "desc"=>"내 애플리케이션 > 개요 > Client ID"),
                                        "secret_key" => array("type"=>"text",   "default"=>"",  "title"=>"API 호출ID", "desc"=>"내 애플리케이션 > 개요 > Client Secret ")
                                )
                        ),
			"ru_social_login_kakao" => array(
                                "title"=>"카카오 로그인 설정",
                                "callback"=>"section_print_global",
                                "field"=>array(
                                        "is_enable" => array("type"=>"select", "default"=>"1", "title"=>"사용여부", "desc"=>"", "input_option"=>array("1"=>"예","0"=>"아니오")),
                                        "api_key" => array("type"=>"text", "default"=>"", "title"=>"API 호출키", "desc"=>"내 애플리케이션 > 설정 > 일반 > REST API키"),
                                        "secret_key" => array("type"=>"text", "default"=>"", "title"=>"API 호출ID", "desc"=>"내 애플리케이션 > 설정 > 고급 > Client Secret ")
                                )
                        )
                        

                );
	}


public function woocommerce_login_form( $args = array() ) {
if(!is_user_logged_in()){
  echo '<div style="text-align:center">';
  echo '<h3>SNS계정으로 로그인</h3>';
  echo do_shortcode ( '[ru_social_login_form type="login"]' );
  echo '</div>';

  echo '<ul style="padding:0; margin:0; margin-bottom:50px">
<li style="margin-top:10px">아직 SNS회원가입을 하지 않으셨다면 네이버.카카오 로그인 인증후 바로 회원가입 페이지로 이동합니다 <strong>10초안에 가입</strong>후 서비스 이용이 가능합니다</li>
<li style="margin-top:10px">계정이 없으시다면 아래 비회원주문을 이용해주세요</li>
</ul>';
}
}

// CUSTOM ENDPOINT REGISTER 
public function ru_social_callback_route() {
    // SNS인증후 콜백
    register_rest_route( 'ru_social_login', 'callback', array(
                    'methods' => 'GET',
                    'callback' => array($this, 'social_callback'),
                )
            );

    // SNS 회원가입 - FRONT
    register_rest_route( 'ru_social_login', 'register', array(
                    'methods' => 'POST',
                    'callback' => array($this, 'social_register'),
                )
            );

    // SNS 회원가입 - BACK
    register_rest_route( 'ru_social_login', 'register-account', array(
                    'methods' => 'POST',
                    'callback' => array($this, 'social_register_account'),
                )
            );


} 


// SOCIAL MEMBER CHECK 
public function register_check($social_account, $social_account_id){

	global $wpdb;
	$result = $wpdb->get_row(
		$wpdb->prepare ("SELECT u.id, u.user_login
			FROM $wpdb->usermeta um1, $wpdb->usermeta um2, $wpdb->users u
			WHERE um1.user_id = um2.user_id
			AND um1.meta_key = 'ru_social_account'
			AND um2.meta_key = 'ru_social_account_id'
			AND u.ID = um2.user_id
			AND um1.meta_value = %s
			AND um2.meta_value = %s", $social_account, $social_account_id)
	);
	
	return $result;
}

// VALIDATION AND CREATER USER
public function social_register_account(){
	
	// wp nonce check 
	if ( ! isset( $_POST['social_register_token'] ) || ! wp_verify_nonce( $_POST['social_register_token'], 'social_register' ) ) {
   		print 'Sorry, your nonce did not verify.';
		exit;
	}


	// 소셜프로필 가져오기
	$social_auth = false; 
	switch($_POST['social_account']){
		case "ru_social_login_kakao" : 
			$social_profile = $this->kakao_profile($_POST["access_token"]);
			if( is_wp_error( $social_profile )){
                        	echo $social_profile->get_error_code() . " : " . $social_profile->get_error_message();
	                        exit;
                	}
			$social_auth = true;
			break;
		case "ru_social_login_naver" : 
			$social_profile = $this->naver_profile($_POST["access_token"]);
                        if( is_wp_error( $profile )){
                                echo $social_profile->get_error_code() . " : " . $social_profile->get_error_message();
                                exit;
                        }
                        $social_auth = true;
			break; 
	}

	if( $social_auth === false ){
		echo "please auth social account";
		exit;
	}
	
	// 강력한 암호 만들기 	
	$social_profile->user_pass = wp_generate_password(20);
	
	// 이메일정보가 없다면 사용자가 입력한 메일주소 가져오기 
	if( $_POST["email"] ) $social_profile->email = $_POST["email"];
	
	// 마케팅정보수신 동의 
	$social_profile->marketing_accept = ($_POST["marketingAccept"] == 'on')?"Y":"N";


	$user_id = $this->create_account($social_profile);
	if( is_wp_error( $user_id ) ) {
		echo $user_id->get_error_code() . " : " . $user_id->get_error_message();
		exit;
	}


	// 로그인
	$user = new WP_User( $user_id );
	wp_set_auth_cookie( $user_id );
	do_action( 'wp_login', $user->user_login, $user );

	// 리다이렉트  
	wp_redirect($_POST["redirect_to"]);

	

}

// CREATE WP USERS
public function create_account($social_profile){
	$user = $social_profile->user_login;
	$email = $social_profile->email;
	$pass = $social_profile->user_pass;
	$social_account = $social_profile->social_account;
	$social_account_id = $social_profile->social_accound_id;
	$nickname = $social_profile->user_nickname;
	$firstname = $social_profile->user_firstname;
	$marketing_accept = $social_profile->marketing_accept;

        if(empty($user) || empty($pass) || empty($email)) return new WP_Error( 'register-failed', 'user_login or pass or email empty');


        if ( !username_exists( $user ) && !email_exists($email) ) {
	    // 회원가입 
            $user_id = wp_create_user( $user, $pass, $email );
            $user = new WP_User( $user_id );

	    // 역할설정(고객)
            $user->set_role( 'customer' );
	    
	    // 닉네임 & 디스플레이네임 설정 
	    $args = array(
                'ID' => $user_id,
                'nickname' => $nickname,
		'first_name' => $firstname,
                'display_name' => $nickname,
            );
	    wp_update_user( $args );

	    // 메타데이터 세팅
	    add_user_meta($user_id, 'ru_social_account', $social_account);
	    add_user_meta($user_id, 'ru_social_account_id', $social_account_id);
	    if($marketing_accept == 'Y') add_user_meta($user_id, 'ru_marketing_accept', 'Y');
	    
            return $user_id; 

        }else{
	   return new WP_Error( 'resigter-error', 'username or email exists' );
	}
}


public function ajax_email_check(){
  if( empty($_POST["mail"]) || email_exists($_POST["mail"])) echo 'DISABLE';
  else echo 'ENABLE';
  wp_die();
}

public function social_register(){


wp_enqueue_script( 'ajax-script', plugins_url( '/inc/script.js', __FILE__ ), array('jquery'),'1.0.47' );
wp_localize_script( 'ajax-script', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )) );

if ( ! isset( $_POST['social_register_token'] ) || ! wp_verify_nonce( $_POST['social_register_token'], 'social_register' ) ) { 
   print 'Sorry, your nonce did not verify.';
   exit;
}

$loginStr = '';


// 소셜프로필 가져오기
        $social_auth = false;
        switch($_POST['social_account']){
                case "ru_social_login_kakao" :
                        $social_profile = $this->kakao_profile($_POST["access_token"]);
                        if( is_wp_error( $social_profile )){
                                echo $social_profile->get_error_code() . " : " . $social_profile->get_error_message();
                                exit;
                        }
                        $social_auth = true;
			$loginStr = '카카오로그인';
                        break;
                case "ru_social_login_naver" :

                        $social_profile = $this->naver_profile($_POST["access_token"]);
                        if( is_wp_error( $social_profile )){
                                echo $social_profile->get_error_code() . " : " . $social_profile->get_error_message();
                                exit;
                        }
                        $social_auth = true;
			$loginStr = '네이버로그인';
                        break;
        }

        if( $social_auth === false ){
                echo "please auth social account";
                exit;
        }

$this->load_option();

header('Content-Type: text/html');
get_header();


?>

<style type="text/css">
#sns-register-wrapper{max-width:350px; margin:100px auto; text-align:center}
#sns-register-wrapper h1{font-size:20px;}
#sns-register-wrapper p.sns-register-desc{font-size:14px; color:#3e3e3e; margin-top:15px}
#sns-register-wrapper .form-field{margin:5px 0}
#sns-register-wrapper button{margin-top:20px; width:100%}
#sns-register-wrapper .form-field-input { margin:5px 0; border:1px solid #dedede; border-radius:0; display:block; padding:10px 20px; color:#3c3c3c; text-align:left; overflow:hidden; cursor:pointer}
#sns-register-wrapper .form-field-input label{ width:100%; box-sizing:border-box;}
#sns-register-wrapper .form-field-input input{ width:100%; box-sizing:border-box;}
#sns-register-wrapper .form-field-input i{font-size:1.5em}
#sns-register-wrapper .form-field-input i{font-size:1.5em; vertical-align:middle; line-height:1}
#sns-register-wrapper .form-field-input span{vertical-align:middle; line-height:1; margin-left:5px}

#sns-register-wrapper .form-field label{border:1px solid #dedede; border-radius:0; display:block; padding:10px 20px; color:#9a9a9a; text-align:left;cursor:pointer}
#sns-register-wrapper .form-field input[type="checkbox"]{display:none}
#sns-register-wrapper .form-field input[type="checkbox"]:checked + label{ background-color:#1BA345; color:#fff }

#sns-register-wrapper .form-field i{font-size:1.5em; vertical-align:middle; line-height:1}
#sns-register-wrapper .form-field span{vertical-align:middle; line-height:1; margin-left:5px}

#sns-register-wrapper .agree { margin-top:20px}
#sns-register-wrapper .agree a{ border:1px solid #dedede; display:inline-block; padding:7px 0; width:40%; box-sizing:border-box }
</style>


<?php
$social_account = $_POST["social_account"];
$access_token = $_POST["access_token"];
$redirect_to = $_POST["redirect_to"];
$email = $social_profile->email;


echo '<div id = "sns-register-wrapper"><h1>SNS  회원가입</h1>';
echo '<p class="sns-register-desc"><strong>'.$loginStr.'</strong> 인증이 완료되었습니다<br />아래 양식작성후 회원가입을 마무리해주세요</p>';
echo '<form id = "sns-register" name="snsRegister" onsubmit="return snsRegisterValidation();" action="/wp-json/ru_social_login/register-account" method="POST">';
echo '<input type="hidden" name="social_account" placeholder="social_account" value="'.$social_account.'" />';
echo '<input type="hidden" name="access_token" placeholder="aceess_token" value="'.$access_token.'" />';
echo '<input type="hidden" name="redirect_to" placheholder="redirect_to" value="'.$redirect_to.'" />';
wp_nonce_field( 'social_register', 'social_register_token' );

// 제공받은 이메일주소가 없거나 중복되면 이메일을 입력받는다 
if( empty($email) || email_exists($email) ){
	echo '<p class="form-field-input"><label for="email"><i class="far fa-envelope"></i><span>이메일주소(필수)</span></label><input type="email" name="email" id="sns-register-email" /></p>';
}

echo '<p class="form-field"><input id="service-accept-all" name="serviceAcceptAll" type="checkbox"><label for="service-accept-all"><i class="far fa-check-circle"></i><span>전체 동의하기</span></label></p>';
echo '<p class="form-field"><input id="service-accept" name="serviceAccept" type="checkbox"><label for="service-accept"><i class="far fa-check-circle"></i><span>서비스 이용약관 확인 및 동의(필수)</span></label></p>';
echo '<p class="form-field"><input id="privacy-accept" name="privacyAccept" type="checkbox"><label for="privacy-accept"><i class="far fa-check-circle"></i><span>개인정보 수집 및 활용에 동의(필수)</span></label></p>';

// 마케팅 정보 수신동의
if($this->options["ru_social_login_global"]["is_marketing"]){
echo '<p class="form-field"><input id="marketing-accept" name="marketingAccept" type="checkbox"><label for="marketing-accept"><i class="far fa-check-circle"></i><span>마케팅정보 수신 동의(선택)</span></label></p>';
}

echo '<button>회원가입</button>';
echo '</form>';
echo '<p class="agree"><a href="/'.$this->options["ru_social_login_global"]["privacy_url"].'" target="_blank">개인정보처리방침</a> <a href="/'.$this->options["ru_social_login_global"]["agree_url"].'" target="_blank">이용약관</a></p>';
echo '</div>';
get_footer();
}

// 카카오 로그인 인증 
public function kakao_auth($code){
	$this->load_option();
	
	// ACCESS TOKEN 요청
	$url = $this->kakao_access_token_url;
	$client_id = $this->options["ru_social_login_kakao"]["api_key"];
	$client_secret = $this->options["ru_social_login_kakao"]["secret_key"]; 
	$redirect_uri  = (($_SERVER["HTTPS"]=='on')?"https://":"http://") . $_SERVER["HTTP_HOST"].'/wp-json/ru_social_login/callback';
	

	$post = array(
	  "grant_type"=>"authorization_code",
	  "client_id"=>$client_id,
	  "redirect_uri"=>$redirect_uri,
	  "code"=>$code,
	  "client_secret"=>$client_secret
	);

	$result = $this->rest_api_curl($url, $post);
	

	// ACCESS TOKEN 발급
	if(!empty($result->error)) return new WP_Error( $result->error, $result->error_description);
	else return $result->access_token;

}

public function kakao_profile($access_token){
	
	// GET USER INFORMATION
        $url = $this->kakao_profile_url;
        $post = "";
        $header = array('Authorization: Bearer '.$access_token, 'Content-Type: application/x-www-form-urlencoded;charset=utf-8');
        $result = $this->rest_api_curl($url, $post, $header);

	if( empty($result->id) ) return new WP_Error( 'auth-failed', 'get kakao profile failed');
	
	$social_profile = (object) array(
                'social_account' => 'kakao',
                'social_accound_id' => $result->id,
                'email' => (isset($result->kakao_account->email))?$result->kakao_account->email:'',
                'user_login' => 'kakao_'.$result->id,
                'user_nickname' => $result->kakao_account->profile->nickname
        );

	#echo "<pre>";
	#print_r($result);
	#echo "</pre>";

	return $social_profile;
}


// 네이버로그인 인증
public function naver_auth($code, $state){
	$this->load_option();

        // ACCESS TOKEN 요청
        $url = $this->naver_access_token_url;
        $client_id = $this->options["ru_social_login_naver"]["api_key"];
        $client_secret = $this->options["ru_social_login_naver"]["secret_key"];
        $redirect_uri  = (($_SERVER["HTTPS"]=='on')?"https://":"http://") . $_SERVER["HTTP_HOST"].'/wp-json/ru_social_login/callback';


        $post = array(
          "grant_type"=>"authorization_code",
          "client_id"=>$client_id,
          "redirect_uri"=>$redirect_uri,
          "code"=>$code,
	  "state"=>$state,
          "client_secret"=>$client_secret
        );

        $result = $this->rest_api_curl($url, $post);
	

        // ACCESS TOKEN 발급
        if(!empty($result->error)) return new WP_Error( $result->error, $result->error_description);
        else return $result->access_token;

}

// 네이버프로필정보 
public function naver_profile($access_token){
	
	// GET USER INFORMATION
        $url = $this->naver_profile_url;
        $post = "";
        $header = array('Authorization: Bearer '.$access_token);
        $result = $this->rest_api_curl($url, $post, $header);
	

        if( $result->resultcode != '00' ) return new WP_Error( 'profile-failed', 'get naver profile failed');


        $social_profile = (object) array(
                'social_account' => 'naver',
                'social_accound_id' => $result->response->id,
                'email' => (isset($result->response->email))?$result->response->email:'',
                'user_login' => 'naver_'.$result->response->id,
                'user_nickname' => $result->response->nickname
        );



        return $social_profile;
}

// 콜백
public function social_callback(){

// csrf검증을 위한 세션시작
session_start();

// state검증
if(!$_GET["state"]){
    echo "허용되지 않은 접속입니다";
    exit;
}

// 변수할당 및 소셜유형 확인 
$social_token = explode("|", $_GET["state"]);

$redirect_to = $social_token[0];
$social_account = $social_token[1];
$social_type = $social_token[2];
$csrf_token = $social_token[3];


$csrf_session = $_COOKIE["ru_snslogin_token"];


// csrf검증 
if($csrf_session != $csrf_token){
   echo "인증을 위한 토큰이 일치하지 않습니다";
   exit;
}


// 인증 및 프로필 가져오기 
switch($social_account){
	case 'ru_social_login_kakao' : 
		// 카카오 인증
		$access_token = $this->kakao_auth($_GET["code"]);
		if( is_wp_error( $access_token )){
			echo $access_token->get_error_code() . " : " . $access_token->get_error_message(); 
			exit;
		}

		$profile = $this->kakao_profile($access_token);
		if( is_wp_error( $profile )){
                        echo $profile->get_error_code() . " : " . $profile->get_error_message();
                        exit;
                }
	break;
	case 'ru_social_login_naver' :
		// 네이버 인증
		$access_token = $this->naver_auth($_GET["code"], $_GET["state"]);
		if( is_wp_error( $access_token )){
                        echo  $access_token->get_error_code() . " : " . $access_token->get_error_message();
                        exit;
                }

		$profile = $this->naver_profile($access_token);
		if( is_wp_error( $profile )){
                        echo $profile->get_error_code() . " : " . $profile->get_error_message();
                        exit;
                }
	break;
}

if( empty($access_token) || empty($profile) ){
	echo '소셜인증실패'; 
	exit;
}

// 소셜회원 여부 체크 
$result = $this->register_check($profile->social_account, $profile->social_accound_id);

if(isset($result->id)){  // SOCIAL 가입이 되어있다면
header('Content-Type: text/html');	
	// SOCIAL계정에 연결된 ID가 활성화 되어 있다면 즉시 로그인
	$user = new WP_User( $result->id );
        wp_set_auth_cookie( $result->id );
        do_action( 'wp_login', $user->user_login, $user );

	// 이전페이지로 리다이레트
	wp_redirect( $redirect_to );


}else{  // SOCIAL 가입이 되어있지 않다면  

	// SNS 간편가입으로 리다이렉트
	header('Content-Type: text/html');
	//echo '<script>alert("연결된 계정이 없습니다 간편회원가입후 서비스페이지로 이동합니다");</script>';
	echo '<form name = "snsRegisterForm" method = "post" action = "/wp-json/ru_social_login/register">';
	echo '<input type = "hidden" name = "social_account" value = "'.$social_account.'" />';
	echo '<input type = "hidden" name = "access_token" value = "'.$access_token.'" />';
	echo '<input type = "hidden" name = "redirect_to" value = "'.$redirect_to.'" />';
	wp_nonce_field( 'social_register', 'social_register_token' );
	echo '</form>';
	echo '<script>';
	echo 'document.snsRegisterForm.submit();';
	echo '</script>';
}


}


  // CURL POST AND RETURN JSON 
  public function rest_api_curl($url='', $post=array(), $header=array()){
    $ch = curl_init();

    $post_field=($post)?http_build_query($post):"";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_field);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    $output = curl_exec($ch); // 데이터 요청 후 수신
    curl_close($ch);  // 리소스 해제
    $json = json_decode($output);

    return $json;
  }

  public function get_social_button($social_account, $val, $type, $myToken=null){

    $redirect_uri  = (($_SERVER["HTTPS"]=='on')?"https://":"http://") . $_SERVER["HTTP_HOST"].'/wp-json/ru_social_login/callback';

    switch($social_account){
      case "ru_social_login_kakao" : 
	$form  = '<form action = "'.$this->kakao_auth_url.'" method="GET" class="sns-login kakao">';
	$form .= '<input type = "hidden" name = "client_id" value="'.$val["api_key"].'" />';
        $form .= '<input type = "hidden" name = "redirect_uri" value = "'.$redirect_uri.'" />';
        $form .= '<input type = "hidden" name = "response_type" value = "code" />';
        $form .= '<input type = "hidden" name = "state" value = "'.$_SERVER["REQUEST_URI"].'|'.$social_account.'|'.$type.'|'.$myToken.'" />';

	if($type == 'login')
		$form .= '<button><span>카카오아이디로 로그인</span></button>';
	else if($type == 'register')
		$form .= '<button><span>카카오아이디로 회원가입</span></button>';
	
	$form .= '</form>';
	break;
      case "ru_social_login_naver" :
	$form  = '<form action = "'.$this->naver_auth_url.'" method="GET" class="sns-login naver">';
        $form .= '<input type = "hidden" name = "client_id" value="'.$val["api_key"].'" />';
        $form .= '<input type = "hidden" name = "redirect_uri" value = "'.$redirect_uri.'" />';
        $form .= '<input type = "hidden" name = "response_type" value = "code" />';
        $form .= '<input type = "hidden" name = "state" value = "'.$_SERVER["REQUEST_URI"].'|'.$social_account.'|'.$type.'|'.$myToken.'" />';

        if($type == 'login')
                $form .= '<button><span>네이버아이디로 로그인</span></button>';
        else if($type == 'register')
                $form .= '<button><span>네이버아이디로 회원가입</span></button>';

        $form .= '</form>';
        break;
    }

    return $form;

  }

  public function get_shortcode($args){

	$this->load_option();

	#session_start();
	#$myToken = bin2hex(openssl_random_pseudo_bytes(10));
	#$_SESSION['ru_social_csrf_token'] = $myToken;

	$html  = '<style type="text/css">';
	$html .= '.ru-social-login-form{padding:0; margin:0; clear:both; overflow:hidden}';
	$html .= '@media(max-width:767px){ .ru-social_login-form li{width:100% !important;} }';
	$html .= '.ru-social-login-form li{list-style:none; padding:0; margin:5px; display:inline-block;}';
	$html .= '.sns-login button{width:222px; height:48px; border:0; background-color:transparent; background-size:222px 48px; background-repeat: no-repeat}';
	$html .= '.sns-login button span{display:none}';
	$html .= '.sns-login.kakao button{background-image: url(/wp-content/plugins/ru-social-login/img/kakao_button.png);}';
	$html .= '.sns-login.naver button{background-image: url(/wp-content/plugins/ru-social-login/img/naver_button.png);}';
	$html .= '</style>';

	$html .= '<ul class="ru-social-login-form">';

	foreach($this->options as $key => $val){
		if($key != 'ru_social_login_global'){
        		if( $val["is_enable"] && $val["api_key"] ){	
				$button = $this->get_social_button($key, $val, $args["type"]);
				$html .= '<li>'.$button.'</li>';
			}
		}
  	}
	$html .= '</ul>';

	# CSRF TOKEN 생성 
	# 인증키로 REST API 인증을 추가로 받기 때문에 쿠키로 해도 보안에 특별히 취약하지 않다
	$html .= '<script>
	var rand = function() {
    		return Math.random().toString(36).substr(2); // remove `0.`
	};
	jQuery(".ru-social-login-form form").bind("submit", function(){
		var token = rand() + rand();
		var state = jQuery(this).find("input[name=state]");
		document.cookie = "ru_snslogin_token=" + token + ";0;path=/";
		state.val( state.val() + token  );
	});
	</script>';

	return $html;
  }

}


class ru_social_login_shortcode{
  

  //on initialize
  public function __construct(){
      add_action('init', array($this,'register_ru_social_login_shortcodes')); //shortcodes
  }  
  
  public function register_ru_social_login_shortcodes(){
    add_shortcode('ru_social_login_form', array($this,'shortcode_output'));
  }

  public function shortcode_output($args){
    global $ru_social_login;
    return $ru_social_login->get_shortcode($args);
  }

}

$ru_social_login_shortcode = new ru_social_login_shortcode;
$ru_social_login = new ru_social_login;
