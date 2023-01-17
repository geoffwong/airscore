
function add_admin()
{
    var xusePk = $('#adminselect option:selected').val();
    var xcomPk = url_parameter('comPk');

    var options = { comPk: xcomPk,  usePk: xusePk };
    console.log(options);

    $.post("add_comp_authorised.php", options, function(res) 
        { 
            console.log(res);
            if (res.result == "ok")
            {
                $('#administrators').DataTable().row.add(res.data).draw();
            }
       });
}
function remove_admin(tdiv)
{
    var xcomPk = url_parameter('comPk');
    var rowind = tdiv.parentNode.parentNode.parentNode.rowIndex;
    //var rowind = $(tdiv).parent().index();
    console.log('rowind='+rowind);
	var xusePk = $('#administrators tr:eq('+rowind+')').text();
    console.log('xusePk='+xusePk);
    var jrow = $('#administrators tr:eq('+rowind+')').remove();

	// post ..
    var options = { comPk: xcomPk,  usePk: xusePk };
    $.post("remove_comp_authorised.php", options, function(res) 
        { console.log(res) });
}
function pop_adminlist(admin)
{
    var res = '';
    console.log(admin);

    var res = '<select id="adminselect" class="form-control form-control-sm" placeholder=".form-control-sm">';
    for (var key in admin)
    {
        res += '<option value="' + admin[key] + '">' + key + '</option>';
    }
    res += '</select>';

    $('#adminlistdiv').html(res);
}

function pop_comp_details(details)
{
    // @todo - populate header
}

$(document).ready(function() {
    var comPk = url_parameter('comPk');
    $('#administrators').dataTable({
        ajax: 'get_authorised.php?comPk='+comPk,
        paging: false,
        searching: false,
        info: false,
        order: [ 1, "asc" ],
        //"columnDefs": [ { "targets": [ 0 ], "visible": false } ],
        "createdRow": function( row, data, index, cells )
        {
            cells[3].innerHTML = '<b><a href="#" onclick="remove_admin(this);">&cross;</a></b>';
        },
        "initComplete": function(settings, json)
        {
            pop_adminlist(json.administrators);
            pop_comp_details(json.compinfo);
        }

    });
});

