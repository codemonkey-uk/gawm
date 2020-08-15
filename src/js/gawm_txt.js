/*
    gawm_txt.js 
    methods that return simple human readable strings, messages, labels, etc
*/

var cards = null;
function gawm_load_txt(oncomplete)
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
    return gawm_deckid_txt(deck) + " Card";
}

function gawm_deckid_txt(deck)
{
    switch (deck)
    {
        case "aliases": return "Alias";
        case "objects": return "Object";
        case "relationships": return "Relationship";
        case "wildcards": return "Wildcard";
        case "motives": return "Motive";
        case "murder_discovery": return "Murder Discovery";
        case "murder_cause": return "Murder Cause";
        
        default: return deck;
    }
}

function gawm_fate_txt(fate)
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

function gawm_act_txt(act)
{
    switch (act)
    {
        case 0:
            return "Setup: Add Players and Select Alias Details.";
        case 1:
            return "Act I: Introductions";
        case 2:
            return "Act II: Investigations";
        case 3:
            return "Act III: Incriminations";
        case 4:
            return "Epilogue";        
        default: // 5+
            return "FIN";
    }
}

function gawm_button_action_giveto_txt(indentity)
{
    return "Give to "+indentity;
}

function gawm_lastbreak_scene_txt(indentity)
{
    return "Last Break: The Accusation - "+indentity+" - and The Guilty ...";
 }   