<?php

/* 구매갯수 기반의 할인
 * 한국에서 흔히 1 + 1 으로 불림 
 * author : Runup. Kim Tae Oh 
 * ref : https://jeroensormani.com/adding-custom-woocommerce-product-fields/
 */ 

if (!defined('ABSPATH'))
    exit;

class ru_sale_for_product_count{
        protected static $_instance = null;

        public static function instance() {
                if ( is_null( self::$_instance ) ) {
                        self::$_instance = new self();
                }
                return self::$_instance;
        }

        public function __construct(){
		add_filter( 'woocommerce_product_data_tabs', array($this, 'add_my_custom_product_data_tab'), 102, 1 );
		add_action( 'woocommerce_product_data_panels', array($this, 'add_my_custom_product_data_fields'));
		add_action( 'woocommerce_process_product_meta', array($this, 'woocommerce_process_product_meta_fields_save'));
		add_action( 'admin_print_scripts', array($this, 'bind_javascript'), 500);
		add_action( 'woocommerce_before_shop_loop_item_title', array($this, 'set_discount_label'));
		add_action( 'wp_head', array($this, 'set_discount_label_css'));
	}
	
	
	public function woocommerce_process_product_meta_fields_save($post_id){



		$ru_product_sale_active = (isset($_POST["ru_product_sale_active"]))?"yes":"no";
		$ru_product_sale_condition = (isset($_POST["ru_product_sale_condition"]))?(int) sanitize_text_field($_POST["ru_product_sale_condition"]):"";
		$ru_product_sale_count = (isset($_POST["ru_product_sale_count"]))?(int) sanitize_text_field($_POST["ru_product_sale_count"]):"";
		$ru_product_sale_label = (isset($_POST["ru_product_sale_label"]))?sanitize_text_field($_POST["ru_product_sale_label"]):"";
		$ru_product_sale_count_from = '';
		$ru_product_sale_count_to = '';

		
		if ( isset( $ru_product_sale_count_from ) ) {
                        $ru_product_sale_count_from = wc_clean( wp_unslash( $_POST['ru_product_sale_count_from'] ) );

                        if ( ! empty( $ru_product_sale_count_from ) ) {
                                $ru_product_sale_count_from = strtotime( date( 'Y-m-d 00:00:00', strtotime( $ru_product_sale_count_from ) ));
                        }
                }

		// Force date to to the end of the day.
                if ( isset( $ru_product_sale_count_to ) ) {
                        $ru_product_sale_count_to = wc_clean( wp_unslash( $_POST['ru_product_sale_count_to'] ) );

                        if ( ! empty( $ru_product_sale_count_to ) ) {
                                $ru_product_sale_count_to = strtotime( date( 'Y-m-d 23:59:59', strtotime( $ru_product_sale_count_to ) ));
                        }
                }

		$meta_field = array(
			'ru_product_sale_active' => $ru_product_sale_active, 
			'ru_product_sale_condition' => $ru_product_sale_condition, 
                        'ru_product_sale_count' => $ru_product_sale_count,
			'ru_product_sale_label' => $ru_product_sale_label,
			'ru_product_sale_count_from' => $ru_product_sale_count_from,
			'ru_product_sale_count_to' => $ru_product_sale_count_to
			
		);


		foreach($meta_field as $key => $v){
			if( empty($v) ){
				delete_post_meta($post_id, $key);	
			}else{
				update_post_meta($post_id, $key, $v);
			}
		}

	}

	public function add_my_custom_product_data_tab($product_data_tabs){
		$product_data_tabs['ru-sale-for-product-count'] = array(
			'label' => '추가증정설정',
			'target' => 'ru_sale_for_product_count_options',
			'priority' => 100
		);

		return $product_data_tabs;
	}

	public function bind_javascript(){
		echo '<script>
		jQuery( function( $ ) {
		$(window).load(function(){
			$("#clear-ru-product-sale-clear").on("click", function(){
				$("#ru_sale_for_product_count_options .sale_price_dates_from").val("");
				$("#ru_sale_for_product_count_options .sale_price_dates_to").val("");
			});



	                $( "#ru_sale_for_product_count_options .datepicker" ).datepicker({
                        	defaultDate: "",
 	                       dateFormat: "yy-mm-dd",
        	                numberOfMonths: 1,
                	        showButtonPanel: true,
                	});
            

			
		});

		});
		</script>';

	}

	public function add_my_custom_product_data_fields(){


	global $post;
	?>
	<!-- id below must match target registered in above add_my_custom_product_data_tab function -->
	<div id="ru_sale_for_product_count_options" class="panel woocommerce_options_panel">
		<?php
		woocommerce_wp_checkbox( array( 
			'id'            => 'ru_product_sale_active', 
			'wrapper_class' => '', 
			'label'         => '추가증정 활성화',
			'description'   => '1+1 개념의 할인을 사용하려면 이 옵션을 활성화 하세요',
			'default'  	=> '0',
			'desc_tip'    	=> false,
		) );

                woocommerce_wp_text_input( array(
                        'id'            => 'ru_product_sale_condition',
                        'wrapper_class' => '',             
                        'label'         => '활성화조건 갯수',
                        'description'   => '설정한 갯수이상 구매시 할인이 활성화 됩니다',
                        'default'       => '0',
			'data_type'	=> 'decimal',
                        'desc_tip'      => true,
                ) );

                woocommerce_wp_text_input( array(
                        'id'            => 'ru_product_sale_count',
                        'wrapper_class' => '',
                        'label'         => '할인 갯수',
                        'description'   => '활성화조건 갯수가 충족됬을시 할인에 반영될 제품의 갯수입니다',
                        'default'       => '0',
                        'data_type'     => 'decimal',
                        'desc_tip'      => true,
                ) );

                woocommerce_wp_textarea_input( array(
                        'id'            => 'ru_product_sale_label',
                        'wrapper_class' => '',
                        'label'         => '라벨텍스트',
                        'description'   => '제품루프 라벨에 붙일 문구입니다',
                        'default'       => '',
                        'data_type'     => '',
                        'desc_tip'      => true,
                ) );


		$sale_price_dates_from_timestamp =  get_post_meta($post->ID, "ru_product_sale_count_from", true) ? get_post_meta($post->ID, "ru_product_sale_count_from", true) : false;
		$sale_price_dates_to_timestamp =  get_post_meta($post->ID, "ru_product_sale_count_to", true) ? get_post_meta($post->ID, "ru_product_sale_count_to", true) : false;

		$sale_price_dates_from = ($sale_price_dates_from_timestamp) ? date_i18n('Y-m-d', $sale_price_dates_from_timestamp) : '';
		$sale_price_dates_to = ($sale_price_dates_to_timestamp) ? date_i18n('Y-m-d', $sale_price_dates_to_timestamp) : '';

                                echo '
		<p class="description">할인 스케줄을 활성화 하려면 아래를 설정하세요 무제한으로 설정하려면 비워두시면 됩니다 <a href="#" id="clear-ru-product-sale-clear">비우기</a></p>
					<p class="form-field">
                                                <label>' . esc_html__( 'Sale start date', 'woocommerce' ) . '</label>
                                                <input type="text" class="datepicker sale_price_dates_from" name="ru_product_sale_count_from" value="' . esc_attr( $sale_price_dates_from ) . '" placeholder="' . esc_attr_x( 'From&hellip;', 'placeholder', 'woocommerce' ) . ' YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
                                        </p>


                                     <p class="form-field">
                                                <label>' . esc_html__( 'Sale end date', 'woocommerce' ) . '</label>
                                                <input type="text" class="datepicker sale_price_dates_to" name="ru_product_sale_count_to" value="' . esc_attr( $sale_price_dates_to ) . '" placeholder="' . esc_attr_x( 'To&hellip;', 'placeholder', 'woocommerce' ) . '  YYYY-MM-DD" maxlength="10" pattern="' . esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ) . '" />
                                        </p>
                                ';

                
		?>
	</div>

	<?php

	}

	# CSS 
	public function set_discount_label_css(){
		echo '<style type="text/css">.ru-sale{z-index:9999; opacity:.8; position:absolute; bottom:0; left:0; background-color:#000; color:#fff; padding:0px 15px; font-size:14px; font-weight:700;font-style:italic}</style>';
	}

	# N + N 라벨생성 
	public function set_discount_label(){
		global $product;

		$sale_active = $product->get_meta('ru_product_sale_active');
		$ru_product_sale_count_from = $product->get_meta('ru_product_sale_count_from'); 
		$ru_product_sale_count_to = $product->get_meta('ru_product_sale_count_to');
		$ru_product_sale_condition = $product->get_meta('ru_product_sale_condition');
		$ru_product_sale_count = $product->get_meta('ru_product_sale_count');
		$ru_product_sale_label = $product->get_meta('ru_product_sale_label');

		$_this_time = current_time('timestamp');


		if(
			$sale_active == 'yes' && 
			(empty($ru_product_sale_count_from) && empty($ru_product_sale_count_to)) || 
			($ru_product_sale_count_from < $_this_time && $ru_product_sale_count_to > $_this_time) 
		){
			echo '<span class="ru-sale">';
			echo $ru_product_sale_condition.' + '.$ru_product_sale_count;
			if( !empty($ru_product_sale_label) ) echo ' ' . $ru_product_sale_label;
			echo ' </span>';
		}
	}

	// 할인적용  
	public function set_discount($cart){
		$product_exclude = array();
		$product_meta = array();
		$product_qty = array();
		$_thistime = time();


		$cart_contents = apply_filters( 'woocommerce_get_cart_contents', (array) $cart->cart_contents );


		// 할인을 적용할 제품이 있는지부터 계산을 위해 필요한 정보를 배열화 한다 
		foreach( $cart_contents as $key => $v){
			$id = $v["product_id"];
			$qty = $v["quantity"];
			$product = $v['data'];
                        
			## 제외 
                        if ( in_array($id, $product_exclude) ){
                                continue;
                        }

			## 이미 조건을 통과한 제품 
			if ( isset ( $product_qty[$id] ) ){
				$product_qty[$id]["total"] += $qty;
				continue;
			}

			## 메타질의 
			$meta = get_post_meta( $id );

			$ru_product_sale_active = (isset($meta["ru_product_sale_active"]))?$meta["ru_product_sale_active"][0]:"no";
                        $ru_product_sale_count_from = (isset($meta["ru_product_sale_count_from"]))?$meta["ru_product_sale_count_from"][0]:null;
                        $ru_product_sale_count_to = (isset($meta["ru_product_sale_count_to"]))?$meta["ru_product_sale_count_to"][0]:null;
                        $ru_product_sale_condition = (isset($meta["ru_product_sale_condition"]))?$meta["ru_product_sale_condition"][0]:0;
                        $ru_product_sale_count = (isset($meta["ru_product_sale_count"]))?$meta["ru_product_sale_count"][0]:0;


			## 제외조건  
			if( 
				$ru_product_sale_active == 'no' || 
				empty($ru_product_sale_condition) ||
				empty($ru_product_sale_count) || 				
				( !empty($ru_product_sale_count_from) && $ru_product_sale_count_from > $_thistime ) ||
        	                ( !empty($ru_product_sale_count_to) && $ru_product_sale_count_to < $_thistime ) 
			){
				$product_exclude[] = $id;
				continue;
			}

			$product_qty[$id]["total"] = $qty;
			$product_qty[$id]["meta"] = array(
                               	"ru_product_sale_count_from" => $ru_product_sale_count_from,
                                "ru_product_sale_count_to" => $ru_product_sale_count_to,
                                "ru_product_sale_condition" => $ru_product_sale_condition,
                                "ru_product_sale_count" => $ru_product_sale_count,
				"subtotal" => $product->get_price()
                        );
				
		}

		// 할인액 계산 
		$discount = 0;
		if( count($product_qty) > 0 ){
			foreach($product_qty as $v){
				if($v["total"] > $v["meta"]["ru_product_sale_condition"]){
					$discount_num = (int) ( $v["total"] / ($v["meta"]["ru_product_sale_condition"] + 1));			
					$discount += $discount_num * $v["meta"]["subtotal"];
				}
			}
		} 

		## 할인액이 있다면 적용 
		if( $discount > 0 ){
	  		$cart->add_fee( '추가증정 할인' , -$discount );
		}

	}
}

?>
