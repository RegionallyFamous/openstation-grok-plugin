import { trackedFetch } from '../tracked-fetch';
import type {
	McpControlCenterConfig,
	McpControlCenterSnapshot,
	McpSetupAction,
	McpSetupResult,
} from './types';

declare global {
	interface Window {
		openStationWindowConfig?: Record< string, unknown >;
	}
}

export const WINDOW_ID = 'openstation-mcp-control-center';
const SOURCE = 'openstation/mcp-control-center';

export function getConfig(): McpControlCenterConfig {
	const getWindowConfig = (
		window.wp as
			| {
					os?: {
						getWindowConfig?: < T >( id: string ) => T | undefined;
					};
				}
			| undefined
	)?.os?.getWindowConfig;
	const config =
		( typeof getWindowConfig === 'function'
			? getWindowConfig< McpControlCenterConfig >( WINDOW_ID )
			: undefined ) ??
		( ( window.openStationWindowConfig ?? {} )[ WINDOW_ID ] as
			| McpControlCenterConfig
			| undefined );

	if ( ! config ) {
		throw new Error( 'MCP Control Center configuration is missing.' );
	}
	return config;
}

export async function fetchSnapshot(
	config: McpControlCenterConfig,
): Promise< McpControlCenterSnapshot > {
	const response = await trackedFetch(
		config.apiBase,
		{
			headers: {
				Accept: 'application/json',
				'X-WP-Nonce': config.restNonce,
			},
		},
		{ source: SOURCE, windowId: WINDOW_ID },
	);

	if ( ! response.ok ) {
		let message = String( response.status );
		try {
			const body = ( await response.json() ) as { message?: string };
			message = body.message || message;
		} catch {
			// A status code is still useful when a proxy returns non-JSON.
		}
		throw new Error( message );
	}

	return ( await response.json() ) as McpControlCenterSnapshot;
}

/** Run one deliberate, nonce-protected MCP setup action. */
export async function runSetupAction(
	config: McpControlCenterConfig,
	setupAction: McpSetupAction,
): Promise< McpSetupResult > {
	const body = new URLSearchParams( {
		action: 'openstation_mcp_setup',
		nonce: config.setupNonce,
		setupAction,
	} );
	const response = await trackedFetch(
		config.ajaxUrl,
		{
			method: 'POST',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body,
		},
		{ source: SOURCE, windowId: WINDOW_ID },
	);

	let payload: {
		success?: boolean;
		data?: { message?: string };
	} = {};
	try {
		payload = ( await response.json() ) as typeof payload;
	} catch {
		throw new Error( String( response.status ) );
	}
	if ( ! response.ok || payload.success !== true ) {
		throw new Error( payload.data?.message || String( response.status ) );
	}
	return {
		message: payload.data?.message || 'Setup updated.',
	};
}
