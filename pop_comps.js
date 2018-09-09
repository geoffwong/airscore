
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_all_comps.php');
    $('#competitions').dataTable({
        ajax: 'get_all_comps.php',
        paging: true,
        order: [[ 4, 'desc' ]],
        lengthMenu: [ 15, 30, 60, 1000 ],
        searching: true,
        info: false
    });
});

