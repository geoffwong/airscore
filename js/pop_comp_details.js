
var comp_details;

function create_task()
{
    var comPk = url_parameter('comPk');
    var options = { };
    var fd = new FormData();
    fd.append('comPk', comPk);
    fd.append('taskname', $('#taskname').val());
    fd.append('date', $('#date').val());
    fd.append('region', $('#region option:selected').val());
    //fd.append('region', $('#region').val());
    fd.append('userfile', $("#customFile")[0].files[0]);

    $.ajax({
            url: 'add_task.php',  
            type: 'POST',
            enctype: 'multipart/form-data',
            data: fd,
            cache: false,
            contentType: false,
            processData: false,
            timeout:0,
            dataType: "text",
            success: function(data) {
                var result;
                console.log(data);
                try {
                    result = JSON.parse(data);
                }
                catch (e)
                {
                    alert("Upload failed: " + e);
                }
                if (result['result'] == 'ok')
                {
                    var tasPk = result['tasPk'];
                    var url = 'task.html?comPk=' + comPk + '&tasPk=' + tasPk;
                    console.log(url);
                    window.location.replace(url);
                }
                else
                {
                    alert(res.result + ": " + res.error);
                }
            }
        });
}
function updated_compinfo(result)
{
    $('#subspin').removeClass('fa-circle-o-notch');
    $('#subspin').removeClass('fa-spin');
    console.log(result);
}

function save_compinfo()
{
	var $rows1 = $('#compinfo1').find('tr:not(:hidden)');
	var $rows2 = $('#compinfo2').find('tr:not(:hidden)');
	var options = { comPk : comp_details.keys.comPk };

    $('#subspin').addClass('fa-circle-o-notch');
    $('#subspin').addClass('fa-spin');

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
    var header = $('#comp_header');
    $('#compinfo1 tbody').html('');
    $('#compinfo2 tbody').html('');
    comp_card(header, comp_details.compinfo);
}

function reset_scoring()
{
    var header = $('#comp_header');
    $('#scoring1 tbody').html('');
    $('#scoring2 tbody').html('');
    scoring_card(header, comp_details.scoring);
}

function reset_formula()
{
    $('#formula1 tbody').html('');
    $('#formula2 tbody').html('');
    $('#formula3 tbody').html('');
    formula_card(comp_details.formula);
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
    if (!info) 
    {
        return;
    }
    for (var tc = 0; tc < info.length; tc++)
    {
        var st = info[tc].tasStartTime;
        var ft = info[tc].tasFinishTime;
        if (st && st.length > 17)
        {
            st = st.substr(11,8);
        }
        if (ft && ft.length > 17)
        {
            ft = ft.substr(11,8);
        }
        $('#tasktbl tbody').append("<tr><td><a href=\"task.html?comPk="+comPk+'&tasPk='+info[tc].tasPk+"\">" + info[tc].tasDate + '</a></td><td>' + info[tc].tasName + '</td><td>' + info[tc].tasDistance + 
            '<td>' + st + ' - ' + ft + '</td></tr>');
    }
}

function region_modal(info,regPk)
{
    //var res = '<select name="region" id="region" class="form-control">';
    var array = [];

    for (var key in info) {
      array.push({
        name: key,
        value: info[key]
      });
    }

    var sorted = array.sort(function(a, b) {
        return (a.value > b.value) ? 1 : ((b.value > a.value) ? -1 : 0)
    });	

    for (nc = 0; nc < sorted.length; nc++)
    {
        if (regPk == sorted[nc].key)
        {
            $("#region").append('<option value="' + sorted[nc].key + '" selected>' + sorted[nc].value + '</option>');
        }
        else
        {
            $("#region").append('<option value="' + sorted[nc].key + '">' + sorted[nc].value + '</option>');
        }
    }
    res += '</select>';

    //$('#regiondiv').html(res);
}

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    var dstr = (new Date()).toISOString().substring(0,10);
    $('#date').val(dstr);
    microAjax('get_comp_details.php' + window.location.search, function(data) {
            var json = JSON.parse(data);
            console.log(json);

            var header = $('#comp_header');
            var tasks = $('#tasks');

            comp_details = json;

            // comp info
            comp_card(header, json.compinfo);

            scoring_card(header, json.scoring);
        
            console.log('comType='+json.scoring.comType);
            if (json.scoring.comType == "OLC")
            {
                // hide divs
                $('#formrow').hide();
                $('#formula').hide();
                $('#taskrow').hide();
                $('#tasks').hide();
                return;
            }

            // formula
            formula_card(json.formula);

            // tasks
            task_card(json.taskinfo);

            // regions
            region_modal(json.regions, json.keys.regPk);

    });
});

$('#customFile').change(function() {
  var file = $('#customFile')[0].files[0].name;
  $('#selected_task').text(file);
});
