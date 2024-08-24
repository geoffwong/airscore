var map_ruler;
function add_map_row(comPk, task, count)
{
    var colmd7 = document.createElement("div");
    colmd7.className="col-md-7";

    var colmd5 = document.createElement("div");
    colmd5.className="col-md-5";


    var canvas = 'map_canvas' + count;
    var canvasdiv = document.createElement("div");
    canvasdiv.setAttribute('id', canvas);
    canvasdiv.setAttribute('style', 'top: 10px; left: 10; width:100%; height:300px; float: left');

    //var createA = document.createElement('a');
    //createA.setAttribute('href', '#');
    //createA.appendChild(canvasdiv);
    colmd7.appendChild(canvasdiv);

    var body = document.createElement('div');
    body.innerHTML = '<br><h3 id=\"task_hd\">Task '+task.tasName+'</h3><h4>'+task.tasDate+'</h4>' +
                    task.tasComment + 
                    '<br><table class="taskinfo">' +
                    '<tr><td>Task Type:</td><td>' + task.tasTaskType.toUpperCase() + '</td></tr>' + 
                    '<tr><td>Task Distance:</td><td>' + task.tasShortest + ' km</td></tr>' + 
                    '<tr><td>Day Quality:</td><td>' + task.tasQuality + '</td></tr></table><br>';

    body.innerHTML = body.innerHTML + '<a class="btn btn-primary" href="task_result.html?comPk='+comPk+'&tasPk='+task.tasPk+'");">Task Scores</a>';
    body.innerHTML = body.innerHTML + '<a class="btn btn-success ml-1" href="http://aerialtechnologies.com.au/para3d/CesiumViewer/index.html?taskid='+task.tasPk+'");">3D Flights</a>';

    body.innerHTML = body.innerHTML + '<a class="btn btn-secondary ml-1" href="download_task.php?comPk='+comPk+'&tasPk='+task.tasPk+'");">XCTrack</a>';
    
    colmd5.appendChild(body);

    var ele = document.getElementById('row'+count);
    ele.appendChild(colmd7);
    ele.appendChild(colmd5);

    ele.style.paddingBottom = "40px";
}
function plot_task_airspace(map, comPk, tasPk)
{
    var options = { };
    options.tasPk = tasPk;
    options.comPk = comPk;
    $.post("get_task_airspace.php", options, function (res) {
        console.log(res);
        if (res.result == 'ok')
        {
            var airspace;

            for (airPk in res.airspaces)
            {
                plot_air(airPk, res.airspaces[airPk], '#999999', false);
            }
        }
        else
        {
            alert("Task airspace failed: " + res.error);
        }
    });
}
function plot_all_tasks(comPk)
{
    microAjax("get_all_tasks.php?comPk="+comPk, 
    function (data) 
    {
        var comp_tasks = JSON.parse(data);
        var count = 1;

        // setup comp info
        var ele = $('#comp_name');
        if (comp_tasks.comp.comClass == "HG")
        {
            ele.addClass("bannerhg");
        }
        else
        {
            ele.addClass("banner");
        }
        ele.html(comp_tasks.comp.comName);
        ele.append($('<div class="row"><div class="col-md-6 ml-1"><h4><b>'+ comp_tasks.comp.comLocation + '</b>&nbsp;<small>' + comp_tasks.comp.comDateFrom + ' - ' + comp_tasks.comp.comDateTo + '</small></h4></div>'));
        if (comp_tasks.comp.regPk)
        {
            ele.append($('<div class="row"><div class="col-md-6"><a href="waypoint_map.html?regPk=' + comp_tasks.comp.regPk + '" class="btn btn-secondary">Waypoints</a></div></div>'));
        }
        //ele.append($('<div class="col-md-6"><h5>'+comp_tasks.comp.comMeetDirName + '</h5></div></div>>'));

        // plot tasks
        var all_tasks = comp_tasks.tasks;
        if (all_tasks.length == 0)
        {
           ele.append($('<hr><center><h4 class="display-4">No Tasks</h4></center>'));
        }
        for (taskid in all_tasks)
        {
            var taskinfo = all_tasks[taskid];
            if (taskinfo.waypoints.length > 0)
            {
                add_map_row(comp_tasks.comp.comPk, taskinfo.task, count);
                map = add_map_server('map_canvas'+count, count-1);
                map.addControl(new L.Control.Fullscreen());
                map.on('fullscreenchange', function () {
                    if (map.isFullscreen()) {
                        console.log('entered fullscreen');
                        map_ruler = L.control.ruler().addTo(map);
						plot_pilots_lo(map, taskinfo.task.tasPk);
                        plot_task_airspace(map, comp_tasks.comp.comPk, taskinfo.task.tasPk);
                    } 
                    else {
                        map.removeControl(map_ruler);
                        console.log('exited fullscreen');
                    }
                });
                plot_task_route(map, taskinfo.waypoints);
                count++;
            }
        }

        // add footer
        var foot = document.createElement("footer");
        foot.className="py-5 bg-dark";
        var footdiv = document.createElement("div");
        footdiv.className = "container";
        var para = document.createElement("p");
        para.className = "m-0 text-center text-white";
        para.appendChild(document.createTextNode('airScore'));
        footdiv.appendChild(para);
        foot.appendChild(footdiv);

        $('#main').append($('<hr>'));
    });

}


$(document).ready(function() {
    var comPk = url_parameter("comPk");

    comPk = url_parameter("comPk");
    plot_all_tasks(comPk);
});

