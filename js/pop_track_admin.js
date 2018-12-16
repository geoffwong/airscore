
function del_track(div, id)
{
    var options = { };
    options.tasPk = tasPk;
    options.id = id;
    console.log(options);

    $.post('del_track.php', options, function (res) {
        console.log(res);

        var url;
        if (res.result == "unauthorised")
        {
        }
        else if (!tawPk || res.result != "ok")
        {
            alert(res.result + ": " + res.error);
            return;
        }
    });
      
    // delete from table ..  
}

function confirm_del_track(div)
{
    var rowind = div.parentNode.rowIndex;

    $("#deltrack").modal();
}

$(document).ready(function() {
    $('#tracks').dataTable({
        ajax: 'get_admin_tracks.php' + window.location.search,
        paging: true,
        searching: true,
        info: false,
        order: [ 1, "desc" ],
        lengthMenu: [ 20, 50, 100, 1000 ],
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        //"columnDefs": [ { "targets": [ 0 ], "visible": false } ],
        "createdRow": function( row, data, index, cells )
        {
            cells[5].innerHTML = '<b><a href="#/" onclick="confirm_del_track(this);">&cross;</a></b>';
        }
    });
});

