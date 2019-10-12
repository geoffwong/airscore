function add_ladder()
{
    var options = { };
    options.laddername = $('#laddername').val();
    options.dateto = $('#dateto').val();
    options.datefrom = $('#datefrom').val();
    options.nation = $('#nation').val();
    options.method = $('#method').val();
    options.param = $('#param').val();
    options.compclass = $('#compclass').val();

    $.post('add_ladder.php', options, function (res) {
        console.log(res);

        var comPk = res.comPk;
        var url;
        if (!comPk || res.result != "ok")
        {
            alert(res.result + ": " + res.error);
            return;
        }
        url = 'ladder_overview.html';
        console.log(url);
        window.location.replace(url);
    });
}
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

