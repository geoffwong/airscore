
var comp_details;

function updated_compinfo(result)
{
    console.log(result);
}

function save_compinfo()
{
	var $rows1 = $('#compinfo1').find('tr:not(:hidden)');
	var $rows2 = $('#compinfo2').find('tr:not(:hidden)');
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

	$.post("update_compinfo.php", options, updated_compinfo);
}

function save_scoring()
{
	var $rows1 = $('#scoring1').find('tr:not(:hidden)');
	var $rows2 = $('#scoring2').find('tr:not(:hidden)');
	var options = { comPk : comp_details.keys.comPk };

  	$rows1.each(function () {
    	var $td = $(this).find('td');
		console.log($td.eq(0).text() + "="+ $td.eq(1).text());
        options[$td.eq(0).text()] = $td.eq(1).text();
	});

  	$rows2.each(function () {
    	var $td = $(this).find('td');
		console.log($td.eq(0).text() + "="+ $td.eq(1).text());
        options[$td.eq(0).text()] = $td.eq(1).text();
	});

	$.post("update_scoring.php", options, updated_compinfo);
}

function save_formula()
{
	var $rows1 = $('#formula1').find('tr:not(:hidden)');
	var $rows2 = $('#formula2').find('tr:not(:hidden)');
	var $rows3 = $('#formula3').find('tr:not(:hidden)');
	var options = { comPk : comp_details.keys.comPk, forPk : comp_details.keys.forPk };

  	$rows1.each(function () {
    	var $td = $(this).find('td');
		console.log($td.eq(0).text() + "=" + $td.eq(1).text());
        options[$td.eq(0).text()] = $td.eq(1).text();
	});

  	$rows2.each(function () {
    	var $td = $(this).find('td');
		console.log($td.eq(0).text() + "=" + $td.eq(1).text());
        options[$td.eq(0).text()] = $td.eq(1).text();
	});

  	$rows3.each(function () {
    	var $td = $(this).find('td');
		console.log($td.eq(0).text() + "=" + $td.eq(1).text());
        options[$td.eq(0).text()] = $td.eq(1).text();
	});

	$.post("update_formula.php", options, updated_compinfo);
}

function save_task()
{
}

function reset_compinfo()
{
    $('#compinfo1 tbody').html('');
    $('#compinfo2 tbody').html('');
    comp_card(header, json.compinfo);
}

function reset_scoring()
{
    $('#scoring1 tbody').html('');
    $('#scoring2 tbody').html('');
    scoring_card(header, json.scoring);
}

function reset_formula()
{
    $('#formula1 tbody').html('');
    $('#formula2 tbody').html('');
    $('#formula3 tbody').html('');
    formula_card(json.formula);
}
function comp_card(div, info)
{
    // some GAP parameters
    allkeys = Object.keys(info);
    values = Object.values(info);
    for (var tc = 0; tc < allkeys.length-1; tc+=2)
    {
        //$('#compinfo1 tbody').append('<tr><td><b>'+allkeys[tc].substr(3)+'</b></td><td contenteditable="true">'+values[tc]+'</td></tr>');
        //$('#compinfo2 tbody').append('<tr><td><b>'+allkeys[tc+1].substr(3)+'</b></td><td contenteditable="true">'+values[tc+1]+'</td></tr>');
        add_td($('#compinfo1 tbody'), allkeys[tc], values[tc]);
        add_td($('#compinfo2 tbody'), allkeys[tc+1], values[tc+1]);
    }
    if (tc < allkeys.length)
    {
        add_td($('#compinfo1 tbody'), allkeys[tc], values[tc]);
        tc++;
    }
}

function scoring_card(div, info)
{
    // some GAP parameters
    allkeys = Object.keys(info);
    values = Object.values(info);
    for (var tc = 0; tc < allkeys.length-1; tc+=2)
    {
        add_td($('#scoring1 tbody'), allkeys[tc], values[tc]);
        add_td($('#scoring2 tbody'), allkeys[tc+1], values[tc+1]);
    }
    if (tc < allkeys.length)
    {
        add_td($('#scoring1 tbody'), allkeys[tc], values[tc]);
        tc++;
    }
}

function formula_card(info)
{
    // some GAP parameters
    allkeys = Object.keys(info);
    values = Object.values(info);
    for (var tc = 0; tc < allkeys.length-2; tc+=3)
    {
        add_td($('#formula1 tbody'), allkeys[tc], values[tc]);
        add_td($('#formula2 tbody'), allkeys[tc+1], values[tc+1]);
        add_td($('#formula3 tbody'), allkeys[tc+2], values[tc+2]);
    }
    if (tc < allkeys.length-1)
    {
        add_td($('#formula2 tbody'), allkeys[tc+1], values[tc+1]);
        tc++;
    }
    if (tc < allkeys.length)
    {
        add_td($('#formula1 tbody'), allkeys[tc], values[tc]);
    }
}

function task_card(info)
{
    var comPk = url_parameter('comPk');
    for (var tc = 0; tc < info.length; tc++)
    {
         $('#tasktbl tbody').append("<tr><td><a href=\"task.html?comPk="+comPk+'&tasPk='+info[tc].tasPk+"\">" + info[tc].tasDate + '</a></td><td>' + info[tc].tasName + '</td><td>' + info[tc].tasDistance + 
            '<td>' + info[tc].tasStartTime.substr(11,8) + ' - ' + info[tc].tasFinishTime.substr(11,8) + '</td></tr>');
    }
}

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    microAjax('get_comp_details.php' + window.location.search, function(data) {
            var json = JSON.parse(data);

            var header = $('#comp_header');
            var tasks = $('#tasks');

            comp_details = json;

            // comp info
            comp_card(header, json.compinfo);

            scoring_card(header, json.scoring);
        
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

