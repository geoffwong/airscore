// Relies on font awesome for Play Control
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
            L.DomEvent.addListener(this._container, "click", this._details, this);
            //L.DomEvent.disableClickPropagation(this._container);
            return this._container;
        },
        
    _details: function(ev) {
        console.log("toggle details");
        $("details").toggle();
        map.originalEvent.preventDefault();
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
        L.DomEvent.off(this._container, "click", this._details, this);
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
            //ele.innerHTML = "<div id='playblock'></b><table><tr><td><a href='#' id='clear' onclick='restart();'>&#8920;</a></td><td><a href='#' id='bwd' onclick='backward();'>&ll;</a></td><td><a href='#' id='fwd' onclick='forward();'>&gt;</a></td><td><a href='#' id='ffwd' onclick='fast_forward();'>&gg;</a></td></tr><tr><td colspan='4'><small><div id='clock'>00:00:00</div></small></td></tr></table></b></div>";
            ele.innerHTML = "<div id='playblock'></b><table><tr><td><span class='fa fa-fast-backward' id='clear' onclick='restart();'></span></td><td><span class='fa fa-backward' id='bwd' onclick='backward();'></span></td><td><span class='fa fa-play' id='fwd' onclick='forward();'></span></td><td><span class='fa fa-forward' id='ffwd' onclick='fast_forward();'></span></td></tr><tr><td colspan='4'><small><div id='clock' onclick='set_clock();'>00:00:00</div></small></td></tr></table></b><input type='range' min='1' max='100' value='1' class='slider' id='playslider'></div>";
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
    var tstdiv = document.getElementById("txttest");
    tstdiv.innerHTML = txt;

    //if (!size)
    //{
        //var width = (txt.length+2) * 7;
        //size = [ width, 19 ];
    //}
    size = [ tstdiv.clientWidth+5, tstdiv.clientHeight ];
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
function plot_pilots_lo(map, tasPk)
{
    new microAjax("get_pilots_lo.php?tasPk="+tasPk,
      function(data) {
          var pilots;
          var pos;

          if (data == "") return;

          // Got a good response, create the map objects
          pilots = JSON.parse(data);
          //pbounds = new L.LatLngBounds();
          for (row in pilots)
          {
              var overlay;
              lat = pilots[row]["trlLatDecimal"];
              lon = pilots[row]["trlLongDecimal"];
              name = pilots[row]["name"];

              console.log("name="+name+" lat="+lat+" lon="+lon);
              pos = new L.LatLng(lat,lon);
              //pbounds.extend(pos);
              overlay = add_label(map, pos, name, "pilot");
          }
          //map.fitBounds(pbounds);
    });
}
function add_map_server(name, count)
{
    map_array[count] = L.map(name).setView([-37.5, 145.8], 11);

    satellite = L.tileLayer(get_tileserver(esri_sat, []), {
        //attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, SRTM | Style &copy; OpenTopoMap <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
        attribution: 'Tiles &copy; <a href="https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer">Esri, DigitalGlobe, GeoEye, et al</a>',
        maxZoom: 18, id: 'satellite',
    }).addTo(map_array[count]);

    streets  = L.tileLayer(get_tileserver(esri_ts, []), {id: 'streets',
        attribution: 'Tiles &copy; <a href="https://services.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer">Ersi, OSM, et al</a>',
        });

    topo = L.tileLayer(get_tileserver(esri_topo_ts, []), {id: 'topo',
        attribution: 'Tiles &copy; <a href="https://services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer">Ersi, OSM, et al</a>',
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
    L.easyButton( '<span id="expandtab" style="font-size: 2.0em; line-height: 1.0;"><svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-caret-right-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12.14 8.753l-5.482 4.796c-.646.566-1.658.106-1.658-.753V3.204a1 1 0 0 1 1.659-.753l5.48 4.796a1 1 0 0 1 0 1.506z"/></svg></span>', function() {
        if (sidebar.isVisible())
        {
            sidebar.hide();
            $("#expandtab").html('<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-caret-right-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12.14 8.753l-5.482 4.796c-.646.566-1.658.106-1.658-.753V3.204a1 1 0 0 1 1.659-.753l5.48 4.796a1 1 0 0 1 0 1.506z"/></svg>');
        }
        else
        {
            sidebar.show();
            $("#expandtab").html('<svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-caret-left-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M3.86 8.753l5.482 4.796c.646.566 1.658.106 1.658-.753V3.204a1 1 0 0 0-1.659-.753l-5.48 4.796a1 1 0 0 0 0 1.506z"/></svg>');
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
    return pkey;
}
function add_panel(map, locn, ovhtml, classn)
{
    panel = L.control.panel({ position: locn }).addTo(map);
    panel.show(ovhtml, classn);
    return panel;
}
function merge_tracks(tasPk, traPk, incPk)
{
    console.log("merge: tasPk="+tasPk+" traPk="+traPk+" incPk="+incPk);
    $.post("merge_track.php", { 'tasPk' : tasPk, 'traPk' : traPk, 'incPk' : incPk },  
        function( data ) { console.log( "merged: " + data ); });
}
function award_waypoint(comPk, tasPk, tawPk, trackid, wptime)
{
    //post('award.php?tasPk=' + tasPk, { 'comPk' : comPk, 'trackid' : trackid, 'tawPk' : tawPk, 'wptime' : wptime}, 'post');
    $.post('award.php?comPk=' + comPk + '&tasPk=' + tasPk + '&trackid=' + trackid + '&tawPk=' + tawPk + '&wptime=' + wptime, 'post');
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
        ovhtml = ovhtml + "<br><button class=\"btn btn-primary btn-sm btn-block\" onclick=\"merge_tracks("+tasPk+','+trackid+','+incpk+");\">Merge Track "+incpk+"</button></center>";
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
        cname = count + ".&nbsp;" + ssr[row]["rwpName"];
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
            pbounds.extend(sll);
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
            var adjrad = parseInt(crad);
            adjrad = adjrad;
            if (adjrad > 10000)
            {
                // try to compensate for google maps inaccuracies with large cylinders
                adjrad = adjrad * 0.999;
            }
            circle = new L.Circle(pos, {
                radius:adjrad,
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

    if (count > 2)
    {
        map.fitBounds(pbounds);
    }
}
function plot_task(tasPk, pplo, ttrackid)
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
        if (ttrackid > 0 && is_admin())
        {
            add_award_task(tasPk, ttrackid);
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
