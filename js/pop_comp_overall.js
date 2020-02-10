
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_result.php' + window.location.search);
    var comPk = url.searchParams.get("comPk");
    var flyclass = url_parameter("class");
    if (flyclass)
    {
        $('#dhv').val(flyclass);
    }
    $('#task_result').dataTable({
        ajax: 'get_result.php?comPk='+comPk,
        paging: false,
        searching: true,
        saveState: true,
        info: false,
        "dom": 'lrtip',
        "columnDefs": [
            {
                "targets": [ 1, 2, 6, 8 ],
                "visible": false 
            },
            //{ "type" : "numeric", "targets": [ 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25 ] }
        ],
        "initComplete": function(settings, json) 
        {
            var table= $('#task_result');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length+3;

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
                        "<tr><td>Formula</td><td>" + json.compinfo.forClass + ' ' + json.compinfo.forVersion + '</td></tr>' +
                        "<tr><td>Overall Scoring</td><td>" + json.compinfo.comOverallScore + ' (' + json.compinfo.comOverallParam + ')</td></tr>');
            if (json.compinfo.comOverallScore == 'ftv')
            {
                $('#formula tbody').append(
                        "<tr><td>Total Validity</td><td>" + json.compinfo.TotalValidity+ '</td></tr>');
            }

            // remove empty cols
            for ( var i=1; i<=numCols; i++ ) 
            {
                var empty = true;
                table.DataTable().column(i).data().each( function (e, i) {
                    if (e != "")
                    {
                        empty = false;
                        return false;
                    }
                } );

                if (empty) {
                    table.DataTable().column( i ).visible( false ); 
                }
            }
        }
    });
});

