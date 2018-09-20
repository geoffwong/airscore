
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_result.php' + window.location.search);
    var comPk = url.searchParams.get("comPk");
    $('#task_result').dataTable({
        ajax: 'get_result.php?comPk='+comPk,
        paging: false,
        searching: false,
        info: false,
        "columnDefs": [
            {
                "targets": [ 1, 2 ],
                "visible": false
            },
        ],
        "initComplete": function(settings, json) 
        {
            var table= $('#task_result');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length+1;

            // comp info
            $('#comp_name').text(json.compinfo.comName);
            $('#comp_date').text(json.compinfo.comDateFrom + ' - ' + json.compinfo.comDateTo);
        
            // some GAP parameters
            $('#formula tbody').append(
                        "<tr><td>Director</td><td>" + json.compinfo.comMeetDirName + '</td></tr>' +
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

