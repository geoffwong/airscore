
$(document).ready(function() {
    new microAjax("get_local_tracks.php" + window.location.search,
        function(data) {
        all_airspace = JSON.parse(data);

        $.each(all_airspace, function (i, item) {
            $('#tracks').append($('<option>', { 
                value: item.value,
                text : item.text
            }));
            });
        });
});
