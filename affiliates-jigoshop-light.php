<?php
/**
 * affiliates-jigoshop-light.php
 * 
 * Copyright (c) 2012 "kento" Karim Rahimpur www.itthinx.com
 * 
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 * 
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * This header and all notices must be kept intact.
 * 
 * @author Karim Rahimpur
 * @package affiliates-jigoshop-light
 * @since affiliates-jigoshop-light 1.0.0
 *
 * Plugin Name: Affiliates Jigoshop Integration Light
 * Plugin URI: http://www.itthinx.com/plugins/affiliates-jigoshop-light/
 * Description: Integrates Affiliates with Jigoshop
 * Author: itthinx
 * Author URI: http://www.itthinx.com
 * Version: 1.0.9
 */
define( 'AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN', 'affiliates-jigoshop-light' );

class Affiliates_Jigoshop_Light_Integration {

	const SHOP_ORDER_POST_TYPE = 'shop_order';
	const PLUGIN_OPTIONS = 'affiliates_jigoshop_light';
	const AUTO_ADJUST_DEFAULT = true;
	const NONCE = 'aff_jigo_light_admin_nonce';
	const SET_ADMIN_OPTIONS = 'set_admin_options';
	const REFERRAL_RATE = "referral-rate";
	const REFERRAL_RATE_DEFAULT = "0";
	
	const USAGE_STATS = 'usage_stats';
	const USAGE_STATS_DEFAULT = true;

	/**
	 * Links to posts of type shop_order will be modified only on these
	 * admin pages.
	 * 
	 * @var array
	 */
	private static $shop_order_link_modify_pages = array(
		'affiliates-admin-referrals',
		'affiliates-admin-hits',
		'affiliates-admin-hits-affiliate'
	);

	private static $admin_messages = array();

	/**
	 * Prints admin notices.
	 */
	public static function admin_notices() {
		if ( !empty( self::$admin_messages ) ) {
			foreach ( self::$admin_messages as $msg ) {
				echo $msg;
			}
		}
	}

	/**
	 * Checks dependencies and adds appropriate actions and filters.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		
		$verified = true;
		$disable = false;
		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$active_sitewide_plugins = array_keys( $active_sitewide_plugins );
			$active_plugins = array_merge( $active_plugins, $active_sitewide_plugins );
		}
		$affiliates_is_active = in_array( 'affiliates/affiliates.php', $active_plugins ) || in_array( 'affiliates-pro/affiliates-pro.php', $active_plugins ) || in_array( 'affiliates-enterprise/affiliates-enterprise.php', $active_plugins );
		$jigoshop_is_active = in_array( 'jigoshop/jigoshop.php', $active_plugins );
		$affiliates_jigoshop_is_active = in_array( 'affiliates-jigoshop/affiliates-jigoshop.php', $active_plugins );
		if ( !$affiliates_is_active ) {
			self::$admin_messages[] = "<div class='error'>" . __( 'The <strong>Affiliates Jigoshop Integration Light</strong> plugin requires an Affiliates plugin to be activated: <a href="http://www.itthinx.com/plugins/affiliates" target="_blank">Visit the Affiliates plugin page</a>', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . "</div>";
		}
		if ( !$jigoshop_is_active ) {
			self::$admin_messages[] = "<div class='error'>" . __( 'The <strong>Affiliates Jigoshop Integration Light</strong> plugin requires the <a href="http://wordpress.org/extend/plugins/jigoshop" target="_blank">Jigoshop</a> plugin to be activated.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . "</div>";
		}
		if ( $affiliates_jigoshop_is_active ) {
			self::$admin_messages[] = "<div class='error'>" . __( 'You do not need to use the <srtrong>Affiliates Jigoshop Integration Light</strong> plugin because you are already using the advanced Affiliates Jigoshop Integration plugin. Please deactivate the <strong>Affiliates Jigoshop Integration Light</strong> plugin now.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . "</div>";
		}
		if ( !$affiliates_is_active || !$jigoshop_is_active || $affiliates_jigoshop_is_active ) {
			if ( $disable ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				deactivate_plugins( array( __FILE__ ) );
			}
			$verified = false;
		}

		if ( $verified ) {
			add_action ( 'jigoshop_new_order', array( __CLASS__, 'jigoshop_new_order' ) );
			$options = get_option( self::PLUGIN_OPTIONS , array() );
			add_filter( 'post_type_link', array( __CLASS__, 'post_type_link' ), 10, 4 );
			add_action( 'affiliates_admin_menu', array( __CLASS__, 'affiliates_admin_menu' ) );
			add_filter( 'affiliates_footer', array( __CLASS__, 'affiliates_footer' ) );
		}
	}

	/**
	 * Adds a submenu item to the Affiliates menu for the Jigoshop integration options.
	 */
	public static function affiliates_admin_menu() {
		$page = add_submenu_page(
			'affiliates-admin',
			__( 'Affiliates Jigoshop Integration Light', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ),
			__( 'Jigoshop Integration Light', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ),
			AFFILIATES_ADMINISTER_OPTIONS,
			'affiliates-admin-jigoshop-light',
			array( __CLASS__, 'affiliates_admin_jigoshop_light' )
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
		add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );
	}

	/**
	 * Affiliates Jigoshop Integration Light : admin section.
	 */
	public static function affiliates_admin_jigoshop_light() {
		$output = '';
		if ( !current_user_can( AFFILIATES_ADMINISTER_OPTIONS ) ) {
			wp_die( __( 'Access denied.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) );
		}
		$options = get_option( self::PLUGIN_OPTIONS , array() );
		if ( isset( $_POST['submit'] ) ) {
			if ( wp_verify_nonce( $_POST[self::NONCE], self::SET_ADMIN_OPTIONS ) ) {
				$options[self::REFERRAL_RATE]  = floatval( $_POST[self::REFERRAL_RATE] );
				if ( $options[self::REFERRAL_RATE] > 1.0 ) {
					$options[self::REFERRAL_RATE] = 1.0;
				} else if ( $options[self::REFERRAL_RATE] < 0 ) {
					$options[self::REFERRAL_RATE] = 0.0;
				}
				$options[self::USAGE_STATS] = !empty( $_POST[self::USAGE_STATS] );
			}
			update_option( self::PLUGIN_OPTIONS, $options );
		}

		$referral_rate = isset( $options[self::REFERRAL_RATE] ) ? $options[self::REFERRAL_RATE] : self::REFERRAL_RATE_DEFAULT; 
		$usage_stats   = isset( $options[self::USAGE_STATS] ) ? $options[self::USAGE_STATS] : self::USAGE_STATS_DEFAULT;

		echo
			'<div>' .
			'<h2>' .
			__( 'Affiliates Jigoshop Integration Light', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) .
			'</h2>' .
			'</div>';

		$output .= '<p class="manage" style="padding:1em;margin-right:1em;font-weight:bold;font-size:1em;line-height:1.62em">';
		$output .= __( 'You can support the development of the Affiliates plugin and get additional features with <a href="http://www.itthinx.com/plugins/affiliates-pro/" target="_blank">Affiliats Pro</a> or <a href="http://www.itthinx.com/plugins/affiliates-pro/" target="_blank">Affiliates Enterprise</a>.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN );
		$output .= '</p>';

		$output .= '<div class="manage" style="padding:2em;margin-right:1em;">';
		$output .= '<form action="" name="options" method="post">';		
		$output .= '<div>';
		$output .= '<h3>' . __( 'Referral Rate', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . '</h3>';
		$output .= '<p>';
		$output .= '<label for="' . self::REFERRAL_RATE . '">' . __( 'Referral rate', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN) . '</label>';
		$output .= '&nbsp;';
		$output .= '<input name="' . self::REFERRAL_RATE . '" type="text" value="' . esc_attr( $referral_rate ) . '"/>';
		$output .= '</p>';
		$output .= '<p>';
		$output .= __( 'The referral rate determines the referral amount based on the net sale made.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN );
		$output .= '</p>';
		$output .= '<p class="description">';
		$output .= __( 'Example: Set the referral rate to <strong>0.1</strong> if you want your affiliates to get a <strong>10%</strong> commission on each sale.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN );
		$output .= '</p>';
		
		$output .= '<h3>' . __( 'Usage stats', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . '</h3>';
		$output .= '<p>';
		$output .= '<input name="' . self::USAGE_STATS . '" type="checkbox" ' . ( $usage_stats ? ' checked="checked" ' : '' ) . '/>';
		$output .= ' ';
		$output .= '<label for="' . self::USAGE_STATS . '">' . __( 'Allow the plugin to provide usage stats.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . '</label>';
		$output .= '<br/>';
		$output .= '<span class="description">' . __( 'This will allow the plugin to help in computing how many installations are actually using it. No personal or site data is transmitted, this simply embeds an icon on the bottom of the Affiliates admin pages, so that the number of visits to these can be counted. This is useful to help prioritize development.', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . '</span>';
		$output .= '</p>';

		$output .= '<p>';
		$output .= wp_nonce_field( self::SET_ADMIN_OPTIONS, self::NONCE, true, false );
		$output .= '<input type="submit" name="submit" value="' . __( 'Save', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) . '"/>';
		$output .= '</p>';

		$output .= '</div>';
		$output .= '</form>';
		$output .= '</div>';

		echo $output;

		affiliates_footer();
	}
	
	/**
	 * Add a notice to the footer that the integration is active.
	 * @param string $footer
	 */
	public static function affiliates_footer( $footer ) {
		$options = get_option( self::PLUGIN_OPTIONS , array() );
		$usage_stats   = isset( $options[self::USAGE_STATS] ) ? $options[self::USAGE_STATS] : self::USAGE_STATS_DEFAULT;
		return
			'<div style="font-size:0.9em">' .
			'<p>' .
			( $usage_stats ? "<img src='http://www.itthinx.com/img/affiliates-jigoshop/affiliates-jigoshop-light.png' alt='Logo'/>" : '' ) .
			__( "Powered by <a href='http://www.itthinx.com/plugins/affiliates-jigoshop-light' target='_blank'>Affiliates Jigoshop Integration Light</a>.", AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ) .
			'</p>' .
			'</div>' .
			$footer;
	}

	/**
	 * Returns an edit link for shop_order post types.
	 * 
	 * @param string $post_link
	 * @param array $post
	 * @param boolean $leavename
	 * @param boolean $sample
	 */
	public static function post_type_link( $post_link, $post, $leavename, $sample ) {
		$link = $post_link;
		if (
			// right post type
			isset( $post->post_type) && ( $post->post_type == self::SHOP_ORDER_POST_TYPE ) &&
			// admin page
			is_admin() &&
			// right admin page
			isset( $_REQUEST['page'] ) && in_array( $_REQUEST['page'], self::$shop_order_link_modify_pages ) &&
			// check link 
			( preg_match( "/" . self::SHOP_ORDER_POST_TYPE . "=([^&]*)/", $post_link, $matches ) === 1 ) &&
			isset( $matches[1] ) && ( $matches[1] === $post->post_name )
		) {
			$link = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );
		}
		return $link;
	}

	/**
	 * Record a referral when a new order has been placed.
	 * @param int $order_id the post id of the order
	 */
	public static function jigoshop_new_order( $order_id ) {

		$order_data = get_post_meta( $order_id, 'order_data', true );
		$order_key  = get_post_meta( $order_id, 'order_key', true );
		
		$order = new jigoshop_order( $order_id );

		$total = floatval( $order->order_subtotal );
		if ( $order->order_discount ) {
			$total = $total - floatval( $order->order_discount );
		}
		if ( $total < 0 ) {
			$total = 0;
		}

		$currency	   = get_option( 'jigoshop_currency' );

		$order_link = '<a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '">';
		$order_link .= sprintf( __( 'Order #%s', AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN ), $order_id );
		$order_link .= "</a>";

		$data = array(
			'order_id' => array(
				'title' => 'Order #',
				'domain' => AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN,
				'value' => esc_sql( $order_id )
			),
			'order_total' => array(
				'title' => 'Total',
				'domain' =>  AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN,
				'value' => esc_sql( $total )
			),
			'order_currency' => array(
				'title' => 'Currency',
				'domain' =>  AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN,
				'value' => esc_sql( $currency )
			),
			'order_link' => array(
				'title' => 'Order',
				'domain' =>  AFF_JIGOSHOP_LIGHT_PLUGIN_DOMAIN,
				'value' => esc_sql( $order_link )
			)
		);

		$options = get_option( self::PLUGIN_OPTIONS , array() );
		$referral_rate  = isset( $options[self::REFERRAL_RATE] ) ? $options[self::REFERRAL_RATE] : self::REFERRAL_RATE_DEFAULT;
		$amount = round( floatval( $referral_rate ) * floatval( $total ), AFFILIATES_REFERRAL_AMOUNT_DECIMALS );

		$description = sprintf( 'Order #%s', $order_id );
		affiliates_suggest_referral( $order_id, $description, $data, $amount, $currency );
	}
}
Affiliates_Jigoshop_Light_Integration::init();
