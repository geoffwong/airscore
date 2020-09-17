var onscreen = Array();
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
function merge_tracks(tasPk, traPk, incPk)
{
    new microAjax("merge_track.php?tasPk="+tasPk+"&traPk="+traPk+"&incPk="+incPk, function(data) 
        { 
            window.location.href="tracklog_map.html?trackid="+traPk;
        } );
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
    plot_track_header(body);
    track = body["track"];
    initials = body["initials"];
    pngclass = body["class"];

    line = Array();
    segments = Array();
    trklog = Array();
    color = (onscreen.length % 7)+1;

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
    //polyline = new google.maps.Polyline(line, sprintf("#%02x%02x%02x",lasAlt*(color&0x100>>2) ,lasAlt*(color&0x010>>1),lasAlt*(color&0x1)), 3, 1);
    //map.addOverlay(polyline);
    //segments.push(polyline);
    document.getElementById("foo").value = trackid;
    onscreen[trackid] = Array();
    onscreen[trackid]["track"] = trklog;
    onscreen[trackid]["segments"] = segments;
    onscreen[trackid]["initials"] = initials;
    onscreen[trackid]["class"] = pngclass;
    
    if (!body.tasPk)
    {
        if (points.length > 0)
        {
            plot_track_wp(body, points);
        }
        else
        {
            map.fitBounds(bounds);
        }
    }
}
function add_track(comPk, traPk, interval)
{
    new microAjax("get_track.php?comPk="+comPk+"&trackid="+traPk+"&int="+interval, plot_track);
}
function do_add_track()
{
    var e = document.getElementById("tracks");
    trackid = e.options[e.selectedIndex].value;
    var comPk = url_parameter('comPk');
    if (!onscreen[trackid])
    {
        add_track(comPk, trackid, 5);
    }
}
function plot_track_bounds(jstr)
{
    var trk;

    plot_track(jstr);
    bounds = new L.LatLngBounds();
    trk = onscreen[trackid]["track"];
    for (row in trk)
    {
        lasLat = trk[row][1];
        lasLon = trk[row][2];
        gll = new L.LatLng(lasLat, lasLon);
        bounds.extend(gll);
    }
    map.fitBounds(bounds);
}
function plot_region(jstr)
{
    var task;
    var track;
    var row;
    var count;
    var pos;
    var sz;

    count = 1;
    document.getElementById("foo").value = "plot_region";
    task = JSON.parse(jstr)
    track = task["region"];
    for (row in track)
    {
        lasLat = track[row][0];
        lasLon = track[row][1];
        cname =  track[row][2];
        crad = track[row][3];

        if (count == 1)
        {
            map.setCenter(new L.LatLng(lasLat, lasLon), 13);
        }
    
        count = count + 1;    

        pos = new L.LatLng(lasLat,lasLon);
        //overlay = new ELabel(map, pos, cname, "waypoint", new google.maps.Size(0,0), 60);
        add_label(map, pos, cname, "waypoint");

        if (crad > 0)
        {
            sz = GSizeFromMeters(map, pos, crad*2,crad*2);
            overlay = new EInsert(map, pos, "circle.png", sz, map.getZoom());
        }

    }

    //document.getElementById("foo").value = trackid;
    return task;
}
function plot_award_task(tasPk, trackid)
{
    // FIX: should show already awarded ones ...
    microAjax("get_track_progress.php?tasPk="+tasPk+"&trackid="+trackid,
    function(data) {
    var task;
    var tps;
    var track;
    var incpk;
    var ovlay;
    var ovhtml;
    var cnt;
    var end = 0;

    var task = JSON.parse(data);
    ovhtml = "<div class=\"htmlControl\"><b>Award Points</b><br><form name=\"trackdown\" method=\"post\">\n";
    track = task["task"];
    tps = 0 + task["turnpoints"];
    incpk = task["merge"];
    cnt = 0;
    plot_waypoints(track);
    // fix to show awarded points - unclick to unaward ..
    for (row in track)
    {
        cnt = cnt + 1;
        if (cnt > tps)
        {
            name = track[row]['rwpName'];
            tawtype = track[row]['tawType'];
            tawPk = track[row]['tawPk'];
            if ((end == 0) && (tawtype == 'endspeed' || tawtype == 'goal'))
            {
                ovhtml = ovhtml +  cnt + ". <input onblur=\"x_award_waypoint(" + tasPk + "," + tawPk + "," + trackid + ",this.value,done)\" type=\"text\" name=\"goaltime\" size=5>&nbsp;" + name + "<br>";
                end = 1;
            }
            else if (tawtype == 'speed')
            {
                ovhtml = ovhtml +  cnt + ". <input onblur=\"x_award_waypoint(" + tasPk + "," + tawPk + "," + trackid + ",this.value,done)\" type=\"text\" name=\"goaltime\" size=5>&nbsp;" + name + "<br>";
            }
            else
            {
                ovhtml = ovhtml +  cnt + ". <input type=\"checkbox\" name=\"turnpoint\" onclick=\"x_award_waypoint(" + tasPk + "," + tawPk + "," + trackid + "," + cnt + ",done)\">&nbsp;" + name + "<br>";
            }
        }
    }
    // add in a 'merge with' option?
    if (incpk > 0)
    {
        ovhtml = ovhtml + "<br><center><input type=\"button\" name=\"domerge\" value=\"Merge "+incpk+"\" onclick=\"merge_tracks("+tasPk+","+trackid+","+incpk+");\"></center>";
    }
    ovhtml = ovhtml + "</form></div>";
    add_panel(map, "bottomright", ovhtml, 'map_control');
    });
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
function plot_track_wp(body, track)
{
    var row;
    var line;
    var polyline;
    var gll;
    var count;
    var pos;
    var wpt;

    if (track.length < 1)
    {
        return;
    }
    count = 1;
    line = Array();
    segments = Array();
    bounds = new L.LatLngBounds();
    for (var row = 0; row < track.length; row++)
    {
        lasLat = track[row][0];
        lasLon = track[row][1];
        if (track[row].length > 2)
        {
            cname = track[row][2];
        }
        else
        {
            cname = "" + count;
        }

        gll = new L.LatLng(lasLat, lasLon);
        line.push(gll);
        bounds.extend(gll);
    
        if (body['formula_version'] == 'airgain-count')
        {
            //var sz = GSizeFromMeters(map, gll, 400*2, 400*2);
            //overlay = new EInsert(map, gll, "circle.png", sz, map.getZoom());
			var clor = '#cccccc'
     		var circle = new L.Circle(gll, {
                radius:400,
				color:clor,
				opacity:1.0,
				weight:1.0, 
				fillColor:clor,
				fillOpacity:0.2,
			}).addTo(map);
        }
        else
        {
            if (count % 10 == 0)
            {
                polyline = new L.Polyline(line, {
                    color:"#ff0000", weight:2, opacity:1 }).addTo(map);
                segments.push(polyline);
                line = Array();
                line.push(gll);
            }
        }
        count = count + 1;    

        pos = new L.LatLng(lasLat,lasLon);
        wpt = add_label(map, pos, cname, "waypoint");
    }

    if (body['formula_version'] != 'airgain-count')
    {
        polyline = new L.Polyline(line, { color:"#ff0000", weight:2, opacity:1 }).addTo(map);
        segments.push(polyline);
    }

    map.fitBounds(bounds);
}
function plot_glider(glider,lat,lon,alt)
{
    // FIX: plot a point ...
    var mark;
    var html;
    var off;

    //var para = L.icon({
        //iconUrl: (onscreen[glider]["ic"]%6) + ".png",
        //iconSize: [38, 95],
        //iconAnchor: [10, 20],
        //popupAnchor: [-3, -76],
    //});
    //L.marker([50.505, 30.57], {icon: myIcon}).addTo(map);

    // load image ...
    html = "<div id=\"" + 'gl' + glider + "\"><center>" + onscreen[glider]["initials"] + "<br><img src=\"images/" + onscreen[glider]["class"] + (onscreen[glider]["ic"]%7) + ".png\"></img><br>"+Math.floor(alt/10)+"</center></div>";

    //if (alt > 999) { off = new google.maps.Size(-8,16); } else { off = new google.maps.Size(-12,16); }

    if (!onscreen[glider]["icon"])
    {
        var pos = new L.LatLng(lat,lon);
        mark = add_label(map, pos, html, "animate", [26,48], [13,38]);   //off, 100
        onscreen[glider]["icon"] = mark;
    }
    else
    {
        $("#gl" + glider).html(html);
        onscreen[glider]["icon"].setLatLng(new L.LatLng(lat,lon));
    }
}
function animate_update()
{
    var count;
    var lasLat, lasLon, lasAlt, lasTme;
    var flag;

    if (step < 0)
    {
        current = current + step;
    }
    for (glider in onscreen)
    {
        track = onscreen[glider]["track"];
        count = onscreen[glider]["pos"];
        lasLat = 0;
        if (count >= 0 && count < track.length)
        {
            lasTme = track[count][0];
            //console.log('count='+count+' lasTme='+lasTme+' current='+current);
            flag = 1;
            // $('#clock').html(lasTme);
            while ((lasTme*step < current*step) && (count < track.length))
            {
                lasTme = track[count][0];
                lasLat = track[count][1];
                lasLon = track[count][2];
                lasAlt = track[count][3];
                //lasAlt = (track[row][3]/10)%256;
                if (step < 0)
                {
                    count--;
                }
                else
                {
                    count++;
                }
            }
            if (lasLat != 0)
            {
                onscreen[glider]["pos"] = count;
                plot_glider(glider,lasLat,lasLon,lasAlt);
            }
        }
    }
    $('#clock').html(format_seconds(current*interval));
	var range = time_bounds['last'] - time_bounds['first'];
	var perc = (current - time_bounds['first']) * 100 / range;
	$('#playslider').val(perc);

    if (step > 0)
    {
        current = current + step;
    }
    //document.getElementById("foo").value = current;
    if (flag == 1 && pause == 0)
    {
        timer=setTimeout("animate_update()",250);
    }
}
function animate_init()
{
    var mintime;
    var ic=0;

    mintime = 999999;
    for (row in onscreen)
    {
        if (onscreen[row]["track"][0][0] < mintime)
        {
            mintime = onscreen[row]["track"][0][0];
            console.log('mintime='+mintime);
        }
        onscreen[row]["pos"] = 0;
        onscreen[row]["icon"] = 0;
        onscreen[row]["ic"] = ic++;
    }
    current = mintime;
}
function restart()
{
    clearTimeout(timer);
    if (!pause)
    {
        $('#fwd').removeClass("fa-pause");
        $('#fwd').addClass("fa-play");
    }
    pause = 1;
    step = interval;

    // clear current icons;
    for (glider in onscreen)
    {
        if (onscreen[glider]["icon"])
        {
            onscreen[glider]["icon"].remove(null);
        }
    }
    $('#clock').html("00:00:00");

    animate_init();
    //current = -1;
}
function forward()
{
    step = interval;
    if (pause)
    {
        //$('#fwd').html("&#8214;");
        var tm = $('#clock').html();
        if (tm == '00:00:00' || tm == '')
        {
            animate_init();
        }
        else
        {
            var nc = clock2seconds(tm) / interval;
            if (nc != current)
            {
                current = nc;
                animate_update();
            }
        }

        $('#fwd').removeClass("fa-play");
        $('#fwd').addClass("fa-pause");
    }
    else
    {
        //$('#fwd').html("&gt;");
        $('#fwd').removeClass("fa-pause");
        $('#fwd').addClass("fa-play");
    }
    pause = !pause;
    clearTimeout(timer);
    animate_update();
}
function fast_forward()
{
    step = step * 2;
    clearTimeout(timer);
    animate_update();
}
function backward()
{
    pause = !pause;
    step = - interval;
    clearTimeout(timer);
    animate_update();
}
function clear_map()
{
    var segments;

    // Remove line segments ..
    for (row in onscreen)
    {
        segments = onscreen[row]["segments"];
        for (i in segments)
        {
            segments[i].setMap(null);
        }
        if (onscreen[glider]["icon"])
        {
            onscreen[glider]["icon"].setMap(null);
        }
    }

    // clear it ..
    onscreen = Array();
}
function set_clock()
{
    if (!pause)
    {
        $('#fwd').removeClass("fa-pause");
        $('#fwd').addClass("fa-play");
    }
    pause = 1;
    $('#clock').attr('contenteditable','true');
    $('#clock').focus();
}
function download_track()
{
    var traPk = url_parameter('trackid');
    post('download_tracks.php?traPk=' + traPk, { }, 'post');
}
function download_top_tracks()
{
    var tasPk = url_parameter('tasPk');
    if (tasPk > 0)
    {
        post('download_tracks.php', { 'tasPk' : tasPk, 'count' : 20 }, 'post');
    }
}
function download_all_tracks()
{
    var tasPk = url_parameter('tasPk');
    if (tasPk > 0)
    {
        post('download_tracks.php', { 'tasPk' : tasPk, 'count' : 0 }, 'post');
    }
}


$(document).ready(function() {
    var comPk = url_parameter("comPk");
    var tasPk = url_parameter("tasPk");
    var trackid = url_parameter('trackid');
    var posint = parseInt(url_parameter('interval'));
    if (posint > 0)
    {
        console.log(posint);
        interval = posint;
    }

    var mapdiv = document.getElementById("map");
    mapdiv.setAttribute('style', 'top: 0px; left: 0px; width:100%; height:90vh; float: left');
    map = add_map_server('map', 0);
    add_map_extra(map);
    add_play_controls(map);

	$('#playslider').on("change", function() {
		//$(this).next().html($(this).val());

        var tm = $('#clock').html();
        if (tm == '00:00:00' || tm == '')
        {
            animate_init();
		}

		var range = time_bounds['last'] - time_bounds['first'];
		$('#fwd').removeClass("fa-pause");
		$('#fwd').addClass("fa-play");
		pause = 1;
		clearTimeout(timer);
		
		var new_current = range * $(this).val() / 100 + time_bounds['first'];
    	step = interval;
		if (new_current < current)
		{
    		step = - interval;
		}
		current = new_current;
		$('#clock').html(format_seconds(current*interval));
		animate_update();
	});
	time_bounds['first'] = 86400 * 2;
	time_bounds['last'] = -86400 * 2;

    if (tasPk > 0)
    {
        plot_task(tasPk, false, trackid);
    }
    add_track(comPk, trackid, interval);
});

$(document).ready(function() {
    new microAjax("get_local_tracks.php" + window.location.search,
        function(data) {
        var traPk = url_parameter('trackid');
        local_tracks = JSON.parse(data);

        $.each(local_tracks, function (i, item) {
            if (item != traPk)
            {
                $('#tracks').append($('<option>', { 
                    value: item,
                    text : i
                }));
            }
            });
        });
});


