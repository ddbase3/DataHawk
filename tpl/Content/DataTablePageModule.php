		<section>
			<div class="frame">
				<div id="reportDatatable"></div>
			</div>
		</section>

<script>

document.addEventListener('DOMContentLoaded', () => {
    urlDataTable = 'base3.php?name=coursereportconnector&out=json';

    (async () => {
	await AssetLoader.loadCssAsync('components/Base3/DataHawk/jquerydatatable/jquerydatatable.css');
	await AssetLoader.loadScriptAsync('components/Base3/DataHawk/jquerydatatable/jquerydatatable.js');

	console.log('JqueryDataTable loaded');

        var columns = [
                { key: '_usr_data_last_login', label: 'Last login', visible: false },
                { key: '_usr_data_last_login_de_date', label: 'Last login (de)' },
                { key: '_usr_data_login', label: 'Login' },
                { key: '_usr_data_firstname', label: 'First name' },
                { key: '_usr_data_lastname', label: 'Last name' },
                { key: '_usr_data_fullname_de', label: 'Full name', visible: false },
                { key: '_usr_data_email', label: 'Email', visible: false },
                { key: '_usr_data_active', label: 'Active', visible: false },
                { key: '_usr_data_active_de', label: 'Active (de)' },
                { key: '_object_data_type', label: 'Object type', visible: false },
                { key: '_object_data_title', label: 'Title' },
                { key: '_crs_settings_contact_email', label: 'Contact mail' },
                { key: '_ut_lp_marks_status', label: 'Marks status', options: [
                        { value: '0', label: '🔴 Fail' },
                        { value: '1', label: '🟡 Intermediate' },
                        { value: '2', label: '🟢 Pass' },
                        { value: 'x', label: '❌ No info' }
                ] },
                { key: '_ut_lp_marks_percentage', label: 'Marks percentage' }
        ];

        var cellRenderer = function (row, column, value, type) {
                switch (column.key) {
                        case '_usr_data_last_login':
                        case '_usr_data_last_login_de_date':
                                if (value || type != 'value') return $.fn.jqueryDataTable.renderers.cell(row, column, value, type);
                                return $('<td style="background-color:#c99;">');
                        case '_usr_data_login':
                        case '_usr_data_firstname':
                        case '_usr_data_lastname':
                        case '_usr_data_fullname_de':
                        case '_usr_data_email':
                                if (type != 'value') return $.fn.jqueryDataTable.renderers.cell(row, column, value, type);
                                return $('<td style="background-color:#fff;">');
                        case '_ut_lp_marks_status':
                                if (type != 'value') return $.fn.jqueryDataTable.renderers.cell(row, column, value, type);
                                return $('<td style="text-align:center;">');
                        case '_ut_lp_marks_percentage':
                                if (type != 'value') return $.fn.jqueryDataTable.renderers.cell(row, column, value, type);
                                return $('<td style="text-align:right;">');
                }
                return $.fn.jqueryDataTable.renderers.cell(row, column, value, type);
        }

        var valueRenderer = function(row, column, value) {
                switch (column.key) {
                        case '_usr_data_email':
                        case '_crs_settings_contact_email':
                                if (!value || !value.length || value == 'nomail') return $('<span>❌</span>');
                                return $('<a href="mailto:' + value + '">' + value + '</a>');
                        case '_ut_lp_marks_status':
                                if (value == 0) return '🔴';
                                if (value == 1) return '🟡';
                                if (value == 2) return '🟢';
                                return $('<span>');
                        case '_ut_lp_marks_percentage':
                                return value ? value + '%' : '';
                }
                return $.fn.jqueryDataTable.renderers.valueCell(row, column, value);
        };

        var filterRenderer = function (col, settings, $el) {
                switch (col.key) {
                        case '_ut_lp_marks_status':
                                return $.fn.jqueryDataTable.renderers.filterCellSelect(col, settings, $el);
                }
                return $.fn.jqueryDataTable.renderers.filterCell(col, settings, $el);
        }

        $('#reportDatatable').jqueryDataTable({
                dataSource: urlDataTable,
                columns: columns,
                sortColumn: '_usr_data_login',
                sortDirection: 'asc',
                pageSize: 10,
                layoutTargets: {
                        pager: 'header.right',
                        pageSize: 'footer.right',
                        info: 'footer.center',
                        resetButton: 'footer.left',
                        columnSelector: 'header.left'
                },
                renderers: {
                        pager: $.fn.jqueryDataTable.renderers.compactPager,
                        valueCell: valueRenderer,
                        cell: cellRenderer,
                        filterCell: filterRenderer
                },
                onRowClick: function(row) {
                        console.log('Zeile geklickt:', row);
                }
	});

    })();

});

</script>

<style>
	#reportDatatable { overflow:auto; }
	#reportDatatable * { font-size:10px; }
	#reportDatatable td, #reportDatatable th { padding:3px; }
</style>

