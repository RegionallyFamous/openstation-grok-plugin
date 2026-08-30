import { describe, expect, it } from 'vitest';
import {
	abilityNeedsReview,
	abilityProviders,
	filterAbilities,
	formatSchema,
} from '../../src/mcp-control-center/model';
import type { AbilityRow } from '../../src/mcp-control-center/types';

function ability( overrides: Partial< AbilityRow > = {} ): AbilityRow {
	return {
		name: 'desktop-mode/search-posts',
		label: 'Search posts',
		description: 'Search published WordPress posts.',
		provider: 'desktop-mode',
		category: 'openstation',
		mcp_public: true,
		exposure: 'mcp',
		mcp_type: 'tool',
		mcp_type_valid: true,
		risk: 'read',
		annotations: {
			readonly: true,
			destructive: false,
			idempotent: true,
		},
		input_schema: { type: 'object' },
		output_schema: { type: 'object' },
		...overrides,
	};
}

describe( 'MCP Control Center model', () => {
	it( 'flags destructive, unknown, and MCP-public writes for review', () => {
		expect( abilityNeedsReview( ability() ) ).toBe( false );
		expect( abilityNeedsReview( ability( { risk: 'destructive' } ) ) ).toBe(
			true,
		);
		expect( abilityNeedsReview( ability( { risk: 'unknown' } ) ) ).toBe(
			true,
		);
		expect( abilityNeedsReview( ability( { risk: 'write' } ) ) ).toBe( true );
		expect(
			abilityNeedsReview(
				ability( { exposure: 'mcp-invalid', mcp_public: false } ),
			),
		).toBe( true );
		expect(
			abilityNeedsReview( ability( { mcp_type_valid: false } ) ),
		).toBe( true );
		expect(
			abilityNeedsReview(
				ability( { risk: 'write', mcp_public: false } ),
			),
		).toBe( false );
	} );

	it( 'filters by exposure, provider, review state, and text', () => {
		const rows = [
			ability(),
			ability( {
				name: 'commerce/refund-order',
				label: 'Refund order',
				provider: 'commerce',
				mcp_public: false,
				exposure: 'private',
				risk: 'destructive',
			} ),
		];

		expect(
			filterAbilities( rows, {
				query: 'published',
				exposure: 'public',
				provider: 'desktop-mode',
			} ),
		).toEqual( [ rows[ 0 ] ] );
		expect(
			filterAbilities( rows, {
				query: '',
				exposure: 'review',
				provider: '',
			} ),
		).toEqual( [ rows[ 1 ] ] );
	} );

	it( 'returns unique sorted provider names', () => {
		expect(
			abilityProviders( [
				ability( { provider: 'zeta' } ),
				ability( { provider: 'alpha' } ),
				ability( { provider: 'zeta' } ),
			] ),
		).toEqual( [ 'alpha', 'zeta' ] );
	} );

	it( 'formats missing schemas as a copyable JSON object', () => {
		expect( formatSchema( null ) ).toBe( '{}' );
		expect( formatSchema( { type: 'string' } ) ).toContain(
			'"type": "string"',
		);
	} );
} );
