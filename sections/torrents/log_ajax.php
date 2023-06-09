<?php

$torrent = (new Gazelle\Manager\Torrent)->findById((int)$_GET['torrentid']);
if (is_null($torrent)) {
    error(404);
}

echo $Twig->render('torrent/riplog.twig', [
    'id'        => $torrent->id(),
    'list'      => $torrent->logfileList(new Gazelle\File\RipLog, new Gazelle\File\RipLogHTML),
    'log_score' => (int)($_GET['logscore'] ?? 0),
    'viewer'    => $Viewer,
]);
