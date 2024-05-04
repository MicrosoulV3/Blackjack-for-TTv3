<?php
require_once("backend/functions.php");
dbconn(false);

if ($CURUSER["class"] < "1") {
    show_error_msg("Sorry...", "I dont know why everyone wouldnt be able to play so...This setting is stupid, but do as you will", 1);
}

if (!function_exists('get_user_name')) {
    function get_user_name($userid)
    {
        $r = SQL_Query_exec("select username from users where id=$userid");
        $a = mysqli_fetch_array($r);
        return "$a[username]";
    }
}
// Bet size
$mb = $site_config['betsize']*1024*1024*1024; //bet size. Edit config.php to change the value
$now = sqlesc(get_date_time());

// Pull the user's statisctics
$r = SQL_Query_exec("SELECT bjwins, bjlosses FROM users WHERE id=$CURUSER[id]");
$a = mysqli_fetch_array($r);
$tot_wins = $a['bjwins'];
$tot_losses = $a['bjlosses'];
$tot_games = $tot_wins + $tot_losses;

// If this gets through there is an error somewhere!
$win_perc = "<a href=\"/sendmessage.php?receiver=1\">Error!</a>";

// Calculate user's win percentage
if ($tot_losses == 0) {
    if ($tot_wins > 0) // 0 losses, > 0 wins = 100%
        $win_perc = "100%";
    if ($tot_wins == 0) // 0 losses, 0 wins = "---"
        $win_perc = "---";
} else if ($tot_losses > 0) {
    if ($tot_wins == 0) // > 0 losses, 0 wins = 0%
        $win_perc = "0";
    if ($tot_wins > 0) // > 0 losses, > 0 wins = return win % rounded to nearest tenth
        $win_perc = number_format(($tot_wins / $tot_games) * 100, 1);
    $win_perc .= "%";
}

// Add a user's +/- statistic
$plus_minus = $tot_wins - $tot_losses;
if ($plus_minus >= 0) {
    $plus_minus = mksize(($tot_wins - $tot_losses) * $mb);
} else {
    $plus_minus = "-";
    $plus_minus .= mksize(($tot_losses - $tot_wins) * $mb);
}

// Game Mechanics
if ($_POST["game"]) {
    $cardcountres = SQL_Query_exec("select count(id) from cards");
    $cardcountarr = mysqli_fetch_array($cardcountres);
    $cardcount = $cardcountarr[0];
    if ($_POST["game"] == 'start') {
        if ($CURUSER["uploaded"] < $mb)
            show_error_msg("Sorry " . $CURUSER["username"], "You haven't uploaded " . mksize($mb) . " yet.", 1);
        $required_ratio = 0.3;
        if ($CURUSER["downloaded"] > 0)
            $ratio = number_format($CURUSER["uploaded"] / $CURUSER["downloaded"], 2);
        else if ($CURUSER["uploaded"] > 0)
            $ratio = 999;
        else
            $ratio = 0;
        if ($ratio < $required_ratio)
            show_error_msg("Sorry " . $CURUSER["username"], "Your ratio is lower than the requirement of " . $required_ratio . "%.", 1);
        $res = SQL_Query_exec("select count(*) from blackjack where userid=$CURUSER[id] and status='waiting'");
        $arr = mysqli_fetch_array($res);
        if ($arr[0] > 0) {
            show_error_msg("" .T_("SORRY"). "", "" .T_("ERROR_YOU_LAST_GAME"). "<br /><br /><a href=blackjack.php>" .T_("BACK"). "</a>", 1);
        } else {
            $res = SQL_Query_exec("select count(*) from blackjack where userid=$CURUSER[id] and status='playing'");
            $arr = mysqli_fetch_array($res);
            if ($arr[0] > 0)
                show_error_msg("" .T_("SORRY"). "", "" .T_("ERROR1"). " <form method=post name=form action=$phpself><input type=hidden name=game value=cont><center><input type=submit value=' " .T_("CONTINUE_OLD_GAME"). " '></center></form>", 1);
        }
        $cardid = rand(1, $cardcount);
        $cardres = SQL_Query_exec("select * from cards where id=$cardid");
        $cardarr = mysqli_fetch_array($cardres);
        if ($cardarr['points'] == 1)
            $cardarr['points'] = 11;
        SQL_Query_exec("insert into blackjack (userid, points, cards, date) values($CURUSER[id], $cardarr[points], $cardid, $now)");
        stdhead("Blackjack");
        begin_frame("Blackjack");
        print("<h1>" .T_("WELCOME"). ", <a href=userdetails.php?id=$CURUSER[id]>$CURUSER[username]</a>!</h1>\n");
        print("<table align=center cellspacing=0 cellpadding=3 width=600>\n");
        print("<tr><td colspan=2 cellspacing=0 cellpadding=5 >");
        print("<form name=blackjack method=post action=$phpself>");
        print("<table class=message width=100% cellspacing=0 cellpadding=5 >\n");
        print("<tr><td align=center><img src=images/cards/" . $cardarr["pic"] . " width=71 height=96 border=0></td></tr>");
        print("<tr><td align=center><b>Points = $cardarr[points]</b></td></tr>");
        print("<tr><td align=center><input type=hidden name=game value=cont><input type=submit value=' " .T_("HIT_ME"). " '></td></tr>");
        print("</table>");
        print("</form>");
        print("</td></tr></table><br />");
        end_frame();
        stdfoot();
    } elseif ($_POST["game"] == 'cont') {
        $playeres = SQL_Query_exec("select * from blackjack where userid=$CURUSER[id]");
        $playerarr = mysqli_fetch_array($playeres);
        $showcards = "";
        $aces = 0;
        $points = 0;
        $cards = $playerarr["cards"];
        $usedcards = explode(" ", $cards);
        $arr = array();
        foreach ($usedcards as $array_list)
            $arr[] = $array_list;
        foreach ($arr as $card_id) {
            $used_card = SQL_Query_exec("SELECT * FROM cards WHERE id='$card_id'");
            $used_cards = mysqli_fetch_array($used_card);
            $showcards .= "<img src=images/cards/" . $used_cards["pic"] . " width=71 height=96 border=0> ";
            if ($used_cards["points"] > 1)
                $points = $points + $used_cards['points'];
            else
                $aces = $aces + 1;
        }
        $cardid = rand(1, $cardcount);
        while (in_array($cardid, $arr)) {
            $cardid = rand(1, $cardcount);
        }
        $cardres = SQL_Query_exec("select * from cards where id=$cardid");
        $cardarr = mysqli_fetch_array($cardres);
        $showcards .= "<img src=images/cards/" . $cardarr["pic"] . " width=71 height=96 border=0> ";
        if ($cardarr["points"] > 1)
            $points = $points + $cardarr["points"];
        else
            $aces = $aces + 1;
        for ($i = 0; $i < $aces; $i++) {
            if ($points < 11 && $aces - $i == 1)
                $points = $points + 11;
            else
                $points = $points + 1;
        }
        
        $mysqlcards = "$playerarr[cards] $cardid";
        SQL_Query_exec("update blackjack set points=$points, cards='$mysqlcards' where userid=$CURUSER[id]");
        if ($points == 21) {
            $waitres = SQL_Query_exec("select count(*) from blackjack where status='waiting' and userid!=$CURUSER[id]");
            $waitarr = mysqli_fetch_array($waitres);
            stdhead("Blackjack");
            begin_frame("Blackjack");
            print("<h1>" .T_("GAME_OVER"). "</h1>\n");
            print("<table align=center cellspacing=0 cellpadding=3 width=600>\n");
            print("<tr><td colspan=2 cellspacing=0 cellpadding=5 />");
            print("<table class=message width=100% cellspacing=0 cellpadding=5 >\n");
            print("<tr><td align=center>$showcards</td></tr>");
            print("<tr><td align=center><b>Points = $points</b></td></tr>");
            if ($waitarr[0] > 0) {
                $r = SQL_Query_exec("select * from blackjack where status='waiting' and userid!=$CURUSER[id] order by date asc LIMIT 1");
                $a = mysqli_fetch_assoc($r);
                if ($a["points"] != 21) {
                    $winorlose = "you won " . mksize($mb);
                    SQL_Query_exec("update users set uploaded = uploaded + $mb, bjwins = bjwins + 1 where id=$CURUSER[id]");
                    SQL_Query_exec("update users set uploaded = uploaded - $mb, bjlosses = bjlosses + 1 where id=$a[userid]");
                    SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                    SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                    $dt = sqlesc(get_date_time());
                    $msg = sqlesc("You lost to $CURUSER[username] (you got $a[points] points, and $CURUSER[username] got 21 points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play again![/url]");
                    SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
                } else {
                    $winorlose = "nobody won";
                    SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                    SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                    $dt = sqlesc(get_date_time());
                    $msg = sqlesc("You tied with $CURUSER[username] (Both of you had $a[points] points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play again![/url]");
                    SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
                    
                }
                print("<tr><td align=center>" .T_("YOUR_OPPONENT_WAS"). " " . get_user_name($a["userid"]) . ", " .T_("THEY_HAD"). " $a[points] points, $winorlose.<br /><br /><center><b><a href=blackjack.php>" .T_("PLAY_AGAIN"). "</a></b></center></td></tr>");
            } else {
                SQL_Query_exec("update blackjack set status = 'waiting', date='" . get_date_time() . "' where userid = $CURUSER[id]");
                print("<tr><td align=center>" .T_("ODTHER_PLAYERS1"). "<br />" .T_("ODTHER_PLAYERS2"). "<br /><br /><center><b><a href=blackjack.php>" .T_("BACK"). "</a></b><br /></center></td></tr>");
            }
            print("</table>");
            print("</td></tr></table><br />");
            end_frame();
            stdfoot();
        } elseif ($points > 21) {
            $waitres = SQL_Query_exec("select count(*) from blackjack where status='waiting' and userid!=$CURUSER[id]");
            $waitarr = mysqli_fetch_array($waitres);
            stdhead("Blackjack");
            begin_frame("Blackjack");
            print("<h1>" .T_("GAME_OVER"). "</h1>\n");
            print("<table align=center cellspacing=0 cellpadding=3 width=600>\n");
            print("<tr><td colspan=2 cellspacing=0 cellpadding=5 />");
            print("<table class=message width=100% cellspacing=0 cellpadding=5 >\n");
            print("<tr><td align=center>$showcards</td></tr>");
            print("<tr><td align=center><b>Points = $points</b></td></tr>");
            if ($waitarr[0] > 0) {
                $r = SQL_Query_exec("select * from blackjack where status='waiting' and userid!=$CURUSER[id] order by date asc LIMIT 1");
                $a = mysqli_fetch_assoc($r);
                if ($a["points"] > 21) {
                    $winorlose = "nobody won";
                    SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                    SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                    $dt = sqlesc(get_date_time());
                    $msg = sqlesc("Your opponent was $CURUSER[username], Nobody won.\n\n [url=".$site_config["SITEURL"]."/blackjack.php]" .T_("BACK"). "[/url]");
                    SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
                } else {
                    $winorlose = "you lost " . mksize($mb);
                    SQL_Query_exec("update users set uploaded = uploaded - $mb, bjlosses = bjlosses + 1 where id=$CURUSER[id]");
                    SQL_Query_exec("update users set uploaded = uploaded + $mb, bjwins = bjwins + 1 where id=$a[userid]");
                    SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                    SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                    $dt = sqlesc(get_date_time());
                    $msg = sqlesc("You beat $CURUSER[username] (You had $a[points] points, $CURUSER[username] had $points points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]" .T_("BACK"). "[/url]");
                    SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
                }
                print("<tr><td align=center>Your opponent was " . get_user_name($a["userid"]) . ", They had $a[points] points, $winorlose.<br /><br /><center><b><a href=blackjack.php>Play again</a></b></center></td></tr>");
            } else {
                SQL_Query_exec("update blackjack set status = 'waiting', date='" . get_date_time() . "' where userid = $CURUSER[id]");
                print("<tr><td align=center>" .T_("ODTHER_PLAYERS1"). "<br />" .T_("ODTHER_PLAYERS2"). "<br /><br /><center><b><a href=blackjack.php>" .T_("BACK"). "</a></b><br /></center></td></tr>");
            }
            print("</table>");
            print("</td></tr></table><br />");
            end_frame();
            stdfoot();
        } else {
            stdhead("Blackjack");
            begin_frame("Blackjack");
            print("<h1>" .T_("WELCOME"). ", <a href=userdetails.php?id=$CURUSER[id]>$CURUSER[username]</a>!</h1>\n");
            print("<table align=center cellspacing=0 cellpadding=3 width=600>\n");
            print("<tr><td colspan=2 cellspacing=0 cellpadding=5 />");
            print("<table class=message width=100% cellspacing=0 cellpadding=5 >\n");
            print("<tr><td align=center>$showcards</td></tr>");
            print("<tr><td align=center><b>Points = $points</b></td></tr>");
            print("<form name=blackjack method=post action=$phpself>");
            print("<tr><td align=center><input type=hidden name=game value=cont><input type=submit value=' " .T_("HIT_ME"). " '></td></tr>");
            print("</form>");
            print("<form name=blackjack method=post action=$phpself>");
            print("<tr><td align=center><input type=hidden name=game value=stop><input type=submit value=' " .T_("STAY"). " '></td></tr>");
            print("</form>");
            print("</table>");
            print("</td></tr></table><br />");
            end_frame();
            stdfoot();
        }
    } elseif ($_POST["game"] == 'stop') {
        $playeres = SQL_Query_exec("select * from blackjack where userid=$CURUSER[id]");
        $playerarr = mysqli_fetch_array($playeres);
        $showcards = "";
        $cards = $playerarr["cards"];
        $usedcards = explode(" ", $cards);
        $arr = array();
        foreach ($usedcards as $array_list)
            $arr[] = $array_list;
        foreach ($arr as $card_id) {
            $used_card = SQL_Query_exec("SELECT * FROM cards WHERE id='$card_id'");
            $used_cards = mysqli_fetch_array($used_card);
            $showcards .= "<img src=images/cards/" . $used_cards["pic"] . " width=71 height=96 border=0> ";
        }
        $waitres = SQL_Query_exec("select count(*) from blackjack where status='waiting' and userid!=$CURUSER[id]");
        $waitarr = mysqli_fetch_array($waitres);
        stdhead("Blackjack");
        begin_frame("Blackjack");
        print("<h1>" .T_("GAME_OVER"). "</h1>\n");
        print("<table align=center cellspacing=0 cellpadding=3 width=600>\n");
        print("<tr><td colspan=2 cellspacing=0 cellpadding=5 />");
        print("<table class=message width=100% cellspacing=0 cellpadding=5 >\n");
        print("<tr><td align=center>$showcards</td></tr>");
        print("<tr><td align=center><b>Points = $playerarr[points]</b></td></tr>");
        if ($waitarr[0] > 0) {
            $r = SQL_Query_exec("select * from blackjack where status='waiting' and userid!=$CURUSER[id] order by date asc LIMIT 1");
            $a = mysqli_fetch_assoc($r);
            if ($a["points"] == $playerarr['points']) {
                $winorlose = "nobody won";
                SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                $dt = sqlesc(get_date_time());
                $msg = sqlesc("Your opponent was $CURUSER[username], you both had $a[points] points - it was a tie.\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play Again[/url]");
                SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
            } elseif ($a["points"] < $playerarr['points'] && $a['points'] < 21) {
                $winorlose = "you won " . mksize($mb);
                SQL_Query_exec("update users set uploaded = uploaded + $mb, bjwins = bjwins + 1 where id=$CURUSER[id]");
                SQL_Query_exec("update users set uploaded = uploaded - $mb, bjlosses = bjlosses + 1 where id=$a[userid]");
                SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                $dt = sqlesc(get_date_time());
                $msg = sqlesc("You lost to $CURUSER[username] (You had $a[points] points, $CURUSER[username] had $playerarr[points] points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play Again[/url]");
                SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
            } elseif ($a["points"] > $playerarr['points'] && $a['points'] < 21) {
                $winorlose = "you lost " . mksize($mb);
                SQL_Query_exec("update users set uploaded = uploaded - $mb, bjlosses = bjlosses + 1 where id=$CURUSER[id]");
                SQL_Query_exec("update users set uploaded = uploaded + $mb, bjwins = bjwins + 1 where id=$a[userid]");
                SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                $dt = sqlesc(get_date_time());
                $msg = sqlesc("You beat $CURUSER[username] (You had $a[points] points, $CURUSER[username] had $playerarr[points] points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play Again[/url]");
                SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
            } elseif ($a["points"] == 21) {
                $winorlose = "you lost " . mksize($mb);
                SQL_Query_exec("update users set uploaded = uploaded - $mb, bjlosses = bjlosses + 1 where id=$CURUSER[id]");
                SQL_Query_exec("update users set uploaded = uploaded + $mb, bjwins = bjwins + 1 where id=$a[userid]");
                SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                $dt = sqlesc(get_date_time());
                $msg = sqlesc("You beat $CURUSER[username] (You had $a[points] points, $CURUSER[username] had $playerarr[points] points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play Again[/url]");
                SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
            } elseif ($a["points"] < $playerarr['points'] && $a['points'] > 21) {
                $winorlose = "you lost " . mksize($mb);
                SQL_Query_exec("update users set uploaded = uploaded - $mb, bjlosses = bjlosses + 1 where id=$CURUSER[id]");
                SQL_Query_exec("update users set uploaded = uploaded + $mb, bjwins = bjwins + 1 where id=$a[userid]");
                SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                $dt = sqlesc(get_date_time());
                $msg = sqlesc("You beat $CURUSER[username] (You had $a[points] points, $CURUSER[username] had $playerarr[points] points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play Again[/url]");
                SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
            } elseif ($a["points"] > $playerarr['points'] && $a['points'] > 21) {
                $winorlose = "you won " . mksize($mb);
                SQL_Query_exec("update users set uploaded = uploaded + $mb, bjwins = bjwins + 1 where id=$CURUSER[id]");
                SQL_Query_exec("update users set uploaded = uploaded - $mb, bjlosses = bjlosses + 1 where id=$a[userid]");
                SQL_Query_exec("delete from blackjack where userid=$CURUSER[id]");
                SQL_Query_exec("delete from blackjack where userid=$a[userid]");
                $dt = sqlesc(get_date_time());
                $msg = sqlesc("You lost to $CURUSER[username] (You had $a[points] points, $CURUSER[username] had $playerarr[points] points).\n\n [url=".$site_config["SITEURL"]."/blackjack.php]Play Again[/url]");
                SQL_Query_exec("INSERT INTO messages (subject, sender, receiver, added, msg, poster) VALUES('Blackjack game', 0, $dbqueryreturn[userid], $dt, $msg, 0)");
            }
            print("<tr><td align=center>Your opponent was " . get_user_name($a["userid"]) . ", they had $a[points] points, $winorlose.<br /><br /><center><b><a href=blackjack.php>Play again</a></b></center></td></tr>");
        } else {
            SQL_Query_exec("update blackjack set status = 'waiting', date='" . get_date_time() . "' where userid = $CURUSER[id]");
            print("<tr><td align=center>" .T_("ODTHER_PLAYERS1"). "<br />" .T_("ODTHER_PLAYERS2"). "<br /><br /><center><b><a href=blackjack.php>" .T_("BACK"). "</a></b><br /></center></td></tr>");
        }
        print("</table>");
        print("</td></tr></table><br />");
        end_frame();
        stdfoot();
    }
} else {
    // Start screen - Not currently playing a game
    stdhead("Blackjack");
    begin_frame("Blackjack");
	$rulesmessage = sprintf( T_("BY_PLAYING"), mksize($mb));
    print("<h1><center>Blackjack</center></h1>\n");
    print("<center><table align=center cellspacing=0 cellpadding=3 width=400>\n");
    print("<tr><td colspan=2 cellspacing=0 cellpadding=5 align=center>\n");
    print("<table class=message width=100% cellspacing=0 cellpadding=10 >\n");
    print("<tr><td align=center><img src=images/cards/tp.bmp width=71 height=96 border=0> <img src=images/cards/vp.bmp width=71 height=96 border=0> </td></tr>\n");
    print("<tr><td align=left>" .T_("YOU_MUST_COLLECT_21"). "\n<a href=info_blackjack.html target=wclose
onclick=window.open('info_blackjack.html','wclose','width=820','height=864','toolbar=yes','status=no','left=30','top=20')><img border='0' src='images/INFO_UPLOAD.png' width='40' height='30' alt='Plus d'information!!' title='Plus d'information!!' /></a><br /><br />\n");
    print("<b>" .T_("NOTE"). ":</b> " .$rulesmessage. "</td></tr>\n");
	print("<tr><td align=center>\n");
    print("<form name=form method=post action=$phpself><input type=hidden name=game value=start><input type=submit class=btn value=' " .T_("START"). " '>\n");
    print("</td></tr></table>\n");
    print("</td></tr></table>\n");
    
    print("<br /><br /><br />\n");
    
    print("<table align=center cellspacing=0 cellpadding=3 width=400>\n");
    print("<tr><td colspan=2 cellspacing=0 cellpadding=5 align=center>\n");
    print("<h1><center>" .T_("PERSONNAL_STATISTICS"). "</center></h1>\n");
    print("<tr><td align=left><b>" .T_("WINS"). "</b></td><td align=center><b>$tot_wins</b></td></tr>\n");
    print("<tr><td align=left><b>" .T_("LOSSES"). "</b></td><td align=center><b>$tot_losses</b></td></tr>\n");
    print("<tr><td align=left><b>" .T_("GAMES_PLAYED"). "</b></td><td align=center><b>$tot_games</b></td></tr>\n");
    print("<tr><td align=left><b>" .T_("WIN_PERCENTAGE"). "</b></td><td align=center><b>$win_perc</b></td></tr>\n");
    print("<tr><td align=left><b>+/-</b></td><td align=center><b>$plus_minus</b></td></tr>\n");
	print("<tr><a href=bjstats.php><center><h1>Click here to view all player stats</h1></center></a></tr>\n");
    print("</td></tr></table></center>\n");
    end_frame();
    stdfoot();
}
?>
