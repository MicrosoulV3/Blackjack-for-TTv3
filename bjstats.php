<?php
require_once("backend/functions.php");
require_once("backend/config.php");

dbconn(false);
loggedinonly();
if ($CURUSER["class"] < "1") {
    show_error_msg("Sorry...", "You must be a Crazy Seeder or above to play Blackjack.");
}

$mingames = 2;
stdhead("Blackjack Stats");
begin_frame("Blackjack Stats");
print("<center><h1>Blackjack Stats</h1></center>");

//print("<br />");

print("<center>Stats are cached and updated every 30 minutes. You need to play at least $mingames games to be included.</center>");

print("<br />");

// BEGIN CACHE ///////////////////////////////////////////////////////////

$cachefile = "" . $site_config["cache_dir"] . "/bjstats.txt";
$cachetime = 60 * 30; // 30 minutes
// Serve from the cache if it is younger than $cachetime
if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile))) {
    include($cachefile);
    print("<p align=center><font class=small>This page last updated " . date('Y-m-d H:i:s', filemtime($cachefile)) . ". </font></p>");
    
    end_frame();
    stdfoot();
    
    exit;
}
ob_start(); // start the output buffer

/////////////////////////////////////////////////////////////////////////

function bjtable($res, $frame_caption)
{
    begin_frame($frame_caption, true);
    begin_table();
?>
    <tr>
    <td class=colhead>Rank</td>
    <td class=colhead align=left>User</td>
    <td class=colhead align=right>Wins</td>
    <td class=colhead align=right>Losses</td>
    <td class=colhead align=right>Games</td>
    <td class=colhead align=right>Percentage</td>
    <td class=colhead align=right>Win/Loss</td>
    </tr>
    <?php
    $num = 0;
    while ($a = mysqli_fetch_assoc($res)) {
        ++$num;
        
        //Calculate Win %
        $win_perc = number_format(($a['wins'] / $a['games']) * 100, 1);
        
        // Add a user's +/- statistic
        $plus_minus = $a['wins'] - $a['losses'];
        if ($plus_minus >= 0) {
            $plus_minus = mksize(($a['wins'] - $a['losses'])  * 1024 * 1024 * 1024);
        } else {
            $plus_minus = "-";
            $plus_minus .= mksize(($a['losses'] - $a['wins'])  * 1024 * 1024 * 1024);
        }
        
        print("<tr><td>$num</td><td align=left><table border=0 class=main cellspacing=0 cellpadding=0><tr><td class=embedded>" . "<b><a href=userdetails.php?id=" . $a['id'] . ">" . $a['username'] . "</a></b></td>" . "</tr></table></td><td align=right>" . number_format($a['wins'], 0) . "</td>" . "</td><td align=right>" . number_format($a['losses'], 0) . "</td>" . "</td><td align=right>" . number_format($a['games'], 0) . "</td>" . "</td><td align=right>$win_perc</td>" . "</td><td align=right>$plus_minus</td>" . "</tr>\n");
    }
    end_table();
    end_frame();
}

// Most Games Played
$res = SQL_Query_exec("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games FROM users WHERE bjwins + bjlosses > $mingames ORDER BY games DESC LIMIT 10");

bjtable($res, "<center>Most Games Played</center>", "Users");

print("<br /><br />");
// /Most Games Played

// Highest Win %
$res = SQL_Query_exec("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games, bjwins / (bjwins + bjlosses) AS winperc FROM users WHERE bjwins + bjlosses > $mingames ORDER BY winperc DESC LIMIT 10");

bjtable($res, "<center>Highest Win Percentage</center>", "Users");

print("<br /><br />");
// /Highest Win %

// Most Credit Won
$res = SQL_Query_exec("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games, bjwins - bjlosses AS winnings FROM users WHERE bjwins + bjlosses > $mingames ORDER BY winnings DESC LIMIT 10");

bjtable($res, "<center>Most Credit Won</center>", "Users");

print("<br /><br />");
// /Most Credit Won

// Most Credit Lost
$res = SQL_Query_exec("SELECT id, username, bjwins AS wins, bjlosses AS losses, bjwins + bjlosses AS games, bjlosses - bjwins AS losings FROM users WHERE bjwins + bjlosses > $mingames ORDER BY losings DESC LIMIT 10");

bjtable($res, "<center>Most Credit Lost</center>", "Users");

// /Most Credit Lost

// CACHE END ////////////////////////////////////////////////////////////

// open the cache file for writing      
$fp = fopen($cachefile, 'w');
// save the contents of output buffer to the file    
fwrite($fp, ob_get_contents());
// close the file
fclose($fp);
// Send the output to the browser
ob_end_flush();

/////////////////////////////////////////////////////////////////////////

print("<br /><br />");
end_frame();
stdfoot();
?>
