<?php
//
// Implementation of functions implementing First Break
//
// Public, called by API:
// - gawm_is_firstbreak - returns true if at the first break, end of Act I
// 
// Implementation, used during Next Scene:
// - setup_firstbreak - to be called at the start of the scene,
// - complete_firstbreak- to be called at the end of the scene
//

function gawm_is_firstbreak(&$data)
{
    return $data["act"]==1 && $data["scene"] == count($data["players"])+1;
}

function setup_firstbreak(&$data)
{
    $data["victim"]["hand"]=array();
    
    $detail="murder_cause";
    $data["victim"]["hand"][$detail]=array();
    array_push( $data["victim"]["hand"][$detail], array_pop($data["cards"][$detail]) );
    $detail="murder_discovery";
    $data["victim"]["hand"][$detail]=array();
    array_push( $data["victim"]["hand"][$detail], array_pop($data["cards"][$detail]) );    
}

function complete_firstbreak(&$data)
{
    // first break is last scene of act 1, 
    // advance to next act
    $data["act"]+=1;
    $data["scene"]=0;   
}

?>