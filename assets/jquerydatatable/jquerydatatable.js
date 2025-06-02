/*!
 * jQuery DataTable Plugin
 * https://github.com/ddbase3/JqueryDataTable
 *
 * Lightweight, extensible data table plugin with paging, sorting, layout slots and modular renderers.
 * Part of the Contourz Photography ecosystem.
 *
 * Author: Daniel Dahme / BASE3 (https://base3.de)
 * License: LGPL (Lesser General Public License)
 */

(function ($) {

	const defaultRenderers = {

		info: function ($el, { total, page, pageSize }) {
			const from = (page - 1) * pageSize + 1;
			const to = Math.min(page * pageSize, total);
			return $('<div class="jquerydatatable-info"></div>').text(`Datensätze ${from} bis ${to} von ${total}`);
		},

		pager: function ($el, { currentPage, pageSize, totalPages }) {
			const $frag = $(document.createDocumentFragment());
			for (let i = 1; i <= totalPages; i++) {
				const $btn = $('<button type="button"></button>').text(i);
				if (i === currentPage) $btn.attr('disabled', true);
				$btn.on('click', function () {
					$el.data('page', i);
					loadData($el);
				});
				$frag.append($btn);
			}
			return $('<div class="jquerydatatable-pager"></div>').append($frag);
		},

		compactPager: function ($el, { currentPage, pageSize, totalPages }) {
			const $pager = $('<div class="datatable-pager" style="display: inline-flex; align-items: center; gap: 0.5em;"></div>');

			const $prev = $('<button type="button">← Zurück</button>').prop('disabled', currentPage <= 1);
			const $next = $('<button type="button">Vor →</button>').prop('disabled', currentPage >= totalPages);
			const $label = $(`<span>Seite ${currentPage} von ${totalPages}</span>`);

			$prev.on('click', function () {
				if (currentPage > 1) {
					$el.data('page', currentPage - 1);
					loadData($el);
				}
			});

			$next.on('click', function () {
				if (currentPage < totalPages) {
					$el.data('page', currentPage + 1);
					loadData($el);
				}
			});

			$pager.append($prev, $label, $next);
			return $pager;
		},

		pageSizeSelector: function ($el) {
			const $select = $('<select class="jquerydatatable-per-page-selector"></select>');

			const settings = $el.data('settings');
			const options = settings.pageSizeOptions;
			const current = $el.data('pageSize') ?? settings.pageSize;

			options.forEach(num => {
				const $opt = $('<option></option>').val(num).text(`${num} pro Seite`);
				if (num === current) $opt.prop('selected', true);
				$select.append($opt);
			});

			$select.on('change', function () {
				const newSize = parseInt($(this).val(), 10);
				// settings.pageSize = newSize;
				$el.data('pageSize', newSize);
				$el.data('page', 1);
				loadData($el);
			});

			return $('<div class="jquerydatatable-per-page-container"></div>').append($select);
		},

		columnSelector: function($el) {
			const settings = $el.data('settings');
			const visibility = $el.data('columnVisibility');

			const $wrapper = $('<div class="jquerydatatable-column-selector-wrapper" style="position: relative; display: inline-block;"></div>');
			const $button = $('<button type="button" class="column-toggle-btn">Spalten ▾</button>');
			const $menu = $('<div class="column-toggle-menu" style="display:none; position:absolute; top:100%; left:0; z-index:1000; background:white; border:1px solid #ccc; padding:10px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"></div>');

			function updateAllCheckboxes(checked) {
				settings.columns.forEach((col) => {
					visibility[col.key] = checked;
				});
				$el.data('columnVisibility', visibility);
				updateColumnVisibility($el);
				$menu.find('input[type="checkbox"]').not('.all-toggle').prop('checked', checked);
			}

			const $allLine = $('<div style="white-space:nowrap;"></div>');
			const $allOn = $('<button type="button">Alle ein</button>').on('click', function(e) {
				updateAllCheckboxes(true);
				e.stopPropagation();
			});
			const $allOff = $('<button type="button">Alle aus</button>').on('click', function(e) {
				updateAllCheckboxes(false);
				e.stopPropagation();
			});
			$allLine.append($allOn, $allOff);
			$menu.append($allLine, $('<hr style="margin:5px 0;">'));

			settings.columns.forEach((col) => {
				const $label = $('<label style="display:block; margin-bottom: 4px; white-space: nowrap; cursor: pointer;"></label>');
				const $checkbox = $('<input type="checkbox" name="' + col.key + '">')
					.prop('checked', visibility[col.key] !== false)
					.on('change', function (e) {
						visibility[col.key] = $(this).is(':checked');
						$el.data('columnVisibility', visibility);
						updateColumnVisibility($el);
						e.stopPropagation();
					});

				$label.append($checkbox).append(' ' + col.label);
				$menu.append($label);
			});

			$button.on('click', function (e) {
				e.stopPropagation();
				$menu.toggle();
			});

			$(document).on('click.jquerydatatable.columnselector', function () {
				$menu.hide();
			});

			$menu.on('click', function (e) {
				e.stopPropagation();
			});

			$wrapper.append($button, $menu);
			return $wrapper;
		},

		resetButton: function ($el) {
			const $btn = $('<button type="button" class="jquerydatatable-reset">Zurücksetzen</button>');

			$btn.on('click', function () {
				const settings = $el.data('settings');

				// Reset filters
				$el.data('filters', {});
				$el.find('thead input').val('');

				// Reset sort
				settings.sortColumn = '';
				settings.sortDirection = 'asc';

				// Reset page size (falls default konfiguriert, sonst 10)
				$el.data('pageSize', settings.pageSize || 10);

				// Reset pagination
				$el.data('page', 1);

				// Reset column visibility
				const visibility = {};
				settings.columns.forEach((col) => {
					visibility[col.key] = (col.visible !== undefined) ? col.visible : true;
				});
				$el.data('columnVisibility', visibility);

				// Re-render everything needed
				renderTableHeader($el);
				renderPageSizeSelector($el);
				renderColumnSelector($el);
				updateColumnVisibility($el);
				loadData($el);
			});

			return $('<div class="jquerydatatable-reset-container"></div>').append($btn);
		},

		headerCell: function (col, settings) {
			const isSorted = settings.sortColumn === col.key;
			const direction = settings.sortDirection;

			const $label = $('<span></span>').text(col.label).css('white-space', 'nowrap');

			const $indicator = $('<span style="margin-left: 4px;"></span>');
			if (isSorted) $indicator.text(direction === 'asc' ? '▲' : '▼');

			return $('<span></span>').append($label, $indicator);
		},

		filterCell: function (col, settings, $el) {
			let debounceTimeout = null;

			const $wrapper = $('<div style="position: relative; width: 100%;"></div>');
			const $input = $('<input type="text" placeholder="Filter..." style="width: 100%; box-sizing: border-box; padding-right: 18px;">');
			const $reset = $('<span title="Filter zurücksetzen" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #aaa; font-size: 12px;">✖</span>');

			$input.on('input', function () {
				const val = $(this).val();

				clearTimeout(debounceTimeout);
				debounceTimeout = setTimeout(() => {
					const filters = $el.data('filters');
					filters[col.key] = val;
					$el.data('filters', filters);
					$el.data('page', 1);
					loadData($el);
				}, 300);

				$reset.toggle(!!val);
			});

			$reset.on('click', function () {
				$input.val('').trigger('input');
			});

			$reset.hide();
			$wrapper.append($input, $reset);
			return $wrapper;
		},

		filterCellSelect: function (col, settings, $el) {
			const $select = $('<select style="width:100%; box-sizing:border-box;"></select>');
			const options = col.options || [];
			$select.append('<option value="">Alle</option>');
			options.forEach(opt => {
				$select.append(`<option value="${opt.value}">${opt.label}</option>`);
			});

			$select.on('change', function () {
				const filters = $el.data('filters');
				filters[col.key] = $(this).val();
				$el.data('filters', filters);
				$el.data('page', 1);
				loadData($el);
			});

			return $select;
		},

		valueCell: function (row, column, value) {
			return $('<span></span>').text(value ?? '');
		},

		cell: function (row, column, value, type) { 
			if (type == 'value') return $('<td></td>');
			if (type == 'filter') return $('<th></th>');
			return $('<th></th>').css('cursor', 'pointer');
		},

		row: function (row, type) {
			return $('<tr></tr>');
		}
	};

	const methods = {
		init: function (options) {
			return this.each(function () {
				const $el = $(this);
				const settings = $.extend(true, {
					dataSource: null,
					data: null,
					columns: [],
					sortColumn: '',
					sortDirection: 'asc',
					pageSizeOptions: [10, 20, 50],
					pageSize: 10,
					onRowClick: null,
					layoutTargets: {
						pager: 'footer.right',
						pageSize: 'footer.left',
						info: 'footer.center',
						resetButton: 'header.right',
						columnSelector: 'header.left'
					},
					renderers: {}
				}, options);

				settings.renderers = $.extend({}, defaultRenderers, settings.renderers);

				$el.data('settings', settings);
				$el.data('filters', {});
				$el.data('pageSize', settings.pageSize);
				$el.data('page', 1);
				$el.data('columnVisibility', settings.columns.reduce((visibility, col) => {
					visibility[col.key] = col.visible !== undefined ? col.visible : true;
					return visibility;
				}, {}));

				renderTable($el);
				loadData($el);
			});
		}
	};

	function renderTableHeader($el) {
		const settings = $el.data('settings');
		const $thead = $('<thead></thead>');

		// Header row
		const $headerRow = settings.renderers.row(null, 'header');
		settings.columns.forEach(col => {
			const $th = settings.renderers.cell(null, col, null, 'header');
			if (!$th) return;
			const $content = settings.renderers.headerCell(col, settings);
			$th.append($content);

			$th.on('click', () => {
				const newDir = (settings.sortColumn === col.key && settings.sortDirection === 'asc') ? 'desc' : 'asc';
				settings.sortColumn = col.key;
				settings.sortDirection = newDir;
				renderTableHeader($el); // Nur Header neu rendern
				loadData($el); // Neue Daten laden
			});

			$headerRow.append($th);
		});
		$thead.append($headerRow);

		// Filter row
		const $filterRow = settings.renderers.row(null, 'filter');
		settings.columns.forEach(col => {
			const $th = settings.renderers.cell(null, col, null, 'filter');
			if (!$th) return;
			const $filter = settings.renderers.filterCell(col, settings, $el);
			$th.append($filter);
			$filterRow.append($th);
		});
		$thead.append($filterRow);

		$el.find('thead').replaceWith($thead);
	}

	function renderTable($el) {
		const $wrapper = $('<div class="jquerydatatable-wrapper"></div>');

		const $header = $('<div class="jquerydatatable-header"></div>')
			.append('<div class="left"></div><div class="center"></div><div class="right"></div>');
		const $footer = $('<div class="jquerydatatable-footer"></div>')
			.append('<div class="left"></div><div class="center"></div><div class="right"></div>');

		const $table = $('<table class="jquerydatatable-table"><thead></thead><tbody></tbody></table>');

		$wrapper.append($header, $table, $footer);
		$el.empty().append($wrapper);

		renderTableHeader($el);

		renderPageSizeSelector($el);
		renderResetButton($el);

		renderColumnSelector($el);
		updateColumnVisibility($el);
	}

	function resolveLayoutTarget($el, path) {
		const [section, position] = path.split('.');
		return $el.find(`.jquerydatatable-${section} .${position}`);
	}

	function renderInfo($el, data) {
		const settings = $el.data('settings');
		const targetPath = settings.layoutTargets.info;
		const $target = resolveLayoutTarget($el, targetPath);

		$target.empty();
		const $info = settings.renderers.info($el, data);
		$target.append($info);
	}

	function renderPager($el, data) {
		const settings = $el.data('settings');
		const targetPath = settings.layoutTargets.pager;
		const $target = resolveLayoutTarget($el, targetPath);

		$target.empty();
		const $pager = settings.renderers.pager($el, data);
		$target.append($pager);
	}

	function renderPageSizeSelector($el) {
		const settings = $el.data('settings');
		const targetPath = settings.layoutTargets.pageSize;
		const $target = resolveLayoutTarget($el, targetPath);

		$target.empty();
		const $selector = settings.renderers.pageSizeSelector($el);
		$target.append($selector);
	}

	function renderResetButton($el) {
		const settings = $el.data('settings');
		const targetPath = settings.layoutTargets.resetButton;
		const $target = resolveLayoutTarget($el, targetPath);

		$target.empty();
		const $reset = settings.renderers.resetButton($el);
		$target.append($reset);
	}

	function renderColumnSelector($el) {
		const settings = $el.data('settings');
		const targetPath = settings.layoutTargets.columnSelector;
		if (!targetPath) return;

		const $target = resolveLayoutTarget($el, targetPath);
		$target.empty();

		const $selector = settings.renderers.columnSelector($el);
		$target.append($selector);
	}

	function updateColumnVisibility($el) {
		const settings = $el.data('settings');
		const visibility = $el.data('columnVisibility');
		const $table = $el.find('table');

		settings.columns.forEach((col, index) => {
			const colKey = col.key;
			const visible = visibility[colKey] !== false;

			const colIndex = index + 1;
			const selector = `th:nth-child(${colIndex}), td:nth-child(${colIndex})`;

			if (visible) {
				$table.find(selector).show();
			} else {
				$table.find(selector).hide();
			}
		});
	}

	function applyFilters(data, filters) {
		if (!filters || Object.keys(filters).length === 0) return data;

		return data.filter(row =>
			Object.entries(filters).every(([key, value]) =>
				(row[key] || '').toString().toLowerCase().includes(value.toLowerCase())
			)
		);
	}

	function applySorting(data, column, direction) {
		if (!column) return data;

		const sorted = [...data].sort((a, b) => {
			const aVal = a[column];
			const bVal = b[column];

			if (aVal < bVal) return direction === 'asc' ? -1 : 1;
			if (aVal > bVal) return direction === 'asc' ? 1 : -1;
			return 0;
		});

		return sorted;
	}

	function paginate(data, page, pageSize) {
		const start = (page - 1) * pageSize;
		const items = data.slice(start, start + pageSize);
		const totalPages = Math.ceil(data.length / pageSize);

		return { items, totalPages };
	}

	function loadData($el) {
		const settings = $el.data('settings');
		const filters = $el.data('filters');
		const pageSize = $el.data('pageSize');
		const page = $el.data('page');

		const params = {
			sort: settings.sortColumn,
			direction: settings.sortDirection,
			pageSize: pageSize,
			page: page,
			filter: filters
		};

		const render = (response) => {
			const rows = response.data || response.websites || [];
			const $tbody = $el.find('tbody');
			$tbody.empty();

			rows.forEach(row => {
				const $tr = settings.renderers.row(row, 'value');

				settings.columns.forEach(col => {
					const value = row[col.key];
					const $cell = settings.renderers.cell(row, col, value, 'value');
					if (!$cell) return;
					const $content = settings.renderers.valueCell(row, col, value);
					$cell.append($content);
					$tr.append($cell);
				});

				if (typeof settings.onRowClick === 'function') {
					$tr.css('cursor', 'pointer').on('click', () => settings.onRowClick(row));
				}

				$tbody.append($tr);
			});

			renderInfo($el, {
				total: response.total,
				page: response.page,
				pageSize: response.pageSize
			});

			renderPager($el, {
				currentPage: response.page,
				pageSize: response.pageSize,
				totalPages: response.totalPages
			});

			updateColumnVisibility($el);
		};

		if (Array.isArray(settings.data)) {
			const filtered = applyFilters(settings.data, filters);
			const sorted = applySorting(filtered, settings.sortColumn, settings.sortDirection);
			const paginated = paginate(sorted, page, pageSize);

			render({
				data: paginated.items,
				total: filtered.length,
				page: page,
				pageSize: pageSize,
				totalPages: paginated.totalPages
			});
		} else if (typeof settings.dataSource === 'string') {
			$.getJSON(settings.dataSource, params, function (response) {
				render(response);
			});
		} else {
			console.warn('No data or dataSource defined for this datatable.');
		}
	}

	$.fn.jqueryDataTable = function (method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method === 'object' || !method) {
			return methods.init.apply(this, arguments);
		} else {
			$.error('Method ' + method + ' does not exist on jqueryDataTable');
		}
	};

	$.fn.jqueryDataTable.renderers = defaultRenderers;

})(jQuery);

