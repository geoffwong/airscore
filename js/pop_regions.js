
$(document).ready(function() {
    $('#regions').dataTable({
        ajax: 'get_admin_regions.php',
        paging: true,
        searching: true,
        info: false,
        order: [ 1, "asc" ],
        lengthMenu: [ 20, 50, 100, 1000 ],
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        //"columnDefs": [ { "targets": [ 0 ], "visible": false } ],
        "createdRow": function( row, data, index, cells )
        {
            cells[1].innerHTML = '<a href=\"waypoint_map.html?regPk=' + data[0] + '\">' + data[1] + '</a>';
        }
    });
});

