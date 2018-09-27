var srtm_canvas_cache = Array();
function get_pixel(imgsrc, x, y) 
{
    var canvas;
    var context;

    if (srtm_canvas_cache[imgsrc])
    {
        canvas = srtm_canvas_cache[imgsrc];
        context = canvas.getContext('2d');
        result = context.getImageData(x, y, 1, 1).data;
        $('#alt').val(result[0]*10);
    }
    else
    {
        var img = new Image();
        img.src = imgsrc;
        console.log(imgsrc);
        canvas = document.createElement('canvas');
        canvas.width=6000;
        canvas.height=6000;
        context = canvas.getContext('2d', { alpha: false });
        srtm_canvas_cache[imgsrc] = canvas;

        img.onload = function () {
            context.drawImage(img, 0, 0);
            result =  context.getImageData(x, y, 1, 1).data;
             $('#alt').val(result[0]*10);
        }
    }
}
function get_altitude(position, x, y) 
{
    var lat, lng;
    var ns = 'N';
    var ew = 'E';

    if (position.lat < 0)
    {
        lat = -((Math.floor(position.lat / 5)) * 5);
        ns = 'S';
    }
    else
    {
        lat = Math.floor(position.lat / 5) * 5;
    }

    if (position.lng < 0)
    {
        lng = - ((Math.floor(position.lng / 5))* 5);
        ew = 'W';
    }
    else
    {
        lng = Math.floor(position.lng / 5) * 5;
    }
  
    var imgsrc = 'images/' + lat + ns + '_' + lng + ew + '.png';


    var remlat = Math.abs(position.lat) - lat + 5;
    var remlng = Math.abs(position.lng) - lng; 
    var cellsize = 0.00083333333333333;
    var x = remlat / cellsize;
    var y = remlng / cellsize;

    var deci = get_pixel(imgsrc, x, y);
}

//var pos = { lat : -37.5, lng : 147.44 };
//var pos = { lat : -36.803889, lng: 147.031781 };
//var pos = { lat : -36.390579, lng: 146.442617 };
//result = get_altitude(pos); // [255, 255, 255, 0];
//console.log(result);


