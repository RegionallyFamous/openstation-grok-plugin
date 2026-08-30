import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { runSetupAction } from '../../src/mcp-control-center/rest';
import type { McpControlCenterConfig } from '../../src/mcp-control-center/types';

const CONFIG: McpControlCenterConfig = {
	apiBase: 'https://example.test/wp-json/desktop-mode/v1/mcp-control-center',
	restNonce: 'rest-nonce',
	ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
	setupNonce: 'setup-nonce',
};

describe( 'MCP Control Center setup requests', () => {
	let fetchMock: ReturnType< typeof vi.fn >;
	let previousOs: unknown;

	beforeEach( () => {
		const target = window as unknown as {
			wp?: { os?: unknown };
		};
		target.wp ??= {};
		previousOs = target.wp.os;
		fetchMock = vi.fn();
		target.wp.os = { fetch: fetchMock };
	} );

	afterEach( () => {
		( window as unknown as { wp: { os?: unknown } } ).wp.os = previousOs;
	} );

	test( 'posts the named action and dedicated setup nonce to admin-ajax', async () => {
		fetchMock.mockResolvedValue(
			new Response(
				JSON.stringify( {
					success: true,
					data: { message: 'Editorial pack enabled.' },
				} ),
				{
					status: 200,
					headers: { 'Content-Type': 'application/json' },
				},
			),
		);

		const result = await runSetupAction( CONFIG, 'enable_editorial_pack' );
		expect( result.message ).toBe( 'Editorial pack enabled.' );
		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		const [ url, init, tracking ] = fetchMock.mock.calls[ 0 ] as [
			string,
			RequestInit,
			{ source: string; windowId: string },
		];
		expect( url ).toBe( CONFIG.ajaxUrl );
		expect( init.method ).toBe( 'POST' );
		const body = init.body as URLSearchParams;
		expect( body.get( 'action' ) ).toBe( 'openstation_mcp_setup' );
		expect( body.get( 'nonce' ) ).toBe( 'setup-nonce' );
		expect( body.get( 'setupAction' ) ).toBe( 'enable_editorial_pack' );
		expect( tracking.windowId ).toBe( 'openstation-mcp-control-center' );
	} );

	test( 'surfaces the server message when a setup action is refused', async () => {
		fetchMock.mockResolvedValue(
			new Response(
				JSON.stringify( {
					success: false,
					data: { message: 'You cannot install plugins.' },
				} ),
				{
					status: 403,
					headers: { 'Content-Type': 'application/json' },
				},
			),
		);

		await expect(
			runSetupAction( CONFIG, 'install_adapter' ),
		).rejects.toThrow( 'You cannot install plugins.' );
	} );
} );
