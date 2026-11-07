( function() {
	'use strict';

	const GATE_NAME = 'angie_agents_platform';
	const PLATFORM_URL = 'https://angie.elementor.com/wp-agents';
	const LOCAL_STORAGE_PLATFORM_URL_KEY = 'angie_agents_platform_url';
	const LOCAL_STORAGE_NOTIFY_REGISTERED_KEY = 'angie_agents_notify_registered';
	const GATE_REQUEST_TIMEOUT_MS = 2000;
	const GATE_MAX_ATTEMPTS = 20;
	const GATE_RETRY_DELAY_MS = 500;
	const IFRAME_LOAD_TIMEOUT_MS = 30000;
	const PLATFORM_URL_ORIGIN = new URL( PLATFORM_URL ).origin;

	function sleep( ms ) {
		return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	}

	function getAngieContext() {
		const iframe = document.getElementById( 'angie-iframe' ) ||
			document.querySelector( 'iframe[src*="angie/"]' );

		if ( ! iframe?.contentWindow || ! iframe.src ) {
			return null;
		}

		try {
			return { iframe, origin: new URL( iframe.src ).origin };
		} catch {
			return null;
		}
	}

	function waitForIframeLoad( iframe ) {
		return new Promise( ( resolve ) => {
			try {
				if ( iframe.contentDocument?.readyState === 'complete' ) {
					resolve();
					return;
				}
			} catch {
				// Cross-origin iframe — load event may not re-fire if already loaded.
			}

			const timeoutId = setTimeout( () => resolve(), IFRAME_LOAD_TIMEOUT_MS );
			iframe.addEventListener( 'load', () => {
				clearTimeout( timeoutId );
				resolve();
			}, { once: true } );
		} );
	}

	function isLocalhostOrigin( origin ) {
		try {
			const { hostname } = new URL( origin );
			return hostname === 'localhost' || hostname === '127.0.0.1';
		} catch {
			return false;
		}
	}

	function resolvePlatformUrl( stored ) {
		if ( ! stored ) {
			return PLATFORM_URL;
		}

		try {
			const { origin } = new URL( stored );
			if ( origin === PLATFORM_URL_ORIGIN || isLocalhostOrigin( origin ) ) {
				return stored;
			}
		} catch {
			// Invalid URL — fall back to default.
		}

		return PLATFORM_URL;
	}

	function buildPlatformIframeSrc( platformUrl ) {
		const url = new URL( platformUrl );
		const config = window.angieAgentsPageConfig;

		url.searchParams.set( 'embedded', '1' );

		if ( config?.wpUsername ) {
			url.searchParams.set( 'wp_username', config.wpUsername );
		}

		return url.toString();
	}

	async function waitForAngieContext() {
		const deadline = Date.now() + 10000;

		while ( Date.now() < deadline ) {
			const context = getAngieContext();
			if ( context ) {
				await waitForIframeLoad( context.iframe );
				return context;
			}
			await sleep( 200 );
		}

		return null;
	}

	function getGateValue( context, gateName, timeoutMs ) {
		return new Promise( ( resolve ) => {
			const channel = new MessageChannel();
			const timeoutId = setTimeout( () => {
				channel.port1.onmessage = null;
				resolve( null );
			}, timeoutMs );

			channel.port1.onmessage = ( event ) => {
				clearTimeout( timeoutId );
				const { data } = event;
				if ( data?.status === 'success' && typeof data.payload?.value === 'boolean' ) {
					resolve( data.payload.value );
					return;
				}
				resolve( null );
			};

			context.iframe.contentWindow.postMessage(
				{ type: 'angie/get-gate-value', gateName },
				context.origin,
				[ channel.port2 ]
			);
		} );
	}

	async function resolveGateOpen( context ) {
		for ( let attempt = 1; attempt <= GATE_MAX_ATTEMPTS; attempt++ ) {
			const gateOpen = await getGateValue( context, GATE_NAME, GATE_REQUEST_TIMEOUT_MS );
			if ( gateOpen !== null ) {
				return gateOpen;
			}
			if ( attempt < GATE_MAX_ATTEMPTS ) {
				await sleep( GATE_RETRY_DELAY_MS );
			}
		}

		return false;
	}

	function markNotifyConfirmed( button, text ) {
		button.disabled = true;
		button.classList.add( 'angie-agents-notify-confirmed' );
		button.innerHTML =
			'<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">' +
			'<circle cx="10" cy="10" r="9" stroke="#0A875A" stroke-width="1.5" fill="none"/>' +
			'<path d="M6 10.5L8.5 13L14 7.5" stroke="#0A875A" stroke-width="1.5" ' +
			'stroke-linecap="round" stroke-linejoin="round"/></svg>' +
			text;
	}

	function initNotifyButton() {
		const button = document.getElementById( 'angie-agents-notify-btn' );
		if ( ! button ) {
			return;
		}

		if ( localStorage.getItem( LOCAL_STORAGE_NOTIFY_REGISTERED_KEY ) === 'true' ) {
			markNotifyConfirmed( button, "You're already on the list" );
		}

		button.addEventListener( 'click', async function() {
			if ( button.classList.contains( 'angie-agents-notify-confirmed' ) ) {
				return;
			}

			localStorage.setItem( LOCAL_STORAGE_NOTIFY_REGISTERED_KEY, 'true' );
			markNotifyConfirmed( button, "Got it, we'll be in touch soon" );

			const angie = await waitForAngieContext();
			if ( angie ) {
				angie.iframe.contentWindow.postMessage(
					{ type: 'angie_agents_notify_request' },
					angie.origin
				);
			}
		} );
	}

	async function initAgentsPlatform() {
		const page = document.getElementById( 'angie-agents-page' );
		const loading = document.getElementById( 'angie-agents-platform-loading' );
		const host = document.getElementById( 'angie-agents-platform-host' );
		const comingSoon = document.getElementById( 'angie-agents-coming-soon' );

		if ( ! page || ! loading || ! host || ! comingSoon ) {
			return;
		}

		const angie = await waitForAngieContext();
		const gateOpen = angie ? await resolveGateOpen( angie ) : false;
		const platformUrl = gateOpen
			? resolvePlatformUrl( localStorage.getItem( LOCAL_STORAGE_PLATFORM_URL_KEY ) )
			: null;

		if ( ! platformUrl ) {
			return;
		}

		page.classList.add( 'angie-agents-page--checking' );
		loading.hidden = false;
		comingSoon.hidden = true;

		const iframe = document.createElement( 'iframe' );
		iframe.className = 'angie-agents-platform-iframe';
		iframe.src = buildPlatformIframeSrc( platformUrl );
		iframe.title = 'Angie Agents';
		iframe.setAttribute( 'scrolling', 'no' );

		host.replaceChildren( iframe );
		host.hidden = false;
		loading.hidden = true;
		page.classList.remove( 'angie-agents-page--checking' );
		page.classList.add( 'angie-agents-page--platform' );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		initNotifyButton();
		initAgentsPlatform();
	} );
}() );
