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
