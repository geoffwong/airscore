
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_ladder.php'+ window.location.search);
    var ladPk = url.searchParams.get("ladPk");
    $('#ladders').dataTable({
        ajax: 'get_ladder.php?ladPk='+ladPk,
        paging: true,
        order: [[ 3, 'desc' ]],
        lengthMenu: [ 10, 20, 50, 100, 1000 ],
        searching: true,
        info: false,
         "dom": '<"#search"f>rt<"bottom"lip><"clear">',

        "initComplete": function(settings, json) 
        {
            var table= $('#ladders');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length+1;
            
            //
            $('#ladder_name').text(json.ladder.ladName);
            $('#ladder_header').append('<h3>' + json.ladder.ladNationCode + '</h3>');
            $('#ladder_header').append('<h3>' + json.ladder.ladStart + ' - ' + json.ladder.ladEnd + '</h3>');

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

