
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

function updated_compinfo(result)
{
    $('#savespin').removeClass('fa-spinner');
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

function airspace_card(info)
{
    var comPk = url_parameter('comPk');
    for (var tc = 0; tc < info.length; tc++)
    {
         $('#airspace tbody').append("<tr><td><a href=\"task.html?comPk="+comPk+'&tasPk='+info[tc].tasPk+"\">" + info[tc].tasDate + '</a></td><td>' + info[tc].tasName + '</td><td>' + info[tc].tasDistance + 
            '<td>' + info[tc].tasStartTime.substr(11,8) + ' - ' + info[tc].tasFinishTime.substr(11,8) + '</td></tr>');
    }
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
    console.log('check_airspace');
}

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    microAjax('get_task_details.php' + window.location.search, function(data) {
            var json = JSON.parse(data);

            task_details = json;

            // comp info
            task_card($('#taskinfo'), json.taskinfo);

            waypoints_card($('#waypoints'), json.waypoints, json.region);

            populate_waypoints(json.region);
        
            //airspace
            //airspace_card(json.taskinfo);
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

