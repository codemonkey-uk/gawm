<?php
//
// Implementation of functions implementing "Second Break: The Twist"
//
// Public, called by API:
// - gawm_is_twist - returns true if it is the last scene of Act II
// - gawm_twist_detail - lets players move cards into their "twist" pile (to be discarded)
//
// Implementation, used during Next Scene:
// - setup_twist - to be called at the start of the scene,
// - complete_twist - to be called at the start of the scene
//

function gawm_is_twist(&$data)
{
    return $data["act"]==2 && $data["scene"] == count($data["players"]);
}

function gawm_twist_detail(&$data, $player_id, $detail_type, $detail_card)
{
    if (!gawm_is_twist($data))
        throw new Exception('Invalid Twist');
        
    if (!array_key_exists($player_id,$data["players"]))
        throw new Exception('Invalid Player Id: '.$player_id);

    $player = &$data["players"][$player_id];
        
    if (!array_key_exists($detail_type, $player["hand"]))
        throw new Exception('Invalid Detail Type');
    
    $deck_from = &$player["hand"][$detail_type];
    if (!in_array($detail_card,$deck_from))
        throw new Exception('Detail Not Held');
    
    // make sure deck type exists in twist
    if (!array_key_exists($detail_type,$player["twist"]))
    {
        $player["twist"][$detail_type] = array();
    }
        
    // move card from hand into twist
    $deck_to = &$player["twist"][$detail_type];
    array_push($deck_to, $detail_card);
    
    $key = array_search($detail_card, $deck_from);
    unset( $deck_from[$key] );
}

function setup_twist(&$data)
{
    // give every player has a twist "hand" to discard into
    foreach( $data["players"] as &$player )
    {
        // draw any other details needed for Act I
        $player["twist"]=array();
    }
}

function complete_twist(&$data)
{
    // for each player, 
    foreach( $data["players"] as &$player )
    {
        // draw details to replace discards
        $draws = array();
        foreach( $player["twist"] as $detail_type => $deck_from )
        {
            // count draws needed after discarding
            $draws[$detail_type] = count($deck_from) + count($player["hand"][$detail_type]);
            
            // put unused details back in the pack
            foreach($deck_from as $key => $id)
            {
                unset( $deck_from[$key] );
                array_unshift($data["cards"][$detail_type],$id);
            }
        }
        
        // draw backup
        draw_player_cards($data, $player, $draws);
        
        // clear out processed twist json
        unset($player["twist"]);
    }
    
    // twist is last scene of act 2, 
    // advance to next act
    $data["act"]+=1;
    $data["scene"]=0;     
}

?>