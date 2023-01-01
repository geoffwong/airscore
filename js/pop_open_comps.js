
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_open.php');
    $('#competitions').dataTable({
        ajax: 'get_open_comps.php',
        paging: true,
        order: [[ 4, 'desc' ]],
        paging: false,
        searching: false,
        info: false,
        //"dom": '<"search"f><"top"l>rt<"bottom"ip><"clear">',
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        "createdRow": function( row, data, index, cells )
        {
            if (today() < data[4])
            {       
                $(row).addClass('text-warning');
            }
            else if (today() < data[5])
            {
                $(row).addClass('text-info');
            }
            if (data[7] > 0)
            {
                cells[6].innerHTML = data[6]+"<span class=\"badge badge-success ml-2\">" + data[7] + "</span>";
            }
            else
            {
                cells[6].innerHTML = data[6]
            }
        }
    });

    $('#recentcomps').dataTable({
        ajax: 'get_recent_comps.php',
        paging: true,
        order: [[ 5, 'desc' ]],
        paging: false,
        searching: false,
        info: false,
        //"dom": '<"search"f><"top"l>rt<"bottom"ip><"clear">',
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        "createdRow": function( row, data, index, cells )
        {
            if (today() < data[4])
            {       
                $(row).addClass('text-warning');
            }
            else if (today() < data[5])
            {
                $(row).addClass('text-info');
            }
        }
    });
});

