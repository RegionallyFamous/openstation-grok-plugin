/** Data returned by the administrator-only MCP Control Center route. */

export type AbilityRisk = 'read' | 'write' | 'destructive' | 'unknown';
export type ExposureFilter = 'all' | 'public' | 'private' | 'review';

export interface AbilityAnnotations {
	readonly: boolean | null;
	destructive: boolean | null;
	idempotent: boolean | null;
}

export interface AbilityRow {
	name: string;
	label: string;
	description: string;
	provider: string;
	category: string;
	mcp_public: boolean;
	exposure: 'mcp' | 'mcp-opt-out' | 'mcp-invalid' | 'public' | 'private';
	mcp_type: string;
	mcp_type_valid: boolean;
	risk: AbilityRisk;
	annotations: AbilityAnnotations;
	input_schema: unknown;
	output_schema: unknown;
}

export interface AbilitySummary {
	total: number;
	public: number;
	private: number;
	read: number;
	write: number;
	destructive: number;
	unknown: number;
}

export interface AdapterStatus {
	installed: boolean;
	active: boolean;
	version: string;
	default_server: boolean;
	endpoint: string;
	abilities_api: boolean;
	requirements_met: boolean;
	can_install: boolean;
	can_activate: boolean;
	project_url: string;
}

export interface McpSetupStatus {
	editorial_pack_enabled: boolean;
	application_passwords_available: boolean;
	application_passwords_url: string;
	users_url: string;
	https: boolean;
}

export interface McpControlCenterSnapshot {
	adapter: AdapterStatus;
	setup: McpSetupStatus;
	summary: AbilitySummary;
	abilities: AbilityRow[];
}

export interface McpControlCenterConfig {
	apiBase: string;
	restNonce: string;
	ajaxUrl: string;
	setupNonce: string;
}

export type McpSetupAction =
	| 'install_adapter'
	| 'activate_adapter'
	| 'enable_editorial_pack'
	| 'disable_editorial_pack';

export interface McpSetupResult {
	message: string;
}

export interface AbilityFilters {
	query: string;
	exposure: ExposureFilter;
	provider: string;
}
