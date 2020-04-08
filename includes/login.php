<?php
/**
 * Redirect to Membership Account page for user login.
 *
 * @since 2.3
 *
 */
function pmpro_login_redirect( $redirect_to, $request = NULL, $user = NULL ) {
	global $wpdb;

	// Is a user logging in?
	if ( ! empty( $user ) && ! empty( $user->ID ) ) {
		// Logging in, let's figure out where to send them.
		if ( strpos( $redirect_to, "checkout" ) !== false ) {
			// If the redirect url includes the word checkout, leave it alone.
		} elseif ( $wpdb->get_var( "SELECT membership_id FROM $wpdb->pmpro_memberships_users WHERE status = 'active' AND user_id = '" . $user->ID . "' LIMIT 1" ) ) {
			// If logged in and a member, send to wherever they were going.
		} else {
			// Not a member, send to subscription page.
			$redirect_to = pmpro_url( 'levels' );
		}
	} else {
		// Not logging in (login form) so return what was given.
	}

	return apply_filters( 'pmpro_login_redirect_url', $redirect_to, $request, $user );
}
add_filter( 'login_redirect','pmpro_login_redirect', 10, 3 );

//Where is the sign page? Levels page or default multisite page.
function pmpro_wp_signup_location( $location ) {
	if ( is_multisite() && pmpro_getOption("redirecttosubscription") ) {
		$location = pmpro_url("levels");
	}

	return apply_filters( 'pmpro_wp_signup_location', $location );
}
add_filter('wp_signup_location', 'pmpro_wp_signup_location');

//redirect from default login pages to PMPro
function pmpro_login_head() {
	global $pagenow;

	$login_redirect = apply_filters("pmpro_login_redirect", true);
	
	if ( pmpro_is_login_page() || is_page("login") && $login_redirect ) {
		//redirect registration page to levels page
		if ( isset ($_REQUEST['action'] ) && $_REQUEST['action'] == "register" || 
			isset($_REQUEST['registration']) && $_REQUEST['registration'] == "disabled" ) {

				// don't redirect if in admin.
				if ( is_admin() ) {
					return;
				}

				//redirect to levels page unless filter is set.
				$link = apply_filters("pmpro_register_redirect", pmpro_url( 'levels' ));			
				if(!empty($link)) {
					wp_redirect($link);
					exit;
				}

			} else {
				return; //don't redirect if pmpro_register_redirect filter returns false or a blank URL
			}
	 	}
}
add_action('wp', 'pmpro_login_head');
add_action('login_init', 'pmpro_login_head');

/**
 * If a redirect_to value is passed into /login/ and you are logged in already, just redirect there
 *
 * @since 1.7.14
 */
function pmpro_redirect_to_logged_in() {
	if((pmpro_is_login_page() || is_page("login")) && !empty($_REQUEST['redirect_to']) && is_user_logged_in() && (empty($_REQUEST['action']) || $_REQUEST['action'] == 'login') && empty($_REQUEST['reauth'])) {
		wp_safe_redirect($_REQUEST['redirect_to']);
		exit;
	}
}
add_action("template_redirect", "pmpro_redirect_to_logged_in", 5);
add_action("login_init", "pmpro_redirect_to_logged_in", 5);

/**
 * Redirect to the Membership Account page for member login.
 *
 * @since 2.3
 */
function pmpro_login_url( $login_url='', $redirect='' ) {
	$account_page_id = pmpro_getOption( 'account_page_id' );
	if ( ! empty ( $account_page_id ) ) {
		$login_url = get_permalink( $account_page_id );

	if ( ! empty( $redirect ) )
		$login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url ) ;
	}

	return apply_filters( 'pmpro_login_url', $login_url, $redirect );
}
add_filter( 'login_url', 'pmpro_login_url', 50, 2 );

/**
 * Add a hidden field to our login form
 * so we can identify it.
 * Hooks into the WP core filter login_form_top.
 */
function pmpro_login_form_hidden_field( $html ) {
	$html .= '<input type="hidden" name="pmpro_login_form_used" value="1" />';
	
	return $html;
}

/**
 * Show a member login form or logged in member widget.
 *
 * @since 2.3
 *
 */
function pmpro_login_form( $show_menu = true, $show_logout_link = true, $display_if_logged_in = true  ) {
	// Set the message return string.
	$message = '';
	$msgt = 'pmpro_alert';
	if ( isset( $_GET['action'] ) ) {
		switch ( sanitize_text_field( $_GET['action'] ) ) {
			case 'failed':
				$message = __( 'There was a problem with your username or password.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'invalid_username':
				$message = __( 'Unknown username. Check again or try your email address.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'empty_username':
				$message = __( 'Empty username. Please enter your username and try again.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'empty_password':
				$message = __( 'Empty password. Please enter your password and try again.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'incorrect_password':
				$message = __( 'The password you entered for the user is incorrect. Please try again.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'recovered':
				$message = __( 'Check your email for the confirmation link.', 'paid-memberships-pro' );
				break;
		}
	}

	// Logged Out Errors.
	if ( isset( $_GET['loggedout'] ) ) {
		switch ( sanitize_text_field( $_GET['loggedout'] ) ) {
			case 'true':
				$message = __( 'You are now logged out.', 'paid-memberships-pro' );
				$msgt = 'pmpro_success';
				break;
			default:
				$message = __( 'There was a problem logging you out.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
		}
	}

	// Password reset email confirmation.
	if ( isset( $_GET['checkemail'] ) ) {

		switch ( sanitize_text_field( $_GET['checkemail'] ) ) {
			case 'confirm':
				$message = __( 'Check your email for a link to reset your password.', 'paid-memberships-pro' );
				break;
			default:
				$message = __( 'There was an unexpected error regarding your email. Please try again', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
		}
	}

	// Password errors
	if ( isset( $_GET['login'] ) ) {
		switch ( sanitize_text_field( $_GET['login'] ) ) {
			case 'invalidkey':
				$message = __( 'Your reset password key is invalid.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'expiredkey':
				$message = __( 'Your reset password key is expired, please request a new key from the password reset page.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			default:
			break;
				
		}
	}

	if ( isset( $_GET['password'] ) ) {
		switch( sanitize_text_field( $_GET['password'] ) ) {
			case 'changed':
				$message = __( 'Your password has successfully been updated.', 'paid-memberships-pro' );
				$msgt = 'pmpro_success';
				break;
			default:
				$message = __( 'There was a problem updating your password', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
		}
	}

	// Get Errors from password reset.
	if ( isset( $_REQUEST['errors'] ) ) {
		switch ( sanitize_text_field( $_REQUEST['errors'] ) ) {
			case 'invalidcombo':
				$message = __( 'There is no account with that username or email address.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'empty_username':
				$message = __( 'Please enter a valid username.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'invalid_email':
				$message = __( "You've entered an invalid email address.", 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
		}
	}
	
	if ( isset( $_REQUEST['error'] ) ) {
		switch ( sanitize_text_field( $_REQUEST['error'] ) ) {
			case 'password_reset_mismatch':
				$message = __( 'New passwords do not match.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
			case 'password_reset_empty':
				$message = __( 'Please complete all fields.', 'paid-memberships-pro' );
				$msgt = 'pmpro_error';
				break;
		}
	}

	if ( $message ) {
		echo '<div class="pmpro_message ' . esc_attr( $msgt ) . '">'. esc_html( $message ) .'</div>';
	}

	// Show the login form.
	if ( ! is_user_logged_in( ) && ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'reset_pass' ) ) {
		if ( empty( $_GET['login'] ) || empty( $_GET['key'] ) ) { ?>
			<div class="pmpro_login_wrap">
				<h2><?php _e( 'Log In', 'paid-memberships-pro' ); ?></h2>
				<?php
					$username = isset( $_REQUEST['username'] ) ? sanitize_text_field( $_REQUEST['username'] ) : NULL;
					$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url( $_REQUEST['redirect_to'] ) : NULL;
															
					add_filter( 'login_form_top', 'pmpro_login_form_hidden_field' );
					wp_login_form( array( 'value_username' => esc_html( $username ), 'redirect' => esc_url( $redirect_to ) ) );
					remove_filter( 'login_form_top', 'pmpro_login_form_hidden_field' );
				?>
			</div> <!-- end pmpro_login_wrap -->
			<p class="pmpro_form_nav">
				<?php
					/**
					 * Filters the separator used between login form navigation links.
					 *
					 * @since 2.3
					 *
					 * @param string $pmpro_form_nav_separator The separator used between form navigation links.
					 */
					$pmpro_form_nav_separator = apply_filters( 'pmpro_form_nav_separator', ' | ' );

					if ( apply_filters( 'pmpro_show_register_link', get_option( 'users_can_register' ) ) ) {
						$levels_page_id = pmpro_getOption( 'levels_page_id' );
						
						if ( $levels_page_id && pmpro_are_any_visible_levels() ) {
							$pmpro_registration_url = sprintf( '<a href="%s">%s</a>', esc_url( pmpro_url( 'levels' ) ), __( 'Join Now', 'paid-memberships-pro' ) );
						} else {
							$pmpro_registration_url = sprintf( '<a href="%s">%s</a>', wp_registration_url() , __( 'Register', 'paid-memberships-pro' ) );
						}
						
						echo apply_filters( 'pmpro_registration_url', $pmpro_registration_url );

						echo esc_html( $pmpro_form_nav_separator );
					}

					$pmpro_lost_password_url = sprintf( '<a href="%s">%s</a>', add_query_arg( 'action', urlencode( 'reset_pass' ), pmpro_login_url() ), __( 'Lost Password?', 'paid-memberships-pro' ) );

					echo apply_filters( 'pmpro_lost_password_url', $pmpro_lost_password_url );
				?>
			</p>
			<?php
		} 
	}

	if ( ! is_user_logged_in() && isset( $_GET['action'] ) && $_GET['action'] === 'reset_pass' ) {
		pmpro_lost_password_form();
	}

	if ( is_user_logged_in() ) {
		if ( isset( $_REQUEST['login'] ) && isset( $_REQUEST['key'] ) ) {
			esc_html_e( 'You are already signed in.', 'paid-memberships-pro' );
		} elseif ( ! empty( $display_if_logged_in ) ) {
			pmpro_logged_in_welcome( $show_menu, $show_logout_link );
		}
	}

	if ( ! is_user_logged_in() && isset( $_REQUEST['key'] ) ) {
		pmpro_reset_password_form();
	}

}

/**
 * Generate a lost password form for front-end login.
 * @since 2.3
 */
function pmpro_lost_password_form() { ?>
	<div class="pmpro_lost_password_wrap">
		<h2><?php esc_html_e( 'Password Reset', 'paid-memberships-pro' ); ?></h2>
		<p class="pmpro_lost_password-instructions">
			<?php
				esc_html_e( 'Please enter your username or email address. You will receive a link to create a new password via email.', 'paid-memberships-pro' );
			?>
		</p>
		<form id="lostpasswordform" class="pmpro_form" action="<?php echo wp_lostpassword_url(); ?>" method="post">
			<div class="pmpro_lost_password-fields">
				<div class="pmpro_lost_password-field pmpro_lost_password-field-user_login">
					<label for="user_login"><?php esc_html_e( 'Username or Email Address', 'paid-memberships-pro' ); ?></label>
					<input type="text" name="user_login" id="user_login" size="20" />
				</div>
			</div> <!-- end pmpro_lost_password-fields -->
			<div class="pmpro_submit">
				<input type="submit" name="submit" class="lostpassword-button" value="<?php esc_attr_e( 'Get New Password', 'paid-memberships-pro' ); ?>" />
			</div>
		</form>
	</div> <!-- end pmpro_lost_password_wrap -->
	<p class="pmpro_form_nav">
		<?php
			/**
			 * Filters the separator used between login form navigation links.
			 *
			 * @since 2.3
			 *
			 * @param string $pmpro_form_nav_separator The separator used between form navigation links.
			 */
			$pmpro_form_nav_separator = apply_filters( 'pmpro_form_nav_separator', ' | ' );

			echo sprintf( '<a href="%s">%s</a>', esc_url( pmpro_login_url() ), __( 'Log In', 'paid-memberships-pro' ) );

			if ( get_option( 'users_can_register' ) ) {
				echo esc_html( $pmpro_form_nav_separator );

				$pmpro_registration_url = sprintf( '<a href="%s">%s</a>', esc_url( pmpro_url( 'levels' ) ), __( 'Register', 'paid-memberships-pro' ) );

				echo apply_filters( 'pmpro_registration_url', $pmpro_registration_url );

			}
		?>
	</p>
	<?php
}

/**
 * Handle the password reset functionality. Redirect back to login form and show message.
 * @since 2.3
 */
function pmpro_lost_password_redirect() {
	if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
		$account_page = pmpro_getOption( 'account_page_id' );
		$redirect_url = $account_page ? get_permalink( $account_page ): '';

		$errors = retrieve_password();
		if ( is_wp_error( $errors ) ) {	
		$redirect_url = add_query_arg( array( 'errors' => join( ',', $errors->get_error_codes() ), 'action' => urlencode( 'reset_pass' ) ), $redirect_url );
		} else {
			$redirect_url = add_query_arg( array( 'checkemail' => urlencode( 'confirm' ) ), $redirect_url );
		}

		wp_redirect( $redirect_url );
		exit;
	}
}
add_action( 'login_form_lostpassword', 'pmpro_lost_password_redirect' );

/**
 * Redirect Password reset to our own page.
 * @since 2.3
 */
function pmpro_reset_password_redirect() {
	if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
		$account_page = pmpro_getOption( 'account_page_id' );
		$redirect_url = $account_page ? get_permalink( $account_page ): '';
		$user = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );
        if ( ! $user || is_wp_error( $user ) ) {
            if ( $user && $user->get_error_code() === 'expired_key' ) {
				wp_redirect( add_query_arg( 'login', urlencode( 'expiredkey' ), $redirect_url ) );
            } else {
                wp_redirect( add_query_arg( 'login', urlencode( 'invalidkey' ), $redirect_url ));
            }
            exit;
        }
 
        $redirect_url = add_query_arg( array( 'login' => esc_attr( $_REQUEST['login'] ), 'action' => urlencode( 'rp' ) ), $redirect_url );
        $redirect_url = add_query_arg( array( 'key' => esc_attr( $_REQUEST['key'] ), 'action' => urlencode( 'rp' ) ), $redirect_url );
 
        wp_redirect( $redirect_url );
        exit;
	}
}
add_action( 'login_form_rp', 'pmpro_reset_password_redirect' );
add_action( 'login_form_resetpass', 'pmpro_reset_password_redirect' );

/**
 * Show the password reset form after user redirects from email link.
 * @since 2.3
 */
function pmpro_reset_password_form() {
	if ( isset( $_REQUEST['login'] ) && isset( $_REQUEST['key'] ) ) {

		// Error messages
		$errors = array();
		if ( isset( $_REQUEST['error'] ) ) {
			$error_codes = explode( ',', sanitize_text_field( $_REQUEST['error'] ) );
		} ?>
		<div class="pmpro_reset_password_wrap">
			<h2><?php _e( 'Reset Password', 'paid-memberships-pro' ); ?></h2>
			<form name="resetpassform" id="resetpassform" class="pmpro_form" action="<?php echo esc_url( site_url( 'wp-login.php?action=resetpass' ) ); ?>" method="post" autocomplete="off">
				<input type="hidden" id="user_login" name="rp_login" value="<?php echo esc_attr( $_REQUEST['login'] ); ?>" autocomplete="off" />
				<input type="hidden" name="rp_key" value="<?php echo esc_attr( $_REQUEST['key'] ); ?>" />
				<div class="pmpro_reset_password-fields">
					<div class="pmpro_rest_password-field pmpro_rest_password-field-pass1">
						<label for="pass1"><?php esc_html_e( 'New Password', 'paid-memberships-pro' ) ?></label>
						<input type="password" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="off" />
						<p class="lite"><?php echo wp_get_password_hint(); ?></p>
					</div>
					<div class="pmpro_rest_password-field pmpro_rest_password-field-pass2">
						<label for="pass2"><?php esc_html_e( 'Confirm New Password', 'paid-memberships-pro' ) ?></label>
						<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" />
					</div>
				</div> <!-- end pmpro_reset_password-fields -->
				<div class="pmpro_submit">
					<input type="submit" name="submit" id="resetpass-button" class="pmpro_btn pmpro_btn-submit" value="<?php esc_attr_e( 'Reset Password', 'paid-memberships-pro' ); ?>" />
				</div>
			</form>
		</div> <!-- end pmpro_reset_password_wrap -->
		<?php
	}
}

/**
 * Function to handle the actualy password reset and update password.
 * @since 2.3
 */
function pmpro_do_password_reset() {
    if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
        $rp_key = sanitize_text_field( $_REQUEST['rp_key'] );
		$rp_login = sanitize_text_field( $_REQUEST['rp_login'] );
		
		$account_page = pmpro_getOption( 'account_page_id' );
		$redirect_url = $account_page ? get_permalink( $account_page ): '';
		$user = check_password_reset_key( $rp_key, $rp_login );
 
        if ( ! $user || is_wp_error( $user ) ) {
            if ( $user && $user->get_error_code() === 'expired_key' ) {
				wp_redirect( add_query_arg( array( 'login' => urlencode( 'expiredkey' ), 'action' => urlencode( 'rp' ) ), $redirect_url ) );
            } else {
                wp_redirect( add_query_arg( array( 'login' => urlencode( 'invalidkey' ), 'action' => urlencode( 'rp' ) ), $redirect_url ) );
            }
            exit;
        }
 
        if ( isset( $_POST['pass1'] ) ) {
            if ( $_POST['pass1'] != $_POST['pass2'] ) {
				// Passwords don't match
				$redirect_url = add_query_arg( array(
					'key' => urlencode( $rp_key ),
					'login' => urlencode( $rp_login ),
					'error' => urlencode( 'password_reset_mismatch' ),
					'action' => urlencode( 'rp' )
				), $redirect_url );
                
                wp_redirect( $redirect_url );
                exit;
            }
 
            if ( empty( $_POST['pass1'] ) ) {
				// Password is empty
				$redirect_url = add_query_arg( array(
					'key' => urlencode( $rp_key ),
					'login' => urlencode( $rp_login ),
					'error' => urlencode( 'password_reset_empty' ),
					'action' => urlencode( 'rp' )
				), $redirect_url );
 
                wp_redirect( $redirect_url );
                exit;
            }
 
            // Parameter checks OK, reset password
            reset_password( $user, $_POST['pass1'] );
            wp_redirect( add_query_arg( urlencode( 'password' ), urlencode( 'changed' ), $redirect_url ) );
        } else {
           esc_html_e( 'Invalid Request', 'paid-memberships-pro' );
        }
 
        exit;
    }
}
add_action( 'login_form_rp', 'pmpro_do_password_reset' );
add_action( 'login_form_resetpass', 'pmpro_do_password_reset' );

/**
 * Replace the default URL inside the email with the membership account page login URL instead.
 * @since 2.3
 */
function pmpro_password_reset_email_filter( $message, $key, $user_login, $user_data ) {

	$account_page_id = pmpro_getOption( 'account_page_id' );
    if ( ! empty ( $account_page_id ) ) {
        $login_url = get_permalink( $account_page_id );
		$message = str_replace( site_url( 'wp-login.php' ), $login_url, $message );
	}

	return $message;
}
add_filter( 'retrieve_password_message', 'pmpro_password_reset_email_filter', 10, 4 );

/**
 * Authenticate the frontend user login.
 *
 * @since 2.3
 *
 */
 function pmpro_authenticate_username_password( $user, $username, $password ) {
	
	// Only work when the PMPro login form is used.
	if ( empty( $_REQUEST['pmpro_login_form_used'] ) ) {
		return $user;
	}

	// Already logged in.
	if ( is_a( $user, 'WP_User' ) ) {
		return $user;
	}
	
	// For some reason, WP core doesn't recognize this error.
	if ( ! empty( $username ) && empty( $password ) ) {
		$user = new WP_Error( 'invalid_username', __( 'There was a problem with your username or password.', 'paid-memberships-pro' ) );
	}

	// check what page the login attempt is coming from
	$referrer = wp_get_referer();

	if ( !empty( $referrer ) && is_wp_error( $user ) ) {

		$error = $user->get_error_code();
			
		if ( $error ) {
				wp_redirect( add_query_arg( 'action', urlencode( $error ), pmpro_login_url() ) );
			} else {
				wp_redirect( pmpro_login_url() );
			}
	}

	return $user;
}
add_filter( 'authenticate', 'pmpro_authenticate_username_password', 30, 3);


/**
 * Redirect failed login to referrer for frontend user login.
 *
 * @since 2.3
 *
 */
function pmpro_login_failed( $username ) {

	$referrer = wp_get_referer();

	if ( $referrer && ! strstr( $referrer, 'wp-login' ) && ! strstr( $referrer, 'wp-admin' ) ) {
		if ( ! strstr( $referrer, '?login=failed') ) {
			wp_redirect( add_query_arg( array( 'action'=>'failed', 'username' => sanitize_text_field( $username ) ), pmpro_login_url() ) );
		} else {
			wp_redirect( add_query_arg( 'action', 'loggedout', pmpro_login_url() ) );
		}
		exit;
	}
}
add_action( 'wp_login_failed', 'pmpro_login_failed', 10, 2 );

/**
 * Show welcome content for a "Logged In" member with Display Name, Log Out link and a "PMPro Member" menu area.
 *
 * @since 2.3
 *
 */
function pmpro_logged_in_welcome( $show_menu = true, $show_logout_link = true ) {
	if ( is_user_logged_in( ) ) {
		// Set the location the user's display_name will link to based on level status.
		global $current_user, $pmpro_pages;
		if ( ! empty( $pmpro_pages ) && pmpro_hasMembershipLevel() ) {
			$account_page      = get_post( $pmpro_pages['account'] );
			$user_account_link = '<a href="' . esc_url( pmpro_url( 'account' ) ) . '">' . esc_html( preg_replace( '/\@.*/', '', $current_user->display_name ) ) . '</a>';
		} else {
			$user_account_link = '<a href="' . esc_url( admin_url( 'profile.php' ) ) . '">' . esc_html( preg_replace( '/\@.*/', '', $current_user->display_name ) ) . '</a>';
		}
		?>
		<h3 class="pmpro_member_display_name">
			<?php
				/* translators: a generated link to the user's account or profile page */
				printf( esc_html__( 'Welcome, %s', 'paid-memberships-pro' ), $user_account_link );
			?>
		</h3>

		<?php
		/**
		 * Show the "Member Form" menu to users with an active membership level.
		 * The menu can be customized per-level using the Nav Menus Add On for Paid Memberships Pro.
		 *
		 */
		if ( ! empty ( $show_menu ) && pmpro_hasMembershipLevel() ) {
			$pmpro_member_menu_defaults = array(
				'theme_location'  => 'pmpro-member',
				'container'       => 'nav',
				'container_id'    => 'pmpro-member-navigation',
				'container_class' => 'pmpro-member-navigation',
				'fallback_cb'	  => false,
				'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
			);
			wp_nav_menu( $pmpro_member_menu_defaults );
		}
		?>

		<?php
		/**
		 * Optionally show a Log Out link.
		 * User will be redirected to the Membership Account page if no other redirect intercepts the process.
		 *
		 */
		if ( ! empty ( $show_logout_link ) ) { ?>
			<div class="pmpro_member_log_out"><a href="<?php echo wp_logout_url(); ?>"><?php echo esc_html__( 'Log Out', 'paid-memberships-pro' ); ?></a></div>
		<?php
		}
	}
}

/**
 * Allow default WordPress registration page if no level page is set and registrations are open for a site.
 * @since 2.3
 */
function pmpro_no_level_page_register_redirect( $url ) {
	$level = pmpro_url( 'levels' );

	if ( empty( pmpro_url( 'levels' ) ) && get_option( 'users_can_register' ) && ! pmpro_are_any_visible_levels() ) {
		return false;
	}

	return $url;
}
add_action( 'pmpro_register_redirect', 'pmpro_no_level_page_register_redirect' );
