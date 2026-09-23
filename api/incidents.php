<?php

require __DIR__ . '/../db.php';

const DOZVOLJENI_SEVERITY = ['info', 'low', 'medium', 'high', 'critical'];

function filtrirajDozvoljene(?string $raw, array $dozvoljene): array {
    if ($raw === null || $raw === '') return [];
    $trazene = array_map('trim', explode(',', $raw));

    foreach ($trazene as $t) {
        if (!in_array($t, $dozvoljene, true)) {
            jsonOdgovor([
                'error' => "Nepostojeca severity vrednost: $t"
            ], 400);
        }
    }
    return $trazene;
}

$severity = filtrirajDozvoljene($_GET['severity'] ?? null, DOZVOLJENI_SEVERITY);
$limit    = max(1, min(100, (int) ($_GET['limit'] ?? 25)));

$uslovi = []; $vrednosti = [];

if ($severity) {
    $znak = implode(',', array_fill(0, count($severity), '?'));
    $uslovi[] = "severity IN ($znak)";
    array_push($vrednosti, ...$severity);
}

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') 
    { 
        $uslovi[] = "(hostname LIKE ? OR process_name LIKE ? OR title LIKE ?)";
        $sablon = '%' . $q . '%';
        array_push($vrednosti, $sablon, $sablon, $sablon);
     }

$where = $uslovi ? 'WHERE ' . implode(' AND ', $uslovi) : '';

try {
    $st = db()->prepare(
        "SELECT id, created_at, hostname, username,
                process_name, event_id, severity,
                status, confidence, title
         FROM incidents $where
         ORDER BY id DESC
         LIMIT $limit"
    );
    $st->execute($vrednosti);
    $redovi = $st->fetchAll();

    $stc = db()->prepare("SELECT COUNT(*) FROM incidents $where");
    $stc->execute($vrednosti);
    $ukupno = (int) $stc->fetchColumn();
} catch (PDOException $e) {
    error_log('incidents.php: ' . $e->getMessage());
    jsonOdgovor(['error' => 'Greska pri citanju baze.'], 500);
}

jsonOdgovor([
    'data'  => $redovi,
    'total' => $ukupno,
    'as_of' => gmdate('c'),
]);
