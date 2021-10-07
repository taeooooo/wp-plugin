<?php
/*
Plugin Name: Ru Load More
Plugin URI:  https://run-up.co.kr
Description: 개발자를 위한 템플릿 기반의 Load More (관리자 모드 없음)
Version:     1.0.0
Author:      RUNUP
Author URI:  http://www.run-up.co.kr
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

class ru_load_more{
	
	private $TEMPLATE_DIR = WP_CONTENT_DIR . "/" . "ru-load-more-template";

	public function __construct(){
		add_action('init', array($this, 'register_ru_load_more_shortcodes')); //shortcodes


		add_action( 'wp_ajax_ru_load_more', array( $this, 'ajax_add_template') );
	    	add_action( 'wp_ajax_nopriv_ru_load_more', array( $this, 'ajax_add_template') );
	}

	// 템플릿 폴더 생성
	public function install(){
		if( !is_dir( $this->TEMPLATE_DIR ) ){
			mkdir ( $this->TEMPLATE_DIR, 0755 );
		}
	}

        public function register_ru_load_more_shortcodes(){
                add_shortcode('ru_load_more', array($this,'shortcode_output'));
        }

        public function shortcode_output($args){
                return $this->get_shortcode($args);
        }

	public function get_shortcode($args){

		## 템플릿
		$template = $args["template"];

                ## 버튼이름
                $button_name = "MORE";
                if( isset($args["button_name"]) ) $button_name = $args["button_name"];

                ## 출력할 게시물의 수
                $list_per_page = "6";
                if( isset($args["list_per_page"]) ) $list_per_page = $args["list_per_page"];

                ## 출력할 게시물의 수
                $button_class = "type-1";
                if( isset($args["button_class"]) ) $button_class = $args["button_class"];
	
		if ( file_exists( $this->TEMPLATE_DIR . "/" . $template . ".php" )) {
			ob_start();
			include $this->TEMPLATE_DIR . "/" . $template . ".php";
			$buffer = ob_get_contents();
			ob_end_clean();
		}


		$buffer .= '<style type="text/css">
.ru-load-more-button{letter-spacing:1px; font-size:14px;}
.ru-load-more-button.type-1{padding:7px 50px; border-radius:30px;}
.ru-load-more-button.disabled{background-color:#eee !important; color:#fefefe !important;}
</style>';	
		$buffer .= '<div class="ru-load-more" style="text-align:center"><button class="button alt ru-load-more-button '.$button_class.'" id="ru-load-more-'.$template.'" data-template="'.$template.'" data-page="1" data-max-page="'.$loop_max_num_pages.'" data-button-name="'.$button_name.'" data-list-per-page="'.$list_per_page.'">'.$button_name.' (1/'.$loop_max_num_pages.')</button></div>';

		## 페이지 하단에 자바스크립트 생성
		add_action('wp_footer', array($this, 'ru_load_more_scripts'));

		return $buffer;
	}

	public function ajax_add_template(){


		$page = $_POST["page"];
		$template = $_POST["template"];
		$list_per_page = $_POST["list_per_page"];

                if ( file_exists( $this->TEMPLATE_DIR . "/" . $template . ".php" )) {
                        include $this->TEMPLATE_DIR . "/" . $template . ".php";
                }

		wp_die();
	}

	public function ru_load_more_scripts(){
		?>
		<script>
		(function($){
			$(".ru-load-more button").on('click', function(e){
				ru_ajax_load_more(e, $(this));
			});

			function ru_ajax_load_more(e, _current_button){

				e.preventDefault();	
				var _previous_html = _current_button.html();
				var _current_page = _current_button.data('page');
				var _list_per_page = _current_button.data('list-per-page');
				var _max_page = _current_button.data('max-page');
				var _template = _current_button.data('template');
				var _next_page = _current_page + 1;
				var _button_name = _current_button.data('button-name');
				var _process_html = '<i class="fas fa-spinner fa-pulse"></i>';


				var ru_load_more_data = {
					'action': 'ru_load_more',
					'page': _next_page,
					'list_per_page': _list_per_page,
					'is_ajax': 1,
					'template': _template
				}

				$.ajax({
					url : '<?php echo admin_url( 'admin-ajax.php' ); ?>', 
					data : ru_load_more_data, 
					type : 'POST',
					beforeSend : function(){
						_current_button.html(_process_html);
						_current_button.off('click');
					},
					success : function(response) {
						$("#" + _template).append(response);	
						_current_button.data('page', _next_page);
						_current_button.html(_button_name + '(' + _next_page + '/' + _max_page + ')');
						if(_next_page < _max_page){
							_current_button.on('click', function(e){
			                                	ru_ajax_load_more(e, $(this));
                        				});
						}else{	
							_current_button.addClass('disabled');
						}
					
					}
				});
			}

		})(jQuery);
		</script>
		<?php
	}

}


$ru_load_more = new ru_load_more;
