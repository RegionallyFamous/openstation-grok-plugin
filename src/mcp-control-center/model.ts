import type { AbilityFilters, AbilityRow } from './types';

/** Public mutation, destructive behavior, or missing risk metadata merits review. */
export function abilityNeedsReview( ability: AbilityRow ): boolean {
	return (
		ability.risk === 'destructive' ||
		ability.risk === 'unknown' ||
		ability.exposure === 'mcp-invalid' ||
		! ability.mcp_type_valid ||
		( ability.mcp_public && ability.risk === 'write' )
	);
}

export function abilityProviders( abilities: AbilityRow[] ): string[] {
	return [
		...new Set(
			abilities
				.map( ( ability ) => ability.provider.trim() )
				.filter( Boolean ),
		),
	].sort( ( left, right ) => left.localeCompare( right ) );
}

export function filterAbilities(
	abilities: AbilityRow[],
	filters: AbilityFilters,
): AbilityRow[] {
	const query = filters.query.trim().toLocaleLowerCase();

	return abilities.filter( ( ability ) => {
		if ( filters.provider && ability.provider !== filters.provider ) {
			return false;
		}
		if ( filters.exposure === 'public' && ! ability.mcp_public ) {
			return false;
		}
		if ( filters.exposure === 'private' && ability.mcp_public ) {
			return false;
		}
		if ( filters.exposure === 'review' && ! abilityNeedsReview( ability ) ) {
			return false;
		}
		if ( ! query ) {
			return true;
		}

		return [
			ability.name,
			ability.label,
			ability.description,
			ability.provider,
			ability.category,
		]
			.join( ' ' )
			.toLocaleLowerCase()
			.includes( query );
	} );
}

export function formatSchema( schema: unknown ): string {
	return JSON.stringify( schema ?? {}, null, 2 );
}
