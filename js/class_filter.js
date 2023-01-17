
$.fn.dataTable.ext.search.push(
    function( settings, data, dataIndex, row, counter ) {
        var flyclass = $('#dhv option:selected').val();
 
        if (flyclass == '' || flyclass == 'CCC') return true;
        if (flyclass == 'D')
        {
            if (data[5] == 'CCC') return false;
            return true;
        }

        if (data[5] <= flyclass)
        {
            return true;
        }
        return false;
    }
);
 
$(document).ready(function() {
    // Event listener to the two range filtering inputs to redraw on input
    $('#dhv').change( function() {
        var table= $('#task_result').DataTable();
        var flyclass = $('#dhv option:selected').val();
        console.log('flyclass='+flyclass);
        if (flyclass == 'handicap')
        {
            var url = 'handicap_result.html?' + window.location.search.substring(1);
            console.log(url);
            window.location.replace(url);
            return;
        }
        else if (flyclass == 'taskteam')
        {
            var url = 'task_team_result.html?' + window.location.search.substring(1);
            console.log(url);
            window.location.replace(url);
            return;
        }
        else if (flyclass == 'open')
        {
            var url = 'task_result.html?' + window.location.search.substring(1);
            console.log(url);
            window.location.replace(url);
            return;
        }
        table.search('').draw();
    } );
} );


