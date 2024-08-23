
var comp_details;

function create_task()
{
    var comPk = url_parameter('comPk');
    var options = { };
    var fd = new FormData();
    fd.append('comPk', comPk);
    fd.append('taskname', $('#taskname').val());
    fd.append('date', $('#date').val());
    fd.append('createwpts', $('#createwpts').val());
    fd.append('region', $('#region option:selected').val());
    //fd.append('region', $('#region').val());
    fd.append('userfile', $("#customFile")[0].files[0]);
    //console.log(fd);

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
                    alert(result.result + ": " + result.error);
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
    	var td = $(this).find('td');
		//console.log($td.eq(0).text() + "="+ $td.eq(1).text());
		var sel = td.eq(1).find('span');
		if (sel.length > 0)
		{
			console.log(sel.eq(0).attr('key'));
        	options[td.eq(0).text()] = sel.eq(0).attr('key');
		}
		else
		{
        	options[td.eq(0).text()] = td.eq(1).text();
		}
	});

  	$rows2.each(function () {
    	var td = $(this).find('td');
		//console.log($td.eq(0).text() + "=" + $td.eq(1).value());
		var sel = td.eq(1).find('select');
		if (sel.length > 0)
		{
        	options[td.eq(0).text()] = sel.val();
		}
		else
		{
        	options[td.eq(0).text()] = td.eq(1).text();
		}
	});

	console.log(options);
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

function export_fsdb()
{
    var comPk = url_parameter('comPk');
    window.location.replace('download_fsdb.php?comPk='+comPk);
//    $.get('download_fsdb.php?comPk='+comPk);
//    $.ajax({
//            url: 'download_fsdb.php?comPk='+comPk,  
//            type: 'GET',
//            enctype: 'multipart/form-data',
//            cache: false,
//            contentType: false,
//            processData: false,
//            timeout:0,
//            dataType: "xml",
//            success: function(data) {
//                console.log("Success");
//            }
//        });
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
function kvoptions(name, value)
{
    var res = '';
    var nc;

    for (var key in all_enums[name]) 
	{
        if (value == key)
        {
            res += '<option value="' + all_enums[name][key] + '" selected>' + key + '</option>';
        }
        else
        {
            res += '<option value="' + all_enums[name][key] + '">' + key + '</option>';
        }
    }
    return res;
}
function kvspan(txt,val)
{
	return '<span key="'+val+'">'+txt+'</span>';
}
function td_kv_blur(event)
{
    var src = event.srcElement;
    var val = src.options[src.selectedIndex].value;
    var txt = src.options[src.selectedIndex].text;
	console.log("blur text="+txt);
	console.log("blur val="+val);
    src.parentElement.innerHTML = kvspan(txt,val);
}
function kvselectable(name, value)
{
    if (all_enums.hasOwnProperty(name))
    {
        var res = '<select id="' + name +'" class="form-control form-control-sm" placeholder=".form-control-sm" onblur="td_kv_blur(event);">';
        res += kvoptions(name,value);
        res += '</select>';
        return res;
    }
    else
    {
		console.log("unknown all_nums key="+name);
        return undefined;
    }
}
function td_edit_select(event,item)
{
    if (event.srcElement.nodeName == 'TD')
    {
        var val = event.srcElement.innerText;
        var sel = kvselectable(item,val);
        event.srcElement.innerHTML = sel;
    }
}
function add_td_select(div, field, selarr, val)
{
    if (!all_enums.hasOwnProperty(field))
	{
		all_enums[field] = selarr;
	}
	var span = kvspan(val,all_enums[field][val]);
    div.append('<tr><td><b>'+field+"</b></td><td onclick=\"td_edit_select(event,'"+field+"');\">"+span+'</td></tr>');
}
function comp_card(div, info, regions)
{
    // some GAP parameters
    var next = '#compinfo1 tbody';
    allkeys = Object.keys(info);
    values = Object.values(info);
    var trim = -1;
    var regPk = 0;

    for (var tc = 0; tc < allkeys.length; tc++)
    {
        console.log(allkeys[tc]);
        if (allkeys[tc] == 'comRegion')
        {
            trim = tc;
            regPk = values[tc];
            console.log('Region='+regPk);
        }
    }

    if (trim != -1)
    {
        allkeys.splice(trim,1);
        values.splice(trim,1);
    }

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
        next = '#compinfo2 tbody';
    }
    // create a drop down for regions
    for (var key in regions) 
    {
        if (regions[key] == regPk)
        {
            add_td_select($(next), 'Region', regions, key);
        }
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
        add_td($('#formula2 tbody'), allkeys[tc], values[tc]);
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
        key: info[key],
        value: key
      });
    }

    var sorted = array.sort(function(a, b) {
        return (a.value.toLowerCase() > b.value.toLowerCase()) ? 1 : ((b.value.toLowerCase() > a.value.toLowerCase()) ? -1 : 0)
    });	

    for (nc = 0; nc < sorted.length; nc++)
    {
        //console.log("key="+sorted[nc].key+" value="+sorted[nc].value);
        if (regPk == sorted[nc].value)
        {
            //$("#region").append('<option value="' + sorted[nc].key + '" selected>' + sorted[nc].value + '</option>');
            $('#region').append($('<option>', {
                value: sorted[nc].key,
                text: sorted[nc].value,
                selected: true
            }));
        }
        else
        {
            //$("#region").append('<option value="' + sorted[nc].key + '">' + sorted[nc].value + '</option>');
            $('#region').append($('<option>', {
                value: sorted[nc].key,
                text: sorted[nc].value
            }));
        }
    }
    //res += '</select>';

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
            comp_card(header, json.compinfo, json.regions);

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
