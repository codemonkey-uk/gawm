var cards = null;
var game = null;
var game_id = 0;

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
    // temp workaround, card json doesn't have guilt/innocence tokens
    if (deck=="guilt" || deck=='innocence')
        return 'assets/'+deck+'_'+i+'.png';

    return 'assets/'+cards[deck][i]['img'];
}

function img_alt(deck,i)
{
    // temp workaround, card json doesn't have guilt/innocence tokens
    if (deck=='guilt' || deck=='innocence')
        return deck;

    return cards[deck][i]['name']+" ("+cards[deck][i]['subtype']+'): '+cards[deck][i]['desc'];
}

var card_template = `<div class="halfcard $TYPE"> 
  <div class="header" onclick="toggle_show('actions-$ID')">
  <div class="title">$TYPE</div>
  <div class="subtitle">$SUBTYPE</div>  
  </div>
  <div class="name" onclick="toggle_show('flavour-$ID')">$NAME</div>
  <div class="actions" id="actions-$ID">$ACTIONS</div>  
  <div class="flavour" id='flavour-$ID' onclick="toggle_show('flavour-$ID')"><p>$DESC</p></div>
</div>`;

function toggle_show(id) {
  var popup = document.getElementById(id);
  popup.classList.toggle("show");
}

function hand_tostr(hand,player_id,action,postfix)
{
    var html = "<div class='hand'>";
    for (var deck in hand)
    {
        for (var card in hand[deck])
        {
            var i = hand[deck][card];
            var menu = "";
            if (action)
            {
                var click = action+"(game, \""+player_id+"\", \""+deck+"\", "+i+")";
                menu += "<button onclick='"+click+"'>"+action+"</button>";
            }
            var card_str = card_template
                .replaceAll('$ID', deck+"_"+i)
                .replaceAll('$TYPE', deck)
                .replaceAll('$SUBTYPE', cards[deck][i]['subtype'])
                .replaceAll('$NAME', cards[deck][i]['name'])
                .replaceAll('$DESC', cards[deck][i]['desc'])
                .replaceAll('$ACTIONS', menu);
            
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

function active_player_idx()
{
    var c = Object.keys(game.players).length;
    if (game.act==1 && game.scene==c)
    {
        // extra scene, active player should match victim
        var idx = 1;
        for (var player_id in game.players)
        {
            console.log(player_id + " => " +game.victim.player_id);
            if (player_id==game.victim.player_id)
            {
                console.log("active_player_idx in extra scene: "+idx);
                return idx;
            }
            idx++;
        }
    }

    return (game.scene%Object.keys(game.players).length)+1;
}

function show_hand(player,player_uid)
{
    // note:
    // victim can have details but not vote
    // players can vote when they have no details
    return (player.hand && Object.keys(player.hand).length>0) ||
        (game.act < 4 && player_uid!=0);
    ;
}

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

function render_player(player,player_uid,player_idx)
{
    var html = "<div class='player'>";
    if (player_idx>0)
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
    
    // TODO: in last break, active player (most innocent) should SET accused for epilogue

    var detail_action = is_twist() ? "twistdetail" : "playdetail";
    if (show_hand(player,player_uid))
    {
        // voting buttons?
        var pfn = (game_stage_voting() && player_uid!=0) ?
            function(){
                var str = "";
                if (typeof player.vote == "undefined" || player.vote == 1)
                    str += votebutton_html(player_uid,2);
                if (typeof player.vote == "undefined" || player.vote == 2)
                    str += votebutton_html(player_uid,1);
                return str;
            } : null;
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

    html += "<div class='game'>";
    html += "<div>" + game_stage_str() + "</div>";
    if (result.victim)
    {
        html += render_player(result.victim,0,0);
    }
    var player_idx  = 1;
    for (var player in result.players)
        html += render_player(result.players[player],player,player_idx++);
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

function detailaction_ex(gamestate,player_id,detail_type,detail,action,target_id)
{
    console.log("playdetail called:",player_id,detail_type,detail);

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var result = JSON.parse(this.responseText);
            document.getElementById('debug').value = this.responseText;

            render_game(result);
        }
    };

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

function add_player(gamestate)
{
    var player_name = prompt("Please enter your name", "Player "+(Object.keys(game.players).length+1));

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById('debug').value  = this.responseText;
            var result = JSON.parse(this.responseText);
            render_game(result);
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
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById('debug').value  = this.responseText;
            var result = JSON.parse(this.responseText);
            render_game(result);
        }
    };

    // build request json
    var request = {};
    request.action = 'next';
    request.game_id = game_id;

    xmlhttp.open("POST", "game.php", true);
    xmlhttp.send( JSON.stringify(request) );
}

function new_game()
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById('debug').value = this.responseText;
            var result = JSON.parse(this.responseText);
            render_game(result.game);
            console.log("Game Id:" + result.game_id);
            game_id = result.game_id;
        }
    };

    // build request json
    var request = {};
    request.action = 'new';

    xmlhttp.open("POST", "game.php", true);
    xmlhttp.send( JSON.stringify(request) );
}

function reset()
{
    var result = JSON.parse(document.getElementById('debug').value );
    render_game(result);
}

function start()
{
    load_cards();
    new_game();
}