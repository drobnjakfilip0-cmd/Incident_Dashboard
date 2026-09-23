const API = 'api/incidents.php';

const DOZVOLJENE = ['info', 'low', 'medium', 'high', 'critical'];

const params = new URLSearchParams(location.search);
const severityIzUrl = params.get("severity");

let pocetniSeverity = "";
let pocetnaGreska = null;

if (severityIzUrl !== null) {
    if (DOZVOLJENE.includes(severityIzUrl)) {
        pocetniSeverity = severityIzUrl;
    } else {
        pocetnaGreska =
            `Nevažeća severity vrednost: ${severityIzUrl}`;
    }
}


let stanje = {
    filteri: { severity: pocetniSeverity,  q: ""},
    redovi: [], 
    ukupno: 0,
    ucitava: false,
    greska: pocetnaGreska,
    asOf: null,
};

const el = {
    forma: document.querySelector('#filteri'),
    lista: document.querySelector('#lista'),
    brojac: document.querySelector('#brojac'),
    prazno: document.querySelector('#prazno'),
    greska: document.querySelector('#greska'),
    ucitava: document.querySelector('#ucitava'),

}

el.forma.elements.severity.value = stanje.filteri.severity;

function vremeUTC(iso) {
    const d = new Date(iso), p = (n) => String(n).padStart(2, "0");
    return `${d.getUTCFullYear()}-${p(d.getUTCMonth()+1)}-${p(d.getUTCDate())} ` +
    `${p(d.getUTCHours())}:${p(d.getUTCMinutes())}`;
}

function istakni(tekst, pojam) { const frag = document.createDocumentFragment(); if (!pojam) { frag.appendChild(document.createTextNode(tekst)); return frag; } const t = tekst.toLowerCase(); const p = pojam.toLowerCase(); let i = 0, j; while ((j = t.indexOf(p, i)) !== -1) { if (j > i) { frag.appendChild(document.createTextNode(tekst.slice(i, j))); } const m = document.createElement("mark"); m.textContent = tekst.slice(j, j + pojam.length);  frag.appendChild(m); i = j + pojam.length; } if (i < tekst.length) { frag.appendChild(document.createTextNode(tekst.slice(i))); } return frag;}

const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };};

function crtaj() {

    el.ucitava.hidden = !stanje.ucitava;
    el.greska.hidden = stanje.greska === null;
    el.greska.textContent = stanje.greska ?? "";

    el.lista.replaceChildren(...stanje.redovi.map(red));

    el.brojac.textContent = stanje.ukupno ? `Prikazano ${stanje.redovi.length} od ${stanje.ukupno}` : "";

    const imaFilter = Object.values(stanje.filteri).some((v) => v !== "");
    el.prazno.hidden = stanje.redovi.length > 0 || stanje.ucitava || stanje.greska;
    el.prazno.textContent = imaFilter ? "Nijedan incident ne odgovara ovom filteru." : "Nema incidenata.";

}

function red(inc) {

    const tr = document.createElement("tr");
    tr.dataset.id = inc.id;

    

    const vreme = document.createElement("td");
    vreme.textContent = vremeUTC(inc.created_at);

    const sev = document.createElement("td");
    const znak = document.createElement("span");
    znak.className = `sev sev-${inc.severity}`;
    znak.textContent = inc.severity;
    sev.appendChild(znak);

   const host = document.createElement("td");
  host.textContent = inc.hostname;                    // <-- 1

  const proces = document.createElement("td");
  proces.className = "mono";
  proces.textContent = inc.process_name;              // <-- 2

  const naslov = document.createElement("td");
  naslov.textContent = inc.title;                     // <-- 3

  const status = document.createElement("td");
  status.textContent = inc.status;

  tr.append(vreme, sev, host, proces, naslov, status);
  return tr;
    
}

let kontroler = null;

async function ucitaj() {
    kontroler?.abort();
    kontroler = new AbortController();

    stanje = {...stanje, ucitava: true, greska: null};
    crtaj();

    const p = new URLSearchParams();
    p.set("limit", 25);
    if (stanje.filteri.severity) p.set("severity", stanje.filteri.severity);
    if (stanje.filteri.q) p.set("q", stanje.filteri.q);

    try {

        const res = await fetch(`${API}?${p}`, 
            {signal: kontroler.signal}
        );

        if (!res.ok) throw new Error(`Server je vratio ${res.status}`);
        const telo = await res.json();
        stanje = {...stanje, redovi: telo.data, ukupno: telo.total, asOf: telo.as_of, ucitava: false};

    } catch (e) {

        if (e.name === "AbortError") return;

        stanje = {...stanje, ucitava: false, greska: `Neuspelo ucitavanje: ${e.message}`}

    }
    crtaj();
}

el.forma.addEventListener("change", (e) => {
    if (!e.target.matches("select")) return;
    stanje = {...stanje, filteri:{...stanje.filteri, [e.target.name]: e.target.value}};

    const params = new URLSearchParams();

    if (stanje.filteri.severity) {
        params.set("severity", stanje.filteri.severity)
    };

    const query = params.toString();

    history.replaceState(
        null,
        "",
        query ? `?${query}` : location.pathname
    );

    ucitaj();
});

el.forma.addEventListener("submit", (e) => e.preventDefault());

document.querySelector("#f-q").addEventListener("input", debounce((e) => {
  stanje = { ...stanje, filteri: { ...stanje.filteri, q: e.target.value.trim() } };
  ucitaj();
}, 300));

if (pocetnaGreska) {
    crtaj();
} else {
    ucitaj();
}