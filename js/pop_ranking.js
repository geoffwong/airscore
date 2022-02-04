
$(document).ready(function() {
    $('#ranking').dataTable({
        ajax: 'get_trueskill.php' + window.location.search,
        paging: true,
        //"columnDefs" : [ { 'type' : 'numeric' }, 0, 0, 0, 0, { 'type' : 'numeric' } ],
        order: [[5, 'desc'], [ 0, 'asc' ]],
        lengthMenu: [ 50, 100, 1000 ],
        searching: true,
        info: false,
         "dom": '<"#search"f>rt<"bottom"lip><"clear">',

        "initComplete": function(settings, json) 
        {
            var table= $('#ranking');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length+1;
            
            //
            $('#ranking_name').text(json.ladder.ranNationCode + ' ' + json.ladder.ranName + ' - ' + json.ladder.ranDateTo);
            //$('#ladder_header').append('<h5>Max Score: ' + json.ladder.maxScore + '</h5>');

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

