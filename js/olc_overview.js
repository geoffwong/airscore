var map_ruler;
function plot_track(map, body)
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
    //body = resp.track;
    //plot_track_header(body);
    track = body["track"];
    pngclass = body["class"];

    line = Array();
    segments = Array();
    trklog = Array();
    color = 1;

	//if (track[0][0] < time_bounds['first'])
	//{
		//time_bounds['first'] = track[0][0];
	//}

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
            bounds.extend(gll);
        }
        count = count + 1;    
    }

	//if (lasTme > time_bounds['last'])
	//{
		//time_bounds['last'] = lasTme;
	//}

    polyline = new L.Polyline(line, {   
        color: sprintf("#%02x%02x%02x",lasAlt*((color&0x4)>>2) ,lasAlt*((color&0x2)>>1),lasAlt*(color&0x1)), 
        weight: 3, 
        opacity: 1
    });
    polyline.addTo(map);
    //polyline = new google.maps.Polyline(line, sprintf("#%02x%02x%02x",lasAlt*(color&0x100>>2) ,lasAlt*(color&0x010>>1),lasAlt*(color&0x1)), 3, 1);
    //map.addOverlay(polyline);
    //segments.push(polyline);
    //document.getElementById("foo").value = trackid;
    //onscreen[trackid] = Array();
    //onscreen[trackid]["track"] = trklog;
    //onscreen[trackid]["segments"] = segments;
    //onscreen[trackid]["initials"] = initials;
    //onscreen[trackid]["class"] = pngclass;
    
    //    if (points.length > 0)
    //    {
    //        plot_track_wp(body, points);
    //    }
    map.fitBounds(bounds);
}
function add_map_row(comPk, trackinfo, count)
{
    var colmd7 = document.createElement("div");
    colmd7.className="col-md-7";

    var colmd5 = document.createElement("div");
    colmd5.className="col-md-5";

    var canvas = 'map_canvas' + count;
    var canvasdiv = document.createElement("div");
    canvasdiv.setAttribute('id', canvas);
    canvasdiv.setAttribute('style', 'top: 10px; left: 10px; width:100%; height:300px; float: left');
    colmd7.appendChild(canvasdiv);

    var body = document.createElement('div');
    body.innerHTML = '<br><h3 id=\"task_hd\">'+trackinfo.title+'</h3><h4>'+trackinfo.detail.date+' UTC</h4>' +
                    '<br><table class="taskinfo">' +
                    '<tr><td>Pilot:</td><td>' + trackinfo.detail.name +  '</td></tr>' + 
                    '<tr><td>Glider:</td><td>' + trackinfo.detail.glider + '</td></tr>' + 
                    '<tr><td>XC Distance:</td><td>' + trackinfo.detail.dist + ' km</td></tr>' + 
                    '<tr><td>Duration:</td><td>' + trackinfo.detail.duration + '</td></tr></table><br>';

    body.innerHTML = body.innerHTML + '<a class="btn btn-primary" href="tracklog_map.html?comPk='+comPk+'&trackid='+trackinfo.traPk+'");">Full Flight</a>';

    colmd5.appendChild(body);

    ele = document.getElementById('row'+count);
    ele.appendChild(colmd7);
    ele.appendChild(colmd5);

    ele.style.paddingBottom = "40px";
    return canvas;
}
function plot_all_tracks(comPk)
{
    microAjax("get_key_flights.php?comPk="+comPk, 
    function (data) 
    {
        var comp_info = JSON.parse(data);
        var count = 1;

        // setup comp info
        var ele = $('#comp_name');
        ele.html(comp_info.comp.comName + " - <small>" + comp_info.comp.comLocation + "</small>");
        ele.append($('<div class="row"><div class="col-md-6"><h5>'+comp_info.comp.comDateFrom + ' - ' + comp_info.comp.comDateTo + '</h5></div>'));
        if (comp_info.comp.regPk)
        {
            ele.append($('<div class="row"><div class="col-md-6"><a href="waypoint_map.html?regPk=' + comp_info.comp.regPk + '" class="btn btn-secondary">Waypoints</a></div></div>'));
        }
        //ele.append($('<div class="col-md-6"><h5>'+comp_info.comp.comMeetDirName + '</h5></div></div>>'));

        // plot tasks
        var top_tracks = comp_info.tracks;
        if (top_tracks.length == 0)
        {
           ele.append($('<hr><center><h4 class="display-4">No Flights</h4></center>'));
        }
        for (taskid in top_tracks)
        {
            var flightinfo = top_tracks[taskid];

            var mapnm = add_map_row(comp_info.comp.comPk, flightinfo, count);
            var map = add_map_server(mapnm, count-1);

            map.addControl(new L.Control.Fullscreen());
            map.on('fullscreenchange', function () {
                    if (map.isFullscreen()) {
                        console.log('entered fullscreen');
                        map_ruler = L.control.ruler().addTo(map);
                    } 
                    else {
                        map.removeControl(map_ruler);
                        console.log('exited fullscreen');
                    }
            });

            plot_track(map, flightinfo.detail);
            $('.leaflet-control-layers input', '#'+mapnm).get(1).click()
            count++;
        }

        // add footer
        var foot = document.createElement("footer");
        foot.className="py-5 bg-dark";
        var footdiv = document.createElement("div");
        footdiv.className = "container";
        var para = document.createElement("p");
        para.className = "m-0 text-center text-white";
        para.appendChild(document.createTextNode('airScore'));
        footdiv.appendChild(para);
        foot.appendChild(footdiv);

        $('#main').append($('<hr>'));
    });

}


$(document).ready(function() {
    var comPk = url_parameter("comPk");

    comPk = url_parameter("comPk");
    plot_all_tracks(comPk);
});

