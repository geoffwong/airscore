
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_ladder.php'+ window.location.search);
    var ladPk = url.searchParams.get("ladPk");
    $('#ladders').dataTable({
        ajax: 'get_ladder.php?ladPk='+ladPk,
        paging: true,
        order: [[ 3, 'desc' ]],
        lengthMenu: [ 15, 30, 60, 1000 ],
        searching: true,
        info: false,

        "initComplete": function(settings, json) 
        {
            var table= $('#ladders');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length+1;

            // ladder info
            //$('#comp_name').text(json.task.comp_name);
            //$('#task_date').text(json.task.date);
        
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

