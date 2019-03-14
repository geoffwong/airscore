
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_task_result.php' + window.location.search);
    var comPk = url.searchParams.get("comPk");
    var tasPk = url.searchParams.get("tasPk");
    $('#task_result').dataTable({
        ajax: 'get_task_result.php?comPk='+comPk+'&tasPk='+tasPk,
        paging: false,
        searching: true,
        info: false,
        "dom": 'lrtip',
        "columnDefs": [
            {
                "targets": [ 4 ],
                "visible": false
            },
            {
                "targets": [ 3 ],
                "orderData": [ 4, 0 ]
            }
        ],
        "initComplete": function(settings, json) 
        {
            var table= $('#task_result');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length;

            // comp info
            $('#comp_name').text(json.task.comp_name + " - " + json.task.task_name);
            $('#task_date').text(json.task.date + ' ' + json.task.task_type);
            $('#comp_header').append('<b>Start: ' + json.task.start + ' End: ' + json.task.end + '</b><br>');
            if (json.task.stopped)
            {
                $('#comp_header').append('<b>Stopped: ' + json.task.stopped + '</b><br>');
                $('#altbonus').text("S.Alt");

            }
            if (json.task.comp_class != "PG")
            {
                update_classes(json.task.comp_class);
            }
        
            // waypoints
            for (var c=0; c < json.task.waypoints.length; c++)
            {
                $('#waypoints tbody').append("<tr><td>" + json.task.waypoints[c].rwpName + 
                        "</td><td>" + json.task.waypoints[c].tawType + 
                        "</td><td>" + json.task.waypoints[c].tawRadius + 
                        "</td><td>" + Number((json.task.waypoints[c].ssrCumulativeDist/1000).toFixed(1)) +
                        "</td><td>" + json.task.waypoints[c].rwpDescription +
                        "</td></tr>");
            }

            // task info 
            var half = Object.keys(json.formula).length / 2;
            var count = 0;
            $.each( json.formula, function( key, value ) {
                if (count < half)
                {
                    $('#formula1 tbody').append('<tr><td>' + key + '</td><td>' + value + '</td></tr>');
                }
                else
                {
                    $('#formula2 tbody').append('<tr><td>' + key + '</td><td>' + value + '</td></tr>');
                }
                count++;
            });

            $.each( json.metrics, function( key, value ) {
                $('#taskinfo tbody').append('<tr><td>' + key + '</td><td>' + value + '</td></tr>');
            });

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

