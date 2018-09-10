var tile_server_selection = 0;
var http = 'https://';
//http var tileserver = 'tile.opentopomap.org/{z}/{x}/{y}.png';
//https var tileserver = 'tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=b58289867df642afbd83cbea937efcb5'
//var tileserver = 'server.arcgisonline.com/arcgis/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}';
var tileserver = 'server.arcgisonline.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
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
        elem.style.display = 'block';
    },

    onRemove: function(map) { 
        var elem = this._container;
        elem.style.display = 'none';
        // Nothing to do here 
    }
});

L.Map.addInitHook(function () {
    if (this.options.panel) {
        this.panel = new L.Control.Panel();
        this.addControl(this.panel);
    }
});

L.control.panel = function (options) {
    return new L.Control.Panel(options);
};

function get_tileserver()
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

function add_panel(map, locn, ovhtml, classn)
{
    panel = L.control.panel({ position: locn }).addTo(map);
    panel.show(ovhtml, classn);
}

var pbounds;
function add_award_task(tasPk, trackid)
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
    var added = 0;

    var task = JSON.parse(data);
    ovhtml = "<div class=\"htmlControl\"><b>Award Points</b><br><form name=\"trackdown\" method=\"post\">\n";
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
            added = 1;
        }
    }
    // add in a 'merge with' option?
    if (incpk > 0)
    {
        ovhtml = ovhtml + "<br><center><input type=\"button\" name=\"domerge\" value=\"Merge "+incpk+"\" onclick=\"merge_tracks("+tasPk+","+trackid+","+incpk+");\"></center>";
    }
    ovhtml = ovhtml + "</form></div>";

    if (added)
    {
        // check - and admin!
        add_panel(map, "bottomright", ovhtml, 'map_control');
    }
    //ovlay = new HtmlControl(ovhtml, { visible:false, selectable:true, printable:true } );
    //ovlay = new HtmlControl('Hello World!', { visible:false, selectable:true, printable:true } );
    //map.addControl(ovlay, new L.ControlPosition(G_ANCHOR_BOTTOM_RIGHT, new GSize(128, 256)));
    //map.controls[L.ControlPosition.RIGHT_BOTTOM].push(ovlay);
    //map.addControl(ovlay, new L.ControlPosition(G_ANCHOR_BOTTOM_RIGHT, new L.Size(10, 10)));
    //ovlay.setVisible(true);
    });
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
function plot_task(tasPk, pplo, trackid)
{
    var mapdiv = document.getElementById("map");
    mapdiv.setAttribute('style', 'top: 0px; left: 0px; width:100%; height:90vh; float: left');

    map = add_map_server('map', 0);
    microAjax("get_short.php?tasPk="+tasPk, 
    function (data) {
      var task, track, row;
      var line, sline, polyline;
      var prevslat, prevslon, gll, count, color;
      var pos, sz;
      var ihtml, ovlay;
    

      // Got a good response, create the map objects
      //alert("complete: " + data);

      var ssr = JSON.parse(data);

      //add_map_row(ssr, count);
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
      if (trackid > 0)
      {
        add_award_task(tasPk, trackid);
      }
    });
}

function add_label(map, pos, txt, classn)
{
    var size = (txt.length+2) * 7;
    var wpticon = L.divIcon({className: classn, html:txt, iconSize:[size,19], iconAnchor:[-1,-1]});
    return L.marker(pos, {icon: wpticon}).addTo(map);
}

function plot_task_route(map, ssr)
{
    line = Array();
    sline = Array();
    var pbounds; 
    var polyline;
    var pos;

    count = 1;
    console.log(ssr);
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
function add_map_row(comPk,task, count)
{
    var colmd7 = document.createElement("div");
    colmd7.className="col-md-7";

    var colmd5 = document.createElement("div");
    colmd5.className="col-md-5";


    var canvas = 'map_canvas' + count;
    var canvasdiv = document.createElement("div");
    canvasdiv.setAttribute('id', canvas);
    canvasdiv.setAttribute('style', 'top: 10px; left: 10; width:100%; height:300px; float: left');

    //var createA = document.createElement('a');
    //createA.setAttribute('href', '#');
    //createA.appendChild(canvasdiv);
    colmd7.appendChild(canvasdiv);

    var body = document.createElement('div');
    body.innerHTML = '<br><h3 id=\"task_hd\">Task '+task.tasName+'</h3><h4>'+task.tasDate+'</h4>' +
                    task.tasComment + 
                    '<br><table class="taskinfo">' +
                    '<tr><td>Task Type:</td><td>' + task.tasTaskType.toUpperCase() + '</td></tr>' + 
                    '<tr><td>Task Distance:</td><td>' + task.tasShortest + ' km</td></tr>' + 
                    '<tr><td>Day Quality:</td><td>' + task.tasQuality + '</td></tr></table><br>';

    var createB = document.createElement('a');
    createB.setAttribute('href', 'jq_task_result.html?comPk='+comPk+'&tasPk='+task.tasPk);
    createB.className="btn btn-primary";
    createB.appendChild(document.createTextNode('Task Scores'));

    body.appendChild(createB);
    colmd5.appendChild(body);

    var ele = document.getElementById('row'+count);
    ele.appendChild(colmd7);
    ele.appendChild(colmd5);

    ele.style.paddingBottom = "40px";
    //var br = document.createElement("br");
    //var hr = document.createElement("hr");
    //ele.appendChild(br);
    //ele.appendChild(hr);

}

function add_map_server(name, count)
{
    map_array[count] = L.map(name).setView([-37.5, 145.8], 11);

    L.tileLayer(get_tileserver(), {
        //attribution: 'Data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, SRTM | Style &copy; OpenTopoMap <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
        attribution: 'Data &copy; <a href="https://arcgis.com/">ArcGIS</a>, SAT</a>',
        maxZoom: 18, id: 'satmap',
    }).addTo(map_array[count]);

    return map_array[count];
}

function plot_all_tasks(comPk)
{
    microAjax("get_all_tasks.php?comPk="+comPk, 
    function (data) 
    {
        var comp_tasks = JSON.parse(data);
        var count = 1;

        // setup comp info
        var ele = document.getElementById('comp_name');
        ele.innerHTML = comp_tasks.comp.comName + " - <small>" + comp_tasks.comp.comLocation + "</small>";

        // plot tasks
        var all_tasks = comp_tasks.tasks;
        for (taskid in all_tasks)
        {
            var taskinfo = all_tasks[taskid];
            if (taskinfo.waypoints.length > 1)
            {
                add_map_row(comp_tasks.comp.comPk, taskinfo.task, count);
                map = add_map_server('map_canvas'+count, count-1);
                plot_task_route(map, taskinfo.waypoints);
                count++;
            }
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

        var ele = document.getElementById('main');
        // ele.appendChild(foot);
    });

}
