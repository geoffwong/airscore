
var team_details;
var current_team = 0;

function deleted_team(json)
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
    $('#savespin').removeClass('fa-circle-o-notch');
    $('#savespin').removeClass('fa-spin');
    console.log(result);
}

function add_member()
{
    var teaPk = url_parameter('teaPk');
    var options = { };
    options.teaPk = teaPk;
    options.id = $('#waypoint option:selected').val();
    options.how = $('#wpthow option:selected').val();
    options.type = $('#wpttype option:selected').val();
    options.shape = $('#wptshape option:selected').val();
    options.size = $('#wptsize').val();
    console.log(options);

    $('#waypoints tbody').append('<tr>' +
	    '<td style="display:none">' + options.id + '</td><td>' + 
            team_details.region[options.id].rwpName + '</td>' + 
            create_td('tawType', options.type) +
            create_td('tawHow', options.how) +
            create_td('tawShape', options.shape) + 
            '</td><td contenteditable="true" onclick="this.focus(); set_cursor(this);">' + options.size + '</td><td></td><td><b><a href="#" onclick="del_member(this);">&cross;</a></b></td></tr>');
    return;

    $.post('add_team_waypoint.php', options, function (res) {
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

function save_teams()
{
    var $rows1 = $('#teamname').find('tr:not(:hidden)');
    var id = url_parameter('comPk');
    var taskid = team_details.keys.teaPk;
    var options = { comPk : id, teaPk : taskid };

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
    $.post("save_teams.php", options, updated_compinfo);
}

function delete_team()
{
    var id = url_parameter('comPk');
    var teamid = team_details.keys.teaPk;
    var options = { comPk : id, teaPk : teamid };
    console.log(options);

    $.post("delete_team.php", options, deleted_team);
}

function save_team_members()
{
    var $rows1 = $('#memberstbl').find('tr:has(td)');
    var id = url_parameter('comPk');
    var teamid = current_team;
    var options = { comPk : id, teaPk : teamid };
    var members = [ ];
    var count = 1;

      $rows1.each(function () {
        var $cols = $(this).find('td');
        var wptrow = [ ];
        wptrow.push(count);
        count++;
        $cols.each(function(i, item) {
            wptrow.push($(this).text());
        });
        members.push(wptrow);
    });

    options['members'] = JSON.stringify(members);
    console.log(options);

    $('#wptspin').addClass('fa-circle-o-notch');
    $('#wptspin').addClass('fa-spin');
    $.post("save_team_members.php", options, function (res) {
        $('#wptspin').removeClass('fa-circle-o-notch');
        $('#wptspin').removeClass('fa-spin');
        if (res.result == 'unauthorised')
        {
            alert('Unauthorised');
        }
        console.log(res);
    });
}

function reset_teaminfo()
{
    $('#compinfo1 tbody').html('');
    $('#compinfo2 tbody').html('');
    comp_card(header, json.compinfo);
}

function dragme(id)
{
    var idstr = '#' + id;
    console.log(idstr);
    $(idstr).draggable();
}

function in_team(info, pilPk)
{
    teams = Object.keys(info);
    values = Object.values(info);
    var html = '';
    for (var tc = 0; tc < teams.length; tc++)
    {
        for (var pc = 0; pc < values[tc].length; pc++)
        {
            if (values[tc][pc].pilPk == pilPk) return 1;
        }
    }
    return 0;
}

function available_pilots(data)
{
    // list without people in teams already ...
    var pilots = data.pilots;
    var teams = data.teams;
    var available = [];

    for (var tc = 0; tc < pilots.length; tc++)
    {
        if (in_team(teams,pilots[tc].pilPk) == 0)
        {
            available.push(pilots[tc]);
        }
    }

    return available;
}

function pilot_card(data)
{
    // some GAP parameters
    var info = data.pilots;
    var html = '';
    var available = available_pilots(data);
    for (var tc = 0; tc < available.length; tc++)
    {
        idstr = 'pil' + available[tc].pilPk;
        button = '<button id="' + idstr + '" type="button" class="btn btn-light tl-1 draggable" onclick="add_to_team('+available[tc].pilPk+');">' + available[tc].pilLastName + '</button>';
        html = html + button;
    }
    $('#pilots').html(html);
    $('#pilots .draggable').draggable();

    // make draggable
    //for (var tc = 0; tc < info.length; tc)
    //{
    //    idstr = '#pil' + info[tc].pilPk;
    //    $(idstr).draggable();
    //}
}

function team_card(info)
{
    // some GAP parameters
    console.log(info);
    teams = Object.keys(info);
    values = Object.values(info);
    var html = '';
    for (var tc = 0; tc < teams.length; tc++)
    {
        console.log(values[tc]);
        var idstr = 'tea' + values[tc][0].teaPk;
        button = '<button id="' + idstr + '" type="button" class="btn btn-light tl-2" onclick="members_card('+values[tc][0].teaPk+');">' + values[tc][0].teaName + '</button>';
        html = html + button;
        $('#teams').html(html);
    }
}

function del_member(tdiv)
{
    var rowind = tdiv.parentNode.parentNode.parentNode.rowIndex;
    //var rowind = $(tdiv).parent().index();
    console.log('rowind='+rowind);
    var jrow = $('#memberstbl tr:eq('+rowind+')').remove();
}

function set_cursor(div)
{
    var sel = window.getSelection(), range = sel.getRangeAt(0);
    range.setStartAfter(div.childNodes[0]);
    sel.removeAllRanges();
    sel.addRange(range);
}

function add_to_team(pilPk)
{
    var row = {};

    team = team_details.teams[current_team];
    for (var tc = 0; tc < team_details.pilots.length; tc++)
    {
        if (team_details.pilots[tc].pilPk == pilPk)
        {
            //console.log('add_to_team: '+current_team+' '+pilPk + '==' + team_details.pilots[tc].pilPk);
            row.pilPk = pilPk;
            row.teaPk = current_team;
            row.teaName = team_details.teams[current_team][0].teaName;
            row.tepModifier = team_details.pilots[tc].tepModifier;
            row.pilLastName = team_details.pilots[tc].pilLastName;
            row.pilFirstName = team_details.pilots[tc].pilFirstName;
            row.pilSex = team_details.pilots[tc].pilSex;
            row.pilFlightWeight = team_details.pilots[tc].pilFlightWeight;
            if (team[0].pilPk == null) 
            {
                team_details.teams[current_team] = [];
                team = team_details.teams[current_team];
            }
            team.push(row);
            members_card(current_team);
            break;
        }
    }

}

function members_card(teamid)
{
    // some GAP parameters
    team = team_details.teams[teamid];
    set_current_team(teamid, team[0].teaName);
    $('#members tbody').html('');
    for (var tc = 0; tc < team.length; tc++)
    {
        if (team[tc].pilPk == null) break;

        $('#members tbody').append(
            '<tr><td style="display:none">' + team[tc].pilPk + '</td><td>' + 
            team[tc].pilFirstName + ' ' + team[tc].pilLastName + '</td><td>' + 
            team[tc].pilSex + '</td><td>' + 
            team[tc].pilFlightWeight + 
            '</td><td contenteditable="true" onclick="this.focus(); set_cursor(this);">' + team[tc].tepModifier + '</td><td></td><td><b><a href="#" onclick="del_member(this);">&cross;</a></b></td></tr>');
    }
}

function set_current_team(tid, name)
{
    //console.log("set_current_team="+name);
    current_team = tid;
    $('#current_members').text('Members:' + name);
}

function add_team()
{
    var comPk = url_parameter('comPk');
    var options = { };
    options.comPk = comPk;
    options.name = $('#teamname').val();
    //console.log(options);

    $.post('add_team.php', options, function (res) {
        console.log(res);

        // add waypoint to table
        //$('#teams tbody').append(res.teaPk + res.teamname);
        if (res['result'] != 'ok')
        {
            alert(res['result']);
            return;
        }
        html = $('#teams').html();
        var idstr = 'tea' + res.teaPk;
        button = '<button id="' + idstr + '" type="button" class="btn btn-light tl-2" onclick="members_card('+res.teaPk+');">' + res.teaName + '</button>';
        html = html + button;
        $('#teams').html(html);
        var T = {};
        T.teaPk = res.teaPk;
        T.teaName = res.teaName;
        //P.pilPk = P.pilLastName = P.pilFirstName = P.pilSex = P.pilFlightWeigh TP.tepModifier
        team_details.teams[res.teaPk] = [ T ];
        members_card(res.teaPk);
    });
}

function populate_members(info)
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

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    $.post('get_team_details.php' + window.location.search, function(data) {
            //var json = JSON.parse(data);
            team_details = data;

            // comp info
            pilot_card(team_details);
            team_card(team_details.teams);
            //populate_members(team_details.teams);
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

$("#memberstbl tbody").sortable({
        helper: fixHelperModified,
        stop: updateIndex,
        distance: 5,
        delay: 100,
        opacity: 0.6,
        cursor: 'move',
        update: function() {}
    }).disableSelection();

