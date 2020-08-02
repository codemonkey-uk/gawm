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
    $link = rate_limited_connect("new");
    
    $query = "INSERT INTO `games` (`uid`, `time`, `data`) VALUES (NULL, CURRENT_TIMESTAMP, ?)";
    if ($stmt = mysqli_prepare($link, $query))
    {
        $game_encoded = json_encode($game);
        mysqli_stmt_bind_param($stmt, "s", $game_encoded);
        mysqli_stmt_execute($stmt);
        $game_id = mysqli_insert_id($link);
    }
    purge_old_games($link);
    mysqli_close($link);

    return $game_id;
}

// mostly making sure there is not retention of player notes
function purge_old_games($link)
{
    $query = "DELETE FROM `gawm`.`games` WHERE time < DATE_SUB(NOW(), INTERVAL 14 day);";
    if ($stmt = mysqli_prepare($link, $query))
    {
        mysqli_stmt_execute($stmt);
    }
}

function load_for_edit($game_id, &$data, $action)
{
    $link = rate_limited_connect($action);
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

function record_event($link, $action, $detail_type, $detail)
{
    $query = "INSERT INTO stats (date, action, detail_type, detail) "
    . "VALUES (CURDATE(), ?, ?, ?) ON DUPLICATE KEY UPDATE count = count + 1;";
    $stmt = mysqli_stmt_init($link);
    if (mysqli_stmt_prepare($stmt, $query))
    {
        // special case to keep player uids out of the stats table
        if ($detail_type=='player') $detail = 0;
        
        mysqli_stmt_bind_param($stmt, "ssi", $action, $detail_type, $detail);
        mysqli_stmt_execute($stmt);
    }
}

function rate_limited_connect($action)
{
    $link = db_connect();
    if ($action == 'new' || $action == 'edit_note')
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($ip=="") $ip="127.0.0.1"; // for localhost testing
        
        // new games, rate limit at 12 per day, 
        // allows frequent play and mistakes on creation, but not spam
        // note edits, 30 an hour allows all notes to be set during the course of a game
        // but should prevents server being abused as IM chat room
        
        $n = ($action == 'new') ? 0.5 : 0.5;
        $f = ($action == 'new') ? 'HOUR' : 'MINUTE';
        $l = ($action == 'new') ? 12 : 30;
        
        $query = "INSERT INTO rates (`ipv4`, action)"
            . "VALUES (INET_ATON(?), ?) "
            . "ON DUPLICATE KEY UPDATE count = 1 + GREATEST(0, `count` - (".$n."*TIMESTAMPDIFF(".$f.",`time`,NOW())))";
        
        $stmt = mysqli_stmt_init($link);
        if (mysqli_stmt_prepare($stmt, $query))
        {
            mysqli_stmt_bind_param($stmt, "ss", $ip, $action);
            mysqli_stmt_execute($stmt);
        }
        
        $query = "SELECT `count` FROM `rates` WHERE (`ipv4` = INET_ATON(?) AND `action` = ?)";

        $stmt = mysqli_stmt_init($link);
        if (mysqli_stmt_prepare($stmt, $query))
        {
            mysqli_stmt_bind_param($stmt, "ss", $ip, $action);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_array($result, MYSQLI_NUM);
            
            // should only be one
            if (isset($row))
            {
                foreach ($row as $r)
                {
                    $count = json_decode($r,true);
                    if ($count > $l)
                    {
                        mysqli_close($link);
                        http_response_code(429);
                        die ("Exceded usage limit ".$count." / ".$l.", try again in ".(($count-$l)/$n)." ".$f);
                    }
                }
            }
        }
    }
    
    return $link;
}

function cancel_edit($link)
{
    mysqli_close($link);
}

?>
