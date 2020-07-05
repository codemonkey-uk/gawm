<?php
require 'gawm.php';

// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json, true);

switch ($data["act"])
{
    case 0:
        complete_setup($data);
        break;
    case 1:
        $data["scene"]+=1;
        if ($data["scene"] == count($data["players"]))
        {
            // TODO: Extra Scene
        }
        if ($data["scene"] == count($data["players"])+1)
        {
            // TODO: First Break (Murder)
        }
        if ($data["scene"] > count($data["players"])+1)
        {
            // Move to Act II
            $data["act"]+=1;
            $data["scene"]=0;
        }
        break;
    case 2:
        $data["scene"]+=1;
        if ($data["scene"] == count($data["players"]))
        {
            // TODO: Second Break
        }
        if ($data["scene"] == count($data["players"])+1)
        {
            // Move to Act III
            $data["act"]+=1;
            $data["scene"]=0;
        }
        break;    
    case 3:
        $data["scene"]+=1;
        if ($data["scene"] == 2*count($data["players"]))
        {
            // TODO: Last Break
        }
        if ($data["scene"] == 2*count($data["players"])+1)
        {
            // Move to Epilogue
            $data["act"]+=1;
            $data["scene"]=0;
        }
        break; 
    case 4:
        if ($data["scene"]+1<count($data["players"]))
        {
            $data["scene"]+=1;
        }
        break;
}

// convert back to json
$encoded = json_encode($data);

//temp, return to caller
// todo: write to DB, return UID
header('Content-Type: application/json');
echo $encoded;

?>