
$(document).ready(function() {
    var pilPk = url_parameter("pilPk");
    var ladPk = url_parameter("ladPk");
    var url = 'pilPk=' + pilPk;
    if (ladPk > 0)
    {
        url = url + '&ladPk=' + ladPk;
    }
    $('#pilot_tracks').dataTable({
        ajax: 'get_pilot_details.php?'+url,
        paging: true,
        searching: true,
        info: false,
        order: [ 0, "desc" ],
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        //"columnDefs": [ { "targets": [ 0 ], "visible": false } ],

        "initComplete": function(settings, json) 
        {
            var table= $('#pilot_details');
            var rows = $("tr", table).length-1;
            var numCols = $("th", table).length;

            // comp info
            var bg = "url('flags/"+json.info.pilNationCode.toLowerCase()+"') no-repeat left top";
            console.log("bg="+bg);
            $("#pilnation").css("background", bg);
            $("#pilnation").css("text-shadow", "-1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000");
            $('#pilot_name').text(json.info.pilFirstName);
            $('#pilot_nation').text(json.info.pilNationCode);
            $('#pilot_nation').text(json.info.pilNationCode);
        
            // waypoints
            $('#pilot_details tbody').append(
                        "<tr><td>Total tracks" + "</td><td>" + json.info.numTracks + 
                        "<tr><td>Hours logged" + "</td><td>" + json.info.total_hours + 
                        "</tr><tr></td><td>AA/AAA Tasks</td><td>" + json.info.tasks + 
                        "</tr><tr></td><td>Goal Percentage</td><td>" + json.info.goal_perc + 
                        "</tr><tr></td><td>Avg. Goal Speed</td><td>" + json.info.goal_speed + 
                        "</td></tr>");

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

