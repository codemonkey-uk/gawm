<?php
//
// Implementation of functions implementing Epilogue
//
// Public, called by API:
// - gawm_is_epilogue - returns true if in the epilogue, Act 4
//
// Implementation, used during Next Scene:
// - setup_epilogue - to be called at the start of the act,
// - complete_epilogue- to be called at the end of each scene
//

function gawm_is_epilogue(&$data)
{
    return $data["act"]==4;
}

function gawm_is_epilogue_inprogress_or_complete(&$data)
{
    return $data["act"]>=4;
}

function setup_epilogue(&$data)
{
    // only set up on the 1st scene of this special *act*
    if ($data["scene"]>0)
        return;
        
    // Fates & Fate Types,
    $most_innocent = $data["most_innocent"];
    $the_accused = $data["the_accused"];
    $most_guilty = current(gawm_list_players_by_most_guilty($data));
    $data["most_guilty"] = $most_guilty;
    
    // for each player,
    foreach( $data["players"] as $id => &$player )
    {
        if (isset($player["fate"]))
            throw new Exception("Internal Error, Unexpected Fate found on ".$id.": ".json_encode($player));
            
        if ($id==$most_guilty)
        {
            if ($the_accused==$id)
                $player["fate"]="got_caught";
            else
                $player["fate"]="gawm";
        }
        else if ($id==$most_innocent)
        {
            if ($most_guilty==$the_accused)
                $player["fate"]="got_it_right";
            else
                $player["fate"]="got_it_wrong";
        }
        else
        {
            if ($id==$the_accused)
                $player["fate"]="got_framed";
            else
                $player["fate"]="got_out_alive";
        }
    }
}

function complete_epilogue(&$data)
{
    // one epilogue scene per player, no details no voting, simple as it comes
    $data["scene"]+=1;
    if ($data["scene"] == gawm_scene_count($data["act"], count($data["players"])))
    {
        $data["act"]+=1;
        $data["scene"]=0;
    }
}

?>
