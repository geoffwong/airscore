
$(document).ready(function() {
    $('#airspace').dataTable({
        ajax: 'get_admin_airspace.php',
        paging: true,
        order: [[ 1, 'desc' ]],
        lengthMenu: [ 15, 30, 60, 1000 ],
        searching: true,
        info: false,
        "dom": '<"#search"f>rt<"bottom"lip><"clear">'
    });


    $('#airspace tbody').on('click', 'tr', function () {
        if ( $(this).hasClass('selected') ) {
            $(this).removeClass('selected');
            $('#control').html('Add');
            $('#clear').hide();
            $('#delete').hide();
        }
        else {
            var table=$('#airspace').DataTable();
            var data = table.row(this).data();
            table.$('tr.selected').removeClass('selected');
            $(this).addClass('selected');
            $('#control').html('Update');
            $('#clear').show();
            $('#delete').show();
            $("input[name='regid']").val(data[0]);
            $("input[name='region']").val(data[1]);
            $("input[name='lat']").val(data[2]);
            $("input[name='long']").val(data[3]);
        }
    });
});

$('#customFile').change(function() {
  var file = $('#customFile')[0].files[0].name;
  $('#selected_openair').text(file);
});

