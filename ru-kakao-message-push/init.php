<?php
/*
Plugin Name: Ru Kakao Message Push 
Plugin URI:  https://run-up.co.kr
Description: kakao talk push via woocommerce trigger
Version:     1.0.0
Author:      RUNUP
Author URI:  http://www.run-up.co.kr
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

class ru_kakao_push{

	private $DEBUG_MODE = true;
	private $ADMIN_TEMPLATE = "kakao_admin";
	private $USER_TEMPLATE_HOLD = "kakao_hold";
	private $USER_TEMPLATE_PROCESSING = "kakao_processing";
	private $USER_TEMPLATE_COMPLETED = "kakao_completed";

	public function __construct(){

		## 관리자 메뉴 추가 
		add_action( 'admin_menu', array($this, 'add_plugin_page'));
		add_action( 'admin_init', array( $this, 'page_init' ) );
		

		## 우커머스 상태에 따른 트리거 
		add_action( 'woocommerce_order_status_pending', 	array($this, 'mysite_pending'), 10, 1);
		add_action( 'woocommerce_order_status_failed', 		array($this, 'mysite_failed'), 10, 1);
		add_action( 'woocommerce_order_status_on-hold', 	array($this, 'mysite_hold'), 10, 1);
		add_action( 'woocommerce_order_status_processing', 	array($this, 'mysite_processing'), 10, 1);
		add_action( 'woocommerce_order_status_completed', 	array($this, 'mysite_completed'), 10, 1);
		add_action( 'woocommerce_order_status_refunded', 	array($this, 'mysite_refunded'), 10, 1);
		add_action( 'woocommerce_order_status_cancelled', 	array($this, 'mysite_cancelled'), 10, 1);	
	}

	
	private function init_config_structure(){

		# 옵션의 기본구조 정의
        	$this->CONFIG_STRUCTURE = array(
                	"ru_kakao_message_global" => array(
				"title"=>"카카오알림톡 기본설정", 
				"callback"=>"section_print_global", 
				"field"=>array(
                        		"adminMobile" => array("type"=>"text", "default"=>"", "title"=>"관리자 연락처", "desc"=>""),
		                        "is_admin_send" => array("type"=>"select", "default"=>"1", "title"=>"관리자 주문/취소 전송", "desc"=>"", "input_option"=>array("1"=>"예","0"=>"아니오") ),
        		                "is_user_hold_send" => array("type"=>"select", "default"=>"1", "title"=>"고객 입금확인 전송", "desc"=>"", "input_option"=>array("1"=>"예","0"=>"아니오")),
                		        "is_user_processing_send" => array("type"=>"select", "default"=>"1", "title"=>"고객 처리중 전송", "desc"=>"", "input_option"=>array("1"=>"예","0"=>"아니오")),
                        		"is_user_completed_send" => array("type"=>"select", "default"=>"1", "title"=>"고객 완료 전송", "desc"=>"", "input_option"=>array("1"=>"예","0"=>"아니오"))

	                	)
			),
        	        "ru_kakao_message_lgcns" => array(
				"title"=>"LG CNS 톡드림 설정", 
				"callback"=>"section_print_lgcns", 
				"field"=>array(
                 	        	"auth_token" => array("type"=>"text", "default"=>"", "title"=>"API 호출키", "desc"=>""),
	                        	"serverName" => array("type"=>"text", "default"=>"", "title"=>"API 호출ID", "desc"=>""),
		                        "service" => array("type"=>"text", "default"=>"", "title"=>"서비스번호", "desc"=>"")
				)
			),
                        "ru_kakao_message_lgcns_template_mapping" => array(
                                "title"=>"LG CNS 톡드림 템플릿 매핑",
                                "callback"=>"section_print_mapping"
                        )

	        );
		
		# 템플릿 추가 
		foreach ( glob( plugin_dir_path( __FILE__ ) . "template/*.php" ) as $file ) {
                	$fileArr = explode(".", basename($file));
                	$template = $fileArr[0];
			$default = "";
			switch($template){
				case "kakao_admin" : $default = "order_to_admin"; break;
				case "kakao_completed" : $default = "completed_customer"; break;
				case "kakao_hold" : $default = "hold_customer"; break;
				case "kakao_processing" : $default = "processing_customer"; break;
			}

			$this->CONFIG_STRUCTURE["ru_kakao_message_lgcns_template_mapping"]["field"][$template] = array("type"=>"text", "default"=>$default, "title"=>$template, "desc"=>"");
        	}


	}
	
    /**
     * 관리자 메뉴에 추가
     */
	public function add_plugin_page() {
		add_options_page(
			'Ru Kakao Message Settiing', 
			'Ru KaKao Message', 
			'manage_options', 
			'ru-kakao-message', 
			array( $this, 'create_admin_page' ) 
		);	
	}

    private function load_option(){
	$this->init_config_structure();
        foreach($this->CONFIG_STRUCTURE as $option_key => $option_val){
                $this->options[$option_key] = get_option( $option_key );
        }
    }

    /**
     * 관리자 페이지 생성
     */
    public function create_admin_page()
    {

	$this->load_option();


        ?>
        <div class="wrap">
            <h1>카카오 알림톡 세팅</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'ru-kakao-message-setting-group' );
                do_settings_sections( 'ru-kakao-message-setting' );
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
		if( isset($_GET["page"]) && ($_GET["page"] == 'ru-kakao-message'  || $_SERVER["PHP_SELF"] == '/wp-admin/options.php') ) {
		
		## 옵션에 대한 구조를 생성
		$this->init_config_structure();
		
		## 옵션구조에 맞게 양식 생성 
		foreach($this->CONFIG_STRUCTURE as $option_key => $option_val){

			## 옵션등록
			register_setting(
            			'ru-kakao-message-setting-group', // Option group
			        $option_key,         		  // Option name
            			array( $this, 'sanitize' )        // Sanitize
        		);

			## 섹션생성
			add_settings_section(
            			$option_key,                            // ID
            			$option_val['title'],   		// Title
				array($this, $option_val['callback']),  // Callback
            			'ru-kakao-message-setting'              // Page
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
			            'ru-kakao-message-setting',
			            $option_key,
			            ['id'=>$field_key, 'option'=>$option_key, 'key'=>$field_key, 'desc'=>$field_val['desc'], 'type'=>$field_val['type'], 'input_option'=>$input_option]
		        	);
			}
		}

		}

	}



    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
	foreach($input as $key => $val){
	    switch($key){
	    	case "service" : 
  	      		$new_input[$key] = absint( $input[$key] );
			break;
		case "kakao_admin" :
		case "kakao_hold" :
		case "kakao_processing" : 
		case "kakao_completed" :
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

    public function section_print_lgcns()
    {
        print '톡드림 포탈 > API 서비스 관리 화면에서 확인 가능';
    }
  
    public function section_print_mapping()
    {
        print '톡드림 포탈 > 메시지 관리 > 알림톡 템플릿 관리 화면에서 확인 가능';
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

    /**
     * LGCNS 비즈메시지 전송
     */
	public function send_message($template_code, $message, $mobile, $buttons=null){

		$auth_token = $this->options["ru_kakao_message_lgcns"]["auth_token"];
		$serverName = $this->options["ru_kakao_message_lgcns"]["serverName"];
		$service = $this->options["ru_kakao_message_lgcns"]["service"];
		$paymentType = "P";

		if( empty($auth_token) || empty($serverName) || empty($service) || empty($paymentType) || empty($template_code) || empty($message) || empty($message) ) return false;

		$header = array(
			'Content-Type:application/json; charset=utf-8',
			'authToken: ' . $auth_token, 
			'serverName: ' . $serverName,
			'paymentType: ' . $paymentType 
		);

		$body = array(
			'service' => $service,
			'mobile' => $mobile, 
			'template' => $template_code,
			'message' => $message
		);

		if($buttons){
			$body["buttons"][] = array("name"=>"배송조회");
		}

		$body_json = json_encode($body);
		
		if($this->DEBUG_MODE){
			echo "<pre>";
			echo "request header ";
			print_r($header);
			echo "request body ";
			print_r($body);
			echo "</pre>";
			echo "request json ";
			echo $body_json;
			#exit;
		}

		$url = "https://talkapi.lgcns.com//request/kakao.json";
		$ch = curl_init(); //curl 사용 전 초기화 필수(curl handle)
		curl_setopt($ch, CURLOPT_URL, $url); 
		curl_setopt($ch, CURLOPT_POST, 1); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body_json); 
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$res = curl_exec ($ch);

		if($this->DEBUG_MODE){
			echo "<p>RESPONSE</p>";
			echo nl2br($res);
		}


	}

    /**
     * 템플릿 치환
     */

	private function get_template_source($template, $order_id){


		# 템플릿 가져오기
                $template_source = plugin_dir_path( __FILE__ )."template/{$template}.php";
                $buffer = file_get_contents($template_source);	



		$order = wc_get_order( $order_id );

		## 기본정보 
		$buffer = str_replace("{site_title}", get_bloginfo('name'), $buffer);
		$buffer = str_replace("{order_number}", $order_id, $buffer);

		## 계좌정보	
		$bacs_accounts_info = get_option( 'woocommerce_bacs_accounts');
		$buffer = str_replace("{bank}", $bacs_accounts_info[0]["bank_name"], $buffer);
		$buffer = str_replace("{bank_account}", $bacs_accounts_info[0]["account_number"], $buffer);
		$buffer = str_replace("{bank_owner}", $bacs_accounts_info[0]["account_name"], $buffer);

		## 주문정보
		$order_data = $order->get_data(); // The Order data
		$buffer = str_replace("{billing_firstname}", $order_data['billing']['first_name'], $buffer);
		$buffer = str_replace("{payment_method}", $order_data['payment_method_title'], $buffer);

		## 배송정보
		$shipping_data = $order->get_data();
		$shipping_method = $order->get_shipping_method();

		## 상품정보
		$product = "";
		$price=0;
		$order_item = $order->get_items();
		$order_count = sizeof($order_item);
		
		foreach ($order->get_items() as $item_key => $item ):		
			if($order_count > 1) $product .= "\r\n";
			$product .= $item->get_name();
			$product .= " x ";
			$product .= $item->get_quantity(); 
		endforeach;
		
		
		$total = strip_tags(wc_price($order_data["total"]));
		if($order_data['shipping_total'] > 0){
			$shipping_total = strip_tags(wc_price($order_data['shipping_total'])) . " - " . $order->get_shipping_method();
		}else{
			$shipping_total = $order->get_shipping_method();	
		}
		

		$buffer = str_replace("{product}", $product, $buffer);
		$buffer = str_replace("{shipping_total}", $shipping_total, $buffer);
		$buffer = str_replace("{total}", $total, $buffer);
		
		## 메타데이터 
		$order_meta = get_post_meta($order_id);
		if( isset( $order_meta["ru_tracking_company"][0] ) ){
			$tracking_company = ru_tracking_woocommerce::get_tracking_company();
			$buffer = str_replace("{tracking_company}", $tracking_company[$order_meta["ru_tracking_company"][0]], $buffer);
		}

		if( isset( $order_meta["ru_tracking_code"][0] ) ){
	                $buffer = str_replace("{tracking_code}", $order_meta["ru_tracking_code"][0], $buffer);
		}

		return $buffer;
	}

	## 모바일 검증
        private function mobile_validation($mobile){

                $mobile = trim($mobile);
                $mobile = preg_replace("/[^0-9]*/s", "", $mobile);
                $mobile = sprintf("%s-%s-%s", substr($mobile,0,3), substr($mobile,3,4), substr($mobile,7,4));

                if(preg_match("/^[0-9]{3}-[0-9]{4}-[0-9]{4}$/", $mobile)) {
                        return $mobile;
                }else{
                        return false;
                }
        }


	/* 
	 * 여기서부터는 주문상태별 메시지에 대한 코드 
	 */	

	## 주문이 접수되는 상태 
	public function mysite_pending($order_id) {}

	## 주문실패 
	public function mysite_failed($order_id) {}

        ## 환불
        public function mysite_refunded($order_id) {}

	## 결제확인중 
	public function mysite_hold($order_id) {
		$this->load_option();
		
	
		# 관리자 메시지 발송 
		if( $this->options["ru_kakao_message_global"]["is_admin_send"] && $this->options["ru_kakao_message_global"]["adminMobile"]  && !is_admin() ) {
			
			$mobile = $this->options["ru_kakao_message_global"]["adminMobile"];

			$template_code = $this->options["ru_kakao_message_lgcns_template_mapping"][$this->ADMIN_TEMPLATE];					
			$template_content = $this->get_template_source($this->ADMIN_TEMPLATE, $order_id);
			$template_content = str_replace("{order_type}", "새 주문", $template_content);
			$template_content = str_replace("{payment_state}", " - 결제대기", $template_content);
			$this->send_message($template_code, $template_content, $mobile);
		}

		# 사용자 메시지 발송
		if( $this->options["ru_kakao_message_global"]["is_user_hold_send"] ) {

			$order = wc_get_order($order_id);
			$mobile = $this->mobile_validation($order->get_billing_phone());
			if( $mobile === false )	return false;
				

                        $template_code = $this->options["ru_kakao_message_lgcns_template_mapping"][$this->USER_TEMPLATE_HOLD];
                        $template_content = $this->get_template_source($this->USER_TEMPLATE_HOLD, $order_id);
                        $this->send_message($template_code, $template_content, $mobile);
	
		}

	}
	
	# 처리중 
	public function mysite_processing($order_id) {
		
		$this->load_option(); 

                # 관리자 메시지 발송
                if( $this->options["ru_kakao_message_global"]["is_admin_send"] && $this->options["ru_kakao_message_global"]["adminMobile"] && !is_admin() ) {

                       	$mobile = $this->options["ru_kakao_message_global"]["adminMobile"];
                       	$template_code = $this->options["ru_kakao_message_lgcns_template_mapping"][$this->ADMIN_TEMPLATE];
                       	$template_content = $this->get_template_source($this->ADMIN_TEMPLATE, $order_id);
                       	$template_content = str_replace("{order_type}", "새 주문", $template_content);
			$template_content = str_replace("{payment_state}", " - 결제완료", $template_content);
                       	$this->send_message($template_code, $template_content, $mobile);

                }

		
		# 사용자 메시지 발송
                if( $this->options["ru_kakao_message_global"]["is_user_processing_send"] ) {

                        $order = wc_get_order($order_id);
                        $mobile = $this->mobile_validation($order->get_billing_phone());
                        if( $mobile === false ) return false;

                        $template_code = $this->options["ru_kakao_message_lgcns_template_mapping"][$this->USER_TEMPLATE_PROCESSING];
                        $template_content = $this->get_template_source($this->USER_TEMPLATE_PROCESSING, $order_id);
                        $this->send_message($template_code, $template_content, $mobile);

                }

	}

	# 완료 
	public function mysite_completed($order_id) {
		$this->load_option();

                # 사용자 메시지 발송
                if( $this->options["ru_kakao_message_global"]["is_user_completed_send"] ) {

                        $order = wc_get_order($order_id);
                        $mobile = $this->mobile_validation($order->get_billing_phone());
                        if( $mobile === false ) return false;
			$buttons = array("name"=>"배송조회");
                        $template_code = $this->options["ru_kakao_message_lgcns_template_mapping"][$this->USER_TEMPLATE_COMPLETED];
                        $template_content = $this->get_template_source($this->USER_TEMPLATE_COMPLETED, $order_id);
                        $this->send_message($template_code, $template_content, $mobile, $buttons);

                }
	}
	

	## 취소 
	public function mysite_cancelled($order_id) {

                $this->load_option();
        
                # 관리자 메시지 발송 
                if( $this->options["ru_kakao_message_global"]["is_admin_send"] && $this->options["ru_kakao_message_global"]["adminMobile"] && !is_admin()) {
                             
                        $mobile = $this->options["ru_kakao_message_global"]["adminMobile"];
                        $template_code = $this->options["ru_kakao_message_lgcns_template_mapping"][$this->ADMIN_TEMPLATE];
                        $template_content = $this->get_template_source($this->ADMIN_TEMPLATE, $order_id);
                        $template_content = str_replace("{order_type}", "주문취소", $template_content);
			$template_content = str_replace("{payment_state}", "", $template_content);

                        $this->send_message($template_code, $template_content, $mobile);
                }

	}
		
}

$ru_kakao_push = new ru_kakao_push();
