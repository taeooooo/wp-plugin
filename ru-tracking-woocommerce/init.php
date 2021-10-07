<?php
/*
Plugin Name: Ru Tracking For Woocommerce
Plugin URI:  https://run-up.co.kr
Description: Korea Order Tracking
Version:     1.0.0
Author:      RUNUP
Author URI:  http://www.run-up.co.kr
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

class ru_tracking_woocommerce {

	public $this_options = "";

	public function __construct(){
		add_filter( 'manage_edit-shop_order_columns', array($this, 'ru_tracking_column') );
		add_action( 'manage_shop_order_posts_custom_column' , array($this, 'ru_tracking_column_data') );
		add_action( 'admin_print_scripts', array($this, 'ru_tracking_update'), 500 );
                add_action( 'wp_ajax_tracking_update', array($this, 'ajax_tracking_update') );
		add_action( 'woocommerce_view_order', array($this, 'view_tracking'), 1 );
		
	    	## 관리자 메뉴 추가
	    	add_action( 'admin_menu', array($this, 'add_plugin_page'));
    		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

  ## KEY 반환 
  public function get_tracking_company() {
	include "ru_tracking_config.php";
	return $ru_tracking_company;
  }

   public function add_plugin_page() {
                add_options_page(
                        'Ru Woocommerce Tracking Settiing',
                        'Ru Woo Tracking',
                        'manage_options',
                        'ru-tracking-woocommerce',
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
            <h1>배송추적 세팅</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'ru-tracking-woocommerce-setting-group' );
                do_settings_sections( 'ru-tracking-woocommerce-setting' );
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
		
		## 주문페이지 
		if( isset($_GET["post_type"]) && $_GET["post_type"] == "shop_order" ){
			$this->load_option();
		}

		## 관리자 페이지 세팅
		if( (isset($_GET["page"]) && ($_GET["page"] == 'ru-tracking-woocommerce') || $_SERVER["PHP_SELF"] == '/wp-admin/options.php')) {

                ## 옵션에 대한 구조를 생성
                $this->init_config_structure();

                ## 옵션구조에 맞게 양식 생성
                foreach($this->CONFIG_STRUCTURE as $option_key => $option_val){

                        ## 옵션등록
                        register_setting(
                                'ru-tracking-woocommerce-setting-group',  // Option group
                                $option_key,                      // Option name
                                array( $this, 'sanitize' )        // Sanitize
                        );

                        ## 섹션생성
                        add_settings_section(
                                $option_key,                            // ID
                                $option_val['title'],                   // Title
                                array($this, $option_val['callback']),  // Callback
                                'ru-tracking-woocommerce-setting'              // Page
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
                                    'ru-tracking-woocommerce-setting',
                                    $option_key,
                                    ['id'=>$field_key, 'option'=>$option_key, 'key'=>$field_key, 'desc'=>$field_val['desc'], 'type'=>$field_val['type'], 'input_option'=>$input_option]
                                );
                        }
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
	include "ru_tracking_config.php";
        $new_input = array();
	foreach($input as $key => $val){
            switch($key){
                case "ru_tracking_woocommerce_default" :
			foreach($ru_tracking_company as $config_key => $config_val){
				if($config_key == $input[$key] ){
		                        $new_input[$key] = sanitize_text_field( $input[$key] );
        		                break;
				}
			}
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

        }else if($args["type"] == "radio"){
		foreach($args["input_option"] as $key => $val){
			$chk="";
                        if($key == $this->options[$args["option"]][$args["key"]] ) $chk = " checked";
			printf(
	                   '<label><input type="radio" id="%s" name="%s" value="%s"%s><span>%s</span>%s</label>',
        	           $args["id"] . "_" . $key,
                	   $args["option"]."[".$args["key"]."]",
	                   $key,
			   $chk,
			   $val,
        	           isset( $args["desc"] ) ? "<p>".$args["desc"]."</p>" : ''
                	);
		}
	}
    }

	private function init_config_structure(){
		include "ru_tracking_config.php";

                # 옵션의 기본구조 정의
                $this->CONFIG_STRUCTURE = array(
                        "ru_tracking_woocommerce_global" => array(
                                "title"=>"기본설정",
                                "callback"=>"section_print_global",
				"field"=>array("ru_tracking_woocommerce_default"=>array("type"=>"radio", "default"=>"", "title"=>"기본택배사 선택", "desc"=>""))
			)
                );
		
		foreach($ru_tracking_company as $key => $val){
			$this->CONFIG_STRUCTURE["ru_tracking_woocommerce_global"]["field"]["ru_tracking_woocommerce_default"]["input_option"][$key] = $val;
		}
		
        }

	## 주문서에 배송정보 출력   	
	public function view_tracking($order_id){
		global $ru_tracking_company;
		$order = wc_get_order( $order_id );

		$tracking_company = get_post_meta( $order_id, 'ru_tracking_company', true );
		$tracking_code = get_post_meta( $order_id, 'ru_tracking_code', true );
		
		if( $order->get_status() == 'completed') {

			echo '<h2>배송조회</h2>';
			if( $tracking_company && $tracking_code ){
				echo '<ul>';
				echo '<li>택배사 : '.$ru_tracking_company->$tracking_company["str"].'</li>';
				echo '<li>운송장 번호 : '.$tracking_code.'</li>';
				echo '</ul>';
			}else{
				echo '<p>이 주문건의 배송정보가 조회되지 않습니다 판매자에게 직접 문의 바랍니다</p>';
			}

		}
	}

	// 컬럼 추가 
	public function ru_tracking_column( $order_columns ) {
        	$order_columns['order_tracking'] = "운송장";
        	return $order_columns;
	 }

	// 컬럼 데이터 
	public function ru_tracking_column_data( $colname ) {
        	global $the_order; // the global order object
		global $ru_tracking_company;

	        if( $colname == 'order_tracking' ) {
			$default_company = $this->options["ru_tracking_woocommerce_global"]["ru_tracking_woocommerce_default"];

        	        echo '<form>';
			echo '<a href="javascript:void(0)" style="display:block"><input type = "text" class="tracking-code-input" id="tracking-code-'.$the_order->get_order_number().'" placeholder="송장번호" value="'.get_post_meta( $the_order->get_order_number(), 'ru_tracking_code', true ).'" style="width:100%" /></a>';
                	echo '<a href="javascript:void(0)" style="display:block;margin-top:5px">';
	                echo '<select id = "tracking-company-'.$the_order->get_order_number().'" style="float:left; width:50%; margin:0">';
			echo '<option value="">택배사</option>';
			$ru_tracking_company_meta = get_post_meta( $the_order->get_order_number(), 'ru_tracking_company', true );
			foreach($ru_tracking_company as $key => $val){
				$chk = '';
				if($ru_tracking_company_meta){
					if($key == $ru_tracking_company_meta ) $chk = ' selected';
				}else{
					if($default_company  == $key) $chk = ' selected';
				}

				echo '<option value="'.$key.'"'.$chk.'>'.$val.'</option>';
			}

			echo '</select>';
                	echo '<span style="float:left; width:48%; margin:0; margin-left:2%" class = "button ru-tracking-update" href = "#" data-id="'.$the_order->get_order_number().'">업데이트</span>';
        	        echo '</form>';
        	}

  	}

	// AJAX 이벤트 바인딩 
	public function ru_tracking_update(){
        	echo '<script>';

	        echo '
        	(function($){

		$(window).load(function(){


		$(".tracking-code-input").bind("blur", function(){
			if($(this).val()){
				$(this).closest("td").find(".ru-tracking-update").click();
			}
		});

		$(".ru-tracking-update").bind("click", function(e){


			var __this = $(this);

			__this.html("<i class=\'fa fa-circle-o-notch fa-spin fa-fw\'></i> 처리중");
                	var order_id = $(this).data("id");
	                var tracking_company = $("#tracking-company-" + order_id).val();
        	        var tracking_code = $("#tracking-code-" + order_id).val();

                	$.post(ajaxurl, data = {
                        	"action":"tracking_update",
	                        "order_id":order_id,
        	                "tracking_company":tracking_company,
                	        "tracking_code":tracking_code
	                }, function(response) {
        	                if(response == "SUCCESS"){
					__this.html("<i class=\'fa fa-check\'></i> 업데이트");
				}
	                });
        	});
		
		});

        	})(jQuery);
	        ';

        	echo '</script>';
	}
	
	// 메타정보 업데이트 AJAX
	public function ajax_tracking_update(){
        	if ( ! add_post_meta( $_POST["order_id"], 'ru_tracking_company', $_POST["tracking_company"], true ) ) {
                	update_post_meta ( $_POST["order_id"], 'ru_tracking_company', $_POST["tracking_company"] );
	        }

        	if ( ! add_post_meta( $_POST["order_id"], 'ru_tracking_code', $_POST["tracking_code"], true ) ) {
                	update_post_meta ( $_POST["order_id"], 'ru_tracking_code', $_POST["tracking_code"] );
	        }

        	echo "SUCCESS";
	        wp_die();
	}

}


include(plugin_dir_path(__FILE__) . 'ru_tracking_config.php');
$ru_tracking_woocommerce = new ru_tracking_woocommerce;


