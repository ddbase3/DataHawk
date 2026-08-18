<?php
$title = (string)$this->_['title'];
$viewName = (string)$this->_['viewName'];
$serviceUrl = (string)$this->_['service'];
$modularGridCssUrl = (string)$this->_['modularGridCssUrl'];
$modularGridJsUrl = (string)$this->_['modularGridJsUrl'];
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$modularGridStrings = $this->getBricks('clientstack_modulargrid');
$modularGridStrings = is_array($modularGridStrings) ? $modularGridStrings : [];
$t = static function(string $key, string $fallback) use ($translations): string {
	$text = trim((string)($translations[$key] ?? ''));
	return $text !== '' ? $text : $fallback;
};
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />

<style>
	.datahawk-materialization-shell {
		max-width: 1700px;
	}

	.datahawk-materialization-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.datahawk-materialization-shell h2 {
		margin: 0;
		font-size: 18px;
		line-height: 1.25;
		font-weight: 600;
	}

	.datahawk-materialization-shell p {
		margin: 0 0 12px 0;
		max-width: 1200px;
		color: #555;
		line-height: 1.45;
	}

	.datahawk-materialization-toolbar,
	.datahawk-materialization-panel,
	.datahawk-materialization-output {
		margin: 12px 0;
		padding: 10px 12px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
	}

	.datahawk-materialization-toolbar {
		display: flex;
		align-items: center;
		gap: 8px;
		flex-wrap: wrap;
	}

	.datahawk-materialization-cards {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
		gap: 10px;
		margin: 12px 0;
	}

	.datahawk-materialization-card {
		padding: 10px 12px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
	}

	.datahawk-materialization-card-label {
		font-size: 12px;
		color: #666;
		line-height: 1.35;
	}

	.datahawk-materialization-card-value {
		margin-top: 4px;
		font-size: 22px;
		line-height: 1.2;
		font-weight: 600;
		color: #222;
	}

	.datahawk-materialization-button {
		appearance: none;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		cursor: pointer;
		font: inherit;
		font-size: 13px;
		line-height: 1.3;
		min-height: 28px;
		padding: 4px 10px;
		white-space: nowrap;
	}

	.datahawk-materialization-button:hover {
		background: #f5f5f5;
	}

	.datahawk-materialization-button-primary {
		background: #2f5d91;
		border-color: #2f5d91;
		color: #fff;
	}

	.datahawk-materialization-button-primary:hover {
		background: #284f7c;
	}

	.datahawk-materialization-button-small {
		min-height: 24px;
		padding: 3px 8px;
		font-size: 12px;
	}

	.datahawk-materialization-grid .datahawk-materialization-panel-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 10px;
	}

	.datahawk-materialization-grid .datahawk-materialization-grid-root {
		min-height: 120px;
	}

	.datahawk-materialization-grid .datahawk-materialization-grid-panel {
		display: flex;
		align-items: center;
		flex-wrap: nowrap;
		gap: 8px;
		min-width: 0;
		width: 100%;
		padding: 8px 10px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		overflow-x: auto;
	}

	.datahawk-materialization-grid .datahawk-materialization-grid-main {
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		padding: 4px 0;
	}

	.datahawk-materialization-grid .mg-control-group {
		flex-direction: row;
		align-items: center;
		gap: 6px;
		min-width: auto;
	}

	.datahawk-materialization-grid .mg-label {
		white-space: nowrap;
		color: #666;
		font-size: 12px;
	}

	.datahawk-materialization-grid .mg-input,
	.datahawk-materialization-grid .mg-select,
	.datahawk-materialization-grid .mg-button {
		min-height: 28px;
		font-size: 13px;
	}

	.datahawk-materialization-grid input[type="search"].mg-input {
		width: 320px;
	}

	.datahawk-materialization-grid .mg-table-scroll {
		height: 560px;
		overflow: auto;
		padding-bottom: 4px;
	}

	.datahawk-materialization-grid .mg-table thead th {
		position: sticky;
		top: 0;
		z-index: 12;
		background: #fff;
	}

	.datahawk-materialization-grid .mg-table th,
	.datahawk-materialization-grid .mg-table td {
		padding: 6px 8px;
		font-size: 13px;
		vertical-align: top;
	}

	.datahawk-materialization-muted {
		color: #666;
	}

	.datahawk-materialization-code {
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
		font-size: 12px;
		word-break: break-word;
	}

	.datahawk-materialization-pill {
		display: inline-flex;
		align-items: center;
		padding: 1px 6px;
		border: 1px solid #d6d6d6;
		border-radius: 999px;
		background: #fafafa;
		font-size: 11px;
		line-height: 1.35;
		color: #444;
		white-space: nowrap;
	}

	.datahawk-materialization-pill-success,
	.datahawk-materialization-pill-current,
	.datahawk-materialization-pill-due {
		background: #eef7ee;
		border-color: #bddfbd;
	}

	.datahawk-materialization-pill-failed {
		background: #fff0f0;
		border-color: #e4b9b9;
		color: #8a1f1f;
	}

	.datahawk-materialization-pill-running {
		background: #edf6ff;
		border-color: #c3dff5;
	}

	.datahawk-materialization-pill-disabled,
	.datahawk-materialization-pill-old {
		background: #f2f2f2;
		border-color: #d4d4d4;
		color: #666;
	}

	.datahawk-materialization-output {
		font-size: 13px;
		color: #555;
	}

	.datahawk-materialization-output strong {
		color: #222;
	}

	.datahawk-materialization-empty {
		padding: 16px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
		color: #666;
	}
</style>

<div class="datahawk-materialization-shell">
	<h1><?php echo htmlspecialchars($title, ENT_QUOTES); ?></h1>
	<p>
		<?php echo htmlspecialchars($t('intro', 'DataHawk materialization status based on JSON manifests, the registry, recent runs and generated physical tables.'), ENT_QUOTES); ?>
	</p>

	<div class="datahawk-materialization-toolbar">
		<button type="button" class="datahawk-materialization-button" id="datahawk-materialization-reload"><?php echo htmlspecialchars($t('reload', 'Reload'), ENT_QUOTES); ?></button>
		<button type="button" class="datahawk-materialization-button datahawk-materialization-button-primary" id="datahawk-materialization-refresh-due"><?php echo htmlspecialchars($t('refresh_due', 'Refresh due'), ENT_QUOTES); ?></button>
		<button type="button" class="datahawk-materialization-button" id="datahawk-materialization-refresh-all"><?php echo htmlspecialchars($t('refresh_all', 'Refresh all'), ENT_QUOTES); ?></button>
	</div>

	<div id="datahawk-materialization-cards" class="datahawk-materialization-cards"></div>
	<div id="datahawk-materialization-content" class="datahawk-materialization-grid"></div>
	<div id="datahawk-materialization-output" class="datahawk-materialization-output"></div>
</div>

<script>
	(function() {
		const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const VIEW_NAME = <?php echo json_encode($viewName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const TRANSLATIONS = <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const MODULAR_GRID_STRINGS = <?php echo json_encode($modularGridStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

		function tr(key, fallback) {
			const value = String(TRANSLATIONS[key] || '').trim();
			return value !== '' ? value : fallback;
		}
		const contentElement = document.getElementById('datahawk-materialization-content');
		const cardsElement = document.getElementById('datahawk-materialization-cards');
		const outputElement = document.getElementById('datahawk-materialization-output');
		const gridInstances = new Map();

		let currentPage = null;
		let modularGridModulePromise = null;

		function getText(value, placeholder = '-') {
			if(value === null || value === undefined || value === '') {
				return placeholder;
			}

			if(Array.isArray(value)) {
				return value.length ? value.join(', ') : placeholder;
			}

			return String(value);
		}

		function setOutput(message) {
			if(!outputElement) {
				return;
			}

			outputElement.replaceChildren();

			const strong = document.createElement('strong');
			strong.textContent = tr('last_action', 'Last action:');
			outputElement.appendChild(strong);
			outputElement.appendChild(document.createTextNode(' ' + getText(message, tr('value_none', 'None'))));
		}

		function createElement(tagName, className = '', text = null) {
			const element = document.createElement(tagName);

			if(className !== '') {
				element.className = className;
			}

			if(text !== null && text !== undefined) {
				element.textContent = String(text);
			}

			return element;
		}

		function createButton(label, className = '') {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = ('datahawk-materialization-button ' + className).trim();
			button.textContent = label;

			return button;
		}

		function createPill(value, variant = '') {
			const pill = document.createElement('span');
			pill.className = ('datahawk-materialization-pill ' + (variant ? 'datahawk-materialization-pill-' + variant : '')).trim();
			pill.textContent = getText(value);

			return pill;
		}

		function code(value) {
			return createElement('span', 'datahawk-materialization-code', getText(value));
		}

		function createStatusPill(status) {
			const value = getText(status, 'unknown');
			return createPill(tr('status_' + value, value), value);
		}

		function createCurrentPill(isCurrent) {
			return isCurrent ? createPill(tr('status_current', 'current'), 'current') : createPill(tr('status_old', 'old'), 'old');
		}

		function createRegisteredPill(isRegistered) {
			return isRegistered ? createPill(tr('value_yes', 'yes'), 'success') : createPill(tr('value_no', 'no'), 'failed');
		}

		function createDuePill(row) {
			if(!row.enabled) {
				return createPill(tr('status_disabled', 'disabled'), 'disabled');
			}

			return row.is_due ? createPill(tr('status_due', 'due'), 'due') : createPill(tr('status_ok', 'ok'), 'success');
		}

		function actionButton(manifestId) {
			const button = createButton(tr('refresh', 'Refresh'), 'datahawk-materialization-button-small');
			button.addEventListener('click', () => {
				refreshManifest(manifestId).catch((error) => setOutput(tr('refresh_failed', 'Refresh failed:') + ' ' + getText(error && error.message, String(error))));
			});

			return button;
		}

		async function postJson(payload) {
			const response = await fetch(ENDPOINT_URL, {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(payload)
			});

			if(!response.ok) {
				throw new Error(tr('request_failed_status', 'Request failed with status %s').replace('%s', String(response.status)));
			}

			return await response.json();
		}

		function loadModularGridModule() {
			if(!modularGridModulePromise) {
				modularGridModulePromise = import(MODULAR_GRID_URL);
			}

			return modularGridModulePromise;
		}

		async function loadPage(message = '') {
			if(message !== '') {
				setOutput(message);
			}

			const response = await postJson({mode: 'page'});

			if(!response || response.ok !== true) {
				throw new Error(getText(response && response.error, tr('load_failed_data', 'Failed to load materialization data.')));
			}

			currentPage = response;
			renderPage(response);
			await initGrids(response);
			setOutput(tr('loaded_at', 'Loaded materialization data at %s').replace('%s', getText(new Date().toLocaleString())));
		}

		async function refreshManifest(manifestId) {
			setOutput(tr('refreshing_manifest', 'Refreshing %s ...').replace('%s', manifestId));
			const response = await postJson({mode: 'refresh_manifest', manifestId, buildMode: 'refresh'});

			if(!response) {
				throw new Error(tr('no_response', 'No response.'));
			}

			const page = response.page || null;

			if(page) {
				currentPage = page;
				renderPage(page);
				await initGrids(page);
			}
			else {
				await loadPage();
			}

			const result = response.result || {};
			setOutput(tr('refresh_result', 'Refresh %s: %s').replace('%s', manifestId).replace('%s', getText(result.message, response.ok ? tr('status_done', 'done') : tr('status_failed', 'failed'))));
		}

		async function refreshDue(force) {
			setOutput(force ? tr('refreshing_all', 'Refreshing all materializations ...') : tr('refreshing_due', 'Refreshing due materializations ...'));
			const response = await postJson({mode: force ? 'refresh_all' : 'refresh_due'});

			if(!response) {
				throw new Error(tr('no_response', 'No response.'));
			}

			const page = response.page || null;

			if(page) {
				currentPage = page;
				renderPage(page);
				await initGrids(page);
			}
			else {
				await loadPage();
			}

			const ids = response.manifestIds || [];
			setOutput(tr('refresh_finished', '%s finished: %s').replace('%s', force ? tr('refresh_all', 'Refresh all') : tr('refresh_due', 'Refresh due')).replace('%s', ids.length ? ids.join(', ') : tr('no_materializations_due', 'no materializations due')));
		}

		function renderPage(page) {
			renderCards(page.overview || {});
			gridInstances.clear();

			const definitions = getGridDefinitions(page);
			const wrapper = document.createDocumentFragment();

			definitions.forEach((definition) => {
				const panel = createElement('div', 'datahawk-materialization-panel');
				const header = createElement('div', 'datahawk-materialization-panel-header');
				header.appendChild(createElement('h2', '', definition.title));

				if(definition.description) {
					header.appendChild(createElement('div', 'datahawk-materialization-muted', definition.description));
				}

				panel.appendChild(header);
				panel.appendChild(createElement('div', 'datahawk-materialization-grid-root', '', null));
				panel.lastChild.id = definition.rootId;
				wrapper.appendChild(panel);
			});

			contentElement.replaceChildren(wrapper);
		}

		function renderCards(overview) {
			if(!cardsElement) {
				return;
			}

			const cards = [
				[tr('manifests', 'Manifests'), overview.manifest_count],
				[tr('enabled', 'Enabled'), overview.enabled_manifest_count],
				[tr('due', 'Due'), overview.due_manifest_count],
				[tr('current_generations', 'Current generations'), overview.current_generation_count],
				[tr('tables', 'Tables'), overview.materialized_table_count],
				[tr('failures', 'Failures'), overview.failed_recent_run_count]
			];

			cardsElement.replaceChildren();

			cards.forEach(([label, value]) => {
				const card = createElement('div', 'datahawk-materialization-card');
				card.appendChild(createElement('div', 'datahawk-materialization-card-label', label));
				card.appendChild(createElement('div', 'datahawk-materialization-card-value', getText(value, '0')));
				cardsElement.appendChild(card);
			});
		}

		function getGridDefinitions(page) {
			if(VIEW_NAME === 'registry') {
				return [registryGridDefinition('registry')];
			}

			if(VIEW_NAME === 'runs') {
				return [runGridDefinition('runs')];
			}

			if(VIEW_NAME === 'tables') {
				return [tableGridDefinition('tables')];
			}

			if(VIEW_NAME === 'manifests') {
				return [manifestGridDefinition('manifests', true)];
			}

			return [
				manifestGridDefinition('overview_due', false, tr('due_materializations', 'Due materializations'), tr('due_materializations_description', 'Only manifests that are currently due.')),
				manifestGridDefinition('overview_manifests', false, tr('manifests', 'Manifests'), tr('manifests_description', 'Configured materializations and current state.')),
				runGridDefinition('overview_runs', tr('runs', 'Runs'), tr('runs_description', 'All materialization builds.'))
			];
		}

		function manifestGridDefinition(gridView, detailed = true, title = tr('manifests', 'Manifests'), description = '') {
			const columns = [
				{
					key: 'due_text',
					label: tr('due', 'Due'),
					width: 90,
					sortType: 'string',
					render(value, row) {
						return createDuePill(row);
					}
				},
				{
					key: 'id',
					label: tr('manifest', 'Manifest'),
					width: 230,
					sortType: 'string',
					render(value) {
						return code(value);
					}
				},
				{
					key: 'logical_table',
					label: tr('target', 'Target'),
					width: 260,
					sortType: 'string',
					render(value, row) {
						return code(getText(row.target_schema, '') + '.' + getText(row.logical_table, ''));
					}
				},
				{
					key: 'schedule_text',
					label: tr('schedule', 'Schedule'),
					width: 160,
					sortType: 'string'
				},
				{
					key: 'current_row_count',
					label: tr('rows', 'Rows'),
					width: 100,
					sortType: 'number'
				},
				{
					key: 'last_success_text',
					label: tr('last_success', 'Last success'),
					width: 170,
					sortType: 'string'
				},
				{
					key: 'actions',
					label: tr('action', 'Action'),
					width: 110,
					sortable: false,
					render(value, row) {
						return actionButton(row.id);
					}
				}
			];

			if(detailed) {
				columns.splice(4, 0,
					{
						key: 'dependency_refresh',
						label: tr('dependency_mode', 'Dependency mode'),
						width: 150,
						sortType: 'string'
					},
					{
						key: 'depends_on',
						label: tr('depends_on', 'Depends on'),
						width: 260,
						sortType: 'string',
						render(value) {
							return getText(value);
						}
					}
				);
			}

			return {
				gridView,
				rootId: 'datahawk-materialization-grid-' + gridView,
				title,
				description,
				columns,
				defaultSortKey: 'priority',
				defaultSortDirection: 'asc',
				searchPlaceholder: tr('search_manifests', 'Search manifests, tables or schedule'),
				pageSize: 50
			};
		}

		function registryGridDefinition(gridView, title = tr('registry', 'Registry'), description = '') {
			return {
				gridView,
				rootId: 'datahawk-materialization-grid-' + gridView,
				title,
				description,
				defaultSortKey: 'logical_table',
				defaultSortDirection: 'asc',
				searchPlaceholder: tr('search_registry', 'Search registry'),
				pageSize: 50,
				columns: [
					{
						key: 'is_current',
						label: tr('current', 'Current'),
						width: 100,
						sortType: 'number',
						render(value, row) {
							return createCurrentPill(Number(row.is_current) === 1);
						}
					},
					{
						key: 'logical_table',
						label: tr('logical_table', 'Logical table'),
						width: 260,
						sortType: 'string',
						render(value, row) {
							return code(getText(row.schema_name, '') + '.' + getText(row.logical_table, ''));
						}
					},
					{
						key: 'physical_table',
						label: tr('physical_table', 'Physical table'),
						width: 420,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'row_count',
						label: tr('rows', 'Rows'),
						width: 100,
						sortType: 'number'
					},
					{
						key: 'published_text',
						label: tr('published', 'Published'),
						width: 170,
						sortType: 'string'
					},
					{
						key: 'status',
						label: tr('status', 'Status'),
						width: 120,
						sortType: 'string',
						render(value) {
							return createPill(tr('status_' + getText(value, 'unknown'), getText(value)), value === 'published' ? 'success' : '');
						}
					}
				]
			};
		}

		function runGridDefinition(gridView, title = tr('runs', 'Runs'), description = '') {
			return {
				gridView,
				rootId: 'datahawk-materialization-grid-' + gridView,
				title,
				description,
				defaultSortKey: 'id',
				defaultSortDirection: 'desc',
				searchPlaceholder: tr('search_runs', 'Search runs, messages or manifests'),
				pageSize: 50,
				columns: [
					{
						key: 'status',
						label: tr('status', 'Status'),
						width: 110,
						sortType: 'string',
						render(value) {
							return createStatusPill(value);
						}
					},
					{
						key: 'manifest_id',
						label: tr('manifest', 'Manifest'),
						width: 220,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'row_count',
						label: tr('rows', 'Rows'),
						width: 100,
						sortType: 'number'
					},
					{
						key: 'started_text',
						label: tr('started', 'Started'),
						width: 170,
						sortType: 'string'
					},
					{
						key: 'finished_text',
						label: tr('finished', 'Finished'),
						width: 170,
						sortType: 'string'
					},
					{
						key: 'duration',
						label: tr('duration', 'Duration'),
						width: 110,
						sortType: 'number',
						render(value) {
							return value === null || value === undefined ? '-' : String(value) + ' ' + tr('seconds_short', 'sec');
						}
					},
					{
						key: 'message',
						label: tr('message', 'Message'),
						width: 420,
						sortType: 'string'
					}
				]
			};
		}

		function tableGridDefinition(gridView, title = tr('tables', 'Tables'), description = '') {
			return {
				gridView,
				rootId: 'datahawk-materialization-grid-' + gridView,
				title,
				description,
				defaultSortKey: 'table_name',
				defaultSortDirection: 'asc',
				searchPlaceholder: tr('search_tables', 'Search materialized tables'),
				pageSize: 50,
				columns: [
					{
						key: 'is_current',
						label: tr('current', 'Current'),
						width: 100,
						sortType: 'number',
						render(value, row) {
							return createCurrentPill(row.is_current === true);
						}
					},
					{
						key: 'table_name',
						label: tr('table', 'Table'),
						width: 420,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'logical_table',
						label: tr('logical_table', 'Logical table'),
						width: 230,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'row_count',
						label: tr('rows', 'Rows'),
						width: 100,
						sortType: 'number'
					},
					{
						key: 'published_text',
						label: tr('published', 'Published'),
						width: 170,
						sortType: 'string'
					},
					{
						key: 'is_registered',
						label: tr('registered', 'Registered'),
						width: 120,
						sortType: 'number',
						render(value, row) {
							return createRegisteredPill(row.is_registered === true);
						}
					}
				]
			};
		}

		async function initGrids(page) {
			const definitions = getGridDefinitions(page);
			const module = await loadModularGridModule();

			await Promise.all(definitions.map((definition) => initGrid(module, definition)));
		}

		async function initGrid(module, definition) {
			const root = document.querySelector('#' + definition.rootId);

			if(!root || root.dataset.initialized === '1') {
				return;
			}

			root.dataset.initialized = '1';

			const {
				AjaxAdapter,
				ColumnVisibilityPlugin,
				HeaderMenuPlugin,
				InfoPlugin,
				ModularGrid,
				ResetPlugin,
				SearchPlugin,
				SessionStoragePlugin,
				InfiniteScrollPlugin
			} = module;

			const sortTypes = buildSortTypes(definition.columns);
			let grid = null;

			const adapter = new AjaxAdapter({
				url: ENDPOINT_URL,
				method: 'POST',
				rowsPath: 'data',
				totalPath: 'total',
				mapRequest(request) {
					const sortKey = request.sortKey || definition.defaultSortKey || 'id';
					const sortDirection = request.sortDirection || definition.defaultSortDirection || 'asc';

					return {
						mode: 'grid',
						gridView: definition.gridView,
						page: request.page || 1,
						pageSize: request.pageSize || definition.pageSize || 50,
						search: request.search || '',
						sort: [
							{
								key: sortKey,
								dir: sortDirection,
								type: sortTypes[sortKey] || 'string'
							}
						],
						filters: {}
					};
				}
			});

			grid = new ModularGrid('#' + definition.rootId, {
				strings: MODULAR_GRID_STRINGS,
				layout: buildGridLayout(),
				adapter,
				dataMode: 'server',
				server: {
					searchDebounceMs: 220,
					watchStateKeys: ['query']
				},
				features: {
					paging: false
				},
				pageSize: definition.pageSize || 50,
				sort: {
					key: definition.defaultSortKey || 'id',
					direction: definition.defaultSortDirection || 'asc'
				},
				plugins: [
					SearchPlugin,
					HeaderMenuPlugin,
					InfoPlugin,
					ColumnVisibilityPlugin,
					ResetPlugin,
					SessionStoragePlugin,
					InfiniteScrollPlugin
				],
				pluginOptions: {
					search: {
						zone: 'topLine1',
						order: 10,
						label: tr('search', 'Search'),
						placeholder: definition.searchPlaceholder || tr('search', 'Search')
					},
					headerMenu: {
						showSortActions: true,
						showClearSortAction: true,
						showHideColumnAction: true
					},
					columnVisibility: {
						zone: ''
					},
					reset: {
						zone: 'topLine1',
						order: 30,
						label: tr('reset', 'Reset'),
						sections: ['query', 'columns']
					},
					sessionStorage: {
						key: 'datahawk-materialization-grid-' + definition.gridView + '-v2',
						sections: ['query', 'columns']
					},
					info: {
						zone: 'statusZone',
						order: 10,
						displayMode: 'loaded'
					},
					infiniteScroll: {
						threshold: 180,
						pageSize: definition.pageSize || 50,
						containerSelector: '.mg-table-scroll'
					}
				},
				columns: normalizeColumns(definition.columns)
			});

			gridInstances.set(definition.gridView, grid);

			if(typeof grid.on === 'function') {
				grid.on('data:appended', ({appendedCount, totalLoaded}) => {
					setOutput(tr('loaded_more_rows', 'Loaded %s more rows. %s rows are currently loaded.').replace('%s', String(appendedCount || 0)).replace('%s', String(totalLoaded || 0)));
				});
			}

			await grid.init();
		}

		function buildGridLayout() {
			return {
				type: 'stack',
				className: 'mg-layout-root',
				children: [
					{
						type: 'zone',
						key: 'topLine1',
						className: 'datahawk-materialization-grid-panel'
					},
					{
						type: 'view',
						key: 'main',
						className: 'datahawk-materialization-grid-main'
					},
					{
						type: 'zone',
						key: 'statusZone',
						className: 'datahawk-materialization-grid-panel'
					}
				]
			};
		}

		function buildSortTypes(columns) {
			const sortTypes = {};

			columns.forEach((column) => {
				if(column.key) {
					sortTypes[column.key] = column.sortType || 'string';
				}
			});

			return sortTypes;
		}

		function normalizeColumns(columns) {
			return columns.map((column) => {
				const normalized = {
					key: column.key,
					label: column.label,
					width: column.width || 160,
					headerMenu: {
						defaultSortKey: column.key,
						defaultSortDirection: 'asc',
						sortOptions: column.sortable === false ? [] : [
							{
								key: column.key,
								label: column.label
							}
						]
					}
				};

				if(column.render) {
					normalized.render = column.render;
				}

				return normalized;
			});
		}

		document.getElementById('datahawk-materialization-reload').addEventListener('click', () => {
			loadPage(tr('reloading', 'Reloading ...')).catch((error) => setOutput(tr('reload_failed', 'Reload failed:') + ' ' + getText(error && error.message, String(error))));
		});

		document.getElementById('datahawk-materialization-refresh-due').addEventListener('click', () => {
			refreshDue(false).catch((error) => setOutput(tr('refresh_due_failed', 'Refresh due failed:') + ' ' + getText(error && error.message, String(error))));
		});

		document.getElementById('datahawk-materialization-refresh-all').addEventListener('click', () => {
			if(!window.confirm(tr('confirm_refresh_all', 'Refresh all enabled materializations now?'))) {
				return;
			}

			refreshDue(true).catch((error) => setOutput(tr('refresh_all_failed', 'Refresh all failed:') + ' ' + getText(error && error.message, String(error))));
		});

		loadPage(tr('loading', 'Loading ...')).catch((error) => setOutput(tr('loading_failed', 'Loading failed:') + ' ' + getText(error && error.message, String(error))));
	})();
</script>
