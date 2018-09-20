function create_waypoint_marker()
{
    var bounds = map.getBounds();
    var centre = map.getCenter();

    var marker = L.marker(centre, { 
            draggable: true,
            autoPan: true
        }).addTo(map);

    marker.on('dragend', function(event) {
            var position = marker.getLatLng();
            $('#lat').val(position.lat);
            $('#lon').val(position.lng);
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

    var region = JSON.parse(data);
    track = region.waypoints;
    count = 1;
    bounds = new L.LatLngBounds();
    for (var row = 0; row < track.length; row++)
    {
        lasLat = track[row].lat;
        lasLon = track[row].lon;
        cname = track[row].name;;

        gll = new L.LatLng(lasLat, lasLon);
        bounds.extend(gll);
        wpt = add_label(map, gll, cname, "waypoint", [ 60, 17 ]);
    }

    map.fitBounds(bounds);
    });
}
function download_waypoints()
{
    var regPk = url_parameter('regPk');
    var wptformat = $('#wptformat').value;
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
    plot_region_wp(regPk);
});

