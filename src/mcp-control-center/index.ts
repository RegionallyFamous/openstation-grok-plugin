/**
 * MCP Control Center — administrator audit for WordPress abilities and their
 * effective MCP exposure.
 */

import '../ui/components/os-badge/os-badge';
import '../ui/components/os-button/os-button';
import '../ui/components/os-card/os-card';
import '../ui/components/os-code/os-code';
import '../ui/components/os-disclosure/os-disclosure';
import '../ui/components/os-empty-state/os-empty-state';
import '../ui/components/os-notice/os-notice';
import '../ui/components/os-segmented/os-segmented';
import '../ui/components/os-select/os-select';
import '../ui/components/os-spinner/os-spinner';
import '../ui/components/os-text-field/os-text-field';
import { __, _n, sprintf } from '../i18n';
import {
	abilityNeedsReview,
	abilityProviders,
	filterAbilities,
	formatSchema,
} from './model';
import { fetchSnapshot, getConfig, runSetupAction, WINDOW_ID } from './rest';
import type {
	AbilityFilters,
	AbilityRisk,
	AbilityRow,
	ExposureFilter,
	McpControlCenterSnapshot,
	McpSetupAction,
} from './types';

type RenderCallback = ( body: HTMLElement ) => void;

declare global {
	interface Window {
		openStationNativeWindows?: Record< string, RenderCallback | undefined >;
	}
}

function el< K extends keyof HTMLElementTagNameMap >(
	tag: K,
	className?: string,
	text?: string,
): HTMLElementTagNameMap[ K ] {
	const node = document.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	if ( text !== undefined ) {
		node.textContent = text;
	}
	return node;
}

function badge( label: string, tone: string ): HTMLElement {
	const node = document.createElement( 'os-badge' );
	node.setAttribute( 'tone', tone );
	node.textContent = label;
	return node;
}

interface ConfirmOptions {
	title?: string;
	message: string;
	confirmLabel?: string;
	cancelLabel?: string;
	danger?: boolean;
}

function osConfirmGlobal( options: ConfirmOptions ): Promise< boolean > {
	const fn = (
		window.wp as
			| { os?: { confirm?: ( value: ConfirmOptions ) => Promise< boolean > } }
			| undefined
	)?.os?.confirm;
	if ( typeof fn !== 'function' ) {
		return Promise.reject(
			new Error( '[openstation] wp.os.confirm is missing.' ),
		);
	}
	return fn( options );
}

function riskLabel( risk: AbilityRisk ): string {
	return {
		read: __( 'Read only' ),
		write: __( 'Writes data' ),
		destructive: __( 'Destructive' ),
		unknown: __( 'Unspecified risk' ),
	}[ risk ];
}

function riskTone( risk: AbilityRisk ): string {
	return {
		read: 'success',
		write: 'warning',
		destructive: 'danger',
		unknown: 'neutral',
	}[ risk ];
}

function exposureLabel( exposure: AbilityRow[ 'exposure' ] ): string {
	return {
		mcp: __( 'Explicit MCP' ),
		'mcp-opt-out': __( 'MCP opt-out' ),
		'mcp-invalid': __( 'Invalid MCP flag' ),
		public: __( 'Inherited public' ),
		private: __( 'Private default' ),
	}[ exposure ];
}

function schemaDisclosure(
	heading: string,
	schema: unknown,
): HTMLElement {
	const disclosure = document.createElement( 'os-disclosure' );
	disclosure.setAttribute( 'heading', heading );
	const code = document.createElement( 'os-code' );
	code.setAttribute( 'block', '' );
	code.setAttribute( 'copy', '' );
	code.textContent = formatSchema( schema );
	disclosure.appendChild( code );
	return disclosure;
}

function renderAbility( ability: AbilityRow ): HTMLElement {
	const card = document.createElement( 'os-card' );
	card.setAttribute( 'compact', '' );
	card.classList.add( 'os-mcp__ability' );

	const header = el( 'header', 'os-mcp__ability-header' );
	const title = el( 'div', 'os-mcp__ability-title' );
	title.append(
		el( 'h3', '', ability.label || ability.name ),
		el( 'code', '', ability.name ),
	);
	const flags = el( 'div', 'os-mcp__badges' );
	flags.append(
		badge(
			ability.mcp_public ? __( 'MCP public' ) : __( 'MCP private' ),
			ability.mcp_public ? 'info' : 'neutral',
		),
		badge( riskLabel( ability.risk ), riskTone( ability.risk ) ),
	);
	if ( ability.annotations.idempotent === true ) {
		flags.append( badge( __( 'Idempotent' ), 'success' ) );
	} else if ( ability.annotations.idempotent === false ) {
		flags.append( badge( __( 'Non-idempotent' ), 'warning' ) );
	} else {
		flags.append( badge( __( 'Idempotency unspecified' ), 'neutral' ) );
	}
	if ( abilityNeedsReview( ability ) ) {
		flags.append( badge( __( 'Review' ), 'warning' ) );
	}
	header.append( title, flags );

	const description = el(
		'p',
		'os-mcp__ability-description',
		ability.description || __( 'No description provided.' ),
	);
	const meta = el( 'div', 'os-mcp__ability-meta' );
	meta.append(
		badge( ability.provider || __( 'Unknown provider' ), 'neutral' ),
		badge(
			ability.mcp_type_valid
				? ability.mcp_type || __( 'tool' )
				: __( 'tool (invalid type)' ),
			ability.mcp_type_valid ? 'neutral' : 'warning',
		),
		badge(
			exposureLabel( ability.exposure ),
			ability.exposure === 'mcp-invalid' ? 'warning' : 'neutral',
		),
	);

	const schemas = el( 'div', 'os-mcp__schemas' );
	schemas.append(
		schemaDisclosure( __( 'Input schema' ), ability.input_schema ),
		schemaDisclosure( __( 'Output schema' ), ability.output_schema ),
	);

	card.append( header, description, meta, schemas );
	return card;
}

async function renderControlCenter( body: HTMLElement ): Promise< () => void > {
	const root = body.querySelector< HTMLElement >(
		'[data-os-mcp-control-center-root]',
	);
	if ( ! root ) {
		body.textContent = __( 'MCP Control Center container missing.' );
		return () => {};
	}

	const config = getConfig();
	let disposed = false;
	let snapshot: McpControlCenterSnapshot | null = null;
	let busyAction: McpSetupAction | null = null;
	const filters: AbilityFilters = {
		query: '',
		exposure: 'all',
		provider: '',
	};

	root.textContent = '';
	const app = el( 'div', 'os-mcp' );
	const hero = el( 'header', 'os-mcp__hero' );
	const heroCopy = el( 'div', 'os-mcp__hero-copy' );
	heroCopy.append(
		el( 'p', 'os-mcp__eyebrow', __( 'WordPress abilities' ) ),
		el( 'h1', '', __( 'Know exactly what your AI can reach.' ) ),
		el(
			'p',
			'',
			__(
				'Set up the official bridge, opt into reviewed OpenStation abilities, and audit every registered capability and declared risk.',
			),
		),
	);
	const refresh = document.createElement( 'os-button' );
	refresh.setAttribute( 'variant', 'secondary' );
	refresh.textContent = __( 'Refresh audit' );
	hero.append( heroCopy, refresh );

	const noticeHost = el( 'div', 'os-mcp__notice' );
	const statusHost = el( 'section', 'os-mcp__status' );
	const statsHost = el( 'section', 'os-mcp__stats' );
	const toolbar = el( 'section', 'os-mcp__toolbar' );
	const search = document.createElement( 'os-text-field' );
	search.setAttribute( 'type', 'search' );
	search.setAttribute( 'label', __( 'Search abilities' ) );
	search.setAttribute( 'placeholder', __( 'Name, provider, or description…' ) );

	const exposure = document.createElement( 'os-segmented' );
	exposure.setAttribute( 'label', __( 'Exposure' ) );
	exposure.setAttribute( 'value', filters.exposure );
	for ( const [ value, label ] of [
		[ 'all', __( 'All' ) ],
		[ 'public', __( 'Public' ) ],
		[ 'private', __( 'Private' ) ],
		[ 'review', __( 'Review' ) ],
	] as Array< [ ExposureFilter, string ] > ) {
		const segment = document.createElement( 'os-segment' );
		segment.setAttribute( 'value', value );
		segment.textContent = label;
		exposure.appendChild( segment );
	}

	const provider = document.createElement( 'os-select' );
	provider.setAttribute( 'label', __( 'Provider' ) );
	provider.setAttribute( 'value', '' );
	const resultCount = el( 'p', 'os-mcp__result-count' );
	toolbar.append( search, exposure, provider, resultCount );

	const list = el( 'section', 'os-mcp__ability-list' );
	app.append( hero, noticeHost, statusHost, statsHost, toolbar, list );
	root.appendChild( app );

	function paintNotice( message = '', tone = 'error' ): void {
		noticeHost.textContent = '';
		if ( ! message ) {
			return;
		}
		const notice = document.createElement( 'os-notice' );
		notice.setAttribute( 'tone', tone );
		notice.setAttribute( 'not-dismissible', '' );
		notice.textContent = message;
		noticeHost.appendChild( notice );
	}

	function paintStatus(): void {
		if ( ! snapshot ) {
			return;
		}
		statusHost.textContent = '';
		const { adapter, setup } = snapshot;
		const card = document.createElement( 'os-card' );
		card.classList.add( 'os-mcp__adapter-card' );
		const heading = el( 'div', 'os-mcp__adapter-heading' );
		const copy = el( 'div' );
		let statusDescription = __(
			'Install and activate the MCP Adapter to expose public abilities.',
		);
		if ( adapter.active && adapter.default_server ) {
			statusDescription = __(
				'The default MCP bridge is enabled for this WordPress request.',
			);
		} else if ( adapter.active ) {
			statusDescription = __(
				'The MCP Adapter is active, but its default server is disabled.',
			);
		} else if ( adapter.installed ) {
			statusDescription = __( 'The MCP Adapter is installed and ready to activate.' );
		}
		copy.append(
			el( 'h2', '', __( 'WordPress MCP Adapter' ) ),
			el( 'p', '', statusDescription ),
		);
		const badges = el( 'div', 'os-mcp__badges' );
		badges.append(
			badge(
				adapter.installed ? __( 'Installed' ) : __( 'Not installed' ),
				adapter.installed ? 'success' : 'warning',
			),
			badge(
				adapter.active ? __( 'Active' ) : __( 'Inactive' ),
				adapter.active ? 'success' : 'warning',
			),
			badge(
				adapter.abilities_api
					? __( 'Abilities API ready' )
					: __( 'Abilities API unavailable' ),
				adapter.abilities_api ? 'success' : 'danger',
			),
		);
		if ( adapter.version ) {
			badges.append( badge( `v${ adapter.version }`, 'neutral' ) );
		}
		heading.append( copy, badges );
		card.appendChild( heading );

		const adapterActions = el( 'div', 'os-mcp__actions' );
		const docsButton = document.createElement( 'os-button' );
		docsButton.setAttribute( 'variant', 'ghost' );
		docsButton.textContent = __( 'Official docs' );
		docsButton.addEventListener( 'click', () => {
			window.open( adapter.project_url, '_blank', 'noopener,noreferrer' );
		} );
		adapterActions.appendChild( docsButton );

		if ( ! adapter.installed ) {
			const installButton = document.createElement( 'os-button' );
			installButton.setAttribute( 'variant', 'primary' );
			installButton.textContent = __( 'Install official adapter' );
			if ( busyAction === 'install_adapter' ) {
				installButton.setAttribute( 'busy', '' );
			}
			if ( ! adapter.can_install || ! adapter.requirements_met || busyAction ) {
				installButton.setAttribute( 'disabled', '' );
			}
			installButton.addEventListener( 'click', () => {
				void requestSetup( 'install_adapter' );
			} );
			adapterActions.appendChild( installButton );
		} else if ( ! adapter.active ) {
			const activateButton = document.createElement( 'os-button' );
			activateButton.setAttribute( 'variant', 'primary' );
			activateButton.textContent = __( 'Activate adapter' );
			if ( busyAction === 'activate_adapter' ) {
				activateButton.setAttribute( 'busy', '' );
			}
			if ( ! adapter.can_activate || busyAction ) {
				activateButton.setAttribute( 'disabled', '' );
			}
			activateButton.addEventListener( 'click', () => {
				void requestSetup( 'activate_adapter' );
			} );
			adapterActions.appendChild( activateButton );
		}
		card.appendChild( adapterActions );
		if ( ! adapter.requirements_met ) {
			card.appendChild(
				el(
					'p',
					'os-mcp__requirement',
					__( 'MCP Adapter requires WordPress 6.9+ and PHP 7.4+.' ),
				),
			);
		}
		if ( adapter.endpoint ) {
			const endpoint = el( 'div', 'os-mcp__endpoint' );
			endpoint.append( el( 'span', '', __( 'Default endpoint' ) ) );
			const code = document.createElement( 'os-code' );
			code.setAttribute( 'copy', '' );
			code.textContent = adapter.endpoint;
			endpoint.appendChild( code );
			card.appendChild( endpoint );
		}

		const packCard = document.createElement( 'os-card' );
		packCard.classList.add( 'os-mcp__setup-card' );
		const packHeading = el( 'div', 'os-mcp__setup-heading' );
		const packCopy = el( 'div' );
		packCopy.append(
			el( 'h2', '', __( 'OpenStation editorial pack' ) ),
			el(
				'p',
				'',
				__( 'Eight reviewed abilities for drafts, media metadata, and comment moderation. Publishing, deletion, users, settings, plugin installation, and arbitrary code stay out.' ),
			),
		);
		packHeading.append(
			packCopy,
			badge(
				setup.editorial_pack_enabled ? __( 'MCP public' ) : __( 'Private' ),
				setup.editorial_pack_enabled ? 'info' : 'neutral',
			),
		);
		const packAction: McpSetupAction = setup.editorial_pack_enabled
			? 'disable_editorial_pack'
			: 'enable_editorial_pack';
		const packButton = document.createElement( 'os-button' );
		packButton.setAttribute(
			'variant',
			setup.editorial_pack_enabled ? 'ghost' : 'secondary',
		);
		packButton.textContent = setup.editorial_pack_enabled
			? __( 'Make pack private' )
			: __( 'Enable editorial pack' );
		if ( busyAction === packAction ) {
			packButton.setAttribute( 'busy', '' );
		}
		if ( ! adapter.abilities_api || busyAction ) {
			packButton.setAttribute( 'disabled', '' );
		}
		packButton.addEventListener( 'click', () => {
			void requestSetup( packAction );
		} );
		packCard.append( packHeading, packButton );

		const connectionCard = document.createElement( 'os-card' );
		connectionCard.classList.add( 'os-mcp__setup-card' );
		const connectionHeading = el( 'div', 'os-mcp__setup-heading' );
		const connectionCopy = el( 'div' );
		connectionCopy.append(
			el( 'h2', '', __( 'Connect Grok' ) ),
			el(
				'p',
				'',
				__( 'Use a dedicated least-privileged WordPress user and a revocable Application Password. Never paste the secret into a prompt.' ),
			),
		);
		const connectionBadges = el( 'div', 'os-mcp__badges' );
		connectionBadges.append(
			badge( setup.https ? __( 'HTTPS' ) : __( 'HTTPS needed' ), setup.https ? 'success' : 'warning' ),
			badge(
				setup.application_passwords_available
					? __( 'Application Passwords ready' )
					: __( 'Application Passwords unavailable' ),
				setup.application_passwords_available ? 'success' : 'warning',
			),
		);
		connectionHeading.append( connectionCopy, connectionBadges );
		connectionCard.appendChild( connectionHeading );

		const connectionActions = el( 'div', 'os-mcp__actions' );
		const usersButton = document.createElement( 'os-button' );
		usersButton.setAttribute( 'variant', 'ghost' );
		usersButton.textContent = __( 'Manage users' );
		usersButton.addEventListener( 'click', () => {
			window.open( setup.users_url, '_blank', 'noopener,noreferrer' );
		} );
		const profileButton = document.createElement( 'os-button' );
		profileButton.setAttribute( 'variant', 'ghost' );
		profileButton.textContent = __( 'My Application Passwords' );
		if ( ! setup.application_passwords_available ) {
			profileButton.setAttribute( 'disabled', '' );
		}
		profileButton.addEventListener( 'click', () => {
			window.open(
				setup.application_passwords_url,
				'_blank',
				'noopener,noreferrer',
			);
		} );
		connectionActions.append( usersButton, profileButton );
		connectionCard.appendChild( connectionActions );

		if ( adapter.endpoint ) {
			const environment = document.createElement( 'os-code' );
			environment.setAttribute( 'block', '' );
			environment.setAttribute( 'copy', '' );
			environment.textContent = [
				`WORDPRESS_MCP_URL=${ adapter.endpoint }`,
				'WORDPRESS_MCP_AUTHORIZATION=Basic <base64(username:application-password)>',
			].join( '\n' );
			const environmentWrap = el( 'div', 'os-mcp__environment' );
			environmentWrap.append(
				el( 'span', '', __( 'Grok plugin environment' ) ),
				environment,
			);
			connectionCard.appendChild( environmentWrap );
		}

		const setupGrid = el( 'div', 'os-mcp__setup-grid' );
		setupGrid.append( packCard, connectionCard );
		statusHost.append( card, setupGrid );
	}

	async function requestSetup( setupAction: McpSetupAction ): Promise< void > {
		const confirmations: Record< McpSetupAction, ConfirmOptions > = {
			install_adapter: {
				title: __( 'Install the official MCP Adapter?' ),
				message: __( 'OpenStation will download the latest release directly from the WordPress MCP Adapter project on GitHub, install it, and activate it on this site.' ),
				confirmLabel: __( 'Install and activate' ),
			},
			activate_adapter: {
				title: __( 'Activate MCP Adapter?' ),
				message: __( 'Activating the adapter creates its authenticated default MCP endpoint. Only abilities explicitly marked public are discoverable through it.' ),
				confirmLabel: __( 'Activate adapter' ),
			},
			enable_editorial_pack: {
				title: __( 'Expose the editorial pack?' ),
				message: __( 'This makes eight OpenStation abilities discoverable through MCP. The connected WordPress user can read permitted content, create and edit drafts, edit media metadata, and moderate comments according to that user\'s capabilities.' ),
				confirmLabel: __( 'Enable pack' ),
			},
			disable_editorial_pack: {
				title: __( 'Make the editorial pack private?' ),
				message: __( 'Connected MCP clients will stop discovering these eight abilities. Existing WordPress content is not changed.' ),
				confirmLabel: __( 'Make private' ),
			},
		};
		try {
			const confirmed = await osConfirmGlobal( confirmations[ setupAction ] );
			if ( ! confirmed || disposed ) {
				return;
			}
			busyAction = setupAction;
			paintNotice();
			paintStatus();
			const result = await runSetupAction( config, setupAction );
			if ( disposed ) {
				return;
			}
			await load();
			if ( ! disposed ) {
				paintNotice( result.message, 'success' );
			}
		} catch ( error ) {
			if ( ! disposed ) {
				paintNotice(
					`${ __( 'Could not update MCP setup:' ) } ${
						error instanceof Error ? error.message : String( error )
					}`,
				);
			}
		} finally {
			busyAction = null;
			if ( ! disposed ) {
				paintStatus();
			}
		}
	}

	function paintStats(): void {
		if ( ! snapshot ) {
			return;
		}
		statsHost.textContent = '';
		for ( const [ label, value, detail ] of [
			[ __( 'Registered' ), snapshot.summary.total, __( 'abilities' ) ],
			[ __( 'MCP public' ), snapshot.summary.public, __( 'discoverable' ) ],
			[ __( 'Read only' ), snapshot.summary.read, __( 'declared safe reads' ) ],
			[
				__( 'Needs review' ),
				snapshot.abilities.filter( abilityNeedsReview ).length,
				__( 'risk or exposure' ),
			],
		] as Array< [ string, number, string ] > ) {
			const card = document.createElement( 'os-card' );
			card.setAttribute( 'compact', '' );
			card.classList.add( 'os-mcp__stat' );
			card.append(
				el( 'span', 'os-mcp__stat-value', String( value ) ),
				el( 'strong', '', label ),
				el( 'small', '', detail ),
			);
			statsHost.appendChild( card );
		}
	}

	function paintProviderOptions(): void {
		if ( ! snapshot ) {
			return;
		}
		const providers = abilityProviders( snapshot.abilities );
		if ( filters.provider && ! providers.includes( filters.provider ) ) {
			filters.provider = '';
		}
		provider.textContent = '';
		for ( const [ value, label ] of [
			[ '', __( 'All providers' ) ],
			...providers.map(
				( name ) => [ name, name ] as [ string, string ],
			),
		] ) {
			const option = document.createElement( 'os-option' );
			option.setAttribute( 'value', value );
			option.textContent = label;
			provider.appendChild( option );
		}
		provider.setAttribute( 'value', filters.provider );
	}

	function paintList(): void {
		if ( ! snapshot ) {
			return;
		}
		list.textContent = '';
		const abilities = filterAbilities( snapshot.abilities, filters );
		resultCount.textContent = sprintf(
			/* translators: %d: number of abilities visible after filtering. */
			_n( '%d ability shown', '%d abilities shown', abilities.length ),
			abilities.length,
		);
		if ( abilities.length === 0 ) {
			const empty = document.createElement( 'os-empty-state' );
			empty.setAttribute( 'icon', 'filter' );
			empty.setAttribute( 'heading', __( 'No abilities match' ) );
			empty.setAttribute(
				'description',
				__( 'Try a different search, provider, or exposure filter.' ),
			);
			list.appendChild( empty );
			return;
		}
		list.append( ...abilities.map( renderAbility ) );
	}

	async function load(): Promise< void > {
		refresh.setAttribute( 'disabled', '' );
		paintNotice();
		try {
			const next = await fetchSnapshot( config );
			if ( disposed ) {
				return;
			}
			snapshot = next;
			paintStatus();
			paintStats();
			paintProviderOptions();
			paintList();
			if ( ! next.adapter.installed ) {
				paintNotice(
					__( 'The audit works without MCP Adapter, but no MCP client can use these abilities until the adapter is active.' ),
					'warning',
				);
			} else if ( ! next.adapter.active ) {
				paintNotice(
					__( 'MCP Adapter is installed but inactive. Activate it before connecting Grok.' ),
					'warning',
				);
			} else if ( ! next.adapter.default_server ) {
				paintNotice(
					__( 'The MCP Adapter default server is disabled by a WordPress filter.' ),
					'warning',
				);
			}
		} catch ( error ) {
			if ( ! disposed ) {
				paintNotice(
					`${ __( 'Could not load the ability audit:' ) } ${
						error instanceof Error ? error.message : String( error )
					}`,
				);
			}
		} finally {
			refresh.removeAttribute( 'disabled' );
		}
	}

	refresh.addEventListener( 'click', () => void load() );
	search.addEventListener( 'os-input-change', ( event ) => {
		filters.query = ( event as CustomEvent< { value: string } > ).detail.value;
		paintList();
	} );
	exposure.addEventListener( 'os-pick', ( event ) => {
		filters.exposure = ( event as CustomEvent< { value: ExposureFilter } > )
			.detail.value;
		paintList();
	} );
	provider.addEventListener( 'os-pick', ( event ) => {
		filters.provider = ( event as CustomEvent< { value: string } > ).detail.value;
		paintList();
	} );

	await load();
	return () => {
		disposed = true;
	};
}

const registry =
	( window.openStationNativeWindows ??
		( window.openStationNativeWindows = {} ) ) as Record<
		string,
		RenderCallback | undefined
	>;

registry[ WINDOW_ID ] = async ( body: HTMLElement ) => {
	return renderControlCenter( body );
};
