var onscreen = Array();
var current = -1;
var pause = 1;
var timer;
var trackid;
var tasPk;
var interval = 5;

String.prototype.format = function()
{
    var pattern = /\{\d+\}/g;
    var args = arguments;
    return this.replace(pattern, function(capture){ return args[capture.match(/\d+/)]; });
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
        post('download_tracks.php?tasPk=' + tasPk, { }, 'post');
    }
}

$(document).ready(function() {
    var comPk = url_parameter("comPk");
    var tasPk = url_parameter("tasPk");

    var mapdiv = document.getElementById("map");
    mapdiv.setAttribute('style', 'top: 0px; left: 0px; width:100%; height:90vh; float: left');
    map = add_map_server('map', 0);
    add_map_extra(map);
    add_play_controls(map);

    //add_track(comPk, trackid, 5);
});

