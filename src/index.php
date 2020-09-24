<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="../assets/favicon.png" type="image/png">
<link rel="apple-touch-icon-precomposed" href="../assets/favicon.png">

<link rel="stylesheet" href="css/gawm.css">

<script src="js/gawm.js"></script>
<script src="js/gawm_txt.js"></script>
<script src="js/gawm_netqueue.js"></script>

<title>Getting Away With Murder</title>

<!-- social media sharing meta data -->
<meta property="og:url" content="http://gawm.link/">
<meta property="og:title" content="Getting Away With Murder">
<meta property="og:site_name" content="Getting Away With Murder">
<meta property="og:description" content="A co-operative role-play game of drama, wit, and mystery.">

<?php
// social media schenanigans
if ((stripos($_SERVER['HTTP_USER_AGENT'],"facebookexternalhit")!==false) ||
    (stripos($_SERVER['HTTP_USER_AGENT'],"twitterbot")!==false)): ?>
        <meta property="og:image" content="http://gawm.link/assets/fb-image.jpg">
        <meta name="twitter:card" content="summary_large_image">
<?php else: ?>
        <meta property="og:image" content="http://gawm.link/assets/og-image.jpg">
        <meta name="twitter:card" content="summary">
<?php endif; ?>

<meta property="og:image:alt" content="The words Getting Away With Murder appear large over a back and white photo of a mansion, subtitled A co-operative role-play game of drama, wit, and mystery.">


<script>
function start()
{
    document.getElementById('landing').style.display = "none";
    new_game(get_contenteditable('user_name'));
}

function join()
{
    var ugc = get_contenteditable('user_game_code');
    if (ugc!='0000')
    {
        add_player(
            ugc,
            get_contenteditable('user_name'),
            function(){
                document.getElementById('landing').style.display = "none";
                document.title = "GAWM #"+ugc;
                history.pushState(game_id,document.title,create_loadnurl());
            }
        );
    }
}

function getParameterByName(name, url)
{
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

function reset()
{
    var url = window.location.protocol + "//"
        + window.location.hostname
        + window.location.pathname;
    window.location.replace(url);
}

function body_onload()
{
    // pre-fill editable
    var game_id = getParameterByName('ugc');
    if (game_id) document.getElementById('user_game_code').innerHTML = game_id;

    var name = getParameterByName('un');
    if (name) document.getElementById('user_name').innerHTML = name;

    var d = getParameterByName('d');
    if (d) document.getElementById('debug_div').style.display="block";

    gawm_load_txt(function() {
        var action = getParameterByName('a');
        if (action == 'join_game') join();

        if (action == 'load_game')
        {
            reload(getParameterByName('pid'),game_id);
            document.getElementById('landing').style.display = "none";
        }
    });
}

function copy_game_link(textarea)
{
    textarea.select();
    document.execCommand("copy");
    error_popup("Invite link copied to clipboard!");
}

</script>
</head>

<body onload="body_onload()">

<div id="debug_div" style="display: none;"></div>

<div id="loading_div">
<img width=50 height=50 src="assets/loading.gif" alt="rotating circles to indicate network activity">
</div>


<div id="landing">
  <div class="header">
    <h1><span>Getting Away With</span><br><span>Murder</span></h1>
    <h2>A co-operative role-play game of drama, wit, and mystery.</h2>
  </div>

  <div class="about">
  <h3>Getting Away With Murder</h3> is a co-operative role-play game where your group will bring to life a darkly comedic murder mystery. A game can be set up and ready to play in minutes. There's no game-master, no dice and no abilities to keep track of. The entire story is told in one sitting.
  <p>Can you get away with murder?</p>
  </div>

  <div class="feat"><h3>Features</h3>
  <ul>
  <li> Supports 4 to 6 players. </li>
  <li> Booklet with clear explanations of the rules, role-playing tips, and examples.
  <li> <a href="components.html">153 detail cards</a> to help you create the mystery, including 16 characters and 30 motives. </li>
  <li> More than four tredecillion murders to solve! </li>
  <li> Multiple ways to play: <ul> <li> "Print and Play", <li> Tabletop Simulator, <li> and in Web Browser.</ul>
  </ul>
  </div>

  <div class="buy"><h3>You decide the price</h3>
  <p><a href="#">Buy the game</a> for whatever cost you feel is fair. You'll recieve PDFs of the rule book and the Print and Play resources.</p><p>If you had fun, please consider coming back to increase your donation.</p>
  </div>

  <div class="discord"><h3>Community Discord</h3>
  <p>Looking for a group to play with? Want to hear the latest news and developments?</p>
  <p>Join the <a href="https://discord.gg/d2uMBST">community discord</a>.</p>
  </div>

  <div class="cover"><img src="assets/cover_5-3.png" alt="Book Cover"></div>

  <div class="playnow"><h3>Play Online Now</h3>
  <div class="action">
  <br>
  <div class="row">
   <span>Enter your name, </span><span id="user_name" class="edit" contenteditable="true">Player</span><span> and:</span>
   </div>
   <div>
   <button id="new_game" onclick="start()">Create</button> a game, or</div>
   <div>
   <button id="join_game" onclick="join()">Join</button> using a game id <span id="user_game_code" class="edit" contenteditable="true">0000</span>.
  </div>
  </div></div>

  <div class="menu"><h3>Other ways to play</h3>
  <ul> <li>Tabletop Simulator (<a href="https://steamcommunity.com/sharedfiles/filedetails/?id=2231637672">Steam Workshop</a>)</li>
       <li> Print N Play </li>
  </ul></div>
</div>


<div id="game_div"></div>
<div id="cardface_container"></div>

<div id="invite">
    <div class="action">
    <div>Game id: <b><span id='game_id'></span></b>. Have other players use this URL to join in:</div>
    <div><textarea id='invite_url_textarea' onclick="copy_game_link(this);"></textarea></div>
    <div><a id='invite_url_a' class="button" role="button">Email It</a></div>
    </div>
</div>

<div id="gameover" style="display: none;">
<div class='action'>
<div><h1>Thanks for Playing!</h1></div>
<button onclick='reset()'>Start Again</button>
</div></div>

<div class="footer">
Game Rules, Software and Content (c) 2016-2020 Joseph Fowler &amp; Thaddaeus Frogley.<br>
The <a href="https://github.com/codemonkey-uk/gawm">source code and privacy statement</a> for this website are hosted on github.
</div>

<div id="snackbar"></div>

</body>
</html>
