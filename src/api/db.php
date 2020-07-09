<?php

// secrets injected by build system, see: cp_api.sh & makefile
function db_connect()
{
    $hostname = GAWM_DB_HOST;   // eg. mysql.yourdomain.com (unique)
    $username = GAWM_DB_USER;   // the username specified when setting-up the database
    $password = GAWM_DB_PWD;   // the password specified when setting-up the database
    $database = "gawm";   // the database name chosen when setting-up the database (unique)
    return mysqli_connect($hostname,$username,$password,$database);
}

function save_new_game($game)
{
    $link = db_connect();
    $query = "INSERT INTO `games` (`uid`, `time`, `data`) VALUES (NULL, CURRENT_TIMESTAMP, ?)";
    if ($stmt = mysqli_prepare($link, $query)) 
    {
        $game_encoded = json_encode($game);
        mysqli_stmt_bind_param($stmt, "s", $game_encoded);
        mysqli_stmt_execute($stmt);
        $game_id = mysqli_insert_id($link);
    }
    mysqli_close($link);
    
    return $game_id;
}

function load_for_edit($game_id, &$data)
{
    $link = db_connect();
    $query = "SELECT `data` FROM `games` WHERE `uid` = ? FOR UPDATE;";

    $stmt = mysqli_stmt_init($link);
    if (mysqli_stmt_prepare($stmt, $query))
    {
        mysqli_stmt_bind_param($stmt, "s", $game_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_array($result, MYSQLI_NUM);
        
        // should only be one  
        if (isset($row))
        {
            foreach ($row as $r)
            {
                $data = json_decode($r,true);
            }
        }
    }
    else
    {
        $game_out["error"]=$query;
    }
    
    return $link;
}

function complete_edit($link, $game_id, $data)
{
    $query = "UPDATE `games` SET `data` = ? WHERE `uid` = ? ";
    $stmt = mysqli_stmt_init($link);
    if (mysqli_stmt_prepare($stmt, $query))
    {
        $data_encoded = json_encode($data);
        mysqli_stmt_bind_param($stmt, "si", $data_encoded, $game_id);
        mysqli_stmt_execute($stmt);
    }
    mysqli_close($link);
}

?>