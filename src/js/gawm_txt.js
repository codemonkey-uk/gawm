/*
    gawm_txt.js 
    methods that return simple human readable strings, messages, labels, etc
*/

var cards = null;
var txt = null;

function gawm_load_txt(oncomplete)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            txt = JSON.parse(this.responseText);

            // lazy chaining
            gawm_load_cards(oncomplete);
        }
    };

    xmlhttp.open("GET", "assets/en_txt.json", true);
    xmlhttp.send();
}

function gawm_load_cards(oncomplete)
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

function gawm_card_part_txt(deck,i,part)
{
    return cards[deck][i][part];
}

function gawm_card_desc_txt(deck,i)
{
    return cards[deck][i]['desc'];
}

function gawm_card_name_txt(deck,i)
{
    return cards[deck][i]['name'];
}

function gawm_card_subtype_txt(deck,i)
{
    return cards[deck][i]['subtype'];
}

function gawm_default_note_txt(detail_type,i)
{
    if (detail_type=='player') return "Player Note";
    else return cards[detail_type][i]['desc'];
}

function gawm_card_img_alt_txt(deck)
{
    return gawm_component_txt(deck) + " Card";
}

function gawm_component_txt(deck)
{
    return txt["component"][deck];
}

function gawm_txt(fate)
{
    return txt[fate];
}

function gawm_act_txt(act)
{
    return txt["act"][act];
}

function gawm_next_scene_txt(act,scene)
{
    return act==0?"BEGIN":"NEXT";
}

function gawm_button_action_giveto_txt(indentity)
{
    //action_giveto
    return txt["action_giveto"].replace("{ID}",indentity);
}

function gawm_extrascene_scene_txt(indentity)
{
    return txt["extrascene_scene"].replace("{ID}",indentity);
}

function gawm_firstbreak_scene_txt()
{
    return txt["firstbreak_scene"];
}

function gawm_twist_scene_txt()
{
    return txt["twist_scene"];
}

function gawm_lastbreak_scene_txt(indentity)
{
    return txt["lastbreak_scene"].replace("{ID}",indentity);
}

function unassigned_token_txt(indentity,token)
{
    return txt["unassigned_token"]
        .replace("{ID}",indentity)
        .replace("{TOKEN}",token);
}

