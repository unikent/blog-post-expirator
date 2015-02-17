<?php
/**
 * Plugin Name: Blog Post Expirator
 * Description: Allows you to add an expiration date to posts.
 * Version: 1.0.0
 * Author: IS WebDev Team <is-webdev@kent.ac.uk>
 * License: GPL2
 */
new PostExpirator();
class PostExpirator {
	
	public function __construct() {
		
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		add_filter( 'manage_post_posts_columns', array( $this, 'manage_posts_columns' ), 5 );
		add_action( 'manage_post_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 5, 2 );	
		
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_expiring_field') );
		add_action( 'save_post', array( $this, 'save_post_meta' ), 10, 2 );	
		
		add_action('postExpiratorExpire',array( $this, 'postExpiratorExpire'));
		
	}
	
	public function enqueue_scripts( $hook ) {
		if( 'post.php' != $hook ) return;
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'post-expiring', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), null, true );
		wp_enqueue_style( 'post-expiring', plugins_url('assets/css/post-expiring.css', __FILE__) );
	}
	
	public function manage_posts_columns( $columns ){
		$columns['expiring'] = __( 'Expiring', 'postexpiring' );
		return $columns;
	}
	
	public function manage_posts_custom_column( $column_name, $id ){
		global $post;
		if( $column_name === 'expiring' ){
			$postexpired = get_post_meta( $post->ID, 'postexpired', true );
			echo !empty($postexpired) ? $postexpired : __('Never');
		}
	}
	
	public function save_post_meta( $post_id, $post ) {
		if ( $post_id === null || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) return;
		if( isset($_POST['post_expiring']) ) {
			$date_arr  = explode('-', $_POST['post_expiring']);
			if( !isset($date_arr[0]) || !isset($date_arr[1]) || !isset($date_arr[2]) ) {
				delete_post_meta( $post_id, 'postexpired' );
				$this->unscheduleExpiratorEvent($post_id);
			}
			if ( !empty($_POST['post_expiring']) AND checkdate($date_arr[1], $date_arr[2], $date_arr[0]) ) {
				add_post_meta( $post_id, 'postexpired', esc_sql( $_POST['post_expiring'] ), true ) || update_post_meta( $post_id, 'postexpired', esc_sql( $_POST['post_expiring'] ) );
				$ts = get_gmt_from_date($_POST['post_expiring'],'U');
				$this->scheduleExpiratorEvent($post_id,$ts);
			}
		}
	}
	
	public function add_expiring_field() {
		global $post;
		if( !$post->post_type OR ( $post->post_type != 'page' AND $post->post_type != 'post' ) ) return;
		$screen = get_current_screen();
		if( $screen->base != 'post' ) return;
		?>
		<?php $postexpired = get_post_meta( $post->ID, 'postexpired', true ); ?>
		<div class="misc-pub-section curtime misc-pub-curtime">
			<span id="timestamp"><?php _e('Expiring:', 'postexpiring'); ?></span> <span class="setexpiringdate"><?php echo !empty($postexpired) ? $postexpired : __('Never'); ?></span>
			<a href="#edit_expiringdate" class="edit-expiringdate hide-if-no-js"><span aria-hidden="true"><?php _e( 'Edit' ); ?></span> <span class="screen-reader-text"><?php _e('Edit expiring date', 'postexpiring'); ?></span></a>
			<div id="expiringdatediv" class="hide-if-js">
				<div class="wrap"><input type="text" class="expiring-datepicker" data-exdate="<?php echo esc_attr($postexpired); ?>" value="<?php echo esc_attr($postexpired); ?>" style="font-size: 12px;" name="post_expiring" /><a class="set-expiringdate hide-if-no-js button" href="#edit_expiringdate"><?php _e('OK'); ?></a></div>
				<div><a class="cancel-expiringdate hide-if-no-js button-cancel" href="#edit_expiringdate"><?php _e('Cancel'); ?></a></div>				
			</div>
		</div>
		<?php
	}
	

	function postExpiratorExpire($id) {
		if (empty($id)) { 
			return false;
		}
		if (is_null(get_post($id))) {
			return false;
		}

		kses_remove_filters();
		wp_update_post(array('ID' => $id, 'post_status' => 'draft'));
	}
	
	private function scheduleExpiratorEvent($id,$ts) {
       
		$this->unscheduleExpiratorEvent($id);
		
		wp_schedule_single_event($ts,'postExpiratorExpire',array($id)); 
	}

	private function unscheduleExpiratorEvent($id) {

		if (wp_next_scheduled('postExpiratorExpire',array($id)) !== false) {
			wp_clear_scheduled_hook('postExpiratorExpire',array($id));
		}

	}
	
}
