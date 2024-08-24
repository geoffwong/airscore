var aironscreen = Array();
var airlabelonscreen = Array();
var all_airspace;

String.prototype.format = function()
{
    var pattern = /\{\d+\}/g;
    var args = arguments;
    return this.replace(pattern, function(capture){ return args[capture.match(/\d+/)]; });
}
function plot_air(map, airPk, airspace, clor, detailed)
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
    bounds = new L.LatLngBounds();

    console.log(airspace.airName + " " + clor);

    if (aironscreen.hasOwnProperty(airPk))
    {
        aironscreen[airPk].removeFrom(map);
        if (airlabelonscreen.hasOwnProperty(airPk))
        {
            airlabelonscreen[airPk].removeFrom(map);
        }
    }

    shape = airspace.airShape;
    if (detailed)
    {
        cname =  airspace.airName + ': [' + airspace.airClass + ',' + airspace.airBase + '-' + airspace.airTops + 'm]';
    }
    else
    {
        cname =  airspace.airClass;
    }

    if (shape == "circle")
    {
        lasLat = track[0][2];
        lasLon = track[0][3];
        //cname =  airspace.airName + '(' + airspace.airClass + ')';
        crad = parseInt(airspace.airRadius);

        pos = new L.LatLng(lasLat,lasLon);

        circle = new L.Circle(pos, {
                radius:crad, 
                color:clor,
                opacity:1.0,
                weight:1.0,
                fillColor:clor,
                fillOpacity:0.2,
            }).addTo(map);

        if (aironscreen.length == 0 && count == 1)
        {
            map.setView(pos, 13);
        }

        //sz = GSizeFromMeters(map, pos, crad*2,crad*2);
        //map.addOverlay(new EInsert(pos, "bluecircle.png", sz, map.getZoom()));
        
        // Fix - should place on 4(?) points of circle NSEW perhaps
        //label = new ELabel(map, pos, cname, "waypoint", new L.Size(0,0), 60);
        airlabelonscreen[airPk] = add_label(map, pos, cname, "waypoint");
        bounds.extend(pos);

        aironscreen[airPk] = circle;
        return track;
    }

    // otherwise polygon (wedge not handled properly)
    // alert("track len="+track.length);
    //[ $class, $lasLat, $lasLon, $base, $tops, $shape, $radius, $connect, $astart, $aend ];
    //[ $row['airOrder'], $row['awpConnect'], $row['awpLatDecimal'], $row['awpLongDecimal'], $row['awpAngleStart'], $row['awpAngleEnd'], $row['awpRadius'] ];

    for (row in track)
    {
        lasLat = track[row][2];
        lasLon = track[row][3];
        //cname =  airspace.airClass;
        shape = airspace.airShape;
        connect = track[row][1];

        if (aironscreen.length == 0 && count == 1)
        {
            map.setView(new L.LatLng(lasLat, lasLon), 9);
        }

        if (connect == "arc+" || connect == "arc-")
        {
            // add an arc of polylines
            //console.log('row='+row);
            //console.log(track);
            var radius = dist(track[row], track[row-1]);

            wline = make_wedge(track[row], parseFloat(track[row][4]), parseFloat(track[row][5]), radius, connect);
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
    
        count = count + 1;    

        if (!(connect == "arc+" || connect == "arc-"))
        {
            //pos = new L.LatLng(lasLat,lasLon);
            //label  = new ELabel(map, pos, row, "waypoint", new L.Size(0,0), 60);
        }
    }

    if (line.length > 0)
    {
        polyline = new L.polygon(line, {
            color: clor,
            fillColor: clor,
            weight: 2, 
            opacity: 1, 
            fillOpacity:0.2,
            }).addTo(map);

        var center = polyline.getCenter();
        if (cname)
        {
            airlabelonscreen[airPk] = add_label(map, center, cname, "waypoint");
        }
    }

    aironscreen[airPk] = polyline;
    return track;
}
function clear_airspace()
{
    for (key in aironscreen) {
        if (aironscreen.hasOwnProperty(key)) 
        {           
            aironscreen[key].removeFrom(map);
            if (airlabelonscreen.hasOwnProperty(key))
            {
                airlabelonscreen[key].removeFrom(map);
            }
        }
    }

    aironscreen = Array();
}
function add_airspace() 
{
    $("#airspace :selected").map(function(i, el) {
        plot_air(map, $(el).val(), all_airspace[$(el).val()], '#ff00ff', 0);
    });
} 
function delete_airspace() 
{
    $("#airspace :selected").map(function(i, el) {
        $.post('del_airspace.php', { airPk: $(el).val() }, 'post');
    });
} 
function dist(p1, p2)
{
    var earth = 6378137.0;
    var p1lat = p1[2] * Math.PI / 180;
    var p1lon = p1[3] * Math.PI / 180;
    var p2lat = p2[2] * Math.PI / 180;
    var p2lon = p2[3] * Math.PI / 180;
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
    var Clat = center[2] * Math.PI / 180;
    var Clng = center[3] * Math.PI / 180;

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
