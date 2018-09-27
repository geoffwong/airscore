function update_table(data)
{
    $('#pilots').DataTable().ajax.reload(null,false);
    //$('#pilots').DataTable({ ajax: 'get_admin_pilots.php' }).ajax.reload(null,false);
    $('#control').blur();
    $('#delbut').blur();
}
function clear_pilot()
{
    $("input[name='pilotid']").val('');
    $("input[name='first']").val('');
    $("input[name='last']").val('');
    $("input[name='nation']").val('');
    $("input[name='hgfa']").val('');
    $("input[name='civl']").val('');
    $("input[name='gender']").val('');
    $('#control').html('Add');
    $('#clear').hide();
    $('#delete').hide();
}
function addup_pilot()
{
    var action = 'add';
    var id = $("input[name='pilotid']").val();
    var fname = $("input[name='first']").val();
    var lname = $("input[name='last']").val();
    var nation = $("input[name='nation']").val();
    var hgfa = $("input[name='hgfa']").val();
    var civl = $("input[name='civl']").val();
    var sex = $("input[name='gender'] option:selected").val();
    if (parseInt(id) > 0)
    {
        $.post("update_pilot.php", { 'update' : id, 'fname' : fname, 'lname' :  lname, 'nation' :  nation, 'hgfa' : hgfa, 'civl' : civl, 'sex' : sex }, update_table);
    }
    else
    {
        $.post("update_pilot.php", { 'add' : 1, 'fname' : fname, 'lname' :  lname, 'nation' :  nation, 'hgfa' : hgfa, 'civl' : civl, 'sex' : sex }, update_table);
    }
}
function delete_pilot()
{
    var id = $("input[name='pilotid']").val();
    $.post("update_pilot.php", { 'delete' : id }, update_table );
}
$(document).ready(function() {
    $('#clear').hide();
    $('#delete').hide();
    $('#pilots').dataTable({
        ajax: 'get_admin_pilots.php',
        paging: true,
        order: [[ 3, 'asc' ]],
        lengthMenu: [ 20, 40, 80, 1000 ],
        searching: true,
        info: false,
        //"dom": '<"search"f><"top"l>rt<"bottom"ip><"clear">',
        "dom": '<"#search"f>rt<"bottom"lip><"clear">',
        "columnDefs": [ { "targets": [ 0 ], "visible": false, "searchable": false } ]
    });

    $('#pilots tbody').on('click', 'tr', function () {
        if ( $(this).hasClass('selected') ) {
            $(this).removeClass('selected');
            $('#control').html('Add');
            $('#clear').hide();
            $('#delete').hide();
        }
        else {
            var table=$('#pilots').DataTable();
            var data = table.row(this).data();
            table.$('tr.selected').removeClass('selected');
            $(this).addClass('selected');
            $('#control').html('Update');
            $('#clear').show();
            $('#delete').show();
            $("input[name='pilotid']").val(data[0]);
            $("input[name='first']").val(data[3]);
            $("input[name='last']").val(data[4]);
            $("input[name='nation']").val(data[6]);
            $("input[name='hgfa']").val(data[1]);
            $("input[name='civl']").val(data[2]);
            $("input[name='gender']").val(data[5]);
        }
    } );
});


