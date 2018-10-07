
function comp_card(div, info)
{
    $('#comp_name').text(info.comName);
    $('#comp_date').text(info.comDateFrom + ' - ' + info.comDateTo);
        //"<tr><td>Director</td><td>" + info.comMeetDirName + '</td></tr>' +
        //"<tr><td>Location</td><td>" + info.comLocation + '</td></tr>' +
        //"<tr><td>Overall Scoring</td><td>" + info.comOverallScore + ' (' + info.comOverallParam + ')</td></tr>');
}

function formula_card(info)
{
    // some GAP parameters
    allkeys = Object.keys(info);
    values = Object.values(info);
    for (var tc = 0; tc < allkeys.length-2; tc+=3)
    {
        $('#formula1 tbody').append('<tr><td>'+allkeys[tc]+'</td><td>'+values[tc]+'</td></tr>');
        $('#formula2 tbody').append('<tr><td>'+allkeys[tc+1]+'</td><td>'+values[tc+1]+'</td></tr>');
        $('#formula3 tbody').append('<tr><td>'+allkeys[tc+2]+'</td><td>'+values[tc+2]+'</td></tr>');
    }
    if (tc < allkeys.length-1)
    {
        $('#formula1 tbody').append('<tr><td>'+allkeys[tc]+'</td><td>'+values[tc]+'</td></tr>');
        tc++;
    }
    if (tc < allkeys.length)
    {
        $('#formula2 tbody').append('<tr><td>'+allkeys[tc]+'</td><td>'+values[tc]+'</td></tr>');
    }
}

function task_card(info)
{
    var comPk = url_parameter('comPk');
    for (var tc = 0; tc < info.length; tc++)
    {
         $('#tasktbl tbody').append("<tr><td><a href=\"task_details.html?comPk="+comPk+'&tasPk='+info[tc].tasPk+"\">" + info[tc].tasDate + '</a></td><td>' + info[tc].tasName + '</td><td>' + info[tc].tasDistance + 
            '<td>' + info[tc].tasStartTime.substr(11,8) + ' - ' + info[tc].tasFinishTime.substr(11,8) + '</td></tr>');
    }
}

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    microAjax('get_comp_details.php' + window.location.search, function(data) {
            var json = JSON.parse(data);

            var header = $('#comp_header');
            var tasks = $('#tasks');

            // comp info
            comp_card(header, json.compinfo);
        
            if (json.compinfo.comType == "OLC")
            {
                // hide divs
                return;
            }

            // formula
            formula_card(json.formula);


            // tasks
            task_card(json.taskinfo);
    });
});

