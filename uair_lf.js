var onscreen = Array();
var all_airspace;;
var airspaceid;

String.prototype.format = function()
{
    var pattern = /\{\d+\}/g;
    var args = arguments;
    return this.replace(pattern, function(capture){ return args[capture.match(/\d+/)]; });
}
function plot_air(airspace)
{
    var track = airspace['waypoints'];
    var row;
    var line;
    //var trklog;
    var polyline;
    var gll;
    var count;
    var color;
    var pos;
    var sz;
    var label;
    var circle;
    var center;

    count = 1;
    line = Array();
    segments = Array();
    bounds = new L.LatLngBounds();


    shape = airspace.airShape;
    if (shape == "circle")
    {
        lasLat = track[0][2];
        lasLon = track[0][3];
        cname =  airspace.airClass;
        crad = parseInt(airspace.airRadius);

        pos = new L.LatLng(lasLat,lasLon);

        circle = new L.Circle(pos, {
                radius:crad, 
                color:"#0000ff", 
                opacity:1.0,
                weight:1.0,
                fillColor:"#0000ff", 
                fillOpacity:0.4,
            }).addTo(map);

        if (onscreen.length == 0 && count == 1)
        {
            map.setCenter(pos, 13);
        }

        //sz = GSizeFromMeters(map, pos, crad*2,crad*2);
        //map.addOverlay(new EInsert(pos, "bluecircle.png", sz, map.getZoom()));
        
        // Fix - should place on 4(?) points of circle NSEW perhaps
        //label = new ELabel(map, pos, cname, "waypoint", new L.Size(0,0), 60);
        add_label(map, pos, cname, "waypoint");
        bounds.extend(pos);

        onscreen[airspaceid] = track;
        return track;
    }

    //if (shape == "wedge")
    //{
    //    center = track[0];
    //    track.shift();
    //}

    // otherwise polygon (wedge not handled properly)
    // alert("track len="+track.length);
    //[ $class, $lasLat, $lasLon, $base, $tops, $shape, $radius, $connect, $astart, $aend ];
    //[ $row['airOrder'], $row['awpConnect'], $row['awpLatDecimal'], $row['awpLongDecimal'], $row['awpAngleStart'], $row['awpAngleEnd'], $row['awpRadius'] ];

    for (row in track)
    {
        lasLat = track[row][2];
        lasLon = track[row][3];
        cname =  airspace.airClass;
        shape = airspace.airShape;
        connect = track[row][1];

        if (onscreen.length == 0 && count == 1)
        {
            map.setCenter(new L.LatLng(lasLat, lasLon), 9);
        }

        if (connect == "arc+" || connect == "arc-")
        {
            // add an arc of polylines
            var radius = dist(track[row], track[row-1]);

            wline = make_wedge(track[row], parseFloat(track[row][8]), parseFloat(track[row][9]), radius, connect);
            for (pt in wline)
            {
                line.push(wline[pt]);
                bounds.extend(wline[pt]);
            }
            gll = wline[pt];
        }
        else
        {
            gll = new L.LatLng(lasLat, lasLon);
            line.push(gll);
            bounds.extend(gll);
            //trklog.push(track[row]);
        }
    
        if (count % 10 == 0)
        {
            polyline = new L.Polyline(line, {
                color: "#0000ff", 
                weight: 2, 
                opacity: 1,
                } ).addTo(map);
            segments.push(polyline);
            line = Array();
            line.push(gll);
        }
        count = count + 1;    

        if (!(connect == "arc+" || connect == "arc-"))
        {
            //pos = new L.LatLng(lasLat,lasLon);
            //label  = new ELabel(map, pos, row, "waypoint", new L.Size(0,0), 60);
        }
        //sz = GSizeFromMeters(map, pos, crad*2,crad*2);
        //map.addOverlay(new EInsert(pos, "circle.png", sz, 13));
    }

    if (line.length > 0)
    {
        polyline = new L.Polyline(line, {
            color: "#0000ff", 
            weight: 2, 
            opacity: 1, 
            }).addTo(map);
        segments.push(polyline);
    }

    onscreen[airspaceid] = track;
    return track;
}
function add_airspace() 
{
    var e = document.getElementById("airspace");
    var airspaceid = e.options[e.selectedIndex].value;
    plot_air(all_airspace[airspaceid]);
}
function dist(p1, p2)
{
    var earth = 6378137.0;
    var p1lat = p1[1] * Math.PI / 180;
    var p1lon = p1[2] * Math.PI / 180;
    var p2lat = p2[1] * Math.PI / 180;
    var p2lon = p2[2] * Math.PI / 180;
    var dlat = (p2lat - p1lat);
    var dlon = (p2lon - p1lon);


    var a = Math.sin(dlat/2) * Math.sin(dlat/2) + Math.cos(p1lat) * Math.cos(p2lat) * Math.sin(dlon/2) * Math.sin(dlon/2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return earth * c;
}
function make_wedge(center, alpha, beta, radius, dirn)
{
    var points = 16;
    var earth = 6378137.0;
    var delta;
    var Cpoints = [];
    var nlat,nlon;
    var nbrg;

    // to radians
    var Clat = center[1] * Math.PI / 180;
    var Clng = center[2] * Math.PI / 180;

    if (dirn == "arc-")
    {
        // anti
        delta = alpha - beta;
    }
    else
    {
        // clock
        delta = beta - alpha;
    }

    if (delta < 0) 
    {
        delta = delta + Math.PI * 2;
    }

    delta = delta / points;

    if (dirn == "arc-")
    {
        delta = -delta;
    }

    nbrg = alpha;
    for (var i=0; i < points+1; i++) 
    {
        nlat = Math.asin(Math.sin(Clat)*Math.cos(radius/earth) + 
                Math.cos(Clat)*Math.sin(radius/earth)*Math.cos(nbrg) );

        nlon = Clng + Math.atan2(Math.sin(nbrg)*Math.sin(radius/earth)*Math.cos(Clat),
                Math.cos(radius/earth)-Math.sin(Clat)*Math.sin(nlat));

        // back to degrees ..
        nlat = nlat * 180 / Math.PI;
        nlon = nlon * 180 / Math.PI;

        Cpoints.push(new L.LatLng(nlat,nlon));
        
        nbrg = nbrg + delta;
    }

    return Cpoints;
}

$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_local_airspace.php'+ window.location.search);
    //var tasPk = url.searchParams.get("tasPk");

    new microAjax("get_local_airspace.php?" + window.location.search,
        function(data) {
        all_airspace = JSON.parse(data);

        if (all_airspace)
        {
            $.each(all_airspace, function (i, item) {
                $('#airspace').append($('<option>', { 
                    value: i,
                    text : item['airName']
                }));
            });
        }
        else
        {
            $('#airdiv').hide();
        }
        });
});
