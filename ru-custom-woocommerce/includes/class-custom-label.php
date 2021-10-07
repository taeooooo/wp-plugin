<?php

/* 구매갯수 기반의 할인
 * 한국에서 흔히 1 + 1 으로 불림 
 * author : Runup. Kim Tae Oh 
 * ref : https://jeroensormani.com/adding-custom-woocommerce-product-fields/
 */ 

if (!defined('ABSPATH'))
    exit;

class ru_custom_label{
        protected static $_instance = null;

        public static function instance() {
                if ( is_null( self::$_instance ) ) {
                        self::$_instance = new self();
                }
                return self::$_instance;
        }

        public function __construct(){
			
		#add_filter( 'woocommerce_product_data_tabs', array($this, 'add_my_custom_product_data_tab'), 102, 1 );
		#add_action( 'woocommerce_product_data_panels', array($this, 'add_my_custom_product_data_fields'));
		#add_action( 'woocommerce_process_product_meta', array($this, 'woocommerce_process_product_meta_fields_save'));

		#add_action( 'admin_print_scripts', array($this, 'bind_javascript'), 500);
		#add_action( 'woocommerce_before_shop_loop_item_title', array($this, 'set_discount_label'));
		#add_action( 'wp_head', array($this, 'set_discount_label_css'));

		add_action( 'wp_head', array($this, 'set_custom_label_css'));
		add_action( 'woocommerce_before_shop_loop_item', array($this, 'set_custom_label'));
		add_action( 'woocommerce_product_options_advanced', array($this, 'my_woo_custom_price_field') );
		add_action( 'woocommerce_process_product_meta', array($this, 'woocommerce_process_product_meta_fields_save'));
		
		}

		
		public function set_custom_label_css(){

			$options = get_option("ru_custom_woocommerce_global");

			echo "<style type=\"text/css\">";
			echo ".ru-custom-label-container{overflow:hidden; left:0; bottom:0}";
			echo ".ru-custom-label{float:left; background-color:".$options["custom_label_color"]."; margin-left:10px; margin-bottom:5px; color:#fff; padding:0 5px; font-size:11px}";
			echo ".ru-custom-label .label-text{opacity:1}";
			echo "</style>";
		}

		public function set_custom_label(){
			global $product;
			if($product->is_on_sale()){
			                                        // if not variable product.
                                        if ( ! $product->is_type( 'variable' ) ) {
                                                $sale_price = $product->get_sale_price();

                                                if ( $sale_price ) {
                                                        $regular_price      = $product->get_regular_price();
                                                        $percent_sale       = round( ( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 ), 0 );
                                                        
                                                }
                                        } else {

                                                // if variable product.
                                                foreach ( $product->get_children() as $child_id ) {
                                                        $variation = wc_get_product( $child_id );
                                                        if ( $variation instanceof WC_Product ) {
                                                                // Checking in case if the wc_get_product exists or is not false.
                                                                $sale_price = $variation->get_sale_price();
                                                                if ( $sale_price ) {
                                                                        $regular_price                     = $variation->get_regular_price();
                                                                        $percent_sale                      = round( ( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 ), 0 );
                                                                        
                                                                        

                                                                }
                                                        }
                                                }
                                        }
					echo "<div class=\"ru-custom-label-container\">";
					echo '<span class="ru-custom-label">'.$percent_sale.'% SALE</span>';		
					echo "</div>";
			}else{
			$custom_label = $product->get_meta("ru_custom_label");
			if( !empty($custom_label) ){
				$custom_label_arr = explode("|", $custom_label);


				echo "<div class=\"ru-custom-label-container\">";
					foreach($custom_label_arr as $key => $v){
						if( !empty($v) ){
							echo '<span class="ru-custom-label">'.$v.'</span>';
						}
					}
				echo "</div>";
			}
			}
		}


		
		/**
		* Add a Christmas Price field
		**/
		public function my_woo_custom_price_field() {
		  
		  $field = array(
			'id' => 'ru_custom_label',
			'label' => '제품라벨',
			'rows' => '2',
			'desc_tip' => true,
			'description' => '제품의 특성을 표현할 키워드 입니다 제품 카달로그에서 키워드로 구매를 유도합니다',
			'placeholder' => '라벨을 "|" 기호로 구분해서 입력해주세요'
		  );
		  
		  woocommerce_wp_textarea_input( $field );
		}

		public function woocommerce_process_product_meta_fields_save($post_id){
		

  
			$custom_field_value = isset( $_POST['ru_custom_label'] ) ? $_POST['ru_custom_label'] : '';
  
			$product = wc_get_product( $post_id );
			$product->update_meta_data( 'ru_custom_label', $custom_field_value );
			$product->save();


		}
	
}

?>
