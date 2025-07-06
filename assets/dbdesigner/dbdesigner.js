(function($) {
    $.fn.dbdesigner = function(options) {
        var settings = $.extend({
            onchange: function() {}
        }, options);

        var container = this;
        var tables = [];
        var foreignKeys = [];
        var contextMenu = $('<ul class="context-menu"></ul>').css({
            position: 'absolute',
            'z-index': 1000,
            display: 'none',
            background: '#fff',
            border: '1px solid #ccc',
            padding: '5px',
            listStyle: 'none',
            'line-height': 1.5,
            'font-size': '13px',
            'font-family': 'Arial, sans-serif',
            cursor: 'pointer'
        }).appendTo('body');
        var containerWidth = container.width();
        var containerHeight = container.height();

        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', containerWidth);
        svg.setAttribute('height', containerHeight);
        svg.setAttribute('viewBox', '0 0 ' + containerWidth + ' ' + containerHeight);
        svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        svg.classList.add('relationship-lines');
        container[0].appendChild(svg);
        var svgEl = $('svg', container);

    // public functions

    this.clear = function() {
        container.find('.table-box').remove();
        tables = [];
        foreignKeys = [];
        updateRelationships();
        triggerChange();
    }

    this.initializeFromData = function(data) {

        if (data.data) data.data.forEach(tableData => {
            var table = createTable(tableData.name, tableData.id, tableData.position.x, tableData.position.y);

            tableData.fields.forEach(fieldData => {
                createField(table, fieldData.name, fieldData.type);
            });

            tableData.primaryKeys.forEach(pk => {
                if (table.fields.some(f => f.name === pk)) {
                    table.primaryKeys.push(pk);
                }
            });

            renderFields(table);
        });

        if (data.foreignKeys) data.foreignKeys.forEach(link => {
            addForeignKey(link.from.tableId, link.from.fieldName, link.to.tableId, link.to.fieldName);
        });

        updateRelationships();
        triggerChange();
    }

    this.getData = function() {
        var jsonData = generateData();
        return jsonData;    
    }

   // private functions

    function triggerChange() {
        var jsonData = generateData();
        settings.onchange(jsonData);
    }

function generateData() {
    var jsonData = {
        data: tables.map(table => ({
            id: table.id,
            name: table.name,
            fields: table.fields.map(field => ({
                name: field.name,
                type: field.type
            })),
            primaryKeys: [...table.primaryKeys],
            position: { ...table.position }
        })),
        foreignKeys: foreignKeys.map(link => ({
            from: {
                tableId: link.from.tableId,
                tableName: link.from.tableName,
                fieldName: link.from.fieldName
            },
            to: {
                tableId: link.to.tableId,
                tableName: link.to.tableName,
                fieldName: link.to.fieldName
            }
        }))
    };
    return jsonData;
}



        function createField(table, name, type) {
            const existingField = table.fields.find(f => f.name === name);
            if (existingField) return;
            table.fields.push({ name: name, type: type });
        }

        function createTable(name, id, x, y) {

            const existingTable = tables.find(t => t.id === id);
            if (existingTable) return existingTable;

            var table = {
                id: id,
                name: name,
                fields: [],
                primaryKeys: [],
                position: { x: x, y: y }
            };

            var $table = $('<div class="table-box"></div>').css({
                position: 'absolute',
                left: x + 'px',
                top: y + 'px',
                'min-width': '100px',
                border: '1px solid black',
                background: '#f9f9f9',
                cursor: 'pointer'
            }).data('tableid', table.id).appendTo(container).draggable({
                containment: "parent",
                handle: ".table-title",
                drag: function(event, ui) {
                    updateRelationships();
                },
                stop: function(event, ui) {
                    table.position.x = ui.position.left;
                    table.position.y = ui.position.top;
                    updateRelationships();
                    triggerChange();
                }
            });

            var $title = $('<div class="table-title"></div>').css({
                padding: '4px 5px',
                background: '#ddd',
                'font-weight': 'bold',
                'font-size': '14px'
            }).text(name).appendTo($table).on("contextmenu", function(e) {
                e.preventDefault();
                showTableContextMenu(e.pageX, e.pageY, table, $table);
            });

            var $fields = $('<ul class="table-fields"></ul>').css({
                padding: 0,
                margin: 0,
                'list-style': 'none'
            }).appendTo($table).sortable({

stop: function(event, ui) {
    var newFields = [];
    var fieldMap = {}; // Speichert die neuen Feldobjekte zur Zuordnung

    $fields.children().each(function(index, el) {
        var fieldName = $(el).data("fieldname");
        var fieldObj = table.fields.find(f => f.name === fieldName);
        if (fieldObj) {
            fieldObj.$element = $(el); // Neues $element setzen
            fieldMap[fieldObj.name] = fieldObj;
            newFields.push(fieldObj);
        }
    });

    table.fields = newFields;

    // Foreign-Key-Elemente aktualisieren
    foreignKeys.forEach(link => {
        if (link.from.tableId === table.id && fieldMap[link.from.fieldName]) {
            link.from.$element = fieldMap[link.from.fieldName].$element;
        }
        if (link.to.tableId === table.id && fieldMap[link.to.fieldName]) {
            link.to.$element = fieldMap[link.to.fieldName].$element;
        }
    });

    updateRelationships();
    triggerChange();
}

            });

            table.$element = $table;
            table.$fields = $fields;
            tables.push(table);

            return table;
        }

function updateForeignKeyElements() {
    foreignKeys.forEach(link => {
        let fromTable = tables.find(t => t.id === link.from.tableId);
        let toTable = tables.find(t => t.id === link.to.tableId);

        if (fromTable) {
            let fromField = fromTable.fields.find(f => f.name === link.from.fieldName);
            if (fromField) {
                link.from.$element = fromField.$element;
            } else {
                console.warn(`⚠ Foreign Key von ${link.from.fieldName} nicht gefunden.`);
            }
        }

        if (toTable) {
            let toField = toTable.fields.find(f => f.name === link.to.fieldName);
            if (toField) {
                link.to.$element = toField.$element;
            } else {
                console.warn(`⚠ Foreign Key zu ${link.to.fieldName} nicht gefunden.`);
            }
        }
    });
}

function addForeignKey(fromTableId, fromFieldName, toTableId, toFieldName) {

    if (fromTableId == toTableId && fromFieldName == toFieldName) return;

    const existingFk = foreignKeys.find(fk =>
        (fk.from.tableId === fromTableId && fk.from.fieldName === fromFieldName && fk.to.tableId === toTableId && fk.to.fieldName === toFieldName)
        || (fk.from.tableId === toTableId && fk.from.fieldName === toFieldName && fk.to.tableId === fromTableId && fk.to.fieldName === fromFieldName));
    if (existingFk) return;
 
    var fromTable = tables.find(t => t.id === fromTableId);
    var toTable = tables.find(t => t.id === toTableId);

    if (!fromTable || !toTable) return;

    var fromField = fromTable.fields.find(f => f.name === fromFieldName);
    var toField = toTable.fields.find(f => f.name === toFieldName);

    if (!fromField || !toField) return;

    foreignKeys.push({
        from: {
            tableId: fromTable.id,
            tableName: fromTable.name,
            fieldName: fromField.name,
            $element: fromField.$element
        },
        to: {
            tableId: toTable.id,
            tableName: toTable.name,
            fieldName: toField.name,
            $element: toField.$element
        }
    });

    updateForeignKeyElements();
}

function removeForeignKeysForField(tableId, fieldName) {
    // Filtere alle ForeignKeys heraus, die dieses Feld enthalten
    foreignKeys = foreignKeys.filter(link => {
        const isFromDeleted = link.from.tableId === tableId && link.from.fieldName === fieldName;
        const isToDeleted = link.to.tableId === tableId && link.to.fieldName === fieldName;

        if (isFromDeleted || isToDeleted) {
            console.warn(`🗑 Entferne Foreign Key von ${link.from.tableName}.${link.from.fieldName} zu ${link.to.tableName}.${link.to.fieldName}`);
            return false; // Entfernen
        }

        return true; // Behalten
    });

    // Aktualisiere die Darstellung der Beziehungen
    updateRelationships();
    triggerChange();
}

function removeForeignKeysForTable(tableId) {
    // Filtere alle ForeignKeys heraus, die diese Tabelle betreffen
    foreignKeys = foreignKeys.filter(link => {
        const isFromDeletedTable = link.from.tableId === tableId;
        const isToDeletedTable = link.to.tableId === tableId;

        if (isFromDeletedTable || isToDeletedTable) {
            console.warn(`🗑 Entferne Foreign Key von ${link.from.tableName}.${link.from.fieldName} zu ${link.to.tableName}.${link.to.fieldName}`);
            return false; // Entfernen
        }

        return true; // Behalten
    });

    // Aktualisiere die Darstellung der Beziehungen
    updateRelationships();
    triggerChange();
}

function renderFields(table) {
    table.$fields.empty();
    table.fields.forEach((field, index) => {
        var $fieldItem = $('<li class="field-item"></li>')
            .css({
                margin: 0,
                padding: '5px',
                borderBottom: '1px solid #ddd',
                cursor: 'grab'
            })
            .attr('data-fieldname', field.name)  // Hier setzen wir das Attribut
            .appendTo(table.$fields)
            .on("contextmenu", function(e) {
                e.preventDefault();
                field.$element = $fieldItem; // Ensure the element is set
                showFieldContextMenu(e.pageX, e.pageY, table, field, index);
            });

        field.$element = $fieldItem; // Assign $element properly

        if (table.primaryKeys.includes(field.name)) {
            $fieldItem.css("font-weight", "bold");
        }

        $('<div class="field-name"></div>').text(field.name).css({
            'font-size': '13px'
        }).appendTo($fieldItem);
        $('<div class="field-type"></div>').text(field.type).css({
            'font-size': '11px',
            'text-align': 'right',
            color: '#666'
        }).appendTo($fieldItem);
    });

    updateForeignKeyElements();
}


        function updateRelationships() {
            svgEl.empty();

            var containerOffset = container.offset();

            foreignKeys.forEach(link => {
                var $from = $(link.from.$element);
                var $to = $(link.to.$element);
                var fromField = $from.offset();
                var toField = $to.offset();
                
                if (!fromField || !toField) return;

                var fromX = fromField.left - containerOffset.left + $from.width() / 2;
                var fromY = fromField.top - containerOffset.top + $from.height() / 2;
                var toX = toField.left - containerOffset.left + $to.width() / 2;
                var toY = toField.top - containerOffset.top + $to.height() / 2;

                var fromDir = toX > fromX ? 200 : -200;
                var toDir = fromDir * ( Math.abs(fromX - toX) < Math.max($from.width(), $to.width()) ? 1 : -1 );
 
                var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', `M ${fromX} ${fromY} C ${fromX + fromDir} ${fromY}, ${toX + toDir} ${toY}, ${toX} ${toY}`);
                path.setAttribute('stroke', '#333');
                path.setAttribute('fill', 'transparent');
                path.setAttribute('stroke-width', '1');
                svg.appendChild(path);

            });
        }


        function showGlobalContextMenu(x, y) {
            contextMenu.empty();
            $('<li>Tabelle erstellen</li>').appendTo(contextMenu).on('click', function() {
                var tableName = prompt("Tabellenname eingeben:");
                if (tableName) {
                    var containerOffset = container.offset(); 
                    var relativeX = x - containerOffset.left;
                    var relativeY = y - containerOffset.top;
                    createTable(tableName, Date.now(), relativeX, relativeY);

                    updateRelationships();
                    triggerChange();
                }
                contextMenu.hide();
            });

            contextMenu.css({
                top: y + 'px',
                left: x + 'px',
                display: 'block'
            });
        }

        function showTableContextMenu(x, y, table, $table) {
            contextMenu.empty();
            $('<li>Tabellenname ändern</li>').appendTo(contextMenu).on('click', function() {
                var newName = prompt("Neuer Tabellenname:", table.name);
                if (newName) {
                    table.name = newName;
                    $table.find(".table-title").text(newName);
                    triggerChange();
                }
                contextMenu.hide();
            });

            $('<li>Feld hinzufügen</li>').appendTo(contextMenu).on('click', function() {
                var fieldName = prompt("Feldname eingeben:");
                if (fieldName) {
                    createField(table, fieldName, "TEXT");
                    // table.fields.push({ name: fieldName, type: "TEXT" });
                    renderFields(table);
                    triggerChange();
                }
                contextMenu.hide();
            });

            $('<li>Tabelle löschen</li>').appendTo(contextMenu).on('click', function() {
                $table.remove();
                tables = tables.filter(t => t.id !== table.id);

                removeForeignKeysForTable(table.id);

                updateRelationships();
                triggerChange();

                contextMenu.hide();
            });

            contextMenu.css({
                top: y + 'px',
                left: x + 'px',
                display: 'block'
            });
        }

        function showFieldContextMenu(x, y, table, field, index) {
            contextMenu.empty();

            $('<li>Feld hinzufügen</li>').appendTo(contextMenu).on('click', function() {
                var fieldName = prompt("Feldname eingeben:");
                if (fieldName) {
                    createField(table, fieldName, "TEXT");
                    // table.fields.push({ name: fieldName, type: "TEXT" });
                    renderFields(table);
                    triggerChange();
                }
                contextMenu.hide();
            });

            $('<li>Feldname ändern</li>').appendTo(contextMenu).on('click', function() {
                var newName = prompt("Neuer Feldname:", field.name);
                if (newName) {
                    field.name = newName;
                    renderFields(table);
                    triggerChange();
                }
                contextMenu.hide();
            });

            $('<li>Feldtyp ändern</li>').appendTo(contextMenu).on('click', function() {
                var newType = prompt("Neuer Feldtyp:", field.type);
                if (newType) {
                    field.type = newType;
                    renderFields(table);
                    triggerChange();
                }
                contextMenu.hide();
            });

            if (table.primaryKeys.includes(field.name)) {
                $('<li>Primary Key entfernen</li>').appendTo(contextMenu).on('click', function() {
                    table.primaryKeys = table.primaryKeys.filter(pk => pk !== field.name);
                    renderFields(table);
                    triggerChange();
                    contextMenu.hide();
                });
            } else {
                $('<li>Als Primary-Key definieren</li>').appendTo(contextMenu).on('click', function() {
                    table.primaryKeys.push(field.name);
                    renderFields(table);
                    triggerChange();
                    contextMenu.hide();
                });
            }

            $('<li>Feld löschen</li>').appendTo(contextMenu).on('click', function() {
                table.fields.splice(index, 1);
                renderFields(table);

                removeForeignKeysForField(table.id, field.name);

                updateRelationships();
                triggerChange();

                contextMenu.hide();
            });


            $('<li>Als Fremdschlüssel definieren</li>').appendTo(contextMenu).on('click', function() {
                contextMenu.hide();

                var fromTableId = table.id;
                var fromFieldName = field.name;

                $(document).on('click', '.field-item', function selectForeignKey() {
                    var $targetField = $(this);
                    var toFieldName = $targetField.data("fieldname");

                    var $parentTable = $targetField.closest('.table-box'); // Tabelle finden
                    var toTableId = $parentTable.data("tableid"); // Tabellen-ID extrahieren

                    addForeignKey(fromTableId, fromFieldName, toTableId, toFieldName);

                    $(document).off('click', '.field-item', selectForeignKey);

                    updateRelationships();
                    triggerChange();
                });
            });

            $('<li>Fremdschlüssel löschen</li>').appendTo(contextMenu).on('click', function() {
                contextMenu.hide();

                var fieldName = field.name;  // 'field' ist das aktuelle Feld, auf dem der Kontextmenüpunkt geklickt wurde
                var tableId = table.id;      // 'table' ist die aktuelle Tabelle des Feldes

                foreignKeys = foreignKeys.filter(link => {
                    const isFromField = link.from.tableId === tableId && link.from.fieldName === fieldName;
                    const isToField = link.to.tableId === tableId && link.to.fieldName === fieldName;
                    return !(isFromField || isToField);
                });

                updateRelationships();
                triggerChange();

            });


            contextMenu.css({
                top: y + 'px',
                left: x + 'px',
                display: 'block'
            });
        }

        $(document).on('click', function() {
            contextMenu.hide();
        });

        container.on("contextmenu", function(e) {
            e.preventDefault();
            if (!$(e.target).closest('.table-box').length) {
                showGlobalContextMenu(e.pageX, e.pageY);
            }
        });

        return this;
    };
}(jQuery));

