
$(document).ready(function() {
    $('#pilots').dataTable({
        ajax: 'get_all_pilots.php',
        paging: true,
        order: [[ 1, 'asc' ]],
        lengthMenu: [ 15, 30, 60, 1000 ],
        searching: true,
        info: false,
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
    });
});

