var tile_server_selection = 0;
var http = 'https://';
//http var tileserver = 'tile.opentopomap.org/{z}/{x}/{y}.png';
//https var tileserver = 'tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=b58289867df642afbd83cbea937efcb5'
//var tileserver = 'server.arcgisonline.com/arcgis/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}';
var esri_sat = 'server.arcgisonline.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
var esri_ts = 'server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}.jpg';
var esri_topo_ts = 'services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}.jpg';
var esri_semi_ts = 'server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Reference_Overlay/MapServer/tile/{z}/{y}/{x}.jpg';
//  var hydda_road_layer =  L.tileLayer('https://{s}.tile.openstreetmap.se/hydda/roads_and_labels/{z}/{x}/{y}.png', { 
//          maxZoom: 18, attribution: 'Tiles courtesy of <a href="http://openstreetmap.se/" target="_blank">OpenStreetMap Sweden</a>'
//          +' &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(leafmap);
//var select = [ 'a.', 'b.', 'c.' ];
var select = [ ];
var map_array = Array();
var map;

L.Control.Panel = L.Control.extend({
    onAdd: function(map) {
            this._container = L.DomUtil.create('div');
            return this._container;
        },
        
    show: function(message, cssclass) {
        var elem = this._container;
        elem.innerHTML = message;
        elem.className = cssclass;
        elem.style.display = 'inline-block';
    },

    onRemove: function(map) { 
        var elem = this._container;
        elem.style.display = 'none';
        //L.DomEvent.off();
        // Nothing to do here 
    }
});


L.control.panel = function (options) {
    return new L.Control.Panel(options);
};

L.Control.Play = L.Control.extend({
    onAdd: function(map) {
            var ele = L.DomUtil.create('div');
            this._container = ele;
            ele.innerHTML = "<div id='playblock'><table><b><tr><td><a href='#' id='clear' onclick='restart();'>&#8920;</a></td><td><a href='#' id='bwd' onclick='backward();'>&ll;</a></td><td><a href='#' id='fwd' onclick='forward();'>&gt;</a></td><td><a href='#' id='ffwd' onclick='fast_forward();'>&gg;</a></td></tr></b></table></div>";
            ele.className = 'play';
            return this._container;
        },
        
    show: function() {
        var elem = this._container;
        elem.style.display = 'block';
    },

    onRemove: function(map) { 
        var elem = this._container;
        elem.style.display = 'none';
        //L.DomEvent.off();
        // Nothing to do here 
    }
});

L.control.play = function (options) {
    return new L.Control.Play(options);
};

L.Map.addInitHook(function () {
    if (this.options.panel) {
        this.panel = new L.Control.Panel();
        this.addControl(this.panel);
    }
    if (this.options.play) {
        this.play = new L.Control.Play();
        this.addControl(this.play);
    }
});

function add_label(map, pos, txt, classn, size, anchor)
{
    if (!size)
    {
        var width = (txt.length+1) * 7;
        size = [ width, 19 ];
    }
    if (!anchor)
    {
        anchor = [-1, -1];
    }
    var wpticon = L.divIcon({className: classn, html:txt, iconSize:size, iconAnchor:anchor});
    return L.marker(pos, {icon: wpticon}).addTo(map);
}

function get_tileserver(tileserver, select)
{
    if (select.length > 0)
    {
        tile_server_selection = (tile_server_selection + 1) % select.length;
        return http + select[tile_server_selection] + tileserver;
    }
    else
    {
        return http + tileserver;
    }
}

function plot_pilots_lo(tasPk)
{
    microAjax("get_pilots_lo.php?tasPk="+tasPk,
	  function(data) {
          var pilots;
          var pos;
        
    
          // Got a good response, create the map objects
          pilots = RJSON.unpack(JSON.parse(data));
          //pbounds = new L.LatLngBounds();

          for (row in pilots)
          {
              var overlay;
              lat = pilots[row]["trlLatDecimal"];
              lon = pilots[row]["trlLongDecimal"];
              name = pilots[row]["name"];

              //alert("name="+name+" lat="+lat+" lon="+lon);
              pos = new L.LatLng(lat,lon);
              if (!pbounds)
              {
                pbounds = pos.toBounds();
              }
              else
              {
                pbounds.extend(pos);
              }
              overlay = add_label(map, pos, name, "pilot");
      
          }
        
          map.fitBounds(pbounds);
    });
}
function add_map_server(name, count)
{
    map_array[count] = L.map(name).setView([-37.5, 145.8], 11);

    satellite = L.tileLayer(get_tileserver(esri_sat, []), {
        //attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, SRTM | Style &copy; OpenTopoMap <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
        attribution: '&copy; Esri, et al',
        maxZoom: 18, id: 'satellite',
    }).addTo(map_array[count]);

    streets  = L.tileLayer(get_tileserver(esri_ts, []), {id: 'streets',
        attribution: '&copy; Esri, et al'
        });

    topo = L.tileLayer(get_tileserver(esri_topo_ts, []), {id: 'topo',
        attribution: '&copy; Esri, et al'
        });

    //satoverlay = L.tileLayer(get_tileserver(esri_semi_ts, []), {id: 'satover', attribution: 'esri overlay'});

    base_maps = { 'sat' : satellite, 'topo' : topo, 'streets': streets };
    overlay_maps = { }; // { 'overlay' : satoverlay };
    L.control.layers(base_maps, overlay_maps).addTo(map_array[count]);

    //L.tileLayer(get_tileserver(), {
        //attribution: 'Data &copy; <a href="https://arcgis.com/">ArcGIS</a>, SAT</a>',
        //maxZoom: 18, id: 'satmap',
    //}).addTo(map_array[count]);

    return map_array[count];
}
var sidebar;
function add_map_extra(map)
{
    // add sidebar and a control to expand it
    L.easyButton( '<span id="expandtab" style="font-size: 2.2em; line-height: 1;">&rAarr;</span>', function() {
        if (sidebar.isVisible())
        {
            sidebar.hide();
            $("#expandtab").html('&rAarr;');
        }
        else
        {
            sidebar.show();
            $("#expandtab").html('&lAarr;');
        }
    }).addTo(map);

    sidebar = L.control.sidebar('sidebar', {
        position: 'left',
        autoPan: false,
        closeButton: true
        });

    map.addControl(sidebar);
}
function add_play_controls(map)
{
    // add a replay control panel
    var pkey = L.control.play({ position: 'bottomright' }).addTo(map);
    pkey.show();

}
function add_panel(map, locn, ovhtml, classn)
{
    panel = L.control.panel({ position: locn }).addTo(map);
    panel.show(ovhtml, classn);
}

function merge_tracks(tasPk, traPk, incPk)
{
    new microAjax("merge_track.php?tasPk="+tasPk+"&traPk="+traPk+"&incPk="+incPk, function(data) 
        { 
            window.location.href="tracklog_map.html?trackid="+traPk;
        } );
}
function award_waypoint(comPk, tasPk, tawPk, trackid, wptime)
{
    //post('award.php?tasPk=' + tasPk, { 'comPk' : comPk, 'trackid' : trackid, 'tawPk' : tawPk, 'wptime' : wptime}, 'post');
    post('award.php?comPk=' + comPk + '&tasPk=' + tasPk + '&trackid=' + trackid + '&tawPk=' + tawPk + '&wptime=' + wptime, 'post');
    // clear & reload track ..
}
var pbounds;
function add_award_task(tasPk, trackid)
{
    var comPk = url_parameter('comPk');

    // FIX: should show already awarded ones - and allow 'unawarding'
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
    var added = 0;

    var task = JSON.parse(data);
    //ovhtml = "<div class=\"htmlControl\" id=\"award\"><b>Award Points</b><br><form name=\"trackdown\" method=\"post\">\n";
    ovhtml = "<h3>Track Admin - Award</h3><form name=\"trackdown\" method=\"post\">\n";
    track = task["task"];
    tps = 0 + task["turnpoints"];
    incpk = task["merge"];
    cnt = 0;
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
                ovhtml = ovhtml +  cnt + ". <input onblur=\"award_waypoint(" + comPk + ','  + tasPk + "," + tawPk + "," + trackid + ",this.value)\" type=\"text\" name=\"goaltime\" size=5>&nbsp;" + name + "<br>";
                end = 1;
            }
            else if (tawtype == 'speed')
            {
                ovhtml = ovhtml +  cnt + ". <input onblur=\"award_waypoint(" + comPk + ',' + tasPk + "," + tawPk + "," + trackid + ",this.value)\" type=\"text\" name=\"goaltime\" size=5>&nbsp;" + name + "<br>";
            }
            else
            {
                ovhtml = ovhtml +  cnt + ". <input type=\"checkbox\" name=\"turnpoint\" onclick=\"award_waypoint(" + comPk + ',' + tasPk + "," + tawPk + "," + trackid + "," + cnt + ")\">&nbsp;" + name + "<br>";
            }
            added = 1;
        }
    }
    // add in a 'merge with' option?
    if (incpk > 0)
    {
        ovhtml = ovhtml + "<br><button class=\"btn btn-primary btn-sm btn-block\" onclick=\"merge_tracks("+tasPk+','+trackid+','+incpk+");\">Merge Track</button></center>";
    }
    ovhtml = ovhtml + "</form>";


    if (added)
    {
        // check admin!
        $('#award').append(ovhtml);
        $('#award').show();
        //add_panel(map, "bottomright", ovhtml, 'map_control');
    }
    });
}
function plot_task_route(map, ssr)
{
    line = Array();
    sline = Array();
    var pbounds; 
    var polyline;
    var pos;

    count = 1;
    for (row in ssr)
    {
        var overlay;
        var circle;
        lasLat = ssr[row]["rwpLatDecimal"];
        lasLon = ssr[row]["rwpLongDecimal"];
        sLat = ssr[row]["ssrLatDecimal"];
        sLon = ssr[row]["ssrLongDecimal"];
        cname = "" + count + "*" + ssr[row]["rwpName"];
        crad = ssr[row]["tawRadius"];
        shape = ssr[row]["tawShape"];

        gll = new L.LatLng(lasLat, lasLon);
        line.push(gll);

        sll = new L.LatLng(sLat, sLon);
        if (!pbounds)
        {
            pbounds = new L.LatLngBounds(gll, sll);
        }
        else
        {
            pbounds.extend(gll);
        }
        sline.push(sll);
      
        count = count + 1;    
  
        // overlay = new ELabel(map, pos, cname, "waypoint", new L.Size(0,0), 60);
        pos = gll; //new L.LatLng(lasLat,lasLon);
        add_label(map, sll, cname, 'waypoint');
  
        if (shape == "line")
        {
              //sz = GSizeFromMeters(map, pos, crad*2,crad*2);
              //overlay = new EInsert(map, pos, "circle.png", sz, map.getZoom());
              var alpha, beta;
              var lat1, lat2, lon1, lon2;
              var x, y, diflon, brng, perp;

              lat1 = prevslat * Math.PI / 180;
              lon1 = prevslon * Math.PI / 180;
              lat2 = lasLat * Math.PI / 180;
              lon2 = lasLon * Math.PI / 180;
              
              diflon = lon2 - lon1;
              y = Math.sin(diflon) * Math.cos(lat2);
              x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(diflon);
              brng = Math.atan2(y,x);
              alpha = brng - Math.PI / 2;
              beta = brng + Math.PI / 2;
              //alert("brng="+(brng*180/Math.PI) + " alpha="+(alpha*180/Math.PI) + " beta="+(beta*180/Math.PI));
              //wline = make_wedge([ 0, lasLat, lasLon ], alpha, beta, crad, "arc+");
              //wline.push(wline[0]);
              //polyline = L.Polyline(wline, {   
              //        color: "#ff0000",
              //        stroke: true,
              //        weight: 2, 
              //        opacity: 1
              //    }).addTo(map);

        }
        else
        {
              circle = new L.Circle(pos, {
                radius:parseInt(crad),
                stroke:true,
                color:"#ff0000",
                opacity:1.0,
                weight:1.0,
                fill:true,
                fillColor:"#ff0000",
                fillOpacity:0.15,
              }).addTo(map);
        }
        prevslat = sLat;
        prevslon = sLon;
    }
    
    polyline = new L.Polyline(line, {   
                stroke:true,
                color: "#ff0000",
                weight: 2, 
                opacity: 1
            }).addTo(map);

    polyline = new L.Polyline(sline, {   
                stroke:true,
                color: "#0000ff",
                weight: 2, 
                opacity: 1
            }).addTo(map);

    map.fitBounds(pbounds);
}
function plot_task(tasPk, pplo, trackid)
{
    microAjax("get_short.php?tasPk="+tasPk, 
      function (data) {
        var task, track, row;
        var line, sline, polyline;
        var prevslat, prevslon, gll, count, color;
        var pos, sz;
        var ihtml, ovlay;
    
        var ssr = JSON.parse(data);
        plot_task_route(map, ssr);
        count = 1;
        ihtml = "<table>";
        for (row in ssr)
        {
            ihtml = ihtml + "<tr><td><b>" + ssr[row]["rwpName"] + "<b></td><td>" + ssr[row]["tawType"] + "</td><td>" + ssr[row]["tawRadius"] + "m</td><td>" + ssr[row]["tawHow"] + "</td><td>" + sprintf("%0.2f", ssr[row]["ssrCumulativeDist"]/1000) + "km</td></tr>";
        }
        ihtml = ihtml + "</table>";
        add_panel(map, "bottomleft", ihtml, 'map_panel' );
        if (pplo)
        {
            plot_pilots_lo(tasPk);
        }
        if (trackid > 0 && is_admin())
        {
            add_award_task(tasPk, trackid);
        }
    });
}
function is_admin()
{
    if (getCookie('XCauth').length > 0)
    {
        // assume it's good - let backup take care of validation
        return 1;
    }
}
