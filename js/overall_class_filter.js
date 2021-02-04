
$.fn.dataTable.ext.search.push(
    function( settings, data, dataIndex, row, counter ) {
        var flyclass = $('#dhv option:selected').val();
 
        console.log('flyclass='+flyclass+' d[8]='+data[8]);
        if (flyclass >= 'F')
        {
            // HG
            if (flyclass == '' || flyclass == 'open' || flyclass == 'Open') return true;
            if (data[8] < 'F')
            {
                return false;
            }
            if (data[8] <= flyclass)
            {
                return true;
            }
        }
        else
        {
            if (flyclass == '' || flyclass == 'competition' || flyclass == 'open') return true;
            if (data[8] >= 'F')
            {
                return false;
            }
            if (flyclass == 'CCC' || flyclass == 'Open') return true;

            if (flyclass == '2/3' || flyclass == 'D')
            {
                if (data[8] == 'competition' || data[8] == 'CCC') return false;
                return true;
            }

            if (data[8] <= flyclass)
            {
                return true;
            }
        }
        return false;
    }
);
 
$(document).ready(function() {
    // Event listener to the two range filtering inputs to redraw on input
    $('#dhv').change( function() {
        var table = $('#task_result').DataTable();
        var flyclass = $('#dhv option:selected').val();
        // console.log('flyclass='+flyclass);
        table.search('').draw();
    } );
} );


