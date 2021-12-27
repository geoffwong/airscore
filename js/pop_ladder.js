
function result_updated(data)
{
    console.log("result_updated");
    console.log(data);
    var table = $('#ladders').DataTable();
    table.ajax.url('').load();
    //$('#reloadspin').hide();
}
function filter_result()
{
    var ladPk = url_parameter("ladPk");
    //var filter = "class=" + $("input[name='dhv']").find(":selected").text();
    var filter = $("#dhv option:selected").val();
	console.log("filter_result: "+filter);

    //$('#reloadspin').show();
    //$.post("get_ladder.php", { 'ladPk' : ladPk, 'class' : filter }, result_updated);
    var table = $('#ladders').DataTable();
    table.ajax.url('get_ladder.php?ladPk='+ladPk+'&class='+filter).load();
}
$(document).ready(function() {
    $('#ladders').dataTable({
        ajax: 'get_ladder.php' + window.location.search,
        paging: true,
        order: [[ 3, 'desc' ]],
        lengthMenu: [ 20, 50, 200 ],
        searching: true,
        info: false,
         "dom": '<"#search"f>rt<"bottom"lip><"clear">',

        "initComplete": function(settings, json) 
        {
            var table= $('#ladders');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length+1;
            
            //
            $('#ladder_name').text(json.ladder.ladNationCode + ' ' + json.ladder.ladName);
            $('#ladder_header').append('<h5>' + json.ladder.ladStart + ' - ' + json.ladder.ladEnd + '</h5>');
            $('#ladder_header').append('<h5>Max Score: ' + json.ladder.maxScore + '</h5>');
            if (json.ladder.ladClass == 'PG')
            {
                $('#ladder_header').append('<select name="dhv" id="dhv" class="form-control" placeholder="Class" onchange="filter_result()"><option value="3" selected>Open</option><option value="0">Fun</option><option value="1">Sports</option><option value="2">Serial</option><option value="4">Women</option></select>');
            }

            // ladder info
            $.each( json.inc, function( key, value ) {
                $('#compsinc tbody').append('<tr><td><a href=comp_overall.html?comPk=' + value.comPk + '>' + value.comName + '</a></td><td>' + value.lcValue + '</td></tr>');
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

