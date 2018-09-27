
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_admin_comps.php');
    $('#competitions').dataTable({
        ajax: 'get_admin_comps.php',
        paging: true,
        order: [[ 4, 'desc' ]],
        lengthMenu: [ 15, 30, 60, 1000 ],
        searching: true,
        info: false,
        //"dom": '<"search"f><"top"l>rt<"bottom"ip><"clear">',
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        "createdRow": function( row, data, index, cells )
        {
            cells[1].innerHTML = '<a href=\"competition.php?comPk=' + data[0] + '\">' + data[1] + '</a>';
            if (today() < data[3])
            {       
                $(row).addClass('text-warning');
            }
            else if (today() < data[4])
            {
                $(row).addClass('text-info');
            }
        }
    });
});

