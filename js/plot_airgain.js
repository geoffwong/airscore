
String.prototype.format = function()
{
    var pattern = /\{\d+\}/g;
    var args = arguments;
    return this.replace(pattern, function(capture){ return args[capture.match(/\d+/)]; });
}
function plot_waypoints(jstr)
{
    var resp = JSON.parse(jstr)
    var made = resp["made"];
    var missed = resp["missed"];
    //var pngclass = body["class"];

    //@todo: plot_track_header(resp['pilot']);

    var bounds = new L.LatLngBounds();
    plot_track_wp(made, '#cccccc', 'waypoint', bounds);
    plot_track_wp(missed, '#cc0000', 'missedwpt', bounds);
}
function plot_track_header(body)
{
    var ihtml;
    var ovlay;
    // FIX: should show already awarded ones ...
    ihtml = "<div class=\"trackInfo\"><b>" + body["name"] + "</b><br>\n";
    ihtml = ihtml + body["date"] + "<br>";
    ihtml = ihtml + body["glider"] + "<br>";
    if (body["goal"])
    {
        ihtml = ihtml + body["dist"] + "km<br>Goal: " + body["goal"] + "<br>\n";
    }
    else
    {
        ihtml = ihtml + body["dist"] + "km, " + body["duration"] + "<br>\n";
    }
    if (body["comment"])
    {
        ihtml = ihtml + body["comment"] + "<br>\n";
    }
    ihtml = ihtml + "</div>";
    add_panel(map, "topright", ihtml, 'map_panel');
}
function plot_track_wp(track, clor, css, bounds)
{
    var row;
    var gll;
    var count = 1;
    var pos;
    var wpt;

    var keys = Object.keys(track);
    var len = keys.length;
    console.log('len='+len);
    
    if (len < 1)
    {
        return;
    }

    var bounds = new L.LatLngBounds();
    for (var row = 0; row < len; row++)
    {
        lasLat = track[keys[row]][0];
        lasLon = track[keys[row]][1];
        cname = track[keys[row]][2];

        gll = new L.LatLng(lasLat, lasLon);
        bounds.extend(gll);
    
 		var circle = new L.Circle(gll, {
            radius:400,
			color:clor,
			opacity:1.0,
			weight:1.0, 
			fillColor:clor,
			fillOpacity:0.2,
		}).addTo(map);

        count = count + 1;    
        pos = new L.LatLng(lasLat,lasLon);
        wpt = add_label(map, pos, cname, css);
    }
    map.fitBounds(bounds);
}

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    var pilPk = url_parameter('pilPk');

    var mapdiv = document.getElementById("map");
    mapdiv.setAttribute('style', 'top: 0px; left: 0px; width:100%; height:90vh; float: left');
    map = add_map_server('map', 0);
    add_map_extra(map);
    add_play_controls(map);

    new microAjax("get_airgain.php?comPk="+comPk+"&pilPk="+pilPk, plot_waypoints);
});

