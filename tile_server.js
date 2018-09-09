var tile_server_selection = 0;
var http = 'https://';
//http var tileserver = 'tile.opentopomap.org/{z}/{x}/{y}.png';
//https var tileserver = 'tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=b58289867df642afbd83cbea937efcb5'
//var tileserver = 'server.arcgisonline.com/arcgis/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}';
var arc_gis_ts = 'server.arcgisonline.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
var esri_ts = 'server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}.jpg';
var esri_topo_ts = 'services.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}.jpg';
var esri_semi_ts = 'server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Reference_Overlay/MapServer/tile/{z}/{y}/{x}.jpg';
//var esri_semi_ts = 'orona.geog.uni-heidelberg.de/tiles/hybrid/x={x}&y={y}&z={z}';

var topo;
var streets;
var satellite;
var satoverlay;
var base_maps;
var overlap_maps;

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

function add_map_server(name, pos, zoom)
{
    map = L.map(name).setView(pos, zoom);

    satellite = L.tileLayer(get_tileserver(arc_gis_ts, []), {
        //attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, SRTM | Style &copy; OpenTopoMap <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
        attribution: '&copy; Esri, DigitalGlobe, GeoEye, Earthstar Geographics, CNES/Airbus DS, USDA, USGS, AeroGRID, IGN, GIS User Community',
        maxZoom: 18, id: 'satellite',
    }).addTo(map);

    streets  = L.tileLayer(get_tileserver(esri_ts, []), {id: 'streets', 
        attribution: '&copy; Esri, HERE, Garmin, USGS, Intermap, INCREMENT P, NRCan, Esri Japan, METI, NGCC, OSM contributors, GIS User Community'
        });

    topo = L.tileLayer(get_tileserver(esri_topo_ts, []), {id: 'topo', 
        attribution: '&copy; Esri, HERE, Garmin, Intermap, increment P Corp., GEBCO, USGS, FAO, NPS, NRCAN, GeoBase, IGN, Kadaster NL, Ordnance Survey, METI, swisstopo, OSM contributors, GIS User Community'
        });

    satoverlay = L.tileLayer(get_tileserver(esri_semi_ts, []), {id: 'satover', attribution: 'esri overlay'});
    
    base_maps = { 'sat' : satellite, 'topo' : topo, 'streets': streets };
    overlay_maps = { }; // { 'overlay' : satoverlay };
    L.control.layers(base_maps, overlay_maps).addTo(map);
    return map;
}

