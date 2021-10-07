<?php
/*
Plugin Name: Ru Customize For Woocommerce
Plugin URI:  https://run-up.co.kr
Description: Korea Checkout Customizing / My Account Customizing
Version:     1.0.0
Author:      RUNUP
Author URI:  http://www.run-up.co.kr
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

class ru_customize_woocommerce{

        protected static $_instance = null;

        public static function instance() {
                if ( is_null( self::$_instance ) ) {
                        self::$_instance = new self();
                }
                return self::$_instance;
        }

	public function __construct(){

                ## 관리자 메뉴 추가
                add_action( 'admin_menu', array($this, 'add_plugin_page'));
                add_action( 'admin_init', array( $this, 'page_init' ) );

		$this->load_option();
		
		# CSS 제어 ( 세부조정은 메소드 내에서 )
		add_action( 'wp_head', array($this, 'wp_head_css'));
	
		# 우머커스 상품 하단에 고정템플릿 붙이기 
		if ( $this->options["ru_custom_woocommerce_product"]["add_product_below"] )
			add_filter( 'the_content', array($this, 'customizing_woocommerce_description') );		


		# 우커머스 프로덕트 탭제어 ( 일반적으로 추가정보 제거 )
		if ( 1 )  // 메소드 내에서 제어 
			add_filter( 'woocommerce_product_tabs', array($this, 'woo_remove_product_tabs'),  98);
		
		# 화폐단위를 "원"으로 변경	
		if ( $this->options["ru_custom_woocommerce_global"]["chage_currency_symbol"] )
			add_filter('woocommerce_currency_symbol', array($this, 'change_won_currency_symbol'), 10, 2);


		# 카트메시지 변경 
		if ( $this->options["ru_custom_woocommerce_checkout"]["change_cart_message"] ){
			add_action( 'wp_head', array($this, 'custom_related_css'));
			add_filter( 'wc_add_to_cart_message_html', array($this, 'custom_add_to_cart_message'), 10, 2 );
		}

                # 우편번호코드 삽입 ( 체크아웃 . 회원정보 . 배송지 . 청구지 )
		if ( $this->options["ru_custom_woocommerce_global"]["add_daum_postcode"] )
                	add_action( 'wp_footer', array($this, 'daum_postcode_init'));

                # 배송지 명칭 변경
		if ( $this->options["ru_custom_woocommerce_checkout"]["chage_ship_title"] ){
                	add_filter( 'gettext', array($this, 'translate_woocommerce_strings'), 999, 3 );		// 청구상세내용 번역을 다른문자로 변경
			add_action( 'wp_head', array($this, 'hide_ship_title') );				// HEADER에 인라인 CSS
			add_action( 'wp_footer', array($this, 'add_ship_title_event') );			// FOOTER에 인라인 JS
	        	add_action( 'woocommerce_before_checkout_shipping_form', array($this, 'ship_title'));   // 새로운 타이틀 출력 
		}

                # 우커머스 회원정보 lastname 필수제거
		if ( $this->options["ru_custom_woocommerce_myaccount"]["remove_last_name_required"] )
	                add_filter( 'woocommerce_save_account_details_required_fields', array($this, 'wc_remove_required_last_name') );


                # 우커머스 체크아웃 필드 정리
		if ( $this->options["ru_custom_woocommerce_checkout"]["remove_field"] )
                	add_filter( 'woocommerce_checkout_fields', array($this, 'set_fields') );
		
		# 주소필드 재정의
		if ( $this->options["ru_custom_woocommerce_global"]["change_address_field"] )
	                add_filter( 'woocommerce_default_address_fields', array($this,'custom_override_default_address_fields'));
		
		# 청구 주소변경에서 필요없는 필드 제거
		if ( $this->options["ru_custom_woocommerce_myaccount"]["remove_billing_field"] )
	                add_filter( 'woocommerce_billing_fields' , array($this, 'custom_override_billing_fields') );
		
		# 배송 주소변경에서 필요없는 필드 제거
		if ( $this->options["ru_custom_woocommerce_myaccount"]["remove_shipping_field"] )
	                add_filter( 'woocommerce_shipping_fields' , array($this, 'custom_override_shipping_fields') );

                # 우커머스 메뉴 아이템 정리 ( 다운로드 제거 )
		if ( $this->options["ru_custom_woocommerce_myaccount"]["remove_download_item"] ){
                	add_filter ( 'woocommerce_account_menu_items', array($this, 'remove_my_account_links') );	
		}

		# 댓글전송 버튼 액션
		if ( $this->options["ru_custom_woocommerce_product"]["change_comment_action"] ) {
			add_action( 'wp_footer', array($this, 'woo_comment_action'));
		}

		# 상품평 삭제 기능 활성화 
		#if ( $this->options["ru_custom_woocommerce_product"]["enable_comment_remove"] ) {
		#	add_action( 'woocommerce_review_after_comment_text', array($this, 'enable_comment_remove'), 10, 1 );
		#}

		# 주문취소 비활성화 
		if ( $this->options["ru_custom_woocommerce_myaccount"]["remove_order_cancel"] ) {
			add_filter('woocommerce_my_account_my_orders_actions', array($this, 'remove_order_cancel'), 10, 1);
		}

		# 구매갯수기반의 할인
		if ( $this->options["ru_custom_woocommerce_global"]["use_product_count_sale"] ) {
			$ru_sale_for_product = ru_sale_for_product_count::instance();
			add_action( 'woocommerce_cart_calculate_fees', array($ru_sale_for_product, 'set_discount') );
		}

		# Smash Ballon Plugin 연동
		if ( $this->options["ru_custom_woocommerce_global"]["enable_custom_label"] ) {
			$ru_custom_label = ru_custom_label::instance();
			
		}

		# 에스크로 숏코드 생성
		add_shortcode('ru_ascro', array($this, 'ascro_html'));
		add_action('woocommerce_checkout_before_terms_and_conditions', array($this, 'ascro_checkout'));
		add_action('wp_footer', array($this, 'ascro_code'));
	}


	## 체크아웃에 적용
	public function ascro_checkout(){
		echo "<p>";
		$this->ascro_html();
		echo "</p>";
	}

	## 에스크로 출력 숏코드 
	public function ascro_html(){
		echo $this->options["ru_custom_woocommerce_global"]["ascro_html"];
	}
	
	## 에스크로 코드 
	public function ascro_code(){
                echo $this->options["ru_custom_woocommerce_global"]["ascro_code"];
        }

	## 주문취소 비활성화 
	public function remove_order_cancel($actions){
		unset($actions['cancel']);
		return $actions;
	}


	## 리뷰삭제 기능 활성화
	public function enable_comment_remove($comment){
		if( $comment->user_id == get_current_user_id() ){
			echo '<div class="comment-delete-wrap">';
        		echo '<a data-commentid="'.$comment->comment_ID.'" data-postid="'.$comment->comment_ID.'">삭제</a>';
			echo '</div>';
		}
	}



	## 리뷰등록 버튼 액션 추가 
	public function woo_comment_action(){
		echo '<script>
jQuery(".woocommerce #review_form").bind("submit", function(){
jQuery(this).find("#submit").replaceWith("<i class=\"fas fa-circle-notch fa-spin\"></i> 상품평을 등록중입니다");
});
</script>';
	}

        ## wp_head write css 
        public function wp_head_css(){
                $css = "";


                # 국가 
                if($this->options["ru_custom_woocommerce_global"]["remove_country"]){
                	$css .= '#billing_country_field, #shipping_country_field{display:none}';
                }

                ## 상품
                if(is_product()){

                        # 타이틀 
                        if($this->options["ru_custom_woocommerce_product"]["remove_reviews_title"]){
                                $css .= '#reviews #reply-title{display:none}';
                        }

                        # 아바타
                        if($this->options["ru_custom_woocommerce_product"]["remove_reviews_avatar"]){
                                $css .= '.woocommerce #reviews #comments ol.commentlist li .comment-text{margin:0}';
                                $css .= '.woocommerce #reviews #comments ol.commentlist li img.avatar{display:none}';
                        }

                        # 날짜
                        if($this->options["ru_custom_woocommerce_product"]["remove_reviews_publish_date"]){
                                $css .= '#reviews .comment_container .woocommerce-review__dash, #reviews .comment_container .woocommerce-review__published-date{display:none}';
                        }
                }

                ## 출력
                if($css){
                        echo '<style type="text/css">'.$css.'</style>';
                }
        }

	public function uninstall(){
		## 템플릿 삭제
		$template = "form-edit-account.php";
                $template_target = get_stylesheet_directory()."/woocommerce/myaccount";

		unlink( $template_target . "/" . $template ); 
	}

	public function install(){


		## 템플릿 복사
		$template = "form-edit-account.php";
		$template_target = get_stylesheet_directory()."/woocommerce/myaccount";
		$template_source = plugin_dir_path( __FILE__ )."template";

		if( !file_exists( $template_target . "/" . $template ) ){
			if( !is_dir($template_target) ){
				mkdir($template_target, 0755, true);
			}

			copy( $template_source . "/" . $template, $template_target . "/" . $template );
		}

		## 옵션값을 기본세팅
		$this->init_config_structure();
	        foreach($this->CONFIG_STRUCTURE as $option_key => $option_val){
			$options = array();
			foreach($option_val["field"] as $field_key => $field_val){
				$options[$field_key] = $field_val["default"];
			}

			if ( false === get_option( $option_key ) ){				
				add_option( $option_key, $options );
			}
	        }

	}

        private function init_config_structure(){

                # 옵션의 기본구조 정의
                $this->CONFIG_STRUCTURE = array(
                        "ru_custom_woocommerce_global" => array(
                                "title"=>"기본설정",
                                "callback"=>"section_print_global",
									"field"=>array(
                                        "chage_currency_symbol" => array(
												"type"=>"select", 
												"default"=>"1", 
												"title"=>"화폐변경(원)", 
												"desc"=>"한국화폐 단위를 변경합니다 (ex : 0,000원)", 
												"input_option"=>array("1"=>"예","0"=>"아니오")
										),
										"change_address_field" => array(
												"type"=>"select",
                                                "default"=>"1",
                                                "title"=>"주소필드 재정의",
                                                "desc"=>"주소의 순서 및 명칭을 재정의합니다 ( 한국형 쇼핑몰 )",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),      
                                        "add_daum_postcode" => array(
                                                "type"=>"select", 
                                                "default"=>"1", 
                                                "title"=>"다음우편번호 사용",  
                                                "desc"=>"한국 우편번호 검색을 위해 다음API를 사용합니다( 청구주소, 배송주소, 체크아웃에 적용 )",  
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
                                        "remove_country" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"국가숨기기",
                                                "desc"=>"한국형 쇼핑몰을 위해 국가필드를 숨깁니다(CSS)",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        )					,
                                        "use_product_count_sale" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"구매갯수 할인",
                                                "desc"=>"구매갯수 기반의 할인을 활성화 합니다( 한국에서 1+1 용어로 사용)",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
										"enable_custom_label" => array(
                                                "type"=>"select",
                                                "default"=>"0",
                                                "title"=>"커스텀 라벨 활성화",
                                                "desc"=>"제품에 맞춤라벨 기능을 활성화 합니다",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
										"custom_label_color" => array(
                                                "type"=>"text",
                                                "default"=>"#000",
                                                "title"=>"커스텀 라벨 색상",
												"desc"=>""
                                        ),
                                        "ascro_code" => array(
                                                "type"=>"textarea",
                                                "default"=>"",
                                                "title"=>"에스크로코드",
                                                "desc"=>"구매안전서비스는 쇼핑몰 필수고지 사항입니다(사이트하단, 구매페이지) 확인증 연결을 위한 코드가 있을경우 이곳에 입력합니다"
                                              
                                        ),
                                        "ascro_html" => array(
                                                "type"=>"textarea",
                                                "default"=>"",
                                                "title"=>"에스크로문구",
                                                "desc"=>"에스크로 연결문구를 숏코드로 반환합니다 :  [ru_ascro]"
                                                                                      
                                        )

					
                                )
                        ),"ru_custom_woocommerce_myaccount" => array(
                                "title"=>"My Account",
                                "callback"=>"section_print_global",
                                "field"=>array(
                                        "remove_download_item" => array(
						"type"=>"select", 
						"default"=>"1", 
						"title"=>"다운로드 버튼 제거", 
						"desc"=>"다운로드 항목을 제거합니다", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
                                        "remove_last_name_required" => array(
						"type"=>"select", 
						"default"=>"1", 
						"title"=>"필수입력 제거", 
						"desc"=>"회원정보에서 성에 대한 필수조건을 제거합니다( 폼은 기본테마에 템플릿을 복사해야 합니다 )", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
                                        "remove_billing_field" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"청구주소 필드 재정의",
                                                "desc"=>"불필요한 항목을 제거합니다 ( 국가, 성 )",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
                                        "remove_shipping_field" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"배송주소 필드 재정의",
                                                "desc"=>"불필요한 항목을 제거합니다 ( 국가, 성 )",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
                                        "remove_order_cancel" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"주문취소 비활성화",
                                                "desc"=>"사용자가 처리중 상태에서 주문을 취소하지 못하도록 합니다",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        )

                                )
                        ),"ru_custom_woocommerce_checkout" => array(
                                "title"=>"CheckOut",
                                "callback"=>"section_print_global",
                                "field"=>array(
                                        "chage_ship_title" => array(
						"type"=>"select", 
						"default"=>"1", 
						"title"=>"배송지 명칭 변경", 
						"desc"=>"배송지 기본명칭을 CSS로 가리고 새로운 타이틀을 생성합니다(한국형 명칭과 인터페이스 : 배송 / 주문자와 동일합니다)", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
                                        "change_cart_message" => array(
						"type"=>"select", 
						"default"=>"1", 
						"title"=>"카트 메시지에 연관상품 노출", 
						"desc"=>"카트메시지에 연관상품을 노출 하려면 \"예\"를 선택하세요", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
                                        "add_cart_message" => array(
                                                "type"=>"textarea",
                                                "default"=>"아래 상품과 함께 주문후 배송비를 절약해 보세요",
                                                "title"=>"카트 메시지 문구",
                                                "desc"=>"기본 카트메시지 문구이후 출력할 문구입니다"
                                        ),
					"remove_field" => array(
						"type"=>"select", 
						"default"=>"1", 
						"title"=>"일부 필드 제거", "desc"=>"한국형 쇼핑몰 구현을 위해 일부 요소를 제거하고 재정의 합니다 (주문자 정보, 다른배송지)", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
                                        "change_field" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"체크아웃필드 변경",
                                                "desc"=>"한국형 쇼핑몰을 위해 체크아웃 필드를 재정의 합니다 ( 성제거, 순서변경, 주문자에 연락처 추가 )",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        )
                                )
                        ),"ru_custom_woocommerce_product" => array(
                                "title"=>"Single Product",
                                "callback"=>"section_print_global",
                                "field"=>array(
                                        "remove_product_tab" => array(
						"type"=>"select", 
						"default"=>"0", 
						"title"=>"설명 탭 제거", 
						"desc"=>"", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
                                        "remove_additional_information_tab" => array(
						"type"=>"select", 
						"default"=>"1", 
						"title"=>"추가정보 탭 제거", 
						"desc"=>"", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
                                        "remove_reviews_tab" => array(
						"type"=>"select", 
						"default"=>"0", 
						"title"=>"리뷰 탭 제거", 
						"desc"=>"", 
						"input_option"=>array("1"=>"예","0"=>"아니오")
					),
					"remove_reviews_title" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"리뷰 타이틀 제거",
                                                "desc"=>"",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
					"remove_reviews_avatar" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"리뷰 아바타 제거",
                                                "desc"=>"",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
                                        "remove_reviews_publish_date" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"리뷰작성일 제거",
                                                "desc"=>"",
                                                "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
					"change_comment_action" => array(
                                                "type"=>"select",
                                                "default"=>"1",
                                                "title"=>"댓글버튼 전송문구 적용",
                                                "desc"=>"스핀아이콘 + 상품평을  등록중입니다",
                                                 "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
                                        "enable_comment_remove" => array(
                                                "type"=>"select",
                                                "default"=>"0",
                                                "title"=>"상품평 삭제기능 적용",
                                                "desc"=>"본인이 작성한 상품평을 삭제할 수 있습니다",
                                                 "input_option"=>array("1"=>"예","0"=>"아니오")
                                        ),
					"add_product_below" => array(
						"type"=>"text", 
						"default"=>"", 
						"title"=>"하단 고정템플릿", 
						"desc"=>"숏코드를 입력하세요"
					)

                                )
                        )

                );

	}

    /**
     * 관리자 메뉴에 추가
     */
        public function add_plugin_page() {
                add_options_page(
                        'Ru Custom Woocommerce Settiing',
                        'Ru Custom Woocommerce',
                        'manage_options',
                        'ru-custom-woocommerce',
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
            <h1>우커머스 커스터마이징</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'ru-custom-woocommerce-setting-group' );
                do_settings_sections( 'ru-custom-woocommerce-setting' );
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
                                'ru-custom-woocommerce-setting-group', // Option group
                                $option_key,                      // Option name
                                array( $this, 'sanitize' )        // Sanitize
                        );

                        ## 섹션생성
                        add_settings_section(
                                $option_key,                            // ID
                                $option_val['title'],                   // Title
                                array($this, $option_val['callback']),  // Callback
                                'ru-custom-woocommerce-setting'          // Page
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
                                    'ru-custom-woocommerce-setting',
                                    $option_key,
                                    ['id'=>$field_key, 'option'=>$option_key, 'key'=>$field_key, 'desc'=>$field_val['desc'], 'type'=>$field_val['type'], 'input_option'=>$input_option]
                                );
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
		case "ascro_code" :
		case "ascro_html" : 
			$new_input[$key] = $input[$key];
			break;
		case "add_cart_message":
			$new_input[$key] = sanitize_textarea_field( $input[$key] );
			break;
                case "add_product_below" :
			$new_input[$key] = sanitize_text_field( $input[$key] );
			break;
                default :
                        $new_input[$key] = ( $input[$key] == 0 || $input[$key] == 1 )?$input[$key]:"";
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
        }else if($args["type"] == "textarea"){
                printf(
                    '<textarea id="%s" name="%s" rows="5" class="regular-text">%s</textarea>%s',
                    $args["id"],
                    $args["option"]."[".$args["key"]."]",
                    isset( $this->options[$args["option"]][$args["key"]] ) ?  esc_attr($this->options[$args["option"]][$args["key"]])  : '',
                    isset( $args["desc"] ) ? "<p>".$args["desc"]."</p>" : ''
                );
        }else if($args["type"] == "select"){
                $select_option = "";
                foreach($args["input_option"] as $key => $val){
                        $chk="";
                        if(isset($this->options[$args["option"]][$args["key"]]) && $key == $this->options[$args["option"]][$args["key"]] ) $chk = " selected";
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

	
	## 상품 하단에 고정 템플릿 
	public function customizing_woocommerce_description( $content ) {
	


	    // Only for single product pages (woocommerce)
	    if ( is_product() ) {

        	// The custom content
	        $custom_content = do_shortcode($this->options["ru_custom_woocommerce_product"]["add_product_below"]);

        	// Inserting the custom content at the end
	        $content .= $custom_content;
	    }
	    return $content;
	}

	// 우커머스 프로덕트탭 제어 	
	public function woo_remove_product_tabs( $tabs ) {


		if( $this->options["ru_custom_woocommerce_product"]["remove_product_tab"] ){
		    	unset( $tabs['description'] );             // Remove the description tab
		}
		
		if( $this->options["ru_custom_woocommerce_product"]["remove_reviews_tab"] ){
	    		unset( $tabs['reviews'] );                         // Remove the reviews tab
		}

		if( $this->options["ru_custom_woocommerce_product"]["remove_additional_information_tab"] ){
	    		unset( $tabs['additional_information'] );   // Remove the additional information tab
		}

		return $tabs;

	}

	public function custom_related_css(){
		if( is_product() || is_checkout() ) {
			$style = '<style type="text/css">';
			if( is_product() ){
				$style .= '#custom-related .related.products > h2{display:none}';
				$style .= '#custom-related li{margin:0; margin-right:10px;}';
				$style .= '#custom-related li:last-child{margin-right:0}';
				$style .= '#custom-related li .woocommerce-loop-product__title { margin-top: 8px; font-size:.8em !important;}';
				$style .= '#custom-related li span.price{}';
				$style .= '#custom-related li .ru-sale { display:none }';
				$style .= '#custom-related li .onsale { display:none }';
				#$style .= '#custom-related li .price del { display:none }';
			}else if ( is_checkout() ){
				$style .= '.woocommerce-notices-wrapper{display: none !important}';
			}

			$style .= '</style>';
			echo $style;
		}
	}


	// 카트메시지 	
	public function custom_add_to_cart_message($message, $product_id_arr) {


		$product_id = array_keys($product_id_arr);

		$custom_message = '<p>'.$this->options["ru_custom_woocommerce_checkout"]["add_cart_message"].'</p>';
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');

		$posts_per_page = 5;
		$columns = 5;

		if(wp_is_mobile()){
			$posts_per_page = 3;
			$columns = 3;
		}


    		$args = array(
		      'posts_per_page' => $posts_per_page,
		      'columns'        => $columns,
		      'orderby'        => 'date', // @codingStandardsIgnoreLine.
		      'order'          => 'desc',
		);

		$defaults=array();
		$args = wp_parse_args( $args, $defaults );

		// Get visible related products then sort them at random.
		$args['related_products'] = array_filter( array_map( 'wc_get_product', wc_get_related_products( $product_id[0], $args['posts_per_page'] ) ), 'wc_products_array_filter_visible' );

		// Handle orderby.
		$args['related_products'] = wc_products_array_orderby( $args['related_products'], $args['orderby'], $args['order'] );

		// Set global loop values.
		wc_set_loop_prop( 'name', 'related' );
		wc_set_loop_prop( 'columns', apply_filters( 'woocommerce_related_products_columns', $args['columns'] ) );

		ob_start();
	    	wc_get_template( 'single-product/related.php', $args );
		$content = ob_get_contents();
		ob_end_clean();

		$message .= $custom_message . '<div id="custom-related">'.$content.'</div>';

		return $message;
	}

	// 화폐변경 	
	public function change_won_currency_symbol( $currency_symbol, $currency ) {
		switch( $currency ) {
			case 'KRW': $currency_symbol = '원'; break;
		}
		return $currency_symbol;
	}

	// MY ACCOUNT NAVIGATION REMOVE
	public function remove_my_account_links( $menu_links ){
		unset( $menu_links['downloads'] ); // Disable Downloads
		return $menu_links;
	}

	// MY ACCOUNT LAST NAME REMOVE	
	public function custom_override_billing_fields( $fields ) {
	  $fields['billing_first_name']['class'] = array('form-row-wide');
	  unset($fields['billing_last_name']);
	  #unset($fields['billing_country']);
	  return $fields;
	}

	public function custom_override_shipping_fields( $fields ) {
	  $fields['shipping_first_name']['class'] = array('form-row-wide');
	  $fields['shipping_phone1'] = array(
		"label"=>"전화번호",
		"required"=>"1",
		"type"=>"tel",
		"class"=>array("form-row-wide"),
		"validate"=>array("phone"),
		"autocomplete"=>"tel",
		"priority"=>"100"
	  );

          unset($fields['shipping_last_name']);
          #unset($fields['shipping_country']);

          return $fields;
        }

	// 간단한 번역	
	public function translate_woocommerce_strings( $translated, $untranslated, $domain ) {
 
   		if ( ! is_admin() && 'woocommerce' === $domain ) {
 
      			switch ( $translated) {
 
				case '청구 상세 내용' :
 
			        $translated = '주문자 정보';
			        break;
       
      			}
 
   		}   
  
   		return $translated;
 
	}

	public function ship_title(){

		echo '<h3>배송<label style="float:right;font-size:14px"><input type="checkbox" id="check-shipping-billing-same" /> 주문자와 동일합니다</label></h3>';
	}

	public function hide_ship_title(){
		echo '<style type="text/css">#ship-to-different-address{display:none}</style>';
	}

	public function add_ship_title_event(){
                ## 주문자 정보와 동일        
                if(is_checkout()){
                        echo '<script>
                                jQuery("#check-shipping-billing-same").click(function(){
                                        if( jQuery(this).prop("checked") == true ){ 
                                                jQuery("#shipping_first_name").val ( jQuery("#billing_first_name").val() );
                                                jQuery("#shipping_phone1").val ( jQuery("#billing_phone").val() );
                                        }else{  
                                                jQuery("#shipping_first_name").val ( "" ); 
                                                jQuery("#shipping_phone1").val ( "" ); 
                                        }       
                                });     
                        </script>
                        ';        
                } 
	}

	// last name required remove
	public function wc_remove_required_last_name( $fields ) {
	    unset( $fields['account_last_name'] );
	    return $fields;
	}


	// 우편번호 JAVASCRIPT
	public function daum_postcode_init() {
		global $wp;
		
		if(!is_checkout() && !is_wc_endpoint_url( 'edit-address' )) return false;
		
		## 우편번호
		echo '<script>';
		if(is_checkout()){
 		    echo 'var __ru_address_prefix = "shipping";';
		}else if(is_wc_endpoint_url( 'edit-address' )){
		    $load_address = isset( $wp->query_vars['edit-address'] ) ? wc_edit_address_i18n( sanitize_title( $wp->query_vars['edit-address'] ), true ) : 'billing';
		    echo 'var __ru_address_prefix = "'.$load_address.'";';
	
		}

		echo '</script>';
		echo '<script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>';
		echo '<script src="'.plugin_dir_url( RU_CUSTOM_WC_PLUGIN_FILE ) . '/assets/daum_postcode.js'.'?ver=1.004"></script>';

	}

	// 필드 재정의
	public function set_fields( $checkout_fields ) {
	        // remove billing filed
        	unset ( $checkout_fields['billing']['billing_last_name'] );
	        unset ( $checkout_fields['billing']['billing_address_1'] );
	        unset ( $checkout_fields['billing']['billing_address_2'] );
	        unset ( $checkout_fields['billing']['billing_city'] );
        	unset ( $checkout_fields['billing']['billing_postcode'] );

			
        	// change sort	
	        $checkout_fields['billing']['billing_phone']['priority'] = 20;
        	$checkout_fields['billing']['billing_email']['priority'] = 20;
		
		// remove shipping field
		unset ( $checkout_fields['shipping']['shipping_last_name'] );
		$checkout_fields['shipping']['shipping_phone1'] = $checkout_fields['billing']['billing_phone'];


	        return $checkout_fields;

	}
	
	// 주소재정의 
	function custom_override_default_address_fields ($address_fields) {

     		$address_fields['address_1']['label']='기본주소';
     		$address_fields['city']['label']='상세주소';
     		$address_fields['address_2']['placeholder'] = '참고항목';
     		$address_fields['postcode']['priority']=40;

     		return $address_fields;
	}

	
}

