
$(document).ready(function() {
    $('#tracks').dataTable({
        ajax: 'get_admin_tracks.php' + window.location.search,
        paging: true,
        searching: true,
        info: false,
        order: [ 1, "desc" ],
        lengthMenu: [ 10, 20, 50, 100, 1000 ],
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        //"columnDefs": [ { "targets": [ 0 ], "visible": false } ],
    });
});

