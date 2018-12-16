
function login()
{
    var options = { };
    options.username = $('#username').val();
    options.password = $('#password').val();

    $.post('jslogin.php', options, function (res) {
        console.log(res);
        if (res.result == "failed")
		{
            alert("Authorisation Failure");
			return;
		}
        else if (res.result == "ok")
		{
        	window.location.replace(window.location.href);
		}
		else
        {
            alert(res.result + ": " + res.error);
            return;
        }
    }, "json");
}

function authorise()
{
    $(document.body).append(`<div class="row"><div class="modal fade" id="login" tabindex="-1" role="dialog" aria-labelledby="loginModalCenter" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">airScore Login</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <div class="modal-body">
		    <form id="createcomp" action="javascript:void(0);" method="post">
		    <div class="form-group">
		    <div class="form-row">
			  	<label for="date-from" class="col-4 col-form-label">Username</label>
			    <div class="col-8"><input class="form-control" type="text" value="" id="username"></div>
		    </div>
		    <div class="form-row">
			  	<label for="date-from" class="col-4 col-form-label">Password</label>
			    <div class="col-8"><input class="form-control" type="password" value="" id="password"></div>
		    </div>
			</div>
			</form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-dismiss="modal" onclick="login();">Login</button>
          <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div></div></div>`);

    $("#login").modal();
}

