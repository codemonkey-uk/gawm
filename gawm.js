
// put theses in a gawm object maybe? use modules maybe?
var cards = null;
var game = null;
var game_id = 0;
var local_player_id = 0;

function load_cards(oncomplete)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            cards = JSON.parse(this.responseText);
            oncomplete();
        }
    };

    xmlhttp.open("GET", "cards.json", true);
    xmlhttp.send();
}

function deck_is_token(deck)
{
    return deck=="guilt" || deck=='innocence' || 
        deck.startsWith('accuse'); // accuse + accused
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
            case "accuse": return 'assets/accuse.png';  
            case "accused": return 'assets/accused.png';              
        }
    }
    
    // temp workaround, card json doesn't have guilt/innocence tokens
    if (deck_is_token(deck))
        return 'assets/'+deck+'_'+i+'.png';
    
    return 'assets/'+cards[deck][i]['img'];
}

function img_alt(deck,i)
{
    // temp workaround, card json doesn't have guilt/innocence tokens
    if (deck_is_token(deck) || i<0)
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

function get_contenteditable(p_id)
{
    var t = document.getElementById(p_id).innerText;
    t = t.replace(/<br>$/,'');
    return t;
}

function saveEdit(p_id,detail_type,d_id)
{
    var t = get_contenteditable(p_id);
    edit_note(game,local_player_id,detail_type,d_id,t);
}

function saveName(div_id,player_id)
{
    var t = get_contenteditable(div_id);
    console.log("player rename: "+player_id+" -> "+t);
    rename_player(game,player_id,t);
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

function is_hand_redacted(hand)
{
    for (var deck in hand)
    {
        for (var card in hand[deck])
        {
            var i = hand[deck][card];
            if (i>=0) return false;
        }
    }
    return true;
}

function hand_tostr(hand,player_id,action,postfix,label)
{
    var html = "<div class='hand'>";
    html += "<div class='hand_label'>"+label+"</div>";

    // compact style for fully redacted hands
    var style = is_hand_redacted(hand) ? "grid-template-columns: repeat(12, 1fr);" : "";
    
    html += "<div class='hand_grid' style='"+style+"'>";
    for (var deck in hand)
    {
        for (var card in hand[deck])
        {
            var card_str = "";
            var i = hand[deck][card];
            if (deck_is_token(deck) || i<0)
            {
                var divclass = (deck_is_token(deck)) ? "token" : "cardback";
                var url = img_url(deck,i);
                var alt = img_alt(deck,i);
                var img = "<img src=\"" +url+ "\" style='max-width: 100%;max-height: 100%;' alt=\""+alt+"\">";
                var click = (i>=0) ? action+"(game, \""+player_id+"\", \""+deck+"\", "+i+")" : "";
                card_str += "<div class='"+divclass+"' onclick='" +click+ "'>";
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
                var note = (game['notes'] && game['notes'][deck]) ? game['notes'][deck][i] : null;
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
    html += "<div class='token' onclick='" +action+ "' style='cursor: pointer;'>";
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
    if (game.act>=4) return false;
    
    return true;
}

function show_hand(player,player_uid)
{
    // note:
    // victim can have details but not vote
    // players can vote when they have no details
    return (player.hand && Object.keys(player.hand).length>0) ||
        (game.act < 4 && player_uid!=0) ||
        (player_uid==0 && is_firstbreak()) ||
        player.active ||
        player.fate;
}

var pointer_template = `
<div class="pointer" style="_CURSOR" onclick="toggle_show('pointer_id')">
<div class="frame"><img src="assets/pointer.png"/><p>_TEXT</p></div>
<div class="actions" id="pointer_id">_ACTIONS</div>  
</div>
`;

function pointer_html(text, menu)
{
    var cursor = menu.length > 0 ? "cursor: context-menu;" : "";
    return pointer_template
        .replace(/_CURSOR/g, cursor)
        .replace(/_ACTIONS/g, menu)
        .replace(/_TEXT/g, text);
}

var tokenback_template = `
<div class='token' style="_CURSOR" onclick="toggle_show('actions_TYPE')">
<img src="_IMGURL" style='max-width: 100%;max-height: 100%;' alt="_ALT">
<div class="actions" id="actions_TYPE">_ACTIONS</div>  
</div>
`;

function assign_token_html(type, menu)
{
    var url = img_url(type,-1);
    var alt = img_alt(type,-1);
    var cursor = menu.length > 0 ? "cursor: context-menu;" : "";
    return tokenback_template
        .replace(/_TYPE/g, type)
        .replace(/_CURSOR/g, cursor)
        .replace(/_ACTIONS/g, menu)
        .replace(/_IMGURL/g, url)
        .replace(/_ALT/g, alt);
}

function render_unassigned_token(player,player_uid)
{
    var html = "<div class='hand'>";
    html += "<div class='hand_label'>ASSIGN</div>";
    html += "<div class='hand_grid'>";
    var actions = "";
    for (var p in game.players)
    {
        if (p!=player_uid)
        {
            var click = "givetoken(game, \""+player_uid+"\", \""+p+"\")";
            actions += "<button onclick='"+click+"'>Give to "+player_identity_str(p)+"</button>";
        }
    }

    var click = "givetoken(game, \""+player_uid+"\", \"0\")";
    actions += "<button onclick='"+click+"'>Discard</div>";
    
    html += assign_token_html(player.unassigned_token, actions);
    html += '</div>';//hand_grid
    html += '</div>';//hand
    return html;
}

function render_record_accused(player,player_uid)
{
    var html = "<div class='hand'>";
    html += "<div class='hand_label'>ACCUSE</div>";
    html += "<div class='hand_grid'>";
    
    var actions = "";
    for (var p in game.players)
    {
        if (p!=player_uid)
        {
            var click = "record_accused(game, \""+player_uid+"\", \""+p+"\")";
            actions += "<button onclick='"+click+"'>Accuse "+player_identity_str(p)+"</button>";
        }
    }

    if (game.the_accused)
    {
        actions += "<button onclick='next(game)'>End Scene</button>";
    }
    
    html += pointer_html('NEXT',actions);
    
    html += '</div>';//hand_grid
    html += '</div>';//hand
    return html; 
}

function player_identity_str(player_uid)
{
    return player_identity_template(player_uid,"_NAME (_ALIAS)").replace(' (_ALIAS)','');
}

function player_identity_div(player_uid)
{
    var template = "<div class='identity'>";
    template+="<span class='name' id='player_name"+player_uid+"'";
    if (player_uid==local_player_id)
    {
        template += " contenteditable='true'";
        template += " onblur=\"saveName('player_name"+player_uid+"','"+player_uid+"')\"";
    }
    template+=">_NAME</span>";
    var alias = "<span class='alias'>_ALIAS</span>";
    template += alias;
    template += "</div>";
    
    return player_identity_template(player_uid,template).replace(alias,"");
}

function player_identity_template(player_uid,template)
{
    // Victim special case
    var player_name;
    if (player_uid==0)
    {
        player_name = "The Murder Victim";
        var c = Object.keys(game.players).length;
        if (game.act==1 && game.scene==c)
            player_name += " will be...";
    }
    else
    {
        player_name = game['players'][player_uid].name;
    }
    
    // find if there is an alias card 
    var i = undefined;
    
    // if its the victim, the victim always has an alias
    if (player_uid==0)
    {
        i = game['victim']['play']['aliases'][0];
    }
    else
    {   
        // players do not always have an alias...
        if (game['players'][player_uid]['play']['aliases'])
        {
            i = game['players'][player_uid]['play']['aliases'][0];
        }
    }  
    
    // if note is set, use note instead?
    if (i != undefined)
    {
        var alias_t = cards['aliases'][i]['subtype'];
        template = template.replace("_ALIAS",alias_t);
    }  

    return template.replace("_NAME",player_name);
}

function render_player(player,player_uid)
{
    var c = (player.active || player.unassigned_token) ? 'red' : 'black';
    var html = "<div class='player' style='border-color: "+c+"'>";

    html += player_identity_div(player_uid);

    if (player.unassigned_token && player_uid==local_player_id)
    {
        html += render_unassigned_token(player,player_uid);
    }
    else if (game.most_innocent==player_uid && game.act==3 && player_uid==local_player_id)
    {
        html += render_record_accused(player,player_uid);
    }
    else if (show_hand(player,player_uid))
    {
        // other actions, local player or victim
        var pfn = 
            (player_uid==local_player_id || 
            (player_uid==0 && is_firstbreak() && !unassigned_token_msg())) ?
            function(){
                var str = "";
                // you can vote as the active player AFTER you've played your details
                // you can vote on other players scenes at any time
                if (game_stage_voting() && (player.active==false || player.details_left_to_play==false))
                {
                    if (typeof player.vote == "undefined" || player.vote == 1)
                        str += votebutton_html(player_uid,2);
                    if (typeof player.vote == "undefined" || player.vote == 2)
                        str += votebutton_html(player_uid,1);
                }
                if (player.active && player.details_left_to_play==false)
                {
                    if (game.act==0)
                    {
                        var actions = "<button onclick='next(game)'>Start</button>";
                        str += pointer_html('BEGIN',actions);
                    }
                    else
                    {
                        var actions = "<button onclick='next(game)'>End Scene</button>";
                        str += pointer_html('NEXT',actions);
                    }
                }
                
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
                    var can_give = player.active &&
                        (game.act >= 2 || deck!="motives") && 
                        (player_uid==p || deck!="aliases") &&
                        (target_id==0 || !deck.startsWith("murder_"));
                        
                    if (can_give)
                    {
                        var button_text = deck!="aliases" ? "Give to " + player_identity_str(target_id) : "Select";
                        var click = "detailaction_ex(game, \""+player_uid+"\", \""+deck+"\", "+id+",\"play_detail\",\""+target_id+"\")";
                        var name = (target_id==0) ? "The Victim" : game.players[target_id].name;
                        result += "<button onclick='"+click+"'>"+button_text+"</button>";
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
            
        var banner = (player.fate) ? fate_to_str(player.fate) : "HELD";
        html += hand_tostr(player.hand,player_uid,detail_action,pfn,banner);
    }
    if (player.play)
    {
        html += hand_tostr(player.play,player_uid,null,
            function(){
                var str = "";
                if (typeof player.vote != "undefined")
                    str += votediv_html(player_uid,player.vote,"");
                return str;
            },
            "IN PLAY"
        );
    }
    if (player.tokens)
    {
        html += hand_tostr(player.tokens,player_uid,null,
            function(){
                var str = "";
                if (game.the_accused == player_uid)
                    str += assign_token_html('accused','');
                return str;
            },
            "TOKENS"
        );
    }
    html += '</div>';
    return html;
}

function unassigned_token_msg()
{
    for (var player in game.players)
    {
        if (game.players[player].unassigned_token)
            return "Waiting for " +player_identity_str(player) + " to assign a " + game.players[player].unassigned_token + " token.";
    }
    
    return null;
}

function fate_to_str(fate)
{
    switch(fate)
    {
        case "gawm": return "Got Away With Murder!";
        case "got_caught": return "Got Caught";
        case "got_it_right": return "Got It Right";
        case "got_it_wrong": return "Got It Wrong";
        case "got_framed": return "Got Framed";
        case "got_out_alive": return "Got Out Alive";
        default: return "Unknown fate id: "+fate;
    }
}

function game_stage_str()
{
    if (game.act==0)
        return "Setup: Add Players and Select Alias Details.";
    if (game.act==5)
        return "FIN";

    var uatm = unassigned_token_msg();
    if (uatm) return uatm;
    
    var c = Object.keys(game.players).length;

    if (game.act==1 && game.scene==c)
        return "Extra Scene: Introduce a new character.";

    if (is_firstbreak())
        return "First Break: The Murder.";

    if (is_twist())
        return "Second Break: The Twist.";

    if (game.act==3)
        c = c*2;

    if (game.act==3 && game.scene==c)
    {
        var n = player_identity_str(game.most_innocent);
        return "Last Break: The Accusation - "+n+" - and The Guilty ...";
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

    return act + ", Scene " + (game.scene+1) + " / " + c;
}

function create_joinurl()
{
    var url = window.location.protocol + "//"
        + window.location.hostname
        + window.location.pathname
        + "?ugc="+game_id
        + "&un=" + "Player_"+(Object.keys(game.players).length+1)
        + "&a=join_game";
        
    return url;    
}

function render_game(result)
{
    game = result;
    var html = "";
    var debug_html = "";
    
    // temp view-switch ui    
    debug_html += "<span>VIEW: </span>";
    for (var player in game.players)
    {
        if (player==local_player_id) debug_html+="<b>";
        debug_html += "<span onclick='reload(\""+player+"\");' style='cursor: pointer;'> ["+game.players[player].name+"] </span>";
        if (player==local_player_id) debug_html+="</b>";
    }
    debug_html += '<input type="button" onclick="next(game)" value="Next"/>';

    html += "<div class='header'>" + game_stage_str() + "</div>";
    if (result.victim)
    {
        html += render_player(result.victim,0,0);
    }

    if (result.players[local_player_id])
    {
        html += render_player(result.players[local_player_id],local_player_id);
    }
    
    for (var player in result.players)
    {
        if (player!=local_player_id)
            html += render_player(result.players[player],player);
    }
    
    if (game.act==0)
    {
        var url_text = create_joinurl();
        var url_encoded = encodeURI(url_text);
        debug_html += '<span>[<a href="'+url_encoded+'&d=1">Add Player</a>]</span>';
        html += "<div class='action'>";
        html += "<div>Have other players use this URL to join the game:</div>";
        html += "<div><textarea style='width: 50%; margin: auto;'>"+url_encoded+"</textarea></div>";
        html += "<div><a href='mailto:?subject=GAWM%20join%20game%20url&body="+encodeURIComponent(url_encoded)+"'><div class='button'>Email It</div></a></div>";
        html += "</div>";
    }
    else if (game.act>=5)
    {
        document.getElementById('gameover').style.display="block";
    }

    document.getElementById('debug_div').innerHTML = debug_html;
    document.getElementById('game_div').innerHTML = html;
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
    // build request json
    var request = {};
    request.action = action;
    request.game_id = game_id;
    request.player_id=player_id;
    request.target_id=target_id;    
    request.detail_type=detail_type;
    request.detail=detail;

    gawm_sendrequest(request);
}

// edit_note(&$data, $player_id, $detail_type, $detail, $note)
function edit_note(gamestate,player_id,detail_type,detail,note)
{
    // build request json
    var request = {};
    request.action = "edit_note";
    request.game_id = game_id;
    request.player_id=player_id;
    request.detail_type=detail_type;
    request.detail=detail;
    request.note=note;
    
    gawm_sendrequest(request);
}

// rename_player(&$data, $player_id, $player_name)
function rename_player(game,player_id,player_name)
{
    // build request json
    var request = {};
    request.action = "rename_player";
    request.game_id = game_id;
    request.player_id=player_id;
    request.player_name=player_name;
    
    gawm_sendrequest(request);
}

function add_player(id, player_name,onsucess)
{
    game_id = id;
    
    if (!player_name)
        player_name = prompt("Please enter your name", "Player "+(Object.keys(game.players).length+1));

    var request = {};
    request.action = 'add_player';
    request.game_id = game_id;
    request.player_name = player_name;
    
    gawm_sendrequest(request,onsucess);
}

function next(gamestate)
{
    // build request json
    var request = {};
    request.action = 'next';
    request.game_id = game_id;
    request.player_id = local_player_id;

    gawm_sendrequest(request);
}

function reload(player_id,newgame_id)
{    
    // player view switching debug hax
    if (player_id)
        local_player_id = player_id;
    if (newgame_id)
        game_id = newgame_id;
    
    // there is no game zero, do not try to load it
    if (game_id==0)
        return;
        
    // build request json
    var request = {};
    request.action = 'get';
    request.game_id = game_id;
    request.player_id = local_player_id;

    gawm_sendrequest(request);
}

function error_popup(msg) 
{
    // Get the snackbar DIV
    var x = document.getElementById("snackbar");

    // replace player ids in the error message with user-facing names:
    if (game)
    {
        if (game.players)
        {
            for (var player in game.players)
            {
                msg = msg.replace(player, player_identity_str(player));
            }
        }
    }
    
    // Add the "show" class to DIV
    x.className = "show";
    x.innerHTML = msg

    // After 3 seconds, remove the show class from DIV
    setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000);
}

function new_game(player_name)
{
    // build request json
    var request = {};
    request.action = 'new';
    request.player_name = player_name;

    gawm_sendrequest(request);
}
