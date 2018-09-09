
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
                "targets": [ 4 ],
                "visible": true
            },
        ],
        "initComplete": function(settings, json) 
        {
            var table= $('#task_result');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length;

            // comp info
            //$('#comp_name').text(json.task.comp_name);
            //$('#task_date').text(json.task.date);
        
            // waypoints
            //for (var c=0; c < json.task.waypoints.length; c++)
            //{
            //    $('#waypoints tbody').append("<tr><td>" + json.task.waypoints[c].rwpName + 
            //            "</td><td>" + json.task.waypoints[c].tawType + 
            //            "</td><td>" + json.task.waypoints[c].tawRadius + 
            //            "</td><td>" + Number((json.task.waypoints[c].ssrCumulativeDist/1000).toFixed(1)) +
            //            "</td><td>" + json.task.waypoints[c].rwpDescription +
            //            "</td></tr>");
            //}

            // task info 

            // remove empty cols
            for ( var i=1; i<=numCols; i++ ) {
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

