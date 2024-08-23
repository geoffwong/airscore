
var task_details;

function deleted_task(json)
{
    console.log(json);
    if (json.result == 'ok')
    {
        var comPk = json.comPk;
        window.location.replace("competition.html?comPk="+comPk);
        return;
    }

    if (json.result == 'unauthorised')
    {
        alert('Unauthorised to delete task');
        return;
    }

    alert(json.error);
}

function scored_task(json)
{
    $('#scorespin').removeClass('fa-circle-o-notch');
    $('#scorespin').removeClass('fa-spin');
    console.log(json);
    if (json.result == 'ok')
    {
        var comPk = json.comPk;
        var tasPk = json.tasPk;
        window.location.replace("task_result.html?comPk="+comPk+"&tasPk="+tasPk);
        return;
    }

    if (json.result == 'unauthorised')
    {
        alert('Unauthorised to delete task');
        return;
    }

    alert(json.error);
}

function updated_compinfo(result)
{
    $('#savespin').removeClass('fa-circle-o-notch');
    $('#savespin').removeClass('fa-spin');
    console.log(result);
}

function add_waypoint()
{
    var tasPk = url_parameter('tasPk');
    var options = { };
    options.tasPk = tasPk;
    options.id = $('#waypoint option:selected').val();
    options.how = $('#wpthow option:selected').val();
    options.type = $('#wpttype option:selected').val();
    options.shape = $('#wptshape option:selected').val();
    options.size = $('#wptsize').val();
    console.log(options);

    $('#waypoints tbody').append('<tr>' +
	    '<td style="display:none">' + options.id + '</td><td>' + 
            task_details.region[options.id].rwpName + '</td>' + 
            create_td('tawType', options.type) +
            create_td('tawHow', options.how) +
            create_td('tawShape', options.shape) + 
            '</td><td contenteditable="true" onclick="this.focus(); set_cursor(this);">' + options.size + '</td><td></td><td><b><a href="#" onclick="del_waypoint(this);">&cross;</a></b></td></tr>');
    return;

    $.post('add_task_waypoint.php', options, function (res) {
        console.log(res);

        var url;
        var tawPk = res.tawPk;
        if (!tawPk || res.result != "ok")
        {
            alert(res.result + ": " + res.error);
            return;
        }
        // add waypoint to table

    });
}

function save_task()
{
    var $rows1 = $('#taskinfo1').find('tr:not(:hidden)');
    var $rows2 = $('#taskinfo2').find('tr:not(:hidden)');
    var id = url_parameter('comPk');
    var taskid = task_details.keys.tasPk;
    var options = { comPk : id, tasPk : taskid };

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

    $('#savespin').addClass('fa-circle-o-notch');
    $('#savespin').addClass('fa-spin');
    $.post("update_task.php", options, updated_compinfo);
}

function score_task()
{
    var id = url_parameter('comPk');
    var taskid = task_details.keys.tasPk;
    var options = { comPk : id, tasPk : taskid };
    console.log(options);

    $('#scorespin').addClass('fa-circle-o-notch');
    $('#scorespin').addClass('fa-spin');
    $.post("score_task.php", options, scored_task);
}

function delete_task()
{
    var id = url_parameter('comPk');
    var taskid = task_details.keys.tasPk;
    var options = { comPk : id, tasPk : taskid };
    console.log(options);

    $.post("delete_task.php", options, deleted_task);
}

function save_waypoints()
{
    var $rows1 = $('#waypointstbl').find('tr:has(td)');
    var id = url_parameter('comPk');
    var taskid = task_details.keys.tasPk;
    var options = { comPk : id, tasPk : taskid };
    var wptarr = [ ];
    var count = 1;

      $rows1.each(function () {
        var $cols = $(this).find('td');
        var wptrow = [ ];
        wptrow.push(count);
        count++;
        $cols.each(function(i, item) {
            wptrow.push($(this).text());
        });
        wptarr.push(wptrow);
    });

    options['waypoints'] = JSON.stringify(wptarr);
    console.log(options['waypoints']);

    $('#wptspin').addClass('fa-circle-o-notch');
    $('#wptspin').addClass('fa-spin');
    $.post("save_task_waypoints.php", options, function (res) {
        $('#wptspin').removeClass('fa-circle-o-notch');
        $('#wptspin').removeClass('fa-spin');
        console.log(res);
    });
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

function del_waypoint(tdiv)
{
    var rowind = tdiv.parentNode.parentNode.parentNode.rowIndex;
    //var rowind = $(tdiv).parent().index();
    console.log('rowind='+rowind);
    var jrow = $('#waypointstbl tr:eq('+rowind+')').remove();
}

function set_cursor(div)
{
    var sel = window.getSelection(), range = sel.getRangeAt(0);
    range.setStartAfter(div.childNodes[0]);
    sel.removeAllRanges();
    sel.addRange(range);
}

function waypoints_card(div, info, region)
{
    // some GAP parameters
    allkeys = Object.keys(info);
    values = Object.values(info);
    for (var tc = 0; tc < allkeys.length; tc++)
    {
        $('#waypoints tbody').append('<tr><td style="display:none">' +
            values[tc].rwpPk + '</td><td>' + 
            region[values[tc].rwpPk].rwpName + '</td>' + 
            create_td('tawType', values[tc].tawType) +
            create_td('tawHow', values[tc].tawHow) +
            create_td('tawShape', values[tc].tawShape) + 
            '</td><td contenteditable="true" onclick="this.focus(); set_cursor(this);">' + values[tc].tawRadius + '</td><td></td><td><b><a href="#" onclick="del_waypoint(this);">&cross;</a></b></td></tr>');
    }
}

function add_airspace()
{
    var tasPk = url_parameter('tasPk');
    var comPk = url_parameter('comPk');

    var fd = new FormData();
    fd.append('tasPk', tasPk);
    fd.append('comPk', comPk);
    fd.append('airPk', $('#airspacesel option:selected').val());
    fd.append('userfile', $("#customFile")[0].files[0]);
    console.log(fd);

    $.ajax({
            url: 'add_task_airspace.php',  
            type: 'POST',
            enctype: 'multipart/form-data',
            data: fd,
            cache: false,
            contentType: false,
            processData: false,
            timeout:0,
            dataType: "json",
            success: function(result) {
                console.log(result);
                if (result['result'] == 'ok')
                {
                    var tasPk = result.tasPk;
                    // add airspace(s) to table
                    for (var ac = 0; ac < result.airspace.length; ac++)
                    {
                        //alert("ac=" + result.airspace[ac][0]);
                        $('#airspace tbody').append("<tr><td><a href=\"airspace_map.html?airPk="+result.airspace[ac][0]+"\">" + result.airspace[ac][1] + '</a></td><td>' + result.airspace[ac][2] + '</td><td>' + result.airspace[ac][3] + '<td>' + result.airspace[ac][4] + '</td></tr>');
                    }
                }
                else
                {
                    alert(result.result + ": " + result.error);
                }
            }
        });

    //$.post('add_task_airspace.php', options, function (res) { console.log(res); });
}

function airspace_card(info)
{
    for (var tc = 0; tc < info.length; tc++)
    {
         $('#airspace tbody').append("<tr><td><a href=\"airspace_map.html?airPk="+info[tc].airPk+"\">" + info[tc].airName + '</a></td><td>' + info[tc].airClass + '</td><td>' + info[tc].airBase + '<td>' + info[tc].airTops + '</td></tr>');
    }
}

function populate_airspace_selection(res)
{
    var optres = '';
    for (var tc = 0; tc < res.length; tc++)
    {
        optres += '<option value="' + res[tc][1] + '">' + res[tc][0] + '</option>';
    }
    $('#airspacesel').append(optres);
}

function populate_waypoints(info)
{
    var type = options('tawType', '');
    $('#wpttype').append(type);
    var how = options('tawHow', '');
    $('#wpthow').append(how);
    var shape = options('tawShape', '');
    $('#wptshape').append(shape);

    var res = '';
    var nc;
    var allkeys = Object.keys(info);
    for (nc = 0; nc < allkeys.length; nc++)
    {
        res += '<option value="' + allkeys[nc] + '">' + info[allkeys[nc]].rwpName + '</option>';
    }
    $('#waypoint').append(res);
}

function check_airspace()
{
    var tasPk = url_parameter('tasPk');
    var url = 'check_airspace.php?tasPk=' + tasPk;
    console.log(url);
    window.location.replace(url);
}

$(document).ready(function() {
    var tasPk = url_parameter("tasPk");
    microAjax('get_task_details.php' + window.location.search, function(data) {
            var json = JSON.parse(data);

            task_details = json;

            // comp info
            task_card($('#taskinfo'), json.taskinfo);

            waypoints_card($('#waypoints'), json.waypoints, json.region);

            populate_waypoints(json.region);
        
            //airspace
            airspace_card(json.airspace);
    });

    $.post('get_nearby_airspace.php' + window.location.search, function (res) {
        populate_airspace_selection(res);
    });
});

var fixHelperModified = function(e, tr) 
    {
        var $originals = tr.children();
        var $helper = tr.clone();
        $helper.children().each(function(index) {
            $(this).width($originals.eq(index).width())
        });
        return $helper;
    },
    updateIndex = function(e, ui) {
            $('td.index', ui.item.parent()).each(function (i) {
                $(this).html(i + 1);
            });
        };

$("#waypointstbl tbody").sortable({
        helper: fixHelperModified,
        stop: updateIndex,
        distance: 5,
        delay: 100,
        opacity: 0.6,
        cursor: 'move',
        update: function() {}
    }).disableSelection();

