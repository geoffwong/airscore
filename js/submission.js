var comp_json;
function submit_track()
{
    $("#send").html("Sending ...");
    var comPk = $('#compsel').val();
    if (!comPk || comPk == 0)
    {
        alert('No competition selected');
        return;
    }
    var fd = new FormData($("#trackform"));
    fd.append('comid' , comPk);
    fd.append('hgfanum' , $("input[name='hgfanum']").val());
    fd.append('lastname' , $("input[name='lastname']").val());
    fd.append('glider' , $("input[name='glider']").val());
    fd.append('dhv' , $("#dhv option:selected").val());
    fd.append('pilotsafety' , $("#pilotsafety option:selected").val());
    fd.append('pilotquality' , $("#pilotquality option:selected").val());
    fd.append('userfile' , $("#customFile")[0].files[0]);

    var tasPk = $('#routesel').val();
    var extra='';
    if (tasPk > 0)
    {
        fd.append('route', tasPk);
    }

    $.ajax({
            url: 'add_track.php',  
            type: 'POST',
            enctype: 'multipart/form-data',
            data: fd,
            cache: false,
            contentType: false,
            processData: false,
            timeout:0,
            dataType: "text",
            success: function(data) {
                console.log(data);
                var result = JSON.parse(data);
                if (result['result'] == 'ok')
                {
                    var url = "tracklog_map.html?comPk=" + result['comPk'] + "&trackid=" + result['traPk'];
                    var tasPk = result['tasPk'];
                    if (tasPk)
                    {
                        url = url + '&tasPk=' + tasPk;
                    }
                    console.log(url);
                    window.location.replace(url);
                }
                else
                {
                    alert("Track upload failed: " + result['result']);
                }
                $("#send").html("Send Tracklog");
            }
        });
}
function add_tasks(tasks)
{
    if (tasks.length == 0)
    {
        $('#route').hide();
        $('#routesel').empty();
    }
    else
    {
        $('#route').show();
        $('#routesel').empty();
        for (i = 0; i < tasks.length; i++)
        {
            $('#routesel').append($('<option>', {
                    value: tasks[i][0],
                    text: tasks[i][1]
                }));
        }
    }
}

function update_classes(com_class)
{
    if (com_class == 'PG')
    {
        var pg = { novice: '1', fun: '1/2', sports: '2', serial: '2/3', competition: 'competition' };
        $('#dhv option').remove();
        $.each(pg, function (key, val) {
            $('#dhv').append("<option value=\""+val+"\">" + key + "</option>");
        });
        $('#dhv').val('2/3');
    }
    else if (com_class == 'HG')
    {
        var hg = { floater: 'floater', kingpost: 'kingpost', open: 'open', rigid: 'rigid' };
        $('#dhv option').remove();
        $.each(hg, function (key, val) {
            $('#dhv').append("<option value=\""+val+"\">" + key + "</option>");
        });
        $('#dhv').val('open');
    }
}

$("#compsel").change(function () {
    for (i = 0; i < comp_json.length; i++)
    {
        if (comp_json[i].comPk == this.value)
        {
            add_tasks(comp_json[i].tasks);
            update_classes(comp_json[i].comClass);
        }

    }
  });

$(document).ready(function() {
    var comPk = url_parameter("comPk");

    var extra = {};
    if (comPk)
    {
        extra['comPk'] = comPk;
    }
    else
    {
        $('#overall').hide();
    }

    $.post("get_active_comps.php",  extra,
        function(data) {
            var task = data;
            comp_json = task;

            if (task.length == 0)
            {
                $('#compsel').append($('<option>', {
                            value: 0,
                            text: '-- Competition Closed --'
                        }));
            }
            else if (task.length == 1)
            {
                $('#compsel').append($('<option>', {
                            value: task[0].comPk,
                            text: task[0].comName
                        }));
                add_tasks(task[0].tasks);
                if (task[0].comClass == "HG")
                {
                    update_classes("HG");
                }
            }
            else 
            {
                $('#compsel').append($('<option>', {
                            value: 0,
                            text: '-- select competition --'
                        }));
                $('#route').toggle();
                for (i = 0; i < task.length; i++)
                {
                    if (task[i].comPk)
                    {
                        $('#compsel').append($('<option>', {
                            value: task[i].comPk,
                            text: task[i].comName
                        }));
                    }
                }
            }
        });
});

$('#customFile').change(function() {
  var file = $('#customFile')[0].files[0].name;
  $('#selected_igc').text(file);
});
