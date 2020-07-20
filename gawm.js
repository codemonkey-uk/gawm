
// put theses in a gawm object maybe? use modules maybe?
var cards = null;
var game = null;
var game_id = 0;
var local_player_id = 0;

function load_cards()
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            cards = JSON.parse(this.responseText);
        }
    };

    xmlhttp.open("GET", "cards.json", true);
    xmlhttp.send();
}

function img_url(deck,i)
{
    // redacted cards/tokens
    if (i<0)
    {
        switch (deck){
            case "aliases": return 'assets/alias_back.png';
            case "motives": return 'assets/motive_back.png';
            case "objects": return 'assets/object_back.png';
            case "relationships": return 'assets/rel_back.png';
            case "wildcards": return 'assets/wild_back.png';
            case "guilt": return 'assets/guilt.png';
            case "innocence": return 'assets/innocence.png';            
        }
    }
    
    // temp workaround, card json doesn't have guilt/innocence tokens
    if (deck=="guilt" || deck=='innocence')
        return 'assets/'+deck+'_'+i+'.png';
    
    return 'assets/'+cards[deck][i]['img'];
}

function img_alt(deck,i)
{
    // temp workaround, card json doesn't have guilt/innocence tokens
    if (deck=='guilt' || deck=='innocence' || i<0)
        return deck;
    
    return cards[deck][i]['name']+" ("+cards[deck][i]['subtype']+'): '+cards[deck][i]['desc'];
}

var card_template = `<div class="halfcard _TYPE"> 
  <div class="header" style="_CURSOR" onclick="toggle_show('actions-_ID_TYPE')">
  <div class="title">_TYPE</div>
  <div class="subtitle">_SUBTYPE</div>  
  </div>
  <div class="name" onclick="toggle_show('flavour-_ID_TYPE')"><p>_NAME</p></div>
  <div class="actions" id="actions-_ID_TYPE">_ACTIONS</div>  
  <div class="flavour" id='flavour-_ID_TYPE'>
  <p id='flavour-_ID_editp' contenteditable="true" onblur="saveEdit('flavour-_ID_editp','_TYPE','_ID')">_DESC</p>
  </div>
</div>`;

function saveEdit(p_id,detail_type,d_id)
{
    var t = document.getElementById(p_id).innerHTML;
    t = t.replace(/<br>$/,'');
    console.log(t);
    edit_note(game,local_player_id,detail_type,d_id,t);
}

function toggle_show(id)
{
    var popup = document.getElementById(id);
    popup.classList.toggle("show");
}

function isFunction(functionToCheck)
{
    return functionToCheck && {}.toString.call(functionToCheck) === '[object Function]';
}

function hand_tostr(hand,player_id,action,postfix)
{
    var html = "<div class='hand'>";
    for (var deck in hand)
    {
        for (var card in hand[deck])
        {
            var card_str = "";
            var i = hand[deck][card];
            if (deck=="guilt" || deck=='innocence' || i<0)
            {
                // TODO: img based token div, css version
                var url = img_url(deck,i);
                var alt = img_alt(deck,i);
                var img = "<img src=\"" +url+ "\" style='max-width: 100%;max-height: 100%;' alt=\""+alt+"\">";
                var click = (i>=0) ? action+"(game, \""+player_id+"\", \""+deck+"\", "+i+")" : "";
                card_str += "<div class='token' onclick='" +click+ "'>";
                card_str += img;
                card_str += "</div>";
            }
            else 
            {
                var menu = "";
                if (action)
                {
                    if (isFunction(action))
                    {
                        menu += action(deck, i);
                    }
                    else
                    {
                        // old single action path, todo: deprecate this
                        var click = action+"(game, \""+player_id+"\", \""+deck+"\", "+i+")";
                        menu += "<button onclick='"+click+"'>"+action+"</button>";
                    }
                }
                
                // if note is set, use that
                var note = game['notes'][deck] ? game['notes'][deck][i] : null;
                var desc = note ? note : cards[deck][i]['desc'];
                // if note is set, mark card
                // todo: take first line from note for name, if note is set
                var name = cards[deck][i]['name'];
                if (note) name += "*";
                
                cursor = menu.length > 0 ? "cursor: context-menu;" : "";
                card_str = card_template
                    .replace(/_ID/g, i)
                    .replace(/_TYPE/g, deck)
                    .replace(/_SUBTYPE/g, cards[deck][i]['subtype'])
                    .replace(/_NAME/g, name)
                    .replace(/_DESC/g, desc)
                    .replace(/_CURSOR/g, cursor)
                    .replace(/_ACTIONS/g, menu);
            }
            html += card_str;
        }
    }
    if (postfix)
        html += postfix();
    html += '</div>';
    return html;
}

function is_twist()
{
    var c = Object.keys(game.players).length;
    return (game.act==2 && game.scene==c);
}

function is_firstbreak()
{
    var c = Object.keys(game.players).length;
    return (game.act==1 && game.scene==c+1);
}

function votediv_html(player_id,value,action)
{
    var value_str = (value == 1) ? "guilt" : "innocence";
    var html ="";
    var url = 'assets/'+value_str+'.png';
    var img = "<img src=\"" +url+ "\" style='max-width: 100%;max-height: 100%;' alt=\""+value_str+"\">";
    html += "<div class='token' onclick='" +action+ "'>";
    html += img;
    html += "</div>"
    return html;
}

function votebutton_html(player_id,value)
{
    var click = "vote(game, \""+player_id+"\", "+value+")";
    return votediv_html(player_id,value,click);
}

function game_stage_voting()
{
    var c = Object.keys(game.players).length;
    if (game.act==3)
        c = c*2;

    if (game.act == 0) return false;
    if (game.act==1 && game.scene==c) return true;
    if (game.act==1 && game.scene>c) return false;
    if (is_twist()) return false;
    if (game.act==3 && game.scene==c) return false;

    return true;
}

function show_hand(player,player_uid)
{
    // note:
    // victim can have details but not vote
    // players can vote when they have no details
    return (player.hand && Object.keys(player.hand).length>0) ||
        (game.act < 4 && player_uid!=0);
}

// TODO see: https://github.com/codemonkey-uk/gawm/issues/21
function render_unassigned_token(player,player_uid)
{
    var html = "<div>Give: <b>"+player.unassigned_token+"</b> to: ";
    for (var p in game.players)
    {
        if (p!=player_uid)
        {
            var click = "givetoken(game, \""+player_uid+"\", \""+p+"\")";
            html += "<div onclick='"+click+"'>"+game.players[p].name+"</div>";
        }
    }

    var click = "givetoken(game, \""+player_uid+"\", \"0\")";
    html += "<div onclick='"+click+"'>Discard</div>";
    return html; 
}

// TODO see: https://github.com/codemonkey-uk/gawm/issues/23
function render_record_accused(player,player_uid)
{
    var html = "";
    if (game.the_accused)
        html += "<div>Accused => " + game.players[game.the_accused].name + "</div>";
    html += "<div>Accuse: ";
    for (var p in game.players)
    {
        if (p!=player_uid)
        {
            var click = "record_accused(game, \""+player_uid+"\", \""+p+"\")";
            html += "<div onclick='"+click+"'>"+game.players[p].name+"</div>";
        }
    }

    return html; 
}

function render_player(player,player_uid)
{
    var html = "<div class='player'>";
    if (player_uid!=0)
    {
        html += "<div>Player: " + player.name;
        if (player.fate)
            html += " ("+player.fate+")";
        html += ", Hand: </div>";
    }
    else
    {
        var c = Object.keys(game.players).length;
        if (game.act==1 && game.scene==c)
            html += "<div>The Victim will be... (not yet)</div>";
        else
            html += "<div>The Victim.</div>";
    }

    if (player.unassigned_token)
    {
        html += render_unassigned_token(player,player_uid);
    }
    else if (game.most_innocent==player_uid && game.act==3)
    {
        html += render_record_accused(player,player_uid);
    }
    else if (show_hand(player,player_uid))
    {
        // voting buttons?
        var pfn = (game_stage_voting() && player_uid==local_player_id) ?
            function(){
                var str = "";
                if (typeof player.vote == "undefined" || player.vote == 1)
                    str += votebutton_html(player_uid,2);
                if (typeof player.vote == "undefined" || player.vote == 2)
                    str += votebutton_html(player_uid,1);
                return str;
            } : null;
            
        var detail_action = is_twist() ? "twistdetail" :
            function(deck, id){
                
                var fn = function(target_id)
                {
                    var result = "";
                    // motives cant be given until 2nd act,
                    // aliases can only be given to yourself
                    // murder details can only be given to the victim
                    var can_give = 
                        (game.act >= 2 || deck!="motives") && 
                        (player_uid==p || deck!="aliases") &&
                        (target_id==0 || !deck.startsWith("murder_"));
                        
                    if (can_give)
                    {
                        var click = "detailaction_ex(game, \""+player_uid+"\", \""+deck+"\", "+id+",\"play_detail\",\""+target_id+"\")";
                        var name = (target_id==0) ? "The Victim" : game.players[target_id].name;
                        result += "<button onclick='"+click+"'>Give to "+name+"</button>";
                    }
                    
                    return result;
                }
                
                var result = "";
                if (is_firstbreak())
                    result += fn(0);
                for (var p in game.players)
                    result += fn(p);
                    
                return result;
            };
        html += hand_tostr(player.hand,player_uid,detail_action,pfn);
    }
    html += "<div>Details in play: </div>";
    if (player.play)
    {
        html += hand_tostr(player.play,player_uid,null,
            function(){
                var str = "";
                if (typeof player.vote != "undefined")
                    str += votediv_html(player_uid,player.vote,"");
                return str;
            }
        );
    }
    if (player.tokens)
    {
        html += "<div>Tokens recieved: </div>";
        html += hand_tostr(player.tokens,player_uid,null,null);
    }
    html += '</div>';
    return html;
}

function game_stage_str()
{
    if (game.act == 0)
        return "Setup - Add Players and Select Alias Details.";

    var c = Object.keys(game.players).length;

    if (game.act==1 && game.scene==c)
        return "Extra Scene: Introduce a new character.";

    if (game.act==1 && game.scene==c+1)
        return "First Break: The Murder.";

    if (is_twist())
        return "Second Break: The Twist.";

    if (game.act==3)
        c = c*2;

    if (game.act==3 && game.scene==c)
    {
        var n = game.most_innocent;//game.players[game.most_innocent].name;
        return "Last Break: The Accusation ("+n+") and The Guilty.";
    }

    var act = "";
    if (game.act==1)
        act = "Act I: Introductions";
    if (game.act==2)
        act = "Act II: Investigations";
    if (game.act==3)
        act = "Act III: Incriminations";

    if (game.act==4)
        act = "Epilogue";

    return act + " - Scene " + (game.scene+1) + " / " + c;
}

function render_game(result)
{
    game = result;
    var html = "";

    // temp view-switch ui
     html += "<span>VIEW: </span>";
    for (var player in game.players)
    {
        if (player==local_player_id) html+="<b>";
        html += "<span onclick='reload(\""+player+"\");'> - "+game.players[player].name+" - </span>";
        if (player==local_player_id) html+="</b>";
    }
        
    html += "<div class='game'>";
    html += "<div>" + game_stage_str() + "</div>";
    if (result.victim)
    {
        html += render_player(result.victim,0,0);
    }

    html += render_player(result.players[local_player_id],local_player_id);
    
    for (var player in result.players)
    {
        if (player!=local_player_id)
            html += render_player(result.players[player],player);
    }
    html += "</div>";

    document.getElementById('players').innerHTML = html;
    if (game.act>0)
        document.getElementById("add_player").style.visibility = "hidden";
    else
        document.getElementById("add_player").style.visibility = "visible";
}

function givetoken(gamestate,player_id,value)
{
    detailaction(gamestate,player_id,"token",value,"give_token");
}

function record_accused(gamestate,player_id,value)
{
    detailaction(gamestate,player_id,"token",value,"record_accused");
}

function vote(gamestate,player_id,value)
{
    detailaction(gamestate,player_id,"vote",value,"vote");
}

function playdetail(gamestate,player_id,detail_type,detail)
{
    detailaction(gamestate,player_id,detail_type,detail,"play_detail");
}

function twistdetail(gamestate,player_id,detail_type,detail)
{
    detailaction(gamestate,player_id,detail_type,detail,"twist_detail");
}

function detailaction(gamestate,player_id,detail_type,detail,action)
{
    detailaction_ex(gamestate,player_id,detail_type,detail,action,player_id);
}

function generic_response_handler()
{
    if (this.readyState == 4 && this.status == 200) {
        var result = JSON.parse(this.responseText);
        document.getElementById('debug').value = this.responseText;

        render_game(result.game);
    }
};
    
function detailaction_ex(gamestate,player_id,detail_type,detail,action,target_id)
{
    console.log("api action called: ",action,player_id,target_id,detail_type,detail);

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = generic_response_handler;

    // build request json
    var request = {};
    request.action = action;
    request.game_id = game_id;
    request.player_id=player_id;
    request.target_id=target_id;    
    request.detail_type=detail_type;
    request.detail=detail;

    xmlhttp.open("POST", "game.php", true);
    xmlhttp.send( JSON.stringify(request) );
}

// edit_note(&$data, $player_id, $detail_type, $detail, $note)
function edit_note(gamestate,player_id,detail_type,detail,note)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = generic_response_handler;
    
    // build request json
    var request = {};
    request.action = "edit_note";
    request.game_id = game_id;
    request.player_id=player_id;
    request.detail_type=detail_type;
    request.detail=detail;
    request.note=note;
    
    xmlhttp.open("POST", "game.php", true);
    xmlhttp.send( JSON.stringify(request) );
}

function add_player(gamestate)
{
    var player_name = prompt("Please enter your name", "Player "+(Object.keys(game.players).length+1));

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById('debug').value  = this.responseText;
            var result = JSON.parse(this.responseText);
            local_player_id = result.player_id
            console.log("New player: "+local_player_id);
            render_game(result.game);            
        }
    };

    xmlhttp.open("POST", "game.php", true);

    var request = {};
    request.action = 'add_player';
    request.game_id = game_id;
    request.player_name = player_name;
    xmlhttp.send( JSON.stringify(request) );
}

function next(gamestate)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = generic_response_handler;

    // build request json
    var request = {};
    request.action = 'next';
    request.game_id = game_id;
    request.player_id = local_player_id; // TODO: will be required when information hiding is implemented

    xmlhttp.open("POST", "game.php", true);
    xmlhttp.send( JSON.stringify(request) );
}

function reload(player_id)
{
    // player view switching debug hax
    if (player_id)
        local_player_id = player_id;
        
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = generic_response_handler;

    // build request json
    var request = {};
    request.action = 'get';
    request.game_id = game_id;
    request.player_id = local_player_id;

    xmlhttp.open("POST", "game.php", true);
    xmlhttp.send( JSON.stringify(request) );
}

function new_game()
{
    var player_name = prompt("Please enter your name", "Player 1");
    
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById('debug').value = this.responseText;
            var result = JSON.parse(this.responseText);
            console.log("Game Id:" + result.game_id);
            console.log("Player Id:" + result.player_id);            
            game_id = result.game_id;
            local_player_id = result.player_id;
            render_game(result.game);            
        }
    };

    // build request json
    var request = {};
    request.action = 'new';
    request.player_name = player_name;

    xmlhttp.open("POST", "game.php", true);
    xmlhttp.send( JSON.stringify(request) );
}
