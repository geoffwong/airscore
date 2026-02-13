
function members(data)
{
    //console.log('pilot data:', data);
    result = '';
    for (pilot in data)
    {
        result = result + '<tr class="blue-text"><td></td><td>' + pilot + '</td><td></td>';
        for (tscore in data[pilot])
        {
            result = result + '<td>' + data[pilot][tscore] + '</td>';
        }
        result = result + '</tr>';
    }
    return result;
}

$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_team_result.php' + window.location.search);
    var comPk = url.searchParams.get("comPk");
    var flyclass = url_parameter("class");
    if (flyclass)
    {
        $('#dhv').val(flyclass);
    }
    $.fn.DataTable.ext.errMode = function (settings, techNote, message) {
        const tableId = settings.nTable.getAttribute('id');
        if (tableId === 'team_result' && /Requested unknown parameter/.test(message)) {
            return;
        }
        console.error('DataTables error:', { tableId, message, techNote });
    };
    var table = new DataTable("#team_result", {
        ajax: 'get_team_result.php?comPk='+comPk,
        paging: false,
        searching: true,
        saveState: true,
        info: false,
        "dom": 'lrtip',
        columns: [
            {
                className: 'dt-control',
                orderable: false,
                visible: true,
                data: 'position'
            },
            { data: 'name', className: 'dt-left dt-bold', visible: true,  },
            { data: 'total', className: 'dt-left dt-bold', visible: true },
            { data: 'T1', className: 'dt-left' },
            { data: 'T2', className: 'dt-left' },
            { data: 'T3', className: 'dt-left' },
            { data: 'T4', className: 'dt-left' },
            { data: 'T5', className: 'dt-left' },
            { data: 'T6', className: 'dt-left' },
            { data: 'T7', className: 'dt-left' },
            { data: 'T8', className: 'dt-left' },
            { data: 'T9', className: 'dt-left' },
            { data: 'T10', className: 'dt-left' },
            { data: 'T11', className: 'dt-left' },
            { data: 'T12', className: 'dt-left' },
            { data: 'T13', className: 'dt-left' },
            { data: 'T14', className: 'dt-left' },
        ],
        "initComplete": function(settings, json) 
        {
            var trtable= $('#team_result');
            var rows = $("tr", trtable).length-1;
            var numCols = $("th", trtable).length4;

            // comp info
            $('#comp_name').text(json.compinfo.comName);
            $('#comp_date').text(json.compinfo.comDateFrom + ' - ' + json.compinfo.comDateTo);
            if (json.compinfo.comClass != "PG")
            {
                update_classes(json.compinfo.comClass);
            }
        
            // some GAP parameters
            $('#formula tbody').append(
                        "<tr><td>Director</td><td>" + json.compinfo.comMeetDirName + '</td></tr>' +
                        "<tr><td>Location</td><td>" + json.compinfo.comLocation + '</td></tr>' +
                        "<tr><td>Formula</td><td>" + json.compinfo.forClass + ' ' + json.compinfo.forVersion + '</td></tr>');

            if (json.compinfo.comOverallScore == 'ftv')
            {
                $('#formula tbody').append(
                    "<tr><td>Team Scoring</td><td>" + json.compinfo.comTeamScoring + ' (' + json.compinfo.comTeamSize + ')</td></tr>');
                $('#formula tbody').append(
                        "<tr><td>Total Validity</td><td>" + json.compinfo.TotalValidity+ '</td></tr>');
            }
            else
            {
                $('#formula tbody').append(
                    "<tr><td>Team Scoring</td><td>" + json.compinfo.comTeamScoring + ' (' + json.compinfo.comTeamSize + ')</td></tr>');
            }

            if (json.compinfo.forVersion == 'airgain-count' || json.compinfo.forVersion == 'airgain')
            {
                $('#formula tbody').append(
                        '<tr><td><a href="waypoint_map.html?regPk=' + json.compinfo.regPk + '" class="btn btn-secondary btn-sm">Waypoints</a></td></tr>');
            }

            // remove empty cols
            var index = 0;
            table.columns().every(function () {
                // 'this' is the API context for the current column
                var empty = true;
                var column = this;
                var data = column.data();

                // Example: log each cell's data in the column
                //console.log('Column data:', data.toArray());

                // You can also work with nodes, headers, footers, etc.
                // var header = $(column.header()).html();
                data.each( function (e, i) {
                    if (e != null)
                    {
                        empty = false;
                        return false;
                    }
                });

                if (empty && index > 0) {
                    column.visible( false ); 
                }

                index++;
            });
        }
    });

    table.on('click', 'tbody td.dt-control', function (e) {
        let tr = $(this);
        let row = table.row(tr);
 
        if (row.child.isShown()) {
            // This row is already open - close it
            row.child.hide();
        }
        else {
            // Open this row
            row.child($(members(row.data().pilots))).show();
        }
    });
});

