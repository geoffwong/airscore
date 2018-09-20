
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_ladder.php');
    $('#ladders').dataTable({
        ajax: 'get_ladder.php',
        paging: true,
        order: [[ 3, 'desc' ]],
        lengthMenu: [ 15, 30, 60, 1000 ],
        searching: true,
        info: false,
         "dom": '<"#search"f>rt<"bottom"lip><"clear">'
    });
});

