var onscreen = {};
var time_bounds = Array();
var current = -1;
var pause = 1;
var timer;
var trackid;
var tasPk;
var interval = 5;
var step = interval;

String.prototype.format = function()
{
    var pattern = /\{\d+\}/g;
    var args = arguments;
    return this.replace(pattern, function(capture){ return args[capture.match(/\d+/)]; });
}
function plot_track(jstr)
{
    var track;
    var row;
    var line;
    var body;
    var trklog;
    var polyline;
    var gll;
    var count;
    var color;
    var initials;
    var pngclass;
    var offset;
    var resp;
    var points;
    var lasLat, lasLon, lasAlt, lasTme;
    var bounds;

    count = 1;
    offset = 0;
    resp = JSON.parse(jstr)
    body = resp.track;
    points = resp.points;
    trackid = body["trackid"];
    track = body["track"];
    initials = body["initials"];
    pngclass = body["class"];

    line = Array();
    segments = Array();
    trklog = Array();
    onlen = Object.keys(onscreen).length
    color = (onlen % 7)+1;
    console.log("len=" + onlen + " base_color="+color);

	if (track[0][0] < time_bounds['first'])
	{
		time_bounds['first'] = track[0][0];
	}

    for (row in track)
    {
        lasTme = track[row][0];
        lasLat = track[row][1];
        lasLon = track[row][2];
        lasAlt = (track[row][3]/10);
        if (lasAlt > 255)
        {
            lasAlt = 255;
        }

        if (lasTme < -7200)
        {
            continue;
        }

        //if (count == 1) 
        //{ 
        //    map.setCenter(new google.maps.LatLng(lasLat, lasLon)); 
        //    map.setZoom(13); 
        //}
    
        gll = new L.LatLng(lasLat, lasLon);
        line.push(gll);
        if (!bounds)
        {
            bounds = new L.LatLngBounds();
            bounds.extend(gll);
        }
        trklog.push(track[row]);
    
        if (count % 10 == 0)
        {
            // color & 0x100 >> 2, color &0x010 >> 1, color &0x001
            //polyline = new google.maps.Polyline(line, sprintf("#%02x%02x%02x",lasAlt,color*32%256,color*128%256), 3, 1);
            polyline = new L.Polyline(line, {   
                    color: sprintf("#%02x%02x%02x",lasAlt*((color&0x4)>>2) ,lasAlt*((color&0x2)>>1),lasAlt*(color&0x1)), 
                    weight: 3, 
                    opacity: 1
                });
            polyline.addTo(map);
            segments.push(polyline);
            line = Array();
            line.push(gll);
        }
        count = count + 1;    
    }
    bounds.extend(gll);

	if (lasTme > time_bounds['last'])
	{
		time_bounds['last'] = lasTme;
	}

    polyline = new L.Polyline(line, {   
        color: sprintf("#%02x%02x%02x",lasAlt*((color&0x4)>>2) ,lasAlt*((color&0x2)>>1),lasAlt*(color&0x1)), 
        weight: 3, 
        opacity: 1
    });
    polyline.addTo(map);
    document.getElementById("foo").value = trackid;
    onscreen[trackid] = Object();
    onscreen[trackid]["track"] = trklog;
    onscreen[trackid]["segments"] = segments;
    onscreen[trackid]["initials"] = initials;
    onscreen[trackid]["class"] = pngclass;
    console.log("plot_track: glider="+trackid);
    console.log(onscreen);
}
function add_track(comPk, traPk, interval)
{
    new microAjax("get_track.php?comPk="+comPk+"&trackid="+traPk+"&int="+interval, plot_track);
}
$(document).ready(function() {
    new microAjax("get_airgain_tracks.php" + window.location.search,
        function(data) {
        var comPk = url_parameter('comPk');
        local_tracks = JSON.parse(data);

        $.each(local_tracks["tracks"], function (i, item) {
                add_track(comPk, item, 10);
            });
        });
});

