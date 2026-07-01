<?php
$title = (string)$this->_['title'];
$viewName = (string)$this->_['viewName'];
$serviceUrl = (string)$this->_['service'];
$modularGridCssUrl = (string)$this->_['modularGridCssUrl'];
$modularGridJsUrl = (string)$this->_['modularGridJsUrl'];
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
		DataHawk materialization status based on JSON manifests, the registry, recent runs and generated physical tables.
	</p>

	<div class="datahawk-materialization-toolbar">
		<button type="button" class="datahawk-materialization-button" id="datahawk-materialization-reload">Reload</button>
		<button type="button" class="datahawk-materialization-button datahawk-materialization-button-primary" id="datahawk-materialization-refresh-due">Refresh due</button>
		<button type="button" class="datahawk-materialization-button" id="datahawk-materialization-refresh-all">Refresh all</button>
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
			strong.textContent = 'Last action:';
			outputElement.appendChild(strong);
			outputElement.appendChild(document.createTextNode(' ' + getText(message, 'None')));
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
			return createPill(value, value);
		}

		function createCurrentPill(isCurrent) {
			return isCurrent ? createPill('current', 'current') : createPill('old', 'old');
		}

		function createRegisteredPill(isRegistered) {
			return isRegistered ? createPill('yes', 'success') : createPill('no', 'failed');
		}

		function createDuePill(row) {
			if(!row.enabled) {
				return createPill('disabled', 'disabled');
			}

			return row.is_due ? createPill('due', 'due') : createPill('ok', 'success');
		}

		function actionButton(manifestId) {
			const button = createButton('Refresh', 'datahawk-materialization-button-small');
			button.addEventListener('click', () => {
				refreshManifest(manifestId).catch((error) => setOutput('Refresh failed: ' + getText(error && error.message, String(error))));
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
				throw new Error('Request failed with status ' + String(response.status));
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
				throw new Error(getText(response && response.error, 'Failed to load materialization data.'));
			}

			currentPage = response;
			renderPage(response);
			await initGrids(response);
			setOutput('Loaded materialization data at ' + getText(new Date().toLocaleString()));
		}

		async function refreshManifest(manifestId) {
			setOutput('Refreshing ' + manifestId + ' ...');
			const response = await postJson({mode: 'refresh_manifest', manifestId, buildMode: 'refresh'});

			if(!response) {
				throw new Error('No response.');
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
			setOutput('Refresh ' + manifestId + ': ' + getText(result.message, response.ok ? 'done' : 'failed'));
		}

		async function refreshDue(force) {
			setOutput(force ? 'Refreshing all materializations ...' : 'Refreshing due materializations ...');
			const response = await postJson({mode: force ? 'refresh_all' : 'refresh_due'});

			if(!response) {
				throw new Error('No response.');
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
			setOutput((force ? 'Refresh all' : 'Refresh due') + ' finished: ' + (ids.length ? ids.join(', ') : 'no materializations due'));
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
				['Manifests', overview.manifest_count],
				['Enabled', overview.enabled_manifest_count],
				['Due', overview.due_manifest_count],
				['Current generations', overview.current_generation_count],
				['Tables', overview.materialized_table_count],
				['Recent failures', overview.failed_recent_run_count]
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
				manifestGridDefinition('overview_due', false, 'Due materializations', 'Only manifests that are currently due.'),
				manifestGridDefinition('overview_manifests', false, 'Manifests', 'Configured materializations and current state.'),
				runGridDefinition('overview_runs', 'Recent runs', 'Most recent materialization builds.')
			];
		}

		function manifestGridDefinition(gridView, detailed = true, title = 'Manifests', description = '') {
			const columns = [
				{
					key: 'due_text',
					label: 'Due',
					width: 90,
					sortType: 'string',
					render(value, row) {
						return createDuePill(row);
					}
				},
				{
					key: 'id',
					label: 'Manifest',
					width: 230,
					sortType: 'string',
					render(value) {
						return code(value);
					}
				},
				{
					key: 'logical_table',
					label: 'Target',
					width: 260,
					sortType: 'string',
					render(value, row) {
						return code(getText(row.target_schema, '') + '.' + getText(row.logical_table, ''));
					}
				},
				{
					key: 'schedule_text',
					label: 'Schedule',
					width: 160,
					sortType: 'string'
				},
				{
					key: 'current_row_count',
					label: 'Rows',
					width: 100,
					sortType: 'number'
				},
				{
					key: 'last_success_text',
					label: 'Last success',
					width: 170,
					sortType: 'string'
				},
				{
					key: 'actions',
					label: 'Action',
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
						label: 'Dependency mode',
						width: 150,
						sortType: 'string'
					},
					{
						key: 'depends_on',
						label: 'Depends on',
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
				searchPlaceholder: 'Search manifests, tables or schedule',
				pageSize: 50
			};
		}

		function registryGridDefinition(gridView, title = 'Registry', description = '') {
			return {
				gridView,
				rootId: 'datahawk-materialization-grid-' + gridView,
				title,
				description,
				defaultSortKey: 'logical_table',
				defaultSortDirection: 'asc',
				searchPlaceholder: 'Search registry',
				pageSize: 50,
				columns: [
					{
						key: 'is_current',
						label: 'Current',
						width: 100,
						sortType: 'number',
						render(value, row) {
							return createCurrentPill(Number(row.is_current) === 1);
						}
					},
					{
						key: 'logical_table',
						label: 'Logical table',
						width: 260,
						sortType: 'string',
						render(value, row) {
							return code(getText(row.schema_name, '') + '.' + getText(row.logical_table, ''));
						}
					},
					{
						key: 'physical_table',
						label: 'Physical table',
						width: 420,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'row_count',
						label: 'Rows',
						width: 100,
						sortType: 'number'
					},
					{
						key: 'published_text',
						label: 'Published',
						width: 170,
						sortType: 'string'
					},
					{
						key: 'status',
						label: 'Status',
						width: 120,
						sortType: 'string',
						render(value) {
							return createPill(value, value === 'published' ? 'success' : '');
						}
					}
				]
			};
		}

		function runGridDefinition(gridView, title = 'Runs', description = '') {
			return {
				gridView,
				rootId: 'datahawk-materialization-grid-' + gridView,
				title,
				description,
				defaultSortKey: 'id',
				defaultSortDirection: 'desc',
				searchPlaceholder: 'Search runs, messages or manifests',
				pageSize: 50,
				columns: [
					{
						key: 'status',
						label: 'Status',
						width: 110,
						sortType: 'string',
						render(value) {
							return createStatusPill(value);
						}
					},
					{
						key: 'manifest_id',
						label: 'Manifest',
						width: 220,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'row_count',
						label: 'Rows',
						width: 100,
						sortType: 'number'
					},
					{
						key: 'started_text',
						label: 'Started',
						width: 170,
						sortType: 'string'
					},
					{
						key: 'finished_text',
						label: 'Finished',
						width: 170,
						sortType: 'string'
					},
					{
						key: 'duration',
						label: 'Duration',
						width: 110,
						sortType: 'number',
						render(value) {
							return value === null || value === undefined ? '-' : String(value) + ' sec';
						}
					},
					{
						key: 'message',
						label: 'Message',
						width: 420,
						sortType: 'string'
					}
				]
			};
		}

		function tableGridDefinition(gridView, title = 'Tables', description = '') {
			return {
				gridView,
				rootId: 'datahawk-materialization-grid-' + gridView,
				title,
				description,
				defaultSortKey: 'table_name',
				defaultSortDirection: 'asc',
				searchPlaceholder: 'Search materialized tables',
				pageSize: 50,
				columns: [
					{
						key: 'is_current',
						label: 'Current',
						width: 100,
						sortType: 'number',
						render(value, row) {
							return createCurrentPill(row.is_current === true);
						}
					},
					{
						key: 'table_name',
						label: 'Table',
						width: 420,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'logical_table',
						label: 'Logical table',
						width: 230,
						sortType: 'string',
						render(value) {
							return code(value);
						}
					},
					{
						key: 'row_count',
						label: 'Rows',
						width: 100,
						sortType: 'number'
					},
					{
						key: 'published_text',
						label: 'Published',
						width: 170,
						sortType: 'string'
					},
					{
						key: 'is_registered',
						label: 'Registered',
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
				SessionStoragePlugin
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
				layout: buildGridLayout(),
				adapter,
				dataMode: 'server',
				server: {
					searchDebounceMs: 220,
					watchStateKeys: ['query']
				},
				features: {
					paging: true
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
					SessionStoragePlugin
				],
				pluginOptions: {
					search: {
						zone: 'topLine1',
						order: 10,
						label: 'Search',
						placeholder: definition.searchPlaceholder || 'Search'
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
						label: 'Reset',
						sections: ['query', 'columns']
					},
					sessionStorage: {
						key: 'datahawk-materialization-grid-' + definition.gridView + '-v1',
						sections: ['query', 'columns']
					},
					info: {
						zone: 'statusZone',
						order: 10,
						displayMode: 'loaded'
					}
				},
				columns: normalizeColumns(definition.columns)
			});

			gridInstances.set(definition.gridView, grid);
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
			loadPage('Reloading ...').catch((error) => setOutput('Reload failed: ' + getText(error && error.message, String(error))));
		});

		document.getElementById('datahawk-materialization-refresh-due').addEventListener('click', () => {
			refreshDue(false).catch((error) => setOutput('Refresh due failed: ' + getText(error && error.message, String(error))));
		});

		document.getElementById('datahawk-materialization-refresh-all').addEventListener('click', () => {
			if(!window.confirm('Refresh all enabled materializations now?')) {
				return;
			}

			refreshDue(true).catch((error) => setOutput('Refresh all failed: ' + getText(error && error.message, String(error))));
		});

		loadPage('Loading ...').catch((error) => setOutput('Loading failed: ' + getText(error && error.message, String(error))));
	})();
</script>
