<?php
/**
 * BLADE demo -- zajednicka veza sa bazom i sema.
 * SQLite se koristi da bi primer bio pokretljiv bez servera.
 * Produkcija (prema dokumentaciji): MySQL za aplikativne podatke,
 * ClickHouse za sirove logove.
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $putanja = __DIR__ . '/blade.sqlite';
    $prviPut = !file_exists($putanja);

    $pdo = new PDO('sqlite:' . $putanja);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($prviPut) {
        napraviSemu($pdo);
        napuniPodacima($pdo);
    }
    return $pdo;
}

function napraviSemu(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE incidents (
      id            INTEGER PRIMARY KEY,
      created_at    TEXT    NOT NULL,          -- ISO 8601, UTC
      hostname      TEXT    NOT NULL,
      username      TEXT    NOT NULL,
      process_name  TEXT    NOT NULL,
      event_id      INTEGER NOT NULL,
      severity      TEXT    NOT NULL,          -- info|low|medium|high|critical
      status        TEXT    NOT NULL,          -- new|in_progress|closed|false_positive
      confidence    REAL    NOT NULL,          -- 0.0 - 1.0
      title         TEXT    NOT NULL,
      explanation   TEXT    NOT NULL,
      assigned_to   INTEGER NULL
    );
    CREATE TABLE users (
      id       INTEGER PRIMARY KEY,
      username TEXT NOT NULL,
      role     TEXT NOT NULL                   -- viewer|analyst_l1|analyst_l2|admin
    );
    CREATE TABLE agents (
      id        INTEGER PRIMARY KEY,
      hostname  TEXT NOT NULL,
      os        TEXT NOT NULL,
      version   TEXT NOT NULL,
      status    TEXT NOT NULL,                 -- online|offline|isolated
      last_seen TEXT NOT NULL
    );
    CREATE TABLE actions (
      id          INTEGER PRIMARY KEY,
      incident_id INTEGER NOT NULL,
      user_id     INTEGER NOT NULL,
      command     TEXT NOT NULL,
      target      TEXT NOT NULL,
      status      TEXT NOT NULL,               -- pending|done|failed
      created_at  TEXT NOT NULL
    );
    CREATE TABLE log_flow (
      id          INTEGER PRIMARY KEY,
      incident_id INTEGER NOT NULL,
      stage       TEXT NOT NULL,
      ts          TEXT NOT NULL,
      duration_ms INTEGER NOT NULL,
      status      TEXT NOT NULL
    );
    ");
}

function napuniPodacima(PDO $pdo): void {
    $pdo->exec("INSERT INTO users (id, username, role) VALUES
      (1,'m.jovanovic','analyst_l1'),
      (2,'s.petrovic','analyst_l2'),
      (3,'a.nikolic','admin'),
      (4,'direktor','viewer')");

    $hostovi = ['WS-FIN-014','WS-FIN-022','SRV-DC-01','SRV-FILE-03','WS-HR-007',
                'WS-DEV-041','SRV-SQL-02','WS-SALES-118'];
    $procesi  = ['powershell.exe','rundll32.exe','svchost.exe','mimikatz.exe',
                 'cmd.exe','wscript.exe','certutil.exe','regsvr32.exe'];
    $sev      = ['info','low','medium','high','critical'];
    $status   = ['new','new','new','in_progress','closed','false_positive'];
    $naslovi  = [
      'Sumnjivo PowerShell izvrsavanje sa enkodiranom komandom',
      'Pristup LSASS procesu iz nepoznatog procesa',
      'Neuobicajeno vreme prijave za korisnika',
      'Masovno preimenovanje fajlova -- moguci ransomware',
      'Odlazna konekcija ka nepoznatoj IP adresi',
      'Kreiran nov lokalni administratorski nalog',
      'Iskljucen Windows Defender kroz registry',
      'Preuzimanje fajla kroz certutil',
    ];

    $st = $pdo->prepare("INSERT INTO incidents
      (id, created_at, hostname, username, process_name, event_id, severity,
       status, confidence, title, explanation)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)");

    // Determinisan generator -- isti podaci pri svakom pokretanju.
    mt_srand(42);
    $pocetak = strtotime('2026-09-18 08:00:00 UTC');
    for ($i = 1; $i <= 240; $i++) {
        $ts = gmdate('c', $pocetak - ($i * 137));
        $st->execute([
            $i,
            $ts,
            $hostovi[$i % count($hostovi)],
            ['m.jovanovic','SYSTEM','a.peric','n.stankovic'][$i % 4],
            $procesi[$i % count($procesi)],
            [4624, 4688, 4672, 1102, 7045][$i % 5],
            $sev[$i % count($sev)],
            $status[$i % count($status)],
            round(0.55 + (($i * 7) % 45) / 100, 2),
            $naslovi[$i % count($naslovi)],
            'AlertGEN AI: dogadjaj odstupa od naucene osnovne linije za ovaj host.',
        ]);
    }

    // NAMERNO: incident ciji naziv procesa sadrzi HTML.
    // Napadac koji je vec na endpointu moze da nazove proces kako hoce,
    // a to ime putuje kroz ceo pipeline do ekrana analiticara.
    $st->execute([
        999,
        gmdate('c', $pocetak),
        'WS-FIN-014',
        'a.peric',
        '<img src=x onerror="window.__xss=1">',
        4688,
        'critical',
        'new',
        0.97,
        'Proces sa neuobicajenim imenom <script>alert(1)</script>',
        'Naziv procesa sadrzi HTML -- test za bezbedan prikaz.',
    ]);

    $sta = $pdo->prepare("INSERT INTO agents
      (hostname, os, version, status, last_seen) VALUES (?,?,?,?,?)");
    foreach ($hostovi as $n => $h) {
        $sta->execute([$h, 'Windows 11 Pro', '2.4.1',
            ['online','online','online','offline'][$n % 4],
            gmdate('c', $pocetak - $n * 60)]);
    }

    $stf = $pdo->prepare("INSERT INTO log_flow
      (incident_id, stage, ts, duration_ms, status) VALUES (?,?,?,?,?)");
    $faze = [
        ['Agent', 0, 2],  ['Edge Collector', 40, 6], ['Main Collector', 95, 4],
        ['Denoiser Engine', 140, 31], ['VIAI BERT Stage I', 220, 84],
        ['VIAI BERT Stage II', 320, 96], ['AlertGEN AI', 450, 121],
    ];
    foreach ([1, 999] as $inc) {
        foreach ($faze as $f) {
            $stf->execute([$inc, $f[0],
                gmdate('c', $pocetak + $f[1]), $f[2], 'ok']);
        }
    }
}

/** Jedinstven izlaz za JSON odgovore. */
function jsonOdgovor(array $telo, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($telo, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Ko je prijavljen. U pravoj aplikaciji dolazi iz sesije. */
function trenutniKorisnik(): array {
    $id = (int)($_GET['as_user'] ?? $_POST['as_user'] ?? 1);
    $st = db()->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $st->execute([$id]);
    $k = $st->fetch();
    return $k ?: ['id' => 0, 'username' => 'anonymous', 'role' => 'viewer'];
}