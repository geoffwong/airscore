
$(document).ready(function() {
    var url = new URL('http://highcloud.net/xc/get_local_airspace.php'+ window.location.search);
    //var tasPk = url.searchParams.get("tasPk");

    new microAjax("get_local_airspace.php" + window.location.search,
        function(data) {
        all_airspace = JSON.parse(data);

        if (all_airspace)
        {
            $.each(all_airspace, function (i, item) {
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
});
