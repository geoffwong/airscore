function create_comp()
{
    var options = { };
    options.compname = $('#compname').val();
    options.dateto = $('#dateto').val();
    options.datefrom = $('#datefrom').val();

    console.log(options);
    $.post('add_comp.php', options, function (res) {
        console.log(res);

        var comPk = res.comPk;
        var url;
        console.log(res);
        if (res.result == "unauthorised")
        {
            authorise();
            alert("Unauthorised");
            return;
        }
        if (!comPk || res.result != "ok")
        {
            alert(res.result + ": " + res.error);
            return;
        }
        url = 'competition.html?comPk=' + comPk;
        console.log(url);
        window.location.replace(url);
    });
}
$(document).ready(function() {
    $("#datefrom").val(new Date());
    $("#dateto").val(new Date());
    $('#competitions').dataTable({
        ajax: {
            url: 'get_admin_comps.php',
            dataSrc: function (json) {
                    if (json.result == 'unauthorised')
                    {
                        authorise();
                        return;
                    }
                    else
                    {
                        return json.data;
                    }
                }
            },
        paging: true,
        order: [[ 4, 'desc' ]],
        lengthMenu: [ 15, 30, 60, 1000 ],
        searching: true,
        info: false,
        //"dom": '<"search"f><"top"l>rt<"bottom"ip><"clear">',
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        "createdRow": function( row, data, index, cells )
        {
            cells[1].innerHTML = '<a href=\"competition.html?comPk=' + data[0] + '\">' + data[1] + '</a>';
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

