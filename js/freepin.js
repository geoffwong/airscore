function added_pin(row)
{
    //row = JSON.parse(res);

    if (row.result == 'ok')
    {
        var url = "tracklog_map.html?comPk=" + row.comPk + "&trackid=" + row.traPk;
        var tasPk = row.tasPk;
        if (tasPk)
        {
            url = url + '&tasPk=' + tasPk;
        }
        console.log(url);
        window.location.replace(url);
    }
    else
    {
        alert("Pin drop failed: " + row.result);
    }
}
function submit_pin()
{
    var comPk = url_parameter('comPk');
    var hgfa = $('#hgfanum').val();
    var name = $('#lastname').val();
    var glider = $('#glider').val();
    var dhv = $('#dhv').val();
    var safety = $('#pilotsafety').val();
    var quality = $('#pilotquality').val();
    var lat = $('#lat').val();
    var lon = $('#lon').val();
    var tasPk = $('#task').val();

    console.log('hgfa='+hgfa);
    console.log('name='+name);
    console.log('safety='+safety);
    $.post("add_pin.php", { 'comPk': comPk, 'tasPk': tasPk, 'lastname': name, 'hgfanum': hgfa, 'glider' : glider, 'dhv': dhv, 'lon': lon, 'lat': lat, 'pilotsafety': safety, 'pilotquality': quality }, added_pin);
}

var pin_marker;
function create_pin_marker()
{
    var bounds = map.getBounds();
    var centre = map.getCenter();

    $('#addwpt').text('Drop Pin');
    $('#rwpPk').val(0);
    $('#wptid').val('');
    $('#wptdesc').val('');
    $('#delwpt').hide();
    pin_marker = L.marker(centre, { 
            draggable: true,
            autoPan: true
        }).addTo(map);

    pin_marker.on('dragend', function(event) {
            var position = pin_marker.getLatLng();
            $('#lat').val(position.lat);
            $('#lon').val(position.lng);
            $('#addwpt').text('Drop Pin');
            $('#delwpt').hide();
        });

}
function add_tasks(comPk)
{
    console.log('add_tasks=' + comPk);
    new microAjax("get_pin_tasks.php?comPk="+comPk,
    function(data) {
        console.log('data=' + data);
        var tasks = JSON.parse(data);
        $('#task option').remove();
        for (var row = 0; row < tasks.length; row++)
        {
            $('#task').append("<option value=\""+tasks[row][0]+"\">" + tasks[row][2] + ' ' + tasks[row][1] + "</option>");
        }
    });
}
function plot_region_wp(comPk)
{
    var row;
    var polyline;
    var gll;
    var count;
    var pos;
    var wpt;

 // FIX: should show already awarded ones ...
    new microAjax("get_waypoints.php?comPk="+comPk,
    function(data) {
    var region;
    var tps;
    var track;
    var incpk;
    var ovlay;
    var ovhtml;
    var cnt;
    var end = 0;
    var lasLat;
    var lasLon;
    var centre;

    var region = JSON.parse(data);
    track = region.waypoints;
    centre = region.region[0];
    count = 1;
    bounds = new L.LatLngBounds();
    for (var row = 0; row < track.length; row++)
    {
        lasLat = track[row].lat;
        lasLon = track[row].lon;
        var cname = track[row].name;
        var desc = track[row].desc;
        var rwpPk = track[row].rwpPk;

        gll = new L.LatLng(lasLat, lasLon);
        bounds.extend(gll);
        if (pin_marker && (centre == rwpPk))
        {
            pin_marker.setLatLng(gll);
        }
        wpt = add_label(map, gll, cname, "waypoint", [ 60, 17 ]);
        L.Util.setOptions(wpt.options.icon, { 'id' : rwpPk, 'name' : cname, 'desc' : desc });
        //wpt.properties.wptid = cname;
    }

    map.fitBounds(bounds);
    });
}
$(document).ready(function() {
    var comPk = url_parameter("comPk");

    var mapdiv = document.getElementById("map");
    mapdiv.setAttribute('style', 'top: 0px; left: 0px; width:100%; height:90vh; float: left');
    map = add_map_server('map', 0);
    add_map_extra(map);
    add_tasks(comPk);
    create_pin_marker();
    plot_region_wp(comPk);
    sidebar.show();
    L.control.ruler().addTo(map);
});

