function create_region()
{
    var fd = new FormData();
    fd.append('region',  $('#regionname').val());
    fd.append('userfile', $("#customFile")[0].files[0]);

    $.ajax({
            url: 'add_region.php',  
            type: 'POST',
            enctype: 'multipart/form-data',
            data: fd,
            cache: false,
            contentType: false,
            processData: false,
            timeout:0,
            dataType: "text",
            success: function(data) {
                var result = {};
                console.log(data);
                var regPk = 0;
                try {
                    result = JSON.parse(data);
                    regPk = result['regPk'];
                }
                catch (e)
                {
                    alert("Upload failed: " + e);
                }
                if (result['result'] == 'ok')
                {
                    var regPk = result['regPk'];
                    var url = 'waypoint_map.html?regPk='+regPk;
                    console.log(url);
                    window.location.replace(url);
                }
                else
                {
                    alert(result['result'] + ": " + result['error']);
                }
            }
        });
}

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

