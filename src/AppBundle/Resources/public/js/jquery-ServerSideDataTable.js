(function ($) {

    $.ServerSideDataTable = function (url, dataTableOptions, extraOptions) {

        /***
         * @var DataTable
         */
        var table;

        var dataTableSettings = $.extend($.ServerSideDataTable.dataTableDefaults, dataTableOptions);
        var settings = $.extend($.ServerSideDataTable.extraOptionsDefaults, extraOptions);

        if (dataTableSettings.order === undefined) {
            alert('ServerSideDataTable WARNING: No se ha definido un orden para esta tabla');
        }

        dataTableSettings.ajax = url;

        // Muestra o esconde la búsqueda global
        if (!settings.globalSearch) {
            dataTableSettings.dom = dataTableSettings.dom.replace('f', '');
        }

        if (settings.extraData) {

            var firstColumn = {
                "class": "details-control",
                "orderable": false,
                "data": null,
                "defaultContent": ""
            };

            dataTableSettings.columns.unshift(firstColumn);
        }

        if (settings.rowActions) {
            var actionColumn = {
                "data": "actions",
                "render": $.ServerSideDataTable.rowActionsFormatHtml
            };

            dataTableSettings.columns.push(actionColumn);
        }

        if (settings.downloadCsv) {
            dataTableSettings.dom = dataTableSettings.dom.replace('i', 'iB');

            if (dataTableSettings.buttons === undefined) {
                dataTableSettings.buttons = [];
            }

            var csvButton = {
                text: 'Descargar CSV',
                action: $.ServerSideDataTable.downloadCsv
            };

            dataTableSettings.buttons.unshift(csvButton);
        }

        table = $(settings.selector).DataTable(dataTableSettings);

        $(settings.selector).on('init.dt', function () {

            // Agrega busqueda global
            if (settings.globalSearch) {
                $.ServerSideDataTable.addGlobalSearch(table, dataTableSettings, settings);
            }

            // Agrega filtros por cada columns
            if (settings.columnSearch) {
                $.ServerSideDataTable.addColumnSearch(table, dataTableSettings, settings);
            }

            // Valida que si se activa la opción de datos extras por fila entonces se deba implementar el
            // método que retorna los datos formateados
            if (settings.extraData) {

                if ($.ServerSideDataTable.extraDataFormatHtml === null) {
                    alert('ServerSideDataTable ERROR: Es necesario implementar el método "$.ServerSideDataTable.extraDataFormatHtml = function(data){}" para dar el formato a los datos extras de la tabla');
                }

                $.ServerSideDataTable.addExtraData(table, dataTableSettings, settings);

            }

            // Aplica una clase CSS a los inputs que contienen textos cargados
            $(settings.selector + ' > thead td input, .dataTables_wrapper input[type=search]').on('change', function () {
                var $this = $(this);

                if ($this.val() != '') {
                    $this.addClass('filter-applied');
                } else {
                    $this.removeClass('filter-applied');
                }
            });

        });

        return this;

    };

    // Retorna el HTML que será usado para mostrar los inputs de filtros por columna
    $.ServerSideDataTable.getColumnFilterHtml = function (title) {
        return '<input type="text" size="13" placeholder="' + title + '" />';
    };

    // Agrega filtros por columna agregando un delay durante la escritura
    $.ServerSideDataTable.addColumnSearch = function (table, dataTableSettings, settings) {

        $(settings.selector + ' thead td').each(function () {
            var title = $(this).text();

            if (title) {
                $(this).html($.ServerSideDataTable.getColumnFilterHtml(title));
            }
        });

        // Ejecutar filtros por columna con un delay
        var search_timeout = undefined;

        // Uso keydown porque keyup detecta el input al que va el cursor y con keypress no se detecta el tab
        // ref: http://howtodoinjava.com/scripting/jquery/jquery-difference-between-keypress-and-keydown-events/
        $(settings.selector + ' thead input')
            .on('keydown', function (e) {

                if (search_timeout != undefined) {
                    clearTimeout(search_timeout);
                }

                var $this = this;

                search_timeout = setTimeout(function () {
                    search_timeout = undefined;

                    var column = $($this).parent().index();

                    table
                        .column(column + ':visible')
                        .search($this.value)
                        .draw();

                }, dataTableSettings.searchDelay);
            });

    };

    // Sobreescribe la funcionalidad de búsqueda global para agregar un delay durante la escritura
    $.ServerSideDataTable.addGlobalSearch = function (table, dataTableSettings, settings) {

        var global_search_timeout = undefined;

        $('.dataTables_filter input')
            .off()
            .on('keyup', function (e) {

                if (global_search_timeout != undefined) {
                    clearTimeout(global_search_timeout);
                }

                var $this = this;

                global_search_timeout = setTimeout(function () {
                    search_timeout = undefined;

                    table.search($this.value).draw();


                }, dataTableSettings.searchDelay);
            });

    };

    $.ServerSideDataTable.extraDataFormatHtml = null;

    $.ServerSideDataTable.addExtraData = function (table, dataTableSettings, settings) {
        // Array to track the ids of the details displayed rows
        var detailRows = [];

        $(settings.selector + ' tbody').on('click', 'tr td.details-control', function () {
            var tr = $(this).closest('tr');
            var row = table.row(tr);
            var idx = $.inArray(tr.attr('id'), detailRows);

            if (row.child.isShown()) {
                tr.removeClass('details');
                row.child.hide();

                // Remove from the 'open' array
                detailRows.splice(idx, 1);
            }
            else {
                tr.addClass('details');

                var extraData = $.ServerSideDataTable.extraDataFormatHtml(row.data());

                if (settings.extraDataActions) {
                    extraData += $.ServerSideDataTable.extraDataActionsFormatHtml(settings, row.data());
                }

                row.child(extraData).show();

                // Add to the 'open' array
                if (idx === -1) {
                    detailRows.push(tr.attr('id'));
                }
            }
        });

        // On each draw, loop over the `detailRows` array and show any child rows
        table.on('draw', function () {
            $.each(detailRows, function (i, id) {
                $('#' + id + ' td.details-control').trigger('click');
            });
        });
    };

    $.ServerSideDataTable.downloadCsv = function (e, dt, node, config) {
        var url = dt.ajax.url() + '?' + $.param(dt.ajax.params()) + '&download=csv';

        var iframe = document.createElement('iframe');
        iframe.style.height = "0px";
        iframe.style.width = "0px";
        iframe.src = url;
        document.body.appendChild(iframe);
    };

    $.ServerSideDataTable.extraDataActionsFormatHtml = function (settings, data) {
        var ret = '<hr />' +
            '<div class="row">' +
            '   <div class="col-md-11">';

        $.each(data.actions, function (index, action) {
            ret += '<a class="extra-data-actions" href="' + action.url + '">' + action.text + '</a>'
        });

        ret += '    </div>' +
            '</div>';

        return ret;
    };

    $.ServerSideDataTable.rowActionsFormatHtml = function (actions, type, full, meta) {

        // Obtengo la cantidad de acciones
        var count = Object.keys(actions).length;
        console.log(actions);
        console.log(count);

        // Obtengo la primera acción y la elimino del grupo
        var firstAction = actions[Object.keys(actions)[0]];
        //delete actions[Object.keys(actions)[0]];

        var ret = '';

        console.dir(firstAction);
        //console.dir(actions);

        if(count === 1){
            ret = '<a href="'+ firstAction.url +'" class="btn btn-default">'+ firstAction.text +'</a>';
        }
        else if(count > 1){
            ret =
                '<div class="btn-group">' +
                '   <a href="'+ firstAction.url +'" class="btn btn-default">'+ firstAction.text +'</a>' +
                '   <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' +
                '      <span class="caret"></span>' +
                '      <span class="sr-only">Toggle Dropdown</span>' +
                '   </button>' +
                '   <ul class="dropdown-menu">';


            $.each(actions, function (index, action) {
                ret += '      <li><a href="'+ action.url +'">'+ action.text +'</a></li>';
            });

            ret += '   </ul>' +
                '</div>';
        }

        return ret;
    };

    // Configuraciones por default del plugin
    $.ServerSideDataTable.extraOptionsDefaults = {
        selector: '.datatable',
        globalSearch: false,
        columnSearch: true,
        extraData: false,
        downloadCsv: true,
        extraDataActions: false,
        rowActions: false
    };

    // Configuraciones por default del DataTable
    $.ServerSideDataTable.dataTableDefaults = {
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
        processing: true,
        serverSide: true,
        stateSave: false, // La tabla guarda su estado durante los request
        stateDuration: 60, // El estado de la tabla se guarda usando local storage (HTML5 API) durante 60 segundos
        searchDelay: 700,
        pagingType: "full_numbers",
        colReorder: true,
        language: {
            url: null
        }
    };

}(jQuery));