<?php
/**
 * Plugin Name: Clear Cache
 * Description: Plugin to Clear Cache for FastCGI and APCu (all or by url)
 * Author: bedas
 * Version: 1.0.0
 *
 * @link    https://tukutoi.com
 * @since   1.0.0 Introduced on 2024-06-22 10:00
 * @package TukuToi\ClearCache
 */

namespace TukuToi\ClearCache;

defined( 'ABSPATH' ) || exit;

class ClearCache {

	private string $security_failed_lang = '';

	private string $permission_denied_lang = '';

	/**
	 * Constructor
	 *
	 * Initializes the plugin by adding hooks.
	 *
	 * @since 1.0.0 Introduced on 2024-06-17 12:00
	 * @package ClearCache
	 * @access public
	 * @author Beda Schmid <beda@tukutoi.com>
	 */
	public function __construct() {
		$this->security_failed_lang   = __( 'Security check failed', 'clear-cache' );
		$this->permission_denied_lang = __( 'Permission denied', 'clear-cache' );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_clear_nginx_cache', array( $this, 'handle_post_clear_cache' ) );
		add_action( 'admin_post_clear_all_nginx_cache', array( $this, 'handle_clear_all_cache' ) );
		add_action( 'admin_post_clear_apcu_cache', array( $this, 'handle_clear_apcu_cache' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
	}

	/**
	 * Add Admin Page
	 *
	 * Adds a menu item and an admin page.
	 *
	 * @since 1.0.0 Introduced on 2024-06-17 12:00
	 * @package ClearCache
	 * @access public
	 * @author Beda Schmid <beda@tukutoi.com>
	 */
	public function add_admin_page(): void {
		add_submenu_page(
			'tools.php',
			'Cache Controls',
			'Cache Controls',
			'manage_options',
			'cache-controls',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render Admin Page
	 *
	 * Displays the form for clearing the cache.
	 *
	 * @since 1.0.0 Introduced on 2024-06-17 12:00
	 * @package ClearCache
	 * @access public
	 * @author Beda Schmid <beda@tukutoi.com>
	 */
	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1>Clear Cache</h1>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="clear_nginx_cache">
				<?php wp_nonce_field( 'clear_nginx_cache_nonce', 'clear_nginx_cache_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="url">URL</label></th>
						<td><input name="url" type="text" id="url" value="" class="regular-text" required></td>
					</tr>
				</table>
				<?php submit_button( 'Clear Cache' ); ?>
			</form>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="clear_all_nginx_cache">
				<?php wp_nonce_field( 'clear_all_nginx_cache_nonce', 'clear_all_nginx_cache_nonce' ); ?>
				<?php submit_button( 'Clear All Cache' ); ?>
			</form>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="clear_apcu_cache">
				<?php wp_nonce_field( 'clear_apcu_cache_nonce', 'clear_apcu_cache_nonce' ); ?>
				<?php submit_button( 'Clear APCu Cache' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle Form Submission
	 *
	 * Processes the form submission and clears the cache.
	 *
	 * @since 1.0.0 Introduced on 2024-06-17 12:00
	 * @package ClearCache
	 * @access public
	 * @author Beda Schmid <beda@tukutoi.com>
	 */
	public function handle_post_clear_cache(): void {

		$this->check_security_permissions( 'clear_nginx_cache_nonce', 'manage_options' );

		$url        = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$parsed_url = wp_parse_url( $url );

		if ( ! $parsed_url ) {
			$this->redirect_with_message( 'Invalid URL entered', 'error' );
		}

		$cache_path = '/var/www/html/wp-content/cache/fastcgi/';
		$scheme     = $parsed_url['scheme'];
		$host       = $parsed_url['host'];
		$requesturi = $parsed_url['path'];
		$hash       = md5( $scheme . 'GET' . $host . $requesturi );
		$cache_file = $cache_path . substr( $hash, -1 ) . '/' . substr( $hash, -3, 2 ) . '/' . $hash;

		if ( file_exists( $cache_file ) ) {
			$result  = unlink( $cache_file );
			$message = $result ? 'Cache cleared successfully.' : 'Failed to clear cache.';
			$this->redirect_with_message( $message, $result ? 'success' : 'error' );
		} else {
			$message = 'Cache file does not exist.';
			$this->redirect_with_message( $message, 'error' );
		}

		wp_safe_redirect( add_query_arg( 'message', rawurlencode( $message ), wp_get_referer() ) );
		exit;
	}

	/**
	 * Handle Clear All Cache
	 *
	 * Clears all cache files.
	 *
	 * @since 1.0.0 Introduced on 2024-06-17 12:00
	 * @package ClearCache
	 * @access public
	 * @author Beda Schmid <beda@tukutoi.com>
	 */
	public function handle_clear_all_cache(): void {
		$this->check_security_permissions( 'clear_all_nginx_cache_nonce', 'manage_options' );

		$cache_path = '/var/www/html/wp-content/cache/fastcgi/';
		$cleared    = $this->delete_directory_contents( $cache_path );

		$message = $cleared ? 'All cache cleared successfully.' : 'Failed to clear all cache.';
		$this->redirect_with_message( $message, $cleared ? 'success' : 'error' );

		wp_safe_redirect( add_query_arg( 'message', rawurlencode( $message ), wp_get_referer() ) );
		exit;
	}

	/**
	 * Handle Clear APCu Cache
	 *
	 * Clears APCu cache.
	 *
	 * @since 1.0.0 Introduced on 2024-06-17 12:00
	 * @package ClearCache
	 * @access public
	 * @author Beda Schmid <beda@tukutoi.com>
	 */
	public function handle_clear_apcu_cache(): void {
		$this->check_security_permissions( 'clear_apcu_cache_nonce', 'manage_options' );

		if ( function_exists( 'apcu_clear_cache' ) ) {
			$success = apcu_clear_cache();
			$message = $success ? 'APCu Cache cleared successfully' : 'Failed to clear APCu Cache';
			$this->redirect_with_message( $message, $success ? 'success' : 'error' );
		} else {
			$this->redirect_with_message( 'APCu Cache is not enabled', 'error' );
		}
	}

	/**
	 * Display Admin Notice
	 *
	 * Displays the admin notice with the message.
	 *
	 * @since 1.0.0 Introduced on 2024-06-17 12:00
	 * @package ClearCache
	 * @access public
	 */
	public function display_admin_notice(): void {
		if ( isset( $_GET['clear_cache_message'] )
			&& isset( $_GET['clear_cache_type'] )
		) {
			$clear_cache_message = sanitize_text_field( wp_unslash( $_GET['clear_cache_message'] ) );
			$clear_cache_type    = sanitize_key( wp_unslash( $_GET['clear_cache_type'] ) );

			$class = 'success' === $clear_cache_type ? 'notice notice-success' : 'notice notice-error';
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $clear_cache_message ) );
		}
	}

	private function check_security_permissions( $key, $permission ) {
		if ( ! isset( $_POST[ $key ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ $key ] ) ), $key ) ) {
			wp_die( esc_html( $this->security_failed_lang ) );
		}

		if ( ! current_user_can( $permission ) ) {
			wp_die( esc_html( $this->permission_denied_lang ) );
		}
	}

	/**
	 * Recursively delete a directory and its contents.
	 *
	 * @since 1.0.0 Introduced on 2024-06-23 12:00
	 * @package ClearCache
	 * @access private
	 * @param string $dir Directory path to delete.
	 * @return bool True on success, false on failure.
	 */
	private function delete_directory_contents( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		$success = true;

		foreach ( $items as $item ) {
			$path = $item->getRealPath();
			if ( $item->isDir() ) {
				if ( ! rmdir( $path ) ) {
					$success = false;
				}
			} elseif ( ! unlink( $path ) ) {
					$success = false;
			}
		}
		return $success;
	}

			/**
			 * Redirect with Message
			 *
			 * Redirects to the previous page with a message.
			 *
			 * @since 1.0.0 Introduced on 2024-06-17 12:00
			 * @package ClearCache
			 * @access private
			 * @param string $message Message to display.
			 * @param string $type Message type.
			 */
	private function redirect_with_message( string $message, string $type ): void {
		$url = add_query_arg(
			array(
				'clear_cache_message' => rawurlencode( $message ),
				'clear_cache_type'    => $type,
			),
			wp_get_referer()
		);
		wp_safe_redirect( $url );
		exit;
	}
}

new ClearCache();
