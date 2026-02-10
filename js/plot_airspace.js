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

function download_airspace()
{
    var argPk = url_parameter('argPk');
    post('download_airspace.php?argPk=' + argPk, { }, 'post');
}

$("#airspace")
  .change(function () {
    var str = "";
    $("select option:selected").each(function(i, el) {
			var key=$(el).val();
            aironscreen[key].removeFrom(map);
            if (airlabelonscreen.hasOwnProperty(key))
            {
                airlabelonscreen[key].removeFrom(map);
            }
			plot_air(map, key, all_airspace[key], '#0000ff', 1);
    });
    //$("div").text( str );
  })

$(document).ready(function() {
    var regPk = url_parameter("argPk");
    var airPk = url_parameter("airPk");
    var mapdiv = document.getElementById("map");
    mapdiv.setAttribute('style', 'top: 0px; left: 0px; width:100%; height:90vh; float: left');
    map = add_map_server('map', 0);
    add_map_extra(map);
    //add_track(comPk, trackid, 5);

    if (regPk > 0)
    {
        new microAjax("get_region_airspace.php" + window.location.search,
            function(data) {
            all_airspace = JSON.parse(data);
    
            if (all_airspace)
            {
                $.each(all_airspace, function (i, item) {
                    plot_air(map, i, all_airspace[i], '#ff00ff', 0);
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
    }

    if (airPk > 0)
    {
        $('#airdiv').hide();
        new microAjax("get_airspace.php" + window.location.search,
            function(data) {
                airspace = JSON.parse(data);
	            plot_air(map, airPk, airspace[airPk], '#0000ff', 1);
            }); }
});

