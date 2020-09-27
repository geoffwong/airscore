function added_waypoint(res)
{
    alert(res);

    // notify success
    // update a marker to a label
    row = JSON.parse(res);
    if (row.result == 'added')
    {
        var cname = row.rwpName;
        var desc = row.rwpDescription;
        var rwpPk = row.rwpPk;

        gll = new L.LatLng(row.rwpLatDecimal, row.rwpLongDecimal);
        wpt = add_label(map, gll, cname, "waypoint", [ 60, 17 ]);
        L.Util.setOptions(wpt.options.icon, { 'id' : rwpPk, 'name' : cname, 'desc' : desc });
        // remove existing marker
    }
}
function add_waypoint()
{
    var regPk = url_parameter('regPk');
    var rwpPk = $('#rwpPk').val();
    var id = $('#wptid').val();
    var desc = $('#wptdesc').val();
    var lat = $('#lat').val();
    var lon = $('#lon').val();
    var alt = $('#alt').val();
    $.post("update_waypoint.php", { 'regPk': regPk, 'name' : id, 'desc': desc, 'lat' : lat, 'lon': lon, 'alt': alt }, added_waypoint);
}
function create_waypoint_marker()
{
    var bounds = map.getBounds();
    var centre = map.getCenter();

    $('#addwpt').text('Add Waypoint');
    $('#rwpPk').val(0);
    $('#wptid').val('');
    $('#wptdesc').val('');
    $('#delwpt').hide();
    var marker = L.marker(centre, { 
            draggable: true,
            autoPan: true
        }).addTo(map);

    marker.on('dragend', function(event) {
            var position = marker.getLatLng();
            $('#lat').val(position.lat);
            $('#lon').val(position.lng);
            $('#addwpt').text('Add Waypoint');
            $('#delwpt').hide();
            get_altitude(position);
        });

}
function plot_region_wp(regPk)
{
    var row;
    var polyline;
    var gll;
    var count;
    var pos;
    var wpt;

 // FIX: should show already awarded ones ...
    microAjax("get_waypoints.php?regPk="+regPk,
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

    var region = JSON.parse(data);
    track = region.waypoints;
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
        wpt = add_label(map, gll, cname, "waypoint", [ 60, 17 ]);
        L.Util.setOptions(wpt.options.icon, { 'id' : rwpPk, 'name' : cname, 'desc' : desc });
        //wpt.properties.wptid = cname;
        if (is_admin())
        {
            wpt.dragging.enable();
            wpt.on('dragend', function(ev) {
                var position = ev.target.getLatLng();
                $('#rwpPk').val(this.options.icon.options.id);
                $('#wptid').val(this.options.icon.options.name);
                $('#wptdesc').val(this.options.icon.options.desc);
                $('#lat').val(position.lat);
                $('#lon').val(position.lng);
                $('#addwpt').text('Update Waypoint');
                $('#delwpt').show();
                get_altitude(position);
            });
        }
    }

    map.fitBounds(bounds);
    });
}
function download_waypoints()
{
    var regPk = url_parameter('regPk');
    var wptformat = $('#wptformat option:selected').val();
    post('download_waypoints.php?download=' + regPk, { 'format' : wptformat }, 'post');
}
$(document).ready(function() {
    var regPk = url_parameter("regPk");

    var mapdiv = document.getElementById("map");
    mapdiv.setAttribute('style', 'top: 0px; left: 0px; width:100%; height:90vh; float: left');
    map = add_map_server('map', 0);
    add_map_extra(map);
    if (!is_admin())
    {
        $('#airdiv').hide();
    }
    $('#delwpt').hide();
    plot_region_wp(regPk);
    L.control.ruler().addTo(map);
    $('.leaflet-control-layers input', '#map').get(1).click()
});

