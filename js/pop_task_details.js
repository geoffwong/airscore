
var task_details;

function updated_compinfo(result)
{
    console.log(result);
}

function save_task()
{
	var $rows1 = $('#taskinfo1').find('tr:not(:hidden)');
	var $rows2 = $('#taskinfo2').find('tr:not(:hidden)');
	var options = { comPk : comp_details.keys.comPk };

  	$rows1.each(function () {
    	var $td = $(this).find('td');
		console.log($td.eq(0).text() + "="+ $td.eq(1).text());
        options[$td.eq(0).text()] = $td.eq(1).text();
	});

  	$rows2.each(function () {
    	var $td = $(this).find('td');
		console.log($td.eq(0).text() + "=" + $td.eq(1).text());
        options[$td.eq(0).text()] = $td.eq(1).text();
	});

	$.post("update_task.php", options, updated_compinfo);
}

function save_waypoints()
{
}

function save_airspace()
{
}

function reset_taskinfo()
{
    $('#compinfo1 tbody').html('');
    $('#compinfo2 tbody').html('');
    comp_card(header, json.compinfo);
}

function task_card(div, info)
{
    // some GAP parameters
    allkeys = Object.keys(info);
    values = Object.values(info);
    for (var tc = 0; tc < allkeys.length-1; tc+=2)
    {
        add_td($('#taskinfo1 tbody'), allkeys[tc], values[tc]);
        add_td($('#taskinfo2 tbody'), allkeys[tc+1], values[tc+1]);
    }
    if (tc < allkeys.length)
    {
        add_td($('#taskinfo1 tbody'), allkeys[tc], values[tc]);
        tc++;
    }
}

function waypoints_card(div, info, region)
{
    // some GAP parameters
    allkeys = Object.keys(info);
    values = Object.values(info);
    for (var tc = 0; tc < allkeys.length; tc++)
    {
        $('#waypoints tbody').append('<tr><td contenteditable="true">' +
            region[values[tc].rwpPk].rwpName + '</td>' + 
            create_td('tawType', values[tc].tawType) +
            create_td('tawHow', values[tc].tawHow) +
            create_td('tawShape', values[tc].tawShape) + 
            '</td><td contenteditable="true">' + values[tc].tawRadius + '</td></tr>');
    }
}

function airspace_card(info)
{
    var comPk = url_parameter('comPk');
    for (var tc = 0; tc < info.length; tc++)
    {
         $('#airspace tbody').append("<tr><td><a href=\"task.html?comPk="+comPk+'&tasPk='+info[tc].tasPk+"\">" + info[tc].tasDate + '</a></td><td>' + info[tc].tasName + '</td><td>' + info[tc].tasDistance + 
            '<td>' + info[tc].tasStartTime.substr(11,8) + ' - ' + info[tc].tasFinishTime.substr(11,8) + '</td></tr>');
    }
}

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    microAjax('get_task_details.php' + window.location.search, function(data) {
            var json = JSON.parse(data);

            task_details = json;

            // comp info
            task_card($('#taskinfo'), json.taskinfo);

            waypoints_card($('#waypoints'), json.waypoints, json.region);
        
            //airspace
            //airspace_card(json.taskinfo);
    });
});

