function result_done(data)
{
    console.log(data);
    var table = $('#task_result').DataTable();
    table.ajax.reload();
    $('#reloadspin').hide();
}
function delete_result()
{
    var tasPk = url_parameter("tasPk");
    var comPk = url_parameter("comPk");
    var tarpk = $("input[name='tarpk']").val();

    $('#reloadspin').show();
    $.post("delete_task_result.php", { 'tasPk' : tasPk, 'comPk' : comPk, 'tarpk' : tarpk }, result_done);
}
function add_result()
{
    $('#addition').show();
    $("#resultmodal").modal("show");
    $('#tarpk').val('');
}
function update_result()
{
    var tasPk = url_parameter("tasPk");
    var comPk = url_parameter("comPk");
    var hgfa = $("input[name='hgfa']").val();

    var tarpk = $("input[name='tarpk']").val();
    var glider = $("input[name='glider']").val();

    var dist = $("input[name='distance']").val();
    var penalty = $("input[name='penalty']").val();

    var result = $("#resulttype option:checked").val();
    var enrating = $("#enrating option:checked").val();

    console.log('tarpk='+tarpk+' glider='+glider+'dist='+dist+' penalty='+penalty+' result='+result+' enrating='+enrating+' hgfa='+hgfa);
    $('#reloadspin').show();
    $.post("update_task_result.php", { 'tasPk' : tasPk, 'comPk' : comPk, 'tarpk' : tarpk, 'glider' : glider, 'dist' :  dist, 'penalty' :  penalty, 'result' : result, 'enrating' : enrating, 'hgfa' : hgfa }, result_done);
}
function populate_modal(data)
{
    console.log('tarpk='+data[1]);
    $('#tarpk').val(data[1]);
    $('#addition').hide();
    $('#restitle').html(data[2]);
    if (data[10] == "dnf" || data[10] == "abs")
    {
        $('#resulttype select').val(data[10]);
        $('#distance').val('');
    }
    else
    {
        $('#resulttype select').val('lo');
        $('#distance').val(data[10]);
    }
    $('#enrating select').val(data[5]);
    $('#glider').val(data[4]);
    $('#penalty').val(data[15]);
    $("#resultmodal").modal("show");
}
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_task_result.php' + window.location.search);
    var comPk = url.searchParams.get("comPk");
    var tasPk = url.searchParams.get("tasPk");
    $('#reloadspin').hide();
    $('#task_result').dataTable({
        ajax: 'get_task_result.php?comPk='+comPk+'&tasPk='+tasPk,
        paging: false,
        searching: true,
        info: false,
        "dom": 'lrtip',
        "columnDefs": [
            {
                "targets": [ 1, 5 ],
                "visible": false
            },
            {
                "targets": [ 4 ],
                "orderData": [ 5, 1 ]
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
            $('#startend').html('<b>Start: ' + json.task.start + ' End: ' + json.task.end + '</b><br>');
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
	if (getCookie('XCauth').length > 0)
	{
    	$('#task_result tbody').on('click', 'tr', function () {
            var table=$('#task_result').DataTable();
        	var data = table.row( this ).data();
            populate_modal(data);
    	} );
    	$('#comp_header').append('<a class="btn btn-light" onclick="add_result();">Add Result</a>');
	}
});

