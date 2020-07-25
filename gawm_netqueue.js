
var requestInFlight = null;
var requestQueue = [];

function http_response_handler()
{
    // 4 is done, dont need to do any work on any other readyState
    if (this.readyState == 4)
    {
        if  (this.status == 200)
        {
            var result = JSON.parse(this.responseText);
        
            if (result.game)
            {
                render_game(result.game);
            }
        
            if (result.game_id)
            {
                game_id = result.game_id;
                console.log("Game Id:" + result.game_id);
            }
        
            if (result.player_id)
            {
                local_player_id = result.player_id;
                console.log("Player Id:" + result.player_id);  
            }
        
            if (result.pending_id)
            {
                error_popup("Request processed, pending "+result.pending_id);
            }
            
            if (requestInFlight.callback)
                requestInFlight.callback();
        }
        // gawm api uses 400 to indicate a failed request of some sort
        // 400s are reserved for "your not allowed to do that", and return simple messages
        else if (this.status == 400)
        {
            error_popup(this.responseText);
        }
        else 
        {
            error_popup("Unexpected "+this.status+": "+this.responseText);
        }
        
        requestInFlight=null;  
        gawm_pumpRequestQueue();
    }
}

function gawm_pumpRequestQueue()
{
    if (requestQueue.length > 0 && requestInFlight==null)
    {
        requestInFlight=requestQueue.shift();

        requestInFlight.xmlhttp.open("POST", "game.php", true);
        requestInFlight.xmlhttp.send( JSON.stringify(requestInFlight.request) );
    }
}

function gawm_sendrequest(request, callback)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = http_response_handler;
    
    var record = {};
    record.xmlhttp=xmlhttp;
    record.request=request;
    record.callback=callback;
    
    requestQueue.push(record);
    
    if (requestInFlight==null)
    {
        gawm_pumpRequestQueue();
    }
}
