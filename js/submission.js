var comp_json;
function submit_track()
{
    comPk = $('#compsel').value;
    if (!comPk)
    {
        alert('No competition selected');
        return;
    }
    tasPk = $('#routesel').value;

    var extra='';
    if (tasPk > 0)
    {
        extra='&tasPk='+tasPk;
    }
    
    var result = new microAjax("submit_track.php?comPk="+comPk+extra, 
        function(data) {
            var task = JSON.parse(data);
            // similar behavior as an HTTP redirect
            //window.location.replace("http://tracklog_map.html?");
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

$("#compsel").change(function () {
    for (i = 0; i < comp_json.length; i++)
    {
        if (comp_json[i].comPk == this.value)
        {
            add_tasks(comp_json[i].tasks);
        }
    }
  });

$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_active_comps.php' + window.location.search);
    var comPk = url.searchParams.get("comPk");
    var tasPk = url.searchParams.get("tasPk");

    var extra = '';
    if (comPk)
    {
        extra="?comPk=" + comPk;
    }

    microAjax("get_active_comps.php" + extra,
        function(data) {
            var task = JSON.parse(data);
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
