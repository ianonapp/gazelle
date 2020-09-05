<?php
authorize();

$TorrentID = (int)$_POST['torrentid'];
if (!$TorrentID) {
    error(404);
}

if ($Cache->get_value("torrent_{$TorrentID}_lock")) {
    error('Torrent cannot be deleted because the upload process is not completed yet. Please try again later.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

list($UserID, $GroupID, $Size, $InfoHash, $Name, $Year, $ArtistName, $Time, $Media, $Format, $Encoding,
    $HasLog, $HasCue, $HasLogDB, $LogScore, $Remastered, $RemasterTitle, $RemasterYear, $Snatches) = $DB->row('
    SELECT
        t.UserID,
        t.GroupID,
        t.Size,
        t.info_hash,
        tg.Name,
        tg.Year,
        ag.Name,
        t.Time,
        t.Media,
        t.Format,
        t.Encoding,
        t.HasLog,
        t.HasCue,
        t.HasLogDB,
        t.LogScore,
        t.Remastered,
        t.RemasterTitle,
        t.RemasterYear,
        COUNT(x.uid)
    FROM torrents AS t
    LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
    LEFT JOIN artists_group AS ag ON (ag.ArtistID = ta.ArtistID)
    LEFT JOIN xbt_snatched AS x ON (x.fid = t.ID)
    WHERE t.ID = ?
    ', $TorrentID
);
if (($LoggedUser['ID'] != $UserID || time_ago($Time) > 3600 * 24 * 7 || $Snatches > 4) && !check_perms('torrents_delete')) {
    error(403);
}
$sizeMB = number_format($Size / (1024 * 1024), 2) . ' MB';
$RemasterDisplayString = Reports::format_reports_remaster_info($Remastered, $RemasterTitle, $RemasterYear);
if (empty($ArtistName)) {
    $RawName = $Name.($Year ? " ($Year)" : '').($Format || $Encoding || $Media ? " [$Format/$Encoding/$Media]" : '') . $RemasterDisplayString . ($HasCue ? ' (Cue)' : '').($HasLogDB ? " (Log: {$LogScore}%)" : '') . " $sizeMB";
}
elseif ($ArtistName == 'Various Artists') {
    $RawName = "Various Artists - $Name".($Year ? " ($Year)" : '')." [$Format/$Encoding/$Media]{$RemasterDisplayString}" . ($HasCue ? ' (Cue)' : '').($HasLogDB ? " (Log: {$LogScore}%)" : '') . " ($sizeMB)";
}
else {
    $RawName = "$ArtistName - $Name".($Year ? " ($Year)" : '')." [$Format/$Encoding/$Media]{$RemasterDisplayString}" . ($HasCue ? ' (Cue)' : '').($HasLogDB ? " (Log: {$LogScore}%)" : '') . " ($sizeMB)";
}

if ($ArtistName) {
    $Name = "$ArtistName - $Name";
}

if (isset($_SESSION['logged_user']['multi_delete'])) {
    if ($_SESSION['logged_user']['multi_delete'] >= 3 && !check_perms('torrents_delete_fast')) {
        error('You have recently deleted 3 torrents. Please contact a staff member if you need to delete more.');
    }
    $_SESSION['logged_user']['multi_delete']++;
} else {
    $_SESSION['logged_user']['multi_delete'] = 1;
}

$Err = Torrents::delete_torrent($TorrentID, $GroupID);
if ($Err) {
    error($Err);
}

$InfoHash = unpack('H*', $InfoHash);
$sizeMB_hash = "$sizeMB " . strtoupper($InfoHash[1]);
$reason = trim($_POST['reason'] . ' ' . $_POST['extra']);
$Log = "Torrent $TorrentID ($Name) ($sizeMB_hash was deleted by {$LoggedUser['Username']}: $reason";
Torrents::send_pm($TorrentID, $UserID, $RawName, $Log, 0, G::$LoggedUser['ID'] != $UserID);
(new Gazelle\Log)
    ->torrent($GroupID, $TorrentID, $LoggedUser['ID'], "deleted torrent ($sizeMB_hash) for reason: $reason")
    ->general($Log);
View::show_header('Torrent deleted');
?>
<div class="thin">
    <h3>Torrent was successfully deleted.</h3>
</div>
<?php
View::show_footer();
