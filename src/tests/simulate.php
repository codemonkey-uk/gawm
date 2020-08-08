<?php
require_once '../api/gawm.php';
require_once 'testing.php';
require_once 'playthrough.php';

    //$other_players = array_filter( $player_ids, 
    //    function($id)use($data){return $id!=$data["most_innocent"];}
    //);


    // accuse who has the most guilty tokens
    // $g = gawm_list_players_by_most_guilty_tokens($data);
    // $g = gawm_list_players_by_net_tokens($data);
    // $g = gawm_list_players_by_guilty_tokens_vs_innocence_scores($data, $rules);
    // if ($g[0]==$data["most_innocent"])
    //     array_shift($g);
    // $accused = $g[0];
    
$most_guilty_tokens = function ($data)
{
    $g = gawm_list_players_by_most_guilty_tokens($data);
    if ($g[0]==$data["most_innocent"])
        array_shift($g);
    return $g[0];
};
$net_tokens = function ($data)
{
    $g = gawm_list_players_by_net_tokens($data);
    if ($g[0]==$data["most_innocent"])
        array_shift($g);
    return $g[0];
};

function make_rules($override)
{
    $rules = gawm_default_rules;
    foreach($override as $key => $value)
        $rules[$key] = $value;
    return $rules;
}

try{

    $scenario[] = gawm_default_rules;
    $scenario[] = make_rules(["new_player_tokens" => range(1,4)]);
    
    foreach($scenario as $rules)
    {
        $fates=[
            "got_caught" => 0,
            "gawm" => 0,
            "got_it_right" => 0,
            "got_it_wrong" => 0,
            "got_framed" => 0,
            "got_out_alive" => 0
        ];    
        for ($i = 1; $i <= 500; $i++)
        {
            for ($c = 4; $c <= 6; $c++)
            {
                $f = function($data) use ($rules) {
                    $g = gawm_list_players_by_guilty_tokens_vs_innocence_scores($data, $rules);
                    if ($g[0]==$data["most_innocent"])
                        array_shift($g);
                    return $g[0];
                };
                
                test_playthrough($c, $rules, $f);
                foreach(array_keys($data["players"]) as $pk)
                {
                    $fates[$data["players"][$pk]["fate"]]++;
                }
            }
        }
        echo json_encode($fates) . "\n";
    }

}
catch (Exception $e) {
    echo("Caught ".$e."\nWith: ".json_encode($data) ."\n");
    exit(1);
}
?>
