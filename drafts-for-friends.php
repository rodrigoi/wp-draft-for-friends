<?php
/*
Plugin Name: Drafts for Friends
Plugin URI: http://automattic.com/
Description: Now you don't need to add friends as users to the blog in order to let them preview your drafts
Author: Rodrigo Iloro
Version: 0.1
Author URI: http://rodrigo.iloro.net/
*/

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class TT_Shared_Drafts_Table extends WP_List_Table {
	function __construct(){
		global $status, $page;

		parent::__construct( array(
			'singular' => __('share', 'draftsforfriends'),
			'plural'   => __('shares', 'draftsforfriends'),
			'ajax'     => false,
		) );
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'post_title':
				return esc_html( $item->$column_name );
			case 'created_date':
				return human_time_diff( strtotime( $item->$column_name ) ) . __( ' ago', 'draftsforfriends' );
			case 'expiration_date':
				return $this->expiration_to_human_time( $item->expiration_date );
			default:
				return print_r( $item, true ); //true to return the information and not printing it.
		}
	}

	function column_post_title( $item ) {
		$url = get_bloginfo( 'url' ) . '/?p=' . $item->post_id . '&draftsforfriends='. $item->hash;

		$actions = array(
			'copy'   => sprintf( '<a href="#" data-clipboard-text="' . $url . '" class="copy-to-clipboard">' . __( 'Copy to Clipboard', 'draftsforfriends' ) . '</a>' ),
			'extend' => sprintf( '<a href="#" data-hash="%s" class="extend-limit">' . __( 'Extend Limit', 'draftsforfriends' ) . '</a>', $item->hash ),
			'delete' => sprintf( '<a href="?page=%s&action=%s&id=%s&nonce=%s" class="delete">' . __( 'Delete', 'draftsforfriends' ) . '</a>',
				$_REQUEST['page'],
				'delete',
				$item->id,
				wp_create_nonce( 'draftsforfriends-delete-' . $item->id )
			),
		);

		return sprintf( '<a href="%1$s">%2$s</a> <span class="copied">' . __( 'Copied!', 'draftsforfriends') . '</span> %3$s %4$s',
			/*$1%s*/ $url,
			/*$2%s*/ esc_html( $item->post_title ),
			/*$3%s*/ $this->row_actions( $actions ),
			/*$4%s*/ $this->extend_form( $item )
		);
	}

	private function extend_form( $share ) {
		$secs  = __( 'seconds', 'draftsforfriends' );
		$mins  = __( 'minutes', 'draftsforfriends' );
		$hours = __( 'hours', 'draftsforfriends' );
		$days  = __( 'days', 'draftsforfriends' );
		$nonce = wp_create_nonce( 'draftsforfriends-extend-' . $share->id );

		return "
			<form class=\"draftsforfriends-extend\" id=\"draftsforfriends-extend-form-$share->hash\" action=\"\" method=\"post\">
				<input type=\"hidden\" name=\"draftsforfriends-extend-$share->id-nonce\" value=\"$nonce\" />
				<input type=\"hidden\" name=\"action\" value=\"extend\" />
				<input type=\"hidden\" name=\"id\" value=\"$share->id\" />
				<input type=\"hidden\" name=\"post_id\" value=\"$share->post_id\" />" . __( 'Extend for', 'draftsforfriends' ) . "
				<input name=\"expires\" type=\"text\" value=\"2\" size=\"4\" class=\"small-text\" />
				<select name=\"measure\">
					<option value=\"s\">$secs</option>
					<option value=\"m\">$mins</option>
					<option value=\"h\" selected=\"selected\">$hours</option>
					<option value=\"d\">$days</option>
				</select>
				<input type=\"submit\" class=\"button\" name=\"draftsforfriends_extend_submit\" value=\"" . __( 'Save', 'draftsforfriends' ) . "\"/>
				<a class=\"draftsforfriends-extend-cancel\">" . __( 'Cancel', 'draftsforfriends' ) . "</a>
			</form>
			";
	}

	function expiration_to_human_time( $expiration_date ) {
		$time = time();
		$expiration = strtotime( $expiration_date );
		$time_left = $expiration - $time;

		if( $time_left <= 0) {
			return __( 'Expired', 'draftsforfriends' );
		}

		$human_time = array();

		$weeks = floor( $time_left / WEEK_IN_SECONDS );
		$remaining_seconds = $time_left - $weeks * WEEK_IN_SECONDS;
		if( $weeks > 0 ) {
      $human_time[] = sprintf( _n( '%s week', '%s weeks', $weeks ), $weeks );
		}

		$days = floor( $remaining_seconds / DAY_IN_SECONDS );
		$remaining_seconds = $remaining_seconds - $days * DAY_IN_SECONDS;
		if( $days > 0 ) {
      $human_time[] = sprintf( _n( '%s day', '%s days', $days ), $days );
		}

		$hours = floor( $remaining_seconds / HOUR_IN_SECONDS );
		$remaining_seconds = $remaining_seconds - $hours * HOUR_IN_SECONDS;
		if( $hours > 0 ) {
      $human_time[] = sprintf( _n( '%s hour', '%s hours', $hours ), $hours );
		}

		$minutes = floor ( $remaining_seconds / MINUTE_IN_SECONDS );
		if( $minutes > 0 ) {
      $human_time[] = sprintf( _n( '%s min', '%s mins', $minutes ), $minutes );
		}

		$remaining_seconds = $remaining_seconds - $minutes * MINUTE_IN_SECONDS;
		if( $remaining_seconds > 0 ) {
      $human_time[] = sprintf( _n( '%s second', '%s seconds', $remaining_seconds ), $remaining_seconds );
		}

		return implode( ', ', $human_time);
	}

	function get_columns() {
		$columns = array(
			'post_title'      => __( 'Post', 'draftsforfriends' ),
			'created_date'    => __( 'Created', 'draftsforfriends' ),
			'expiration_date' => __( 'Expires', 'draftsforfriends' )
		);
		return $columns;
	}

	function no_items() {
		_e( 'No shared drafts!', 'draftsforfriends' );
	}

	function prepare_items() {
		$per_page = 5;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array(
			$columns,
			$hidden,
			$sortable
		);

		$data = $this->shares;
		$total_items = count( $data );

		$this->items = $data;
	}
}

class DraftsForFriends	{

	function __construct() {
		add_action( 'init', array( &$this, 'init' ) );

		register_activation_hook( __FILE__, array( $this, 'plugin_install' ) );
		register_deactivation_hook( __FILE__, array( $this, 'plugin_uninstall' ) );
	}

	function init() {
		global $current_user, $wpdb;

		$wpdb->drafts_for_friends = $wpdb->prefix . 'drafts_for_friends';

		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_filter( 'the_posts', array( $this, 'the_posts_intercept') );
		add_filter( 'posts_results', array( $this, 'posts_results_intercept') );

		// Load Translation
		load_plugin_textdomain(
			'draftsforfriends',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	function plugin_install() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'drafts_for_friends';
		$create_sql = "CREATE TABLE $table_name (".
			'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,'.
			'post_id bigint(20) unsigned NOT NULL,'.
			'user_id bigint(20) unsigned NOT NULL,'.
			'hash varchar(32) NOT NULL DEFAULT \'\','.
			'created_date datetime NOT NULL,'.
			'expiration_date datetime NOT NULL,'.
			'PRIMARY KEY (id),'.
			'KEY post_id (post_id),'.
			'KEY user_id (user_id),'.
			'KEY postid_hash_expired (post_id, hash, expiration_date));';

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $create_sql );

		add_option( 'draft_for_friends_db_version', '0.1' );
	}

	function plugin_uninstall() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'drafts_for_friends';
		$drop_sql = "DROP TABLE IF EXISTS $table_name";

		$wpdb->query( $drop_sql );
		delete_option( 'draft_for_friends_db_version' );
	}

	function add_admin_pages(){
		add_submenu_page( "edit.php", __( 'Drafts for Friends', 'draftsforfriends'), __('Drafts for Friends', 'draftsforfriends' ), 'publish_posts', __FILE__ , array( $this, 'render_admin_page' ) );
	}

	function admin_scripts( $hook ) {
		if( $hook == 'posts_page_okenobi/drafts-for-friends' ) {
			wp_enqueue_style( 'draftsforfriends', plugins_url( 'css/drafts-for-friends.css', __FILE__, false, '0.1' ) );
			wp_enqueue_script( 'ZeroClipboard', plugins_url( 'js/ZeroClipboard.js', __FILE__ ), array( 'jquery' ), '0.1', true );
			wp_enqueue_script( 'draftsforfriends', plugins_url( 'js/drafts-for-friends.js', __FILE__ ), array( 'jquery' ), '0.1', true );
		}
	}

	function calculate_expiration_seconds( $expires, $measure ) {
		$exp = 60;
		$multiply = 60;
		if ( isset( $expires ) && ( $e = intval( $expires ) ) ) {
			$exp = $e;
		}

		$mults = array(
			's' => 1,
			'm' => 60,
			'h' => 3600,
			'd' => 24 * 3600
		);
		
		if ( isset( $expires ) && $mults[ $measure ] ) {
			$multiply = $mults[ $measure ];
		}
		return $exp * $multiply;
	}

	function process_post_options( $params ) {
		global $wpdb, $current_user;
		$post = get_post( intval( $params['post_id'] ) );

		$expires = intval( $params['expires'] );
		if( $expires == 0 ) {
			return array( 'error' => __( 'Invalid Expiration.', 'draftsforfriends' ) );
		}

		$measure = $params['measure'];
		$possible_measures_values = array( 's', 'm', 'd', 'h' );
		if ( !in_array( $measure, $possible_measures_values ) ) {
			return array( 'error' => __( 'Invalid Measurement Unit.', 'draftsforfriends' ) );
		}

		$expiration_date = time() + $this->calculate_expiration_seconds( $expires, $measure );

		$wpdb->insert(
			$wpdb->drafts_for_friends,
			array(
				'post_id'         => $post->ID,
				'user_id'         => $current_user->ID,
				'hash'            => wp_generate_password( 32, false, false ),
				'created_date'    => current_time( 'mysql', 1 ),
				'expiration_date' => date( 'Y-m-d H:i:s', $expiration_date )
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s'
			)
		);

		if( $wpdb->insert_id ) {
			return array(
				'success' => sprintf( __( 'Shared draft for \'%s\' created.', 'draftsforfriends' ), esc_html( $post->post_title ) )
			);
		}
	}

	function process_extend( $params ) {
		global $wpdb;

		$id = intval( $params['id'] );
		if ( $id == 0 ) {
			return array( 'error' => __( 'Invalid share id.', 'draftsforfriends' ) );
		}

		$expires = intval( $params['expires'] );
		if( $expires == 0 ) {
			return array( 'error' => __( 'Invalid Expiration.', 'draftsforfriends' ) );
		}

		$measure = $params['measure'];
		$possible_measures_values = array( 's', 'm', 'd', 'h' );
		if ( !in_array( $measure, $possible_measures_values ) ) {
			return array( 'error' => __( 'Invalid Measurement Unit.', 'draftsforfriends' ) );
		}

		$share = $this->get_share_by_id( $id );
		if( is_null( $share ) ) {
			return array( 'error' => __( 'Shared Draft not Found.', 'draftsforfriends' ) );
		}

		$time = time();
		$current_expiration_date = strtotime( $share->expiration_date );

		if($time <= $current_expiration_date) {
			$time = $current_expiration_date;
		}

		$new_expiration_date = $time + $this->calculate_expiration_seconds( intval( $expires ), $measure );

		$result = $wpdb->update(
			$wpdb->drafts_for_friends,
			array( 'expiration_date' => date( 'Y-m-d H:i:s', $new_expiration_date ) ),
			array( 'id' => $id ), 
			array( '%s' ),
			array( '%d' )
		);

		if( $result ) {
			return __( 'Shared draft limit extended.', 'draftsforfriends' );
		}
	}

	function process_delete( $params ) {
		global $wpdb;
		$share_id = intval( $params['id'] );

		if( $share_id == 0 ) {
			return array( 'error' => __( 'There are no shared post to delete.', 'draftsforfriends' ) );
		}

		$result = $wpdb->delete( $wpdb->drafts_for_friends, array( 'id' => $share_id ) );

		if( $result ) {
			return array( 'success' => __( 'Shared draft deleted.', 'draftsforfriends' ) );
		}
	}

	function get_drafts() {
		$drafts    = get_posts( array( 'post_status' => 'draft' ) );
		$pending   = get_posts( array( 'post_status' => 'pending' ) );
		$scheduled = get_posts( array( 'post_status' => 'future' ) );

		$results = array(
			array(
				__( 'Your Drafts:', 'draftsforfriends' ),
				$drafts,
			),
			array(
				__( 'Your Scheduled Posts:', 'draftsforfriends' ),
				$scheduled,
			),
			array(
				__( 'Pending Review:', 'draftsforfriends' ),
				$pending,
			),
		);
		return $results; 
	}

	function get_shared() {
		global $wpdb, $current_user;
		return $wpdb->get_results( $wpdb->prepare( "SELECT d.*, p.post_title AS post_title FROM $wpdb->drafts_for_friends d INNER JOIN $wpdb->posts p ON d.post_id = p.id WHERE user_id = %d AND p.post_status != 'trash'", intval( $current_user->ID ) ) );
	}

	function get_share_by_id ( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT d.*, p.post_title AS post_title FROM $wpdb->drafts_for_friends d INNER JOIN $wpdb->posts p ON d.post_id = p.id WHERE d.id = %d", $id ) );
	}

	function can_view( $post_id ) {
		global $wpdb;
		if( isset( $_GET['draftsforfriends'] ) ){
			$hash = sanitize_text_field( $_GET['draftsforfriends'] );
			$has_shares = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $wpdb->drafts_for_friends WHERE post_id = %d and hash = %s AND expiration_date >= %s", intval( $post_id ), $hash, current_time( 'mysql', 1 ) ) ) );
			return ( $has_shares === 1 );
		}
		return false;
	}

	function posts_results_intercept( $posts ) {
		if ( 1 != count( $posts ) ) {
			return $posts;
		}

		$post = $posts[0];
		$status = get_post_status( $post );

		if ( 'publish' != $status && $this->can_view( $post->ID ) ) {
			$post->comment_status = 'closed';
			$this->shared_post = $post;
		}
		return $posts;
	}

	function the_posts_intercept( $posts ) {
		if ( empty( $posts ) && !is_null( $this->shared_post ) ) {
			return array( $this->shared_post );
		} else {
			$this->shared_post = null;
			return $posts;
		}
	}

	function render_admin_page() {
		// Add Shared Post
		if( isset ( $_POST['draftsforfriends_submit'] ) ) {
			if ( wp_verify_nonce( $_POST['draftsforfriends-add-nonce'], 'draftsforfriends-add' ) ) {
				$result = $this->process_post_options( $_POST );
			} else {
				$failed_nonce = true;
			}
		} elseif( isset ( $_POST['draftsforfriends_extend_submit'] ) ) {
			$nonce_key = 'draftsforfriends-extend-' . intval( $_POST['id'] );
			if ( wp_verify_nonce( $_POST[$nonce_key . '-nonce'], $nonce_key ) ) {
				$result = $this->process_extend( $_POST );
			} else {
				$failed_nonce = true;
			}
		} elseif( isset( $_GET['action'] ) && $_GET['action'] == 'delete' ) {
			$nonce_key = 'draftsforfriends-delete-' . intval( $_GET['id'] );
			if ( wp_verify_nonce( $_GET['nonce'], $nonce_key ) ) {
				$result = $this->process_delete( $_GET );
			} else {
				$failed_nonce = true;
			}
		}

		if ( isset ( $failed_nonce ) ) {
			$result = array( 'error' =>  __( 'Unable to verify nonce.', 'draftsforfriends' ) );
		}

		$drafts = $this->get_drafts();
		$shares = $this->get_shared();

		$shared_drafts_table = new TT_Shared_Drafts_Table();
		$shared_drafts_table->shares = $shares;
		$shared_drafts_table->prepare_items();
?>

	<div class="wrap">
		<h2><?php _e( 'Drafts for Friends', 'draftsforfriends' ); ?></h2>
		<?php if ( isset( $result['success'] ) ): ?>
			<div id="message" class="updated below-h2">
				<p><?php echo $result['success']; ?></p>
			</div>
		<?php endif;?>
		<?php if ( isset( $result['error'] ) ): ?>
			<div id="message" class="updated below-h2 error">
				<p><?php echo $result['error']; ?></p>
			</div>
		<?php endif;?>
		<div id="col-container">
			<div id="col-right">
				<div class="col-wrap">
					<?php $shared_drafts_table->display() ?>
				</div>
			</div>
			<div id="col-left">
				<div class="col-wrap">
					<div class="form-wrap">
						<h3><?php _e( 'Share a Draft', 'draftsforfriends' ); ?></h3>
						<form id="draftsforfriends-add" action="" method="post" class="validate">
							<?php wp_nonce_field( 'draftsforfriends-add', 'draftsforfriends-add-nonce' ); ?>
							<div class="form-field form-required">
								<label for="post_id"><?php _e( 'Choose a draft', 'draftsforfriends' ); ?></label>
								<select id="draftsforfriends-postid" name="post_id">
									<?php foreach ( $drafts as $draft ): ?>
										<optgroup label="<?php echo $draft[0]; ?>">
											<?php foreach ( $draft[1] as $post ): ?>
												<option value="<?php echo $post->ID ?>"><?php echo esc_html( $post->post_title ); ?></option>
											<?php endforeach ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
								<p><?php _e( 'The post you\'ll like to share.', 'draftsforfriends'); ?></p>
							</div>
							<div class="form-required">
								<label><?php _e( 'Share it for', 'draftsforfriends' ); ?></label>
								<input name="expires" type="text" value="2" size="4" class="small-text" />
								<select name="measure">
									<option value="s"><?php _e( 'seconds', 'draftsforfriends' ); ?></option>
									<option value="m"><?php _e( 'minutes', 'draftsforfriends' ); ?></option>
									<option value="h" selected="selected"><?php _e( 'hours', 'draftsforfriends' ); ?></option>
									<option value="d"><?php _e( 'days', 'draftsforfriends' ); ?></option>
								</select>
							</div>
							<p class="submit">
								<input type="submit" class="button button-primary" name="draftsforfriends_submit" value="<?php _e( 'Save', 'draftsforfriends' ); ?>" />
							</p>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
	}
}

new DraftsForFriends();
?>
