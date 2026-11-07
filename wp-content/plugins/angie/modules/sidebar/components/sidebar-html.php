<?php

namespace Angie\Modules\Sidebar\Components;

use Angie\Modules\ConsentManager\Module as ConsentManagerModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Sidebar_HTML {
	public function __construct() {
		add_action( 'in_admin_header', [ $this, 'generate_html' ] );

		add_action( 'wp_head', [ $this, 'generate_html' ] );

		add_action( 'elementor/editor/init', function () {
			add_action( 'wp_footer', [ $this, 'generate_html' ] );
		} );
	}

	public function generate_html(): void {
		$is_rtl = is_rtl();
		$dir_attr = $is_rtl ? 'dir="rtl"' : 'dir="ltr"';

		$default_state = get_option( 'angie_sidebar_default_state', 'closed' );
		$is_open = 'open' === $default_state;
		$hidden = $is_open ? 'false' : 'true';

		$html = "
		<!-- Angie Sidebar -->
		<div id='angie-body-top-padding'></div>
		<script>
			// Apply initial state to prevent flash - no transition on load
			(function() {
				const SIDE_MENU_WIDTH = 40;
				const MIN_WIDTH = 310 + SIDE_MENU_WIDTH;
				const MAX_WIDTH = 550 + SIDE_MENU_WIDTH;
				const DEFAULT_WIDTH = 330 + SIDE_MENU_WIDTH;
				const ANGIE_PAGE_SLUG = 'angie-app';

				var defaultState = '" . esc_js( $default_state ) . "';
				var savedState = null;
				var savedWidth = DEFAULT_WIDTH; // Default width
				
				// Check localStorage for saved state and width
				try {
					savedState = localStorage.getItem('angie_sidebar_state');
					var widthStr = localStorage.getItem('angie_sidebar_width');
					if (widthStr) {
						var width = parseInt(widthStr, 10);
						if (width >= MIN_WIDTH && width <= MAX_WIDTH) {
							savedWidth = width;
						}
					}
				} catch (e) {
					// localStorage not available
				}
				
				document.documentElement.style.setProperty('--angie-sidebar-width', savedWidth + 'px');
				
				const isIframe = window.self !== window.top;
				
				const urlParams = new URLSearchParams(window.location.search);
				const isInOAuthFlow = urlParams.has('start-oauth') || 
					urlParams.has('oauth_code') || 
					urlParams.has('oauth_state') || 
					urlParams.has('oauth2_login_success') ||
					urlParams.has('oauth2_state') ||
					urlParams.has('oauth_error');

				let isOAuthGuardActive = false;
				try {
					isOAuthGuardActive = sessionStorage.getItem('angie_oauth_guard_active') === '1';
				} catch (e) {
					console.debug('Angie Sidebar: sessionStorage unavailable in sidebar-html', e);
				}

				const isAngieOAuthPage = urlParams.get('page') === ANGIE_PAGE_SLUG && ( isInOAuthFlow || isOAuthGuardActive );

				var shouldBeOpen = (savedState || defaultState) === 'open' && !isIframe && !( isInOAuthFlow || isAngieOAuthPage );

				function applyAngieClasses() {
					const topPadding = document.getElementById('angie-body-top-padding');
					if (topPadding && document.body) {
						document.body.insertBefore(topPadding, document.body.firstChild);
					}

					if (shouldBeOpen && document.body) {
						// html element
						document.documentElement.classList.add('angie-sidebar-active');
						document.body.classList.add('angie-sidebar-active');
					}
				}

				// Apply immediately if DOM is ready, otherwise wait
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', applyAngieClasses);
				} else {
					applyAngieClasses();
				}
			})();
		</script>

		<div 
			id='angie-sidebar-container'
			role='complementary'
			aria-label='Angie'
			aria-hidden='{$hidden}'
			tabindex='-1'
			{$dir_attr}>
			
			<!-- Loading state -->
			<div id='angie-sidebar-loading' aria-live='polite' class='angie-sr-only'>
			</div>
			
			<!-- Iframe will be injected here by angie.ts -->
		</div>
		";

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}
}
