
var requestInFlight = null;
var requestQueue = [];
var refreshTimeout = undefined;

// reload on gain focus
window.addEventListener("focus", function(event) {
    if (refreshTimeout==undefined) { reload(local_player_id,game_id); }
}, false);

function http_response_handler()
{
    // 4 is done, dont need to do any work on any other readyState
    if (this.readyState == 4)
    {
        if  (this.status == 200)
        {
            var result = JSON.parse(this.responseText);

            var force_rebuild = false;
            if (result.game_id && game_id!=result.game_id)
            {
                game_id = result.game_id;
                console.log("New Game Id:" + result.game_id);
                force_rebuild = true;
            }

            if (result.player_id && result.player_id!=victim_player_id && result.player_id!=local_player_id)
            {
                local_player_id = result.player_id;
                console.log("New Player Id:" + result.player_id);
                force_rebuild = true;
            }

            if (result.game)
            {
                // only rebuild the DOM if the data has changed
                if (force_rebuild || (JSON.stringify(result.game)!=JSON.stringify(game)))
                {
                    render_game(result.game);
                }
            }

            if (result.pending_id)
            {
                error_popup(
                    "Request processed, pending " +
                    result.pending_id
                );
            }

            if (requestInFlight.callback)
            {
                requestInFlight.callback();
            }
        }

        // gawm api uses 400 to indicate a failed request of some sort
        // 400s are reserved for "your not allowed to do that",
        // and return simple messages
        else if (this.status == 400)
        {
            error_popup(this.responseText);
        }
        else
        {
            error_popup("Unexpected "+this.status+": "+this.responseText);
        }

        document.getElementById("loading_div").style.display="none";
        requestInFlight=null;
        gawm_pumpRequestQueue();
    }
}

function gawm_pumpRequestQueue()
{
    if (requestQueue.length > 0 && requestInFlight==null)
    {
        requestInFlight=requestQueue.shift();

        requestInFlight.xmlhttp.open("POST", "api/game.php", true);
        requestInFlight.xmlhttp.send( JSON.stringify(requestInFlight.request) );
        document.getElementById("loading_div").style.display="block";
    }
    else
    {
        // if we dont already have a timer running
        if (refreshTimeout==undefined)
        {
            // stop polling after the game is finished
            if (!is_gameover())
            {
                // set a timer to refresh in 5s
                refreshTimeout = setTimeout(function(){
                    refreshTimeout = undefined;
                    // only refresh if we have focus, and nothing queued
                    if (requestInFlight==null && document.hasFocus())
                    {
                        if (document.activeElement.hasAttribute('contenteditable'))
                        {
                            // if the user is editing a note, just wait another refresh period
                            gawm_pumpRequestQueue();
                        }
                        else
                        {
                            // request an update from the server
                            reload(local_player_id,game_id);
                        }
                    }
                }, 5000);
            }
        }
    }
}

function gawm_sendrequest(request, callback)
{
    // prevent request (click) spam
    if (requestInFlight && requestInFlight.request == request) {
        return;
    }

    for (i = 0; i < requestQueue.length; i++) {
        if (requestQueue[i].request == request) {
            return;
        }
    }

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = http_response_handler;

    var record = {};
    record.xmlhttp=xmlhttp;
    record.request=request;
    record.callback=callback;

    requestQueue.push(record);

    if (requestInFlight==null) {
        gawm_pumpRequestQueue();
    }
}
