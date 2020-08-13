
// put theses in a gawm object maybe? use modules maybe?
var cards = null;
var game = null;
var game_id = 0;
var local_player_id = 0;

function default_note(deck,i)
{
    if (deck=='player') return "Player Note";
    else return cards[deck][i]['desc'];
}

function load_cards(oncomplete)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            cards = JSON.parse(this.responseText);
            oncomplete();
        }
    };

    xmlhttp.open("GET", "assets/cards.json", true);
    xmlhttp.send();
}

function deck_is_token(deck)
{
    return deck=="guilt" || deck=='innocence';
}

function img_url(deck,i)
{
    // redacted cards/tokens
    if (i<0)
    {
        switch (deck){
            case 'aliases': return 'assets/alias_back.png';
            case 'motives': return 'assets/motive_back.png';
            case 'objects': return 'assets/object_back.png';
            case 'relationships': return 'assets/rel_back.png';
            case 'wildcards': return 'assets/wild_back.png';
            case 'guilt': return 'assets/guilt.png';
            case 'innocence': return 'assets/innocence.png';
        }
    }
    
    return 'assets/'+cards[deck][i]['img'];
}

function deck_order(deck)
{
    // redacted cards/tokens
    switch (deck){
        case 'aliases': return 1;
        case 'motives': return 5;
        case 'objects': return 3;
        case 'relationships': return 2;
        case 'wildcards': return 4;
        default: return 99;
    }
}
    
function img_alt(deck,i)
{
    // temp workaround, card json doesn't have guilt/innocence tokens
    if (deck_is_token(deck) || i<0)
        return deck;
    
    return cards[deck][i]['name']+" ("+cards[deck][i]['subtype']+'): '+cards[deck][i]['desc'];
}

var card_template = `<div class="halfcard _TYPE">
  <div class="header" style="_CURSOR" onclick="toggle_show(this.parentElement,'actions')">
  <div class="title">_TYPE_TXT</div>
  <div class="subtitle">_SUBTYPE</div>  
  </div>
  <div class="name" onclick="toggle_show(this.parentElement, 'flavour')"><p>_NAME</p></div>
  <div class="actions">_ACTIONS</div>
  <div class="flavour">
  <p id='flavour-_ID_editp' contenteditable="true" onblur="saveNote('flavour-_ID_editp','_TYPE','_ID')">_DESC</p>
  </div>
</div>`;

function get_contenteditable(div_id)
{
    var t = document.getElementById(div_id).innerText;
    return t;
}

// check if the input event added a <br> to the HTML, use that to trigger a save
function editPlayerNote(div_id,player_id)
{
    var t = document.getElementById(div_id).innerHTML;
        
    if (t.includes('<br>'))
    {
        t = document.getElementById(div_id).innerText;
        document.getElementById(div_id).innerHTML = t;
        if (t!=default_note('player',player_id))
            saveNote(div_id,'player',player_id);
    }
    
    if (t!=default_note('player',player_id))
        document.getElementById(div_id).style.opacity="1";
}

function saveNote(div_id,detail_type,d_id)
{
    var t = get_contenteditable(div_id);
    edit_note(game,local_player_id,detail_type,d_id,t);
}

// check if the input event added a <br> to the HTML, use that to trigger a save
function editName(div_id,player_id)
{
    var t = document.getElementById(div_id).innerHTML;
    if (t.includes('<br>'))
    {
        document.getElementById(div_id).innerHTML = document.getElementById(div_id).innerText;
        saveName(div_id,player_id);
    }
}

function saveName(div_id,player_id)
{
    var t = get_contenteditable(div_id);
    rename_player(game,player_id,t);
}

function toggle_show(container, className)
{
    container.querySelector('.'+className).classList.toggle("show");
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

function token_html(type, i, click)
{
    var result = "<div class='"+type+"'";
    if (click)
    {
        result += "onclick='"+click+"'";
        result += "style='cursor: pointer;'";
    }
    result += ">";
    result += '<div class="frame">';
    if (i>=0)
    {
        result += "<p>"+i+"</p>";
    }
    else
    {
        result += "<img src='assets/"+type+".png'>";
        result += "<p>"+type+"</p>";
    }
    result += "</div>";
    result += "</div>";
    return result;
}

function hand_html(hand,player_id,action,postfix,label)
{
    var html = "<div class='hand'>";
    html += "<div class='hand_label'>"+label+"</div>";

    // compact style for fully redacted hands
    var style = is_hand_redacted(hand) ? "grid-template-columns: repeat(12, 1fr);" : "";
    html += "<div class='hand_grid' style='"+style+"'>";
    
    if (hand)
    {
        var ordered_decks = Object.keys(hand).sort(function(a, b) {
            return deck_order(a)-deck_order(b);
        });

        for (var ideck in ordered_decks)
        {
            var deck = ordered_decks[ideck];
            for (var card in hand[deck])
            {
                var card_html = "";
                var i = hand[deck][card];
                if (deck_is_token(deck))
                {
                    // no action/click on held tokens
                    // note: vote buttons are generated during postfix 
                    card_html += token_html(deck, i, "");
                }
                else if (i<0) 
                {
                    // card backs use img path
                    var divclass = "cardback";
                    var url = img_url(deck,i);
                    var alt = img_alt(deck,i);
                    var img = "<img src=\"" +url+ "\" style='max-width: 100%;max-height: 100%;' alt=\""+alt+"\">";
                    card_html += "<div class='"+divclass+"'>";
                    card_html += img;
                    card_html += "</div>";
                }
                else 
                {
                    var menu = "";
                    if (action)
                    {
                        menu += action(deck, i);
                    }
                
                    // if note is set, use that
                    var note = (game['notes'] && game['notes'][deck]) ? game['notes'][deck][i] : null;
                    var desc = note ? note : cards[deck][i]['desc'];
                    // if note is set, mark card
                    // todo: take first line from note for name, if note is set
                    var name = cards[deck][i]['name'];
                    if (note) name += "*";
                
                    cursor = menu.length > 0 ? "cursor: context-menu;" : "";
                    card_html = card_template
                        .replace(/_ID/g, i)
                        .replace(/_TYPE_TXT/g, gawm_deckid_txt(deck))
                        .replace(/_TYPE/g, deck)
                        .replace(/_SUBTYPE/g, cards[deck][i]['subtype'])
                        .replace(/_NAME/g, name)
                        .replace(/_DESC/g, desc)
                        .replace(/_CURSOR/g, cursor)
                        .replace(/_ACTIONS/g, menu);
                }
                html += card_html;
            }
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

function is_lastbreak()
{
    var c = Object.keys(game.players).length;
    return (game.act==3 && game.scene==c*2);
}

function is_extrascene()
{
    var c = Object.keys(game.players).length;
    return (game.act==1 && game.scene==c)
 }
 
function votediv_html(player_id,value,action)
{
    var value_str = (value == 1) ? "guilt" : "innocence";
    html = token_html(value_str, -1, action);
    
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

var accused_template =`
<div class="pointer"><div class="frame">
<img style="margin-top: 6px; margin-bottom: 1px;" src="assets/cuffs.png" alt="hand cuffs graphic"/>
<p>ACCUSED</p>
</div></div>
`;

function accused_html()
{
    return accused_template;
}

var pointer1_template =`
<div class="pointer" style="cursor: pointer" onclick="_ACTION">
<div class="frame">
<img src="assets/pointer.png" alt="pointing finger graphic"/>
<p>_TEXT</p></div>
</div>
`;

function pointer1_html(text, action)
{
    return pointer1_template
        .replace(/_ACTION/g, action)
        .replace(/_TEXT/g, text);
}

var pointer_template =`
<div class="pointer" style="_CURSOR" onclick="toggle_show(this, 'actions')">
<div class="frame">
<img src="assets/pointer.png" alt="pointing finger graphic"/>
<p>_TEXT</p></div>
<div class="actions">_ACTIONS</div>
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
<div class='_TYPE' style="_CURSOR" onclick="toggle_show(this, 'actions')">
<div class="frame">
<img src="_IMGURL" alt="_ALT"><p>_TYPE</p>
</div>
<div class="actions">_ACTIONS</div>
</div>
`;

function assign_token_html(type, menu)
{
    var url = "assets/"+type+".png";
    var alt = img_alt(type,-1);
    var cursor = menu.length > 0 ? "cursor: context-menu;" : "";
    return tokenback_template
        .replace(/_TYPE/g, type)
        .replace(/_CURSOR/g, cursor)
        .replace(/_ACTIONS/g, menu)
        .replace(/_IMGURL/g, url)
        .replace(/_ALT/g, alt);
}

function unassigned_html(player,player_uid)
{
    var html = "<div class='hand'>";
    html += "<div class='hand_label'>ASSIGN</div>";
    html += "<div class='hand_grid'>";
    var actions = "";
    for (var p in game.players)
    {
        if (p!=player_uid)
        {
            var click = "givetoken(game, \""+player_uid+"\", \""+player.unassigned_token+"\", \""+p+"\")";
            actions += "<button onclick='"+click+"'>Give to "+player_identity_txt(p)+"</button>";
        }
    }

    var click = "givetoken(game, \""+player_uid+"\", \""+player.unassigned_token+"\", \"0\")";
    actions += "<button onclick='"+click+"'>Discard</button>";
    
    html += assign_token_html(player.unassigned_token, actions);
    html += '</div>';//hand_grid
    html += '</div>';//hand
    return html;
}

function record_accused_html(player,player_uid)
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
            actions += "<button onclick='"+click+"'>Accuse "+player_identity_txt(p)+"</button>";
        }
    }

    var label = 'ACCUSE';
    if (game.the_accused)
    {
        actions += "<button onclick='next(game)'>End Scene</button>";
        label = 'NEXT';
    }
    
    html += pointer_html(label,actions);
    
    html += '</div>';//hand_grid
    html += '</div>';//hand
    return html; 
}

function player_identity_txt(player_uid)
{
    return player_identity_template(player_uid,"_NAME (_ALIAS)").replace(' (_ALIAS)','');
}

function player_identity_html(player_uid)
{
    var template = "<div class='identity'>";
    template+="<span class='name' id='player_name"+player_uid+"'";
    if (player_uid==local_player_id)
    {
        template += " contenteditable='true'";
        template += " oninput=\"editName('player_name"+player_uid+"','"+player_uid+"')\"";
        template += " onblur=\"saveName('player_name"+player_uid+"','"+player_uid+"')\"";
    }
    template+=">_NAME</span> ";
    var alias = "<span class='alias'>_ALIAS</span> ";
    template += alias;

    // saveNote(div_id,detail_type,d_id)
    var note_html = " - <span class='name' id='player_note"+player_uid+"' contenteditable='true'"
    note_html += " oninput=\"editPlayerNote('player_note"+player_uid+"','"+player_uid+"')\"";
    note_html += " onblur=\"saveNote('player_note"+player_uid+"','player','"+player_uid+"')\"";

    var note = (game['notes'] && game['notes']['player']) ? game['notes']['player'][player_uid] : null;
    var note_content = note ? replace_playerids(note) : default_note('player',player_uid);
    if (note==null || note_content==default_note('player',player_uid)) note_html += 'style="opacity: 0.5;"';
    note_html +=">"+note_content+"</span> ";
    note_html += "</span> ";
    template += note_html;
    
    template += "</div>";
    
    return player_identity_template(player_uid,template).replace(alias,"");
}

function player_identity_template(player_uid,template)
{
    // Victim special case
    var player_name;
    if (player_uid==0)
    {
        var c = Object.keys(game.players).length;
        if (game.act==1 && game.scene==c)
            player_name = "The Murder Victim will be...";
        else
            player_name = "DECEASED";
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
        var alias_t = cards['aliases'][i]['name'];
        template = template.replace("_ALIAS",alias_t);
    }  

    return template.replace("_NAME",player_name);
}

function player_border_colour(player)
{
    return (player.active || player.unassigned_token) ? '#be0712' : '#0e62bd';
}

function player_html(player,player_uid)
{
    var c = player_border_colour(player);
    var html = "<div class='player' style='border-color: "+c+"'>";

    html += player_identity_html(player_uid);

    if (player.unassigned_token && player_uid==local_player_id)
    {
        html += unassigned_html(player,player_uid);
    }
    else if (game.most_innocent==player_uid && game.act==3 && player_uid==local_player_id)
    {
        html += record_accused_html(player,player_uid);
    }
    else if (show_hand(player,player_uid))
    {
        // other actions, local player or victim
        var pfn = 
            (player_uid==local_player_id || 
            (player_uid==0 && is_firstbreak() && !unassigned_token_msg())) ?
            function(){
                var html = "";
                // you can vote as the active player AFTER you've played your details
                // you can vote on other players scenes at any time
                if (game_stage_voting() && (player.active==false || player.details_left_to_play==false))
                {
                    if (typeof player.vote == "undefined" || player.vote == 1)
                        html += votebutton_html(player_uid,2);
                    if (typeof player.vote == "undefined" || player.vote == 2)
                        html += votebutton_html(player_uid,1);
                }
                if (player.active && player.details_left_to_play==false)
                {
                    if (game.act==0)
                    {
                        html += pointer1_html('BEGIN','next(game)');
                    }
                    else
                    {
                        html += pointer1_html('NEXT','next(game)');
                    }
                }
                
                return html;
            } : null;
            
        var detail_action = is_twist() ? 
            function(deck,id) {
                var click = "twistdetail(game, \""+player_uid+"\", \""+deck+"\", "+id+")";
                return "<button onclick='"+click+"'>Twist (Discard)</button>";
            } :
            function(deck, id){
                // All other detail types menu building
                var fn = function(target_id)
                {
                    var result = "";
                    // motives cant be given until 2nd act,
                    // aliases can only be given to yourself
                    // murder details can only be given to the victim
                    var can_give = player.active &&
                        (game.act >= 2 || deck!="motives") && 
                        (target_id==local_player_id || (deck!="wildcards" && deck!="aliases")) &&
                        (target_id==0 || !deck.startsWith("murder_"));
                    
                    // cant give someone two motives
                    if (deck=="motives")
                    {
                        if (target_id==0)
                            can_give = false;
                        else if (game['players'][target_id]['play']['motives'])
                            can_give = false;
                    }
                    
                    if (can_give)
                    {
                        var button_text = (deck=="aliases" || deck=="wildcards")
                            ? "Select"
                            : gawm_button_action_giveto_txt( player_identity_txt(target_id) )+
                            (deck=="relationships" ? " and..." : '');

                        if (deck=="relationships") {
                            var click = "menu_relationship_next_choice(this.parentElement, \""+player_uid+"\", \""+id+"\", \""+target_id+"\")";
                        } else {
                            var click = "detailaction_ex(game, \""+player_uid+"\", \""+deck+"\", "+id+",\"play_detail\",\""+target_id+"\")";
                        }

                        result += "<button onclick='"+click+"'>"+button_text+"</button>";
                    }
                    
                    return result;
                }
                
                var result = "";
                result += fn(local_player_id);
                if (is_firstbreak())
                    result += fn(0);
                for (var p in game.players)
                    if (local_player_id!=p) result += fn(p);
                    
                return result;
            };
            
        var banner = (player.fate) ? gawm_fate_txt(player.fate) : "HELD";
        html += hand_html(player.hand,player_uid,detail_action,pfn,banner);
    }
    if (player.play)
    {
        html += hand_html(player.play,player_uid,
            // move/give detail menu:
            function(deck, id){
                var result = "";
                if (deck=="objects" && local_player_id==player_uid)
                {
                    for (var target_id in game.players)
                    {
                        if (local_player_id!=target_id)
                        {
                            var button_text = gawm_button_action_giveto_txt( player_identity_txt(target_id) );
                            var click = "detailaction_ex(game, \""+player_uid+"\", \""+deck+"\", "+id+",\"move_detail\",\""+target_id+"\")";
                            result += "<button onclick='"+click+"'>"+button_text+"</button>";
                        }
                    }
                }
                return result;
            },
            // vote buttons
            function(){
                var html = "";
                if (typeof player.vote != "undefined")
                    html += votediv_html(player_uid,player.vote,"");
                return html;
            },
            "IN PLAY"
        );
    }
    if (player.tokens)
    {
        html += hand_html(player.tokens,player_uid,null,
            function(){
                var html = "";
                if (game.the_accused == player_uid)
                    html += accused_html();
                return html;
            },
            "TOKENS"
        );
    }
    html += '</div>';
    return html;
}

function menu_relationship_next_choice(div, player_uid, id, first_target_id)
{
    var result = '';
    
    var buttonfn = function(p)
    {
        var button_text = "Give to " +
            player_identity_txt(first_target_id) + " and "+ player_identity_txt(p);
            var click = "detailaction_ex(game, \""+player_uid+"\", \"relationships\", "+id+
                ",\"play_detail\",\""+first_target_id+"\",\""+p+"\")";
        return "<button onclick='"+click+"'>"+button_text+"</button>";
    }
    
    // put relationship with self first in the button list
    if (player_uid!=first_target_id)
    {
        result += buttonfn(player_uid);
    }
    
    for (var p in game.players)
    {
        if (p!=first_target_id && p!=player_uid) 
        {
            result += buttonfn(p);
        }
    }
    
    // Yikes. Really should just rerender the menu, but no easy way to do this
    result += "<button onclick='render_game(game)'>Undo first choice</button>";
    
    div.innerHTML = result;
}

function unassigned_token_msg()
{
    for (var player in game.players)
    {
        if (game.players[player].unassigned_token)
            return "Waiting for " +player_identity_txt(player) + " to assign a " + game.players[player].unassigned_token + " token.";
    }
    
    return null;
}

function game_stage_txt()
{
    var uatm = unassigned_token_msg();
    if (uatm) return uatm;

    if (is_extrascene())
        return "Extra Scene: Introduce a new character.";

    if (is_firstbreak())
        return "First Break: The Murder.";

    if (is_twist())
        return "Second Break: The Twist.";

    if (is_lastbreak())
        return gawm_lastbreak_scene_txt( player_identity_txt(game.most_innocent) );

    var act_scene = gawm_act_txt(game.act);
    if (game.act>=1 && game.act<=4)
    {
        var c = Object.keys(game.players).length;
        if (game.act==3)
            c = c*2;
        act_scene += ", Scene " + (game.scene+1) + " of " + c;
    }   
    return act_scene;
}

function create_joinurl()
{
    var url = window.location.protocol + "//"
        + window.location.hostname
        + window.location.pathname
        + "?ugc="+game_id
        + "&a=join_game";
        
    return url;
}

function create_loadnurl()
{
    var url = window.location.protocol + "//"
        + window.location.hostname
        + window.location.pathname
        + "?ugc="+game_id
        + "&pid="+local_player_id
        + "&a=load_game";
        
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

    html += "<div class='header'>" + game_stage_txt() + "</div>";
    if (result.victim)
    {
        html += player_html(result.victim,0,0);
    }

    if (result.players[local_player_id])
    {
        html += player_html(result.players[local_player_id],local_player_id);
    }
    
    for (var player in result.players)
    {
        if (player!=local_player_id)
            html += player_html(result.players[player],player);
    }
    
    document.getElementById('invite').style.display= (game.act==0) ? "block" : "hidden";
    if (game.act==0)
    {
        var url_text = create_joinurl();
        var url_encoded = encodeURI(url_text);
        debug_html += '<span>[<a href="'+url_encoded+'&d=1">Add Player</a>]</span>';
        document.getElementById('game_id').innerHTML = game_id;
        document.getElementById('invite_url_textarea').value = url_encoded;
        document.getElementById('invite_url_a').href = "mailto:?subject=GAWM%20join%20game%20url&body="+encodeURIComponent(url_encoded);
    }
    else if (game.act>=5)
    {
        document.getElementById('gameover').style.display="block";
    }

    document.getElementById('debug_div').innerHTML = debug_html;
    document.getElementById('game_div').innerHTML = html;
}

function givetoken(gamestate,player_id,value,target_id)
{
    // _api_give_token expects:
    // - detail type == innocence/guilt
    // - detail = playeruid
    // detailaction(gamestate,player_id,detail_type,detail,action)
    detailaction(gamestate,player_id,value,target_id,"give_token");
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

function detailaction_ex(gamestate,player_id,detail_type,detail,action,target_id,target_id2)
{
    // build request json
    var request = {};
    request.action = action;
    request.game_id = game_id;
    request.player_id = player_id;
    request.detail_type = detail_type;
    request.detail = detail;
    
    if (target_id)
    {
        request.targets=[target_id];
        if (target_id2) request.targets.push(target_id2);
    }

    gawm_sendrequest(request);

    if (detail_type=='relationships')
    {
        var note_target1 = '';
        var note_target2 = '';
        if ('notes' in game && 'player' in game['notes']) {
            note_target1 = target_id in game['notes']['player'] ? game['notes']['player'][target_id] : '';
            note_target2 = target_id2 in game['notes']['player'] ? game['notes']['player'][target_id2] : '';
        }
        edit_note(
            gamestate, player_id, 'player', target_id,
            note_target1 + ' ' + cards['relationships'][detail].name + " with " + target_id2 + ". "
        );
        edit_note(
            gamestate, player_id, 'player', target_id2,
            note_target2 + ' ' + cards['relationships'][detail].name + " with " + target_id + ". "
        );
    }
}

// edit_note(&$data, $player_id, $detail_type, $detail, $note)
function edit_note(gamestate,player_id,detail_type,detail,note)
{
    note = note.trim();
    
    // if the note matches the default note, blank to empty
    if (note == default_note(detail_type,detail))
        note = '';
    
    // only send if the note is changed by the edit
    var current_note = 'notes' in game && detail_type in game['notes']
        ? game['notes'][detail_type][detail] 
        : '';

    if (note!=current_note)
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
        
        // save note locally (prevents a DOM reset) 
        if (!(detail_type in game['notes']))
            game['notes'][detail_type] = [];
        game['notes'][detail_type][detail] = note;
    }
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
    
    // save edit locally
    game['players'][player_id].name = player_name;
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

function replace_playerids(msg)
{
    // replace player ids in the error message with user-facing names:
    if (game)
    {
        if (game.players)
        {
            for (var player in game.players)
            {
                var re = new RegExp(player, 'g');
                msg = msg.replace(re, player_identity_txt(player));
            }
        }
    }
    return msg;
}

function error_popup(msg) 
{
    // Get the snackbar DIV
    var x = document.getElementById("snackbar");

    msg = replace_playerids(msg);
    
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

    gawm_sendrequest(request,function(){
        document.title = "GAWM #"+game_id;
        history.pushState(game_id,document.title,create_loadnurl());
    });
}
