from flask import Flask, request, jsonify
from nltk.sentiment import SentimentIntensityAnalyzer
from nltk.corpus import stopwords
from nltk import download
from rake_nltk import Rake
from langdetect import detect
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans
import os
import re
import nltk
import json
import math
from datetime import datetime
from typing import List, Dict
from collections import defaultdict, Counter

STOPWORDS_ID_CHAT = set(stopwords.words('indonesian')) | set(stopwords.words('english'))
# Tambahkan kata-kata yang "lolos" di laporan kamu ke sini
_CHAT_FILLERS = {
    "sih", "dong", "kok", "kan", "tuh", "deh", "lah", "yah", "ni", "tu", 
    "ya", "yak", "yuk", "loh", "masa", "mana", "tapi", "kalo", "kalau", 
    "biar", "buat", "bikin", "bilang", "gak", "ga", "nggak", "enggak", 
    "kagak", "tak", "ndak", "udah", "sudah", "blm", "belum", "pas", 
    "lagi", "lg", "td", "tadi", "km", "kamu", "aku", "saya", "gw", "gue", 
    "lu", "lo", "elu", "kita", "kalian", "mereka", "dia", "ini", "itu", 
    "sini", "situ", "sana", "bgt", "banget", "aja", "saja", "cuma", 
    "doang", "terus", "trs", "jd", "jadi", "karna", "karena", "krn", 
    "bisa", "bs", "mau", "mo", "pengen", "ingin", "ada", "tiada",
    "sama", "dgn", "dengan", "dr", "dari", "ke", "di", "pd", "pada",
    "kapan", "dimana", "siapa", "mengapa", "kenapa", "gimana", "bagaimana",
    "wkwk", "haha", "hehe", "huhu", "anjir", "njir", "anjing",
    # TAMBAHAN DARI KASUS KAMU:
    "apalah", "apa", "aduh", "wah", "nah", "kek", "kayak", "macam",
}
STOPWORDS_ID_CHAT.update(_CHAT_FILLERS)

def ensure_nltk():
    # pastikan paket-paket penting ada
    needed = ["punkt", "punkt_tab", "stopwords", "vader_lexicon"]
    for pkg in needed:
        try:
            # 'punkt' dan 'punkt_tab' = tokenizers; 'stopwords' = corpora
            if pkg in ("punkt", "punkt_tab"):
                nltk.data.find(f"tokenizers/{pkg}")
            else:
                nltk.data.find(f"corpora/{pkg}")
        except LookupError:
            nltk.download(pkg)

ensure_nltk()


# --- bootstrap ---
download('vader_lexicon', quiet=True)
download('stopwords', quiet=True)
sia = SentimentIntensityAnalyzer()

# Lexicon sederhana untuk Indonesia/Kupang dalam range standar [-1, +1]
ID_EXTRA = {
    "capek": -0.7, "capai": -0.5, "pusing": -0.7, "marah": -0.8, "sedih": -0.7,
    "senang": 0.7, "bahagia": 0.8, "semangat": 0.7, "hepi": 0.7,
    "telat": -0.6, "bolos": -0.8, "berantem": -0.9, "ribut": -0.7, "gaduh": -0.6,
    "PR": -0.3, "tugas": -0.2, "banyak": -0.2, "malas": -0.5, "rajin": 0.5,
    # Kupang-style (contoh)
    "sonde": -0.3, "beta": 0.0, "ko": 0.0, "pigi": -0.1, "teda": -0.2,
    "tara": -0.2, "kaco": -0.5, "cungkel": -0.5, "bongkar": -0.2, "kobo": -0.4
}
# tambahkan ke VADER
sia.lexicon.update({k.lower(): v for k, v in ID_EXTRA.items()})

app = Flask(__name__)

API_KEY = os.environ.get("ML_API_KEY")  # optional
FEEDBACK_FILE = os.environ.get("ML_FEEDBACK_FILE", os.path.join(os.path.dirname(__file__), "feedback_weights.json"))
LEXICON_DIR = os.environ.get("ML_LEXICON_DIR", os.path.join(os.path.dirname(__file__), "lexicons"))
ENABLE_BERT = os.environ.get("ML_ENABLE_BERT", "false").lower() in ("1","true","yes")
BERT_MODEL_NAME = os.environ.get("ML_BERT_MODEL", "indobenchmark/indobert-base-p1")
ENABLE_BERT_WARMUP = os.environ.get("ML_BERT_WARMUP", "false").lower() in ("1","true","yes")
SERVICE_VERSION = os.environ.get("ML_VERSION", "ml-rasaya:2025.11.0")

def check_key():
    if API_KEY:
        # accept both header casings/variants for compatibility
        key = request.headers.get("X-API-KEY") or request.headers.get("X-API-Key")
        if key != API_KEY:
            return False
    return True

def detect_lang(txt, hint=None):
    if hint:
        return hint
    try:
        return detect(txt) if txt and txt.strip() else "id"
    except Exception:
        return "id"

def label_from_score(compound: float) -> str:
    if compound >= 0.05: return "positif"
    if compound <= -0.05: return "negatif"
    return "netral"
 
# Legacy default map removed in favor of taxonomy-derived categories

def load_feedback_weights():
    try:
        with open(FEEDBACK_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception:
        return {}

def save_feedback_weights(weights: dict):
    try:
        with open(FEEDBACK_FILE, 'w', encoding='utf-8') as f:
            json.dump(weights, f, ensure_ascii=False, indent=2)
    except Exception:
        pass

def score_categories_for_text(txt: str, categories_map: dict, feedback: dict):
    """Heuristik scoring kategori:
    - Bangun indeks keyword -> list kategori yang mengandungnya.
    - Jika sebuah keyword muncul di teks dan dipakai beberapa kategori, bobot dibagi rata (1 / n_kategori).
    - Terapkan feedback weight per (keyword, kategori) jika ada.
    - Normalisasi ke proporsi total agar skor relatif antar kategori.
    Mengembalikan (scores, reasons) dengan reasons dibatasi 5 unik per kategori.
    """
    text_low = txt.lower()
    # Invert index: keyword -> categories
    inv = defaultdict(list)
    for cat, kws in categories_map.items():
        for kw in kws:
            kw = (kw or '').strip()
            if kw:
                inv[kw.lower()].append(cat)

    scores = {cat: 0.0 for cat in categories_map.keys()}
    reasons = defaultdict(list)

    for kw_low, cats in inv.items():
        if kw_low in text_low:
            base = 1.0 / max(1, len(cats))
            for cat in cats:
                adj = base + float(feedback.get(kw_low, {}).get(cat, 0.0))
                scores[cat] += adj
                reasons[cat].append(kw_low)

    total = sum(scores.values())
    if total > 0:
        for k in scores.keys():
            scores[k] = round(scores[k] / total, 4)
    # unique reasons, keep max 5
    return scores, {k: sorted(set(v))[:5] for k, v in reasons.items()}

"""
Cleaning & Lexicon Loader (InSet + optional Barasa)
"""

_RE_URL = re.compile(r"https?://\S+|www\.\S+", re.I)
_RE_MENTION = re.compile(r"[@#]\w+")
_RE_NON_ALNUM = re.compile(r"[^0-9a-zA-Z\u00C0-\u024F\u1E00-\u1EFF\u0600-\u06FF\u0900-\u097F\u0980-\u09FF\u0A00-\u0AFF\u0B00-\u0B7F\u0C00-\u0C7F\u0D00-\u0D7F\u0E00-\u0E7F ]+")
_RE_MULTISPACE = re.compile(r"\s+")
_RE_REPEAT = re.compile(r"(.)\1+")

def _norm_negasi(t: str) -> str:
    repl = {
        "gak": "tidak", "ga": "tidak", "gk": "tidak", "nggak": "tidak",
        "ngga": "tidak", "engga": "tidak", "enggak": "tidak", "kagak": "tidak",
        "ndak": "tidak", "tak": "tidak"
    }
    for k, v in repl.items():
        t = re.sub(rf"\b{k}\b", v, t)
    return t

def clean_text(t: str) -> str:
    t = (t or "").strip().lower()
    t = _RE_URL.sub(" ", t)
    t = _RE_MENTION.sub(" ", t)
    t = _RE_NON_ALNUM.sub(" ", t)
    t = _RE_REPEAT.sub(r"\1", t)
    t = _norm_negasi(t)
    t = _RE_MULTISPACE.sub(" ", t).strip()
    # Dialect normalization (Kupang/common variants)
    dialect = {
        "pung": "punya",
        "puny": "punya",
        "beta": "saya",
        "b": "saya",
        "sy": "saya",
        "aku": "saya",
        "deng": "dengan",
        "dng": "dengan",
        "sm": "sama",
        "ko": "kamu",
        "kau": "kamu",
    }
    toks = []
    for tk in t.split():
        toks.append(dialect.get(tk, tk))
    t = " ".join(toks)
    return t


def load_inset_lexicon(base_dir: str) -> Dict[str, float]:
    """Load InSet format: lexicons/inset/{positive.tsv,negative.tsv}."""
    out: Dict[str, float] = {}
    inset_dir = os.path.join(base_dir, "inset")
    pos = os.path.join(inset_dir, "positive.tsv")
    neg = os.path.join(inset_dir, "negative.tsv")
    if os.path.exists(pos):
        with open(pos, "r", encoding="utf-8") as f:
            for line in f:
                w = line.strip().split("\t")[0]
                if w:
                    out[w.lower()] = 1.0
    if os.path.exists(neg):
        with open(neg, "r", encoding="utf-8") as f:
            for line in f:
                w = line.strip().split("\t")[0]
                if w:
                    out[w.lower()] = -1.0
    return out


def load_barasa_csv(path: str) -> Dict[str, float]:
    """Load Barasa CSV with headers; expects at least a 'lemma' column and
    either a 'score' column (float, negative to positive) or separate
    'pos'/'neg' columns that can be combined (score = pos - neg).
    Values are clamped to [-1, 1].
    """
    lex: Dict[str, float] = {}
    try:
        import csv
        with open(path, encoding="utf-8") as f:
            r = csv.DictReader(f)
            for row in r:
                lemma = (row.get("lemma") or row.get("word") or row.get("token") or "").strip().lower()
                if not lemma:
                    continue
                score_val = None
                # Prefer unified score
                if row.get("score") not in (None, ""):
                    try:
                        score_val = float(row.get("score"))
                    except Exception:
                        score_val = None
                # Else try pos/neg columns
                if score_val is None:
                    try:
                        pos = float(row.get("pos") or row.get("positive") or 0)
                        neg = float(row.get("neg") or row.get("negative") or 0)
                        score_val = pos - neg
                    except Exception:
                        score_val = 0.0
                score_val = max(-1.0, min(1.0, float(score_val)))
                lex[lemma] = score_val
    except Exception:
        pass
    return lex


def load_barasa_optional(base_dir: str) -> Dict[str, float]:
    """
    Try to read Barasa resources if available. The provided file wn-msa-all.tab
    is a WordNet-style tab file (no explicit polarity). We don't assign scores
    from it directly; instead we just return empty dict so it doesn't affect
    sentiment unless in the future we add mapping rules.
    If you later provide barasa.csv (word,score), we can extend this loader.
    """
    barasa_dir = os.path.join(base_dir, "barasa")
    wn_file = os.path.join(barasa_dir, "wn-msa-all.tab")
    # Placeholder: no direct sentiment; return empty for now.
    # Future: map synonyms of existing sentiment words and inherit score * 0.8
    if os.path.exists(wn_file):
        return {}
    # also support barasa.csv if added by user
    csv_file = os.path.join(base_dir, "barasa.csv")
    if os.path.exists(csv_file):
        out: Dict[str, float] = {}
        with open(csv_file, "r", encoding="utf-8") as f:
            for line in f:
                if "," in line:
                    w, sc = line.strip().split(",", 1)
                    try:
                        out[w.lower()] = max(-1.0, min(1.0, float(sc)))
                    except Exception:
                        continue
        return out
    return {}


def build_lexicon() -> Dict[str, float]:
    # Start from InSet if available
    lex = load_inset_lexicon(LEXICON_DIR)
    # Merge Barasa if CSV provided; else try optional WordNet source (no polarity)
    barasa_csv = os.path.join(LEXICON_DIR, "barasa", "barasa_lexicon.csv")
    if os.path.exists(barasa_csv):
        lex.update(load_barasa_csv(barasa_csv))
    else:
        bar = load_barasa_optional(LEXICON_DIR)
        lex.update(bar)
    # Add custom Kupang/ID extra (sudah dalam range [-1, +1])
    for k, v in ID_EXTRA.items():
        lex[k.lower()] = max(-1.0, min(1.0, float(v)))
    return lex


LEXICON_ID = build_lexicon()


def score_with_lexicon(text: str, lex: Dict[str, float]) -> float:
    toks = clean_text(text).split()
    if not toks:
        return 0.0
    s = sum(lex.get(t, 0.0) for t in toks)
    # dampen by sqrt length
    return max(-1.0, min(1.0, s / max(1.0, math.sqrt(len(toks)))))

INTENSIFIERS = {"banget": 1.0, "sangat": 0.8, "parah": 0.9, "amat": 0.5}

def negative_gate(aggregate: float, raw_txt: str) -> tuple[bool, float]:
    # severity from magnitude + intensifiers + punctuation and repeats
    clean = clean_text(raw_txt)
    toks = clean.split()
    intens = sum(INTENSIFIERS.get(t, 0.0) for t in toks)
    exclam = min(raw_txt.count("!"), 3) * 0.1
    repeat = 0.1 if _RE_REPEAT.search(raw_txt) else 0.0
    sev = max(0.0, min(1.0, (-aggregate) * 0.7 + intens * 0.2 + exclam + repeat))
    return (aggregate <= -0.05), round(sev, 3)

# =====================
# Taxonomy (topics/subtopics) for semi-supervised labeling
# =====================
TAXONOMY_PATH = os.path.join(os.path.dirname(__file__), "taxonomy.json")
try:
    with open(TAXONOMY_PATH, "r", encoding="utf-8") as _f:
        _TAX = json.load(_f)
except Exception:
    _TAX = {"topics": []}

def _taxonomy_keywords():
    buckets = {}
    subtopics = {}
    for tp in _TAX.get("topics", []):
        bucket = tp.get("bucket") or ""
        topic_id = tp.get("id") or bucket or "TOPIC"
        topic_name = tp.get("name") or topic_id
        buckets.setdefault(bucket, set()).update([str(w).lower() for w in tp.get("keywords", []) if w])
        for st in tp.get("subtopics", []) or []:
            # Maintain internal id (taxonomy id) and external 'code' matching kategori_masalahs.kode
            st_id = st.get("id") or st.get("code") or st.get("name")
            st_code = st.get("code") or st_id
            if not st_id:
                continue
            subtopics[st_id] = {
                "name": st.get("name") or st_id,
                "bucket": bucket,
                "topic_id": topic_id,
                "topic_name": topic_name,
                "code": st_code,
                "keywords": set([str(w).lower() for w in st.get("keywords", []) if w]),
                "examples": st.get("examples", []) or []
            }
    return buckets, subtopics

BUCKET_KW, SUBTOPICS = _taxonomy_keywords()

def build_topic_index_and_categories_map():
    """Builds an index of taxonomy topics and a categories_map for quick keyword scoring.
    Returns (topic_index, categories_map) where topic_index keys are UPPER(topic_name).
    categories_map has the same keys mapping to a flat list of keywords aggregated from
    topic-level keywords and all of its subtopics' keywords.
    """
    topic_index = {}
    categories_map = {}
    for tp in _TAX.get("topics", []):
        topic_id = tp.get("id") or "TOPIC"
        topic_name = tp.get("name") or topic_id
        bucket = tp.get("bucket") or ""
        key = str(topic_name).upper()
        kw = set([str(w).lower() for w in (tp.get("keywords") or []) if w])
        for st in tp.get("subtopics", []) or []:
            for w in st.get("keywords", []) or []:
                if w:
                    kw.add(str(w).lower())
        topic_index[key] = {"id": topic_id, "name": topic_name, "bucket": bucket}
        categories_map[key] = sorted(list(kw))
    return topic_index, categories_map

def extract_keyphrases(texts, lang="id"):
    # RAKE pakai stopwords bhs Inggris default; untuk id sederhana kita kasih stopwords id juga
    sw = set(stopwords.words('indonesian')) | set(stopwords.words('english'))
    r = Rake(stopwords=sw)
    joined = " . ".join(texts)
    r.extract_keywords_from_text(joined)
    ranked = r.get_ranked_phrases_with_scores()
    out = []
    for score, phrase in ranked[:20]:
        out.append({"term": phrase, "weight": float(score)})
    return out

def extract_core_tokens(texts):
    """Ambil token inti dengan pembersihan:
    - lower & clean_text
    - buang stopwords (ID + EN) & filler umum
    - buang token panjang < 3
    - hitung frekuensi, ambil top 10
    """
    freq = Counter()
    try:
        sw_id = set(stopwords.words('indonesian'))
    except Exception:
        sw_id = set()
    try:
        sw_en = set(stopwords.words('english'))
    except Exception:
        sw_en = set()
    filler = {
        'dan','atau','yang','di','ke','dengan','pada','untuk','dari','lagi','sih','deh','lah','ya','kok','kan','udah','aja','pun','itu','ini','jadi','kalau','kalo','bahwa','sementara','sering','kayak','kayakny','nih','tuh','dong','de','si','mungkin','masih','bisa','harus','karena','seperti','kaya','gitu','buat'
    }
    for t in texts:
        for tok in clean_text(t).split():
            if len(tok) < 3: continue
            if tok in sw_id or tok in sw_en or tok in filler: continue
            freq[tok] += 1
    return [w for w,_ in freq.most_common(10)]

def _build_cluster_vectorizer():
    """Vectorizer for clustering top-terms: single-word tokens, heavy stopwords cleanup."""
    try:
        sw_id = set(stopwords.words('indonesian'))
    except Exception:
        sw_id = set()
    try:
        sw_en = set(stopwords.words('english'))
    except Exception:
        sw_en = set()
    extra = {
        # connectors/intensifiers/pronouns/common fillers
        'dan','atau','yang','di','ke','dengan','pada','untuk','dari','lagi','banget','sekali','paling','sih','deh','dong','lah','ya',
        'aku','saya','gue','gua','dia','kamu','kau','ko','kami','kita','mereka',
        'punya','dengar','dng','sm','nih','tuh','kok','kan','udah','lagi','aja','de','si',
    }
    stopset = sw_id | sw_en | extra
    # Use our cleaner as preprocessor; single-word tokens only
    vec = TfidfVectorizer(
        preprocessor=clean_text,
        tokenizer=str.split,
        token_pattern=None,
        lowercase=True,
        stop_words=list(stopset),
        ngram_range=(1,1),
        max_df=0.95,
        min_df=1,
        max_features=1000,
    )
    return vec

@app.get("/health")
def health():
    return jsonify({"status": "ok", "version": SERVICE_VERSION, "bert": ENABLE_BERT})

# =====================
# IndoBERT caching & optional warmup
# =====================
BERT_CACHE = {"tok": None, "mdl": None, "device": "cpu"}

def get_bert():
    if not ENABLE_BERT:
        return None, None, None
    try:
        from transformers import AutoTokenizer, AutoModel  # type: ignore
        import torch  # type: ignore
        dev = "cuda" if torch.cuda.is_available() else "cpu"
        if BERT_CACHE["tok"] is None:
            BERT_CACHE["tok"] = AutoTokenizer.from_pretrained(BERT_MODEL_NAME)
            BERT_CACHE["mdl"] = AutoModel.from_pretrained(BERT_MODEL_NAME).to(dev).eval()
            BERT_CACHE["device"] = dev
        return BERT_CACHE["tok"], BERT_CACHE["mdl"], BERT_CACHE["device"]
    except Exception:
        return None, None, None

# Warmup at startup if requested (download/load once)
if ENABLE_BERT and ENABLE_BERT_WARMUP:
    tok, mdl, dev = get_bert()
    try:
        if tok is not None and mdl is not None:
            import torch  # type: ignore
            with torch.no_grad():
                enc = tok(["warmup"], padding=True, truncation=True, max_length=16, return_tensors="pt")
                _ = mdl(**enc.to(dev))
    except Exception:
        pass

@app.get("/warmup")
def warmup():
    """Optionally trigger BERT load and a tiny forward pass to avoid first-request latency."""
    if not ENABLE_BERT:
        return jsonify({"bert": "disabled"})
    tok, mdl, dev = get_bert()
    if tok is None or mdl is None:
        return jsonify({"bert": "unavailable"}), 500
    try:
        import torch  # type: ignore
        with torch.no_grad():
            enc = tok(["warmup"], padding=True, truncation=True, max_length=16, return_tensors="pt")
            _ = mdl(**enc.to(dev))
        return jsonify({"bert": "ready", "device": dev})
    except Exception as e:
        return jsonify({"bert": "error", "message": str(e)}), 500

@app.post("/analyze")
def analyze():
    if not check_key():
        return jsonify({"error": "unauthorized"}), 401

    data = request.get_json(force=True) or {}
    # Support single or batch
    items = data.get("items")
    if items is None:
        items = [{
            "id": data.get("id") or "item-1",
            "text": data.get("text") or "",
            "lang_hint": (data.get("context") or {}).get("lang_hint") if isinstance(data.get("context"), dict) else None
        }]
    categories_override = data.get("categories")  # optional: { name: [keywords] }
    # Build taxonomy-driven default categories (topics) and index
    TOPIC_INDEX, TAXONOMY_CATEGORIES = build_topic_index_and_categories_map()
    categories_map = {}
    if isinstance(categories_override, dict) and categories_override:
        # sanitize override
        for k, v in categories_override.items():
            if isinstance(v, list):
                categories_map[str(k).upper()] = [str(x) for x in v if isinstance(x, (str, int))]
    if not categories_map:
        # fall back to taxonomy topics aggregated keywords
        categories_map = TAXONOMY_CATEGORIES

    feedback = load_feedback_weights()
    if not isinstance(items, list) or not items:
        return jsonify({"error": "items required"}), 422

    # New detailed results per item
    results = []
    # Legacy structures we keep for compatibility
    per_legacy = []
    all_texts = []
    negatives = []
    per_entry_cats = {}

    for it in items:
        _id = it.get("id") or ""
        raw_txt = (it.get("text") or "").strip()
        clean = clean_text(raw_txt)
        lang_hint = it.get("lang_hint")
        if not clean:
            continue

        # lang detect simple
        lang = detect_lang(raw_txt, hint=lang_hint)

        # sentiment hybrid: Indonesian lexicon + VADER (raw for emoticons)
        s_lex = score_with_lexicon(clean, LEXICON_ID)
        s_vad = sia.polarity_scores(raw_txt).get("compound", 0.0)
        aggregate = float(0.7 * s_lex + 0.3 * s_vad)
        lbl = label_from_score(aggregate)

        neg_flag, severity = negative_gate(aggregate, raw_txt)

        # keyword category scoring (quick)
        cat_scores, reasons = score_categories_for_text(clean, categories_map, feedback)
        best_cat = max(cat_scores, key=cat_scores.get) if cat_scores else None

        cluster = None
        if best_cat:
            # Prefer mapping to taxonomy TOPIC if available
            tp_meta = TOPIC_INDEX.get(str(best_cat).upper())
            if tp_meta:
                cluster = {
                    "id": tp_meta.get("id"),
                    "label": tp_meta.get("name"),
                    "bucket": tp_meta.get("bucket"),
                    "topic_id": tp_meta.get("id"),
                    "topic_name": tp_meta.get("name"),
                    "confidence": 0.5
                }
            else:
                # Fallback to raw category name
                cluster = {"id": best_cat, "label": best_cat, "bucket": str(best_cat).upper(), "confidence": 0.4}

        # Keyphrases (RAKE) per-entry + normalization & dedup
        try:
            rk = Rake(stopwords=STOPWORDS_ID_CHAT, min_length=1, max_length=3)
            rk.extract_keywords_from_text(raw_txt)
            raw_phrases = [p.lower() for p in rk.get_ranked_phrases()[:8]]
        except Exception:
            raw_phrases = []
        dialect_map = {
            "pung": "punya", "puny": "punya", "beta": "saya", "b": "saya", "sy": "saya", "aku": "saya",
            "deng": "dengan", "dng": "dengan", "sm": "sama", "ko": "kamu", "kau": "kamu"
        }
        norm_set = []
        seen = set()
        for phr in raw_phrases:
            toks = [dialect_map.get(t, t) for t in phr.split()]
            norm = " ".join(toks).strip()
            if not norm or norm in seen:
                continue
            seen.add(norm)
            norm_set.append(norm)
        phrases = norm_set[:5]

        # Simple rule-based summary per entry
        if cluster:
            summary = f"Masalah utama: {cluster['label']}. Gejala: {', '.join(phrases[:3])}."
        else:
            summary = f"Inti keluhan: {', '.join(phrases[:3])}."

        results.append({
            "id": _id,
            "clean_text": clean,
            "lang": lang,
            "sentiment": {
                "barasa": s_lex,  # merged Indo lexicon score
                "kupang": 0.0,    # placeholder if you split later
                "english": s_vad, # VADER compound
                "aggregate": aggregate,
                "label": lbl
            },
            "negative_flag": neg_flag,
            "severity": severity,
            "cluster": cluster,
            "summary": summary,
            "key_phrases": phrases,
            "recommendations": [],  # filled later
            "cat_scores": cat_scores,
            "cat_reasons": reasons,
        })

        # legacy aggregation inputs
        per_legacy.append({
            "id": _id,
            "text": raw_txt,
            "lang": lang,
            "sentiment": aggregate,
            "label": lbl,
            "keywords": phrases
        })
        all_texts.append(clean)
        if aggregate <= -0.05:
            negatives.append(clean)
            ranked = sorted([(c, s) for c, s in cat_scores.items() if s > 0], key=lambda x: x[1], reverse=True)
            per_entry_cats[_id] = {
                "ranked": ranked[:3],
                "reasons": {c: reasons.get(c, []) for c, _ in ranked[:3]}
            }

    # global keyphrases + normalization & dedup
    keyphrases = extract_keyphrases(all_texts) if all_texts else []
    if keyphrases:
        dmap = {"pung": "punya", "puny": "punya", "beta": "saya", "b": "saya", "sy": "saya", "aku": "saya", "deng": "dengan", "dng": "dengan", "sm": "sama", "ko": "kamu", "kau": "kamu"}
        agg = {}
        for kp in keyphrases:
            term = kp.get("term", "").lower()
            toks = [dmap.get(t, t) for t in term.split()]
            norm = " ".join(toks).strip()
            if not norm:
                continue
            agg[norm] = max(float(kp.get("weight", 0.0)), agg.get(norm, 0.0))
        keyphrases = [{"term": k, "weight": v} for k, v in sorted(agg.items(), key=lambda x: x[1], reverse=True)[:30]]
        # Inject high-frequency tokens not already present (captures 'telat' dsb)
        freq = Counter()
        try:
            sw_id = set(stopwords.words('indonesian'))
        except Exception:
            sw_id = set()
        for t in all_texts:
            for tok in clean_text(t).split():
                if len(tok) >= 4 and tok not in sw_id:
                    freq[tok] += 1
        top_tokens = [w for w, _ in freq.most_common(20)]
        existing = {kp["term"] for kp in keyphrases}
        for w in top_tokens:
            if w not in existing:
                keyphrases.append({"term": w, "weight": float(freq[w])})
        keyphrases = sorted(keyphrases, key=lambda x: x["weight"], reverse=True)[:30]

    # clustering untuk yang negatif
    clusters = []
    if len(negatives) >= 2:
        used_engine = "tfidf"
        try:
            X = None
            if ENABLE_BERT:
                tok, mdl, dev = get_bert()
                if tok is not None and mdl is not None:
                    import torch
                    with torch.no_grad():
                        enc = tok(negatives, padding=True, truncation=True, max_length=160, return_tensors="pt").to(dev)
                        out = mdl(**enc)
                        cls = out.last_hidden_state[:, 0, :]
                        X = cls.detach().cpu().numpy()
                        used_engine = "bert"
            if X is None:
                # fallback TF-IDF with unigram-only and heavy stopwords for clean top terms
                vec = _build_cluster_vectorizer()
                X = vec.fit_transform(negatives)
            k = 2 if len(negatives) == 2 else min(4, max(2, len(negatives)//2))
            km = KMeans(n_clusters=k, n_init='auto', random_state=42)
            y = km.fit_predict(X)
            # kumpulkan contoh teks per cluster + representative terms if TF-IDF
            rep_terms = []
            if 'vec' in locals():
                terms = vec.get_feature_names_out()
                try:
                    centroids = km.cluster_centers_
                    for ci in range(k):
                        top_idx = centroids[ci].argsort()[-8:][::-1]
                        rep_terms.append([terms[j] for j in top_idx])
                except Exception:
                    rep_terms = [[] for _ in range(k)]
            else:
                rep_terms = [[] for _ in range(k)]
            for ci in range(k):
                ex = [negatives[i] for i in range(len(negatives)) if y[i] == ci][:5]
                clusters.append({
                    "cluster": int(ci),
                    "engine": used_engine,
                    "top_terms": rep_terms[ci] if ci < len(rep_terms) else [],
                    "examples": ex
                })
        except Exception:
            # Hard fallback to pure TF-IDF if BERT failed
            vec = _build_cluster_vectorizer()
            X = vec.fit_transform(negatives)
            k = 2 if len(negatives) == 2 else min(4, max(2, len(negatives)//2))
            km = KMeans(n_clusters=k, n_init='auto', random_state=42)
            y = km.fit_predict(X)
            terms = vec.get_feature_names_out()
            centroids = km.cluster_centers_
            for ci in range(k):
                top_idx = centroids[ci].argsort()[-8:][::-1]
                top_terms = [terms[j] for j in top_idx]
                ex = [negatives[i] for i in range(len(negatives)) if y[i] == ci][:5]
                clusters.append({
                    "cluster": int(ci),
                    "engine": "tfidf",
                    "top_terms": top_terms,
                    "examples": ex
                })

    # aggregate category overview (from negative entries only)
    # Severity-weighted aggregation of ALL category scores for negative entries
    cat_counter = Counter()
    
    for r in results:
        # Ambil severity, minimal 0.5 agar input netral tetap dihitung
        sev = r.get("severity", 0.0)
        weight = 0.5 # Bobot dasar untuk input netral/positif
        
        if r.get("negative_flag"):
            # Jika negatif, bobotnya lebih besar (severity + 1.0)
            weight = 1.0 + sev
            
        for cat, sc in (r.get("cat_scores") or {}).items():
            if sc > 0:
                cat_counter[cat] += sc * weight

    categories_overview = [
        {"category": cat, "score": round(val, 4)} for cat, val in cat_counter.most_common()
    ]

    avg = sum([x["sentiment"] for x in per_legacy]) / len(per_legacy) if per_legacy else 0.0
    summary = {
        "avg_sentiment": round(avg, 3),
        "negative_ratio": round(sum(1 for x in per_legacy if x["label"]=="negatif")/len(per_legacy), 3) if per_legacy else 0.0,
        "notes": "Rangkuman kasar berdasar skor rata-rata & proporsi negatif."
    }

    # Core tokens & auto summary (lebih fokus kata inti bersih)
    core_tokens = extract_core_tokens(all_texts) if all_texts else []
    top_cat_names = [c["category"].title() for c in categories_overview[:2]]
    neg_ratio_pct = f"{summary['negative_ratio']*100:.1f}%" if per_legacy else "0%"
    ct_display = ", ".join(core_tokens[:5]) if core_tokens else "(tidak ada kata inti mencolok)"
    auto_summary = (
        f"Secara umum curhatan bernada {'negatif' if avg < -0.05 else ('positif' if avg > 0.05 else 'netral')} "
        f"(skor {summary['avg_sentiment']}). Proporsi negatif {neg_ratio_pct}. "
        f"Topik dominan: {', '.join(top_cat_names) if top_cat_names else '-'}. "
        f"Kata inti: {ct_display}."
    )

    # Optional semi-supervised subtopic assignment via BERT centroids
    if ENABLE_BERT and results:
        # build centroids from taxonomy examples
        st_ids, st_texts = [], []
        for st_id, meta in SUBTOPICS.items():
            ex = meta.get("examples") or list(meta.get("keywords", []))
            ex = [e for e in ex if isinstance(e, str) and e.strip()]
            if not ex:
                continue
            st_ids.extend([st_id] * len(ex))
            st_texts.extend(ex)
        try:
            import numpy as np
            tok, mdl, dev = get_bert()
            if tok is not None and mdl is not None and st_texts:
                # embed examples
                import torch
                with torch.no_grad():
                    enc = tok(st_texts, padding=True, truncation=True, max_length=160, return_tensors="pt").to(dev)
                    out = mdl(**enc)
                    ex_cls = out.last_hidden_state[:, 0, :].detach().cpu().numpy()
                by = {}
                for sid, vec in zip(st_ids, ex_cls):
                    by.setdefault(sid, []).append(vec)
                centroids = {sid: np.vstack(arr).mean(axis=0) for sid, arr in by.items()}
                # embed inputs and assign
                texts = [r["clean_text"] for r in results]
                with torch.no_grad():
                    enc2 = tok(texts, padding=True, truncation=True, max_length=160, return_tensors="pt").to(dev)
                    out2 = mdl(**enc2)
                    in_cls = out2.last_hidden_state[:, 0, :].detach().cpu().numpy()
                for i, vec in enumerate(in_cls):
                    best_sid, best_sim = None, -1.0
                    for sid, cvec in centroids.items():
                        num = float((vec * cvec).sum())
                        den = float((vec**2).sum())**0.5 * float((cvec**2).sum())**0.5 + 1e-8
                        sim = num / den
                        if sim > best_sim:
                            best_sid, best_sim = sid, sim
                    if best_sid:
                        meta = SUBTOPICS.get(best_sid)
                        if meta:
                            prev_conf = (results[i].get("cluster") or {}).get("confidence", 0.0)
                            results[i]["cluster"] = {
                                "id": best_sid,
                                "subtopic_code": meta.get("code"),
                                "label": meta["name"],
                                "bucket": meta["bucket"],
                                "topic_id": meta.get("topic_id"),
                                "topic_name": meta.get("topic_name"),
                                "confidence": round(max(prev_conf, float(best_sim)), 3)
                            }
        except Exception:
            pass

    # Rule-based recommendations based on bucket & severity
    def recommend_rules(bucket: str, severity_val: float, negative: bool):
        recs = []
        if not bucket:
            return recs
        if negative and severity_val >= 0.7:
            recs.append({"tag": "KONSELING_INDIVIDU", "title": "Sesi konseling individu", "priority": 1, "rationale": "Severity tinggi"})
        if bucket in ("SOSIAL", "DISIPLIN") and negative:
            recs.append({"tag": "MEDIASI_WALI", "title": "Mediasi dengan wali kelas/pihak terkait", "priority": 2, "rationale": f"Kategori {bucket}"})
        if bucket == "AKADEMIK":
            recs.append({"tag": "RAPAT_JADWAL_TUGAS", "title": "Penataan jadwal/tugas", "priority": 3, "rationale": "Kendala akademik"})
        if bucket == "EMOSI":
            recs.append({"tag": "PSYCHOEDU_EMOSI", "title": "Psychoeducation regulasi emosi", "priority": 3, "rationale": "Keluhan emosi"})
        if bucket == "DISIPLIN":
            recs.append({"tag": "PEMBINAAN_DISIPLIN", "title": "Pembinaan disiplin terarah", "priority": 3, "rationale": "Pelanggaran berulang / tata tertib"})
        return recs

    for r in results:
        bucket = (r.get("cluster") or {}).get("bucket")
        r["recommendations"] = recommend_rules(bucket, r.get("severity") or 0.0, r.get("negative_flag") or False)

   # Global recommendations logic
    abs_sent = abs(avg)
    global_recommendations = []
    
    # REVISI FINAL: Hapus logic "top_score * 0.3". 
    # Ambil SEMUA kategori yang skornya minimal 5% (0.05).
    # Biarkan Laravel yang mengatur urutannya.
    valid_categories = [
        c for c in categories_overview 
        if c["score"] >= 0.05 
    ]

    for cat in valid_categories:
        cname = cat["category"]
        score = cat["score"]
        meta = TOPIC_INDEX.get(cname.upper()) or {}
        bucket = meta.get("bucket", "")
        diff = abs(abs_sent - score)
        recs = recommend_rules(bucket, max(0.3, abs_sent), avg < -0.05)
        if recs:
            global_recommendations.append({
                "category": cname,
                "bucket": bucket,
                "score": score,
                "sentiment_abs": round(abs_sent,3),
                "diff_vs_sentiment": round(diff,3),
                "recommendations": recs
            })
    global_recommendations.sort(key=lambda x: (-x["score"], x["diff_vs_sentiment"]))

    return jsonify({
        "version": SERVICE_VERSION,
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "items": results,
        # legacy outputs for compatibility with earlier UI/bridge
        "per_entry": per_legacy,
        "summary": summary,
        "auto_summary": auto_summary,
        "keyphrases": keyphrases,
        "clusters": clusters,
        "per_entry_categories": per_entry_cats,
        "categories_overview": categories_overview,
        "core_tokens": core_tokens,
        "global_recommendations": global_recommendations,
    })

@app.post("/feedback")
def feedback():
    if not check_key():
        return jsonify({"error": "unauthorized"}), 401

    data = request.get_json(force=True) or {}
    # expected: { keywords: ["telat","bolos"], from_category?: "AKADEMIK", to_category?: "DISIPLIN", delta?: 0.2 }
    kws = data.get("keywords") or []
    from_cat = str(data.get("from_category") or "").upper()
    to_cat = str(data.get("to_category") or "").upper()
    delta = float(data.get("delta") or 0.2)
    if not kws or (not from_cat and not to_cat):
        return jsonify({"error": "invalid payload"}), 422

    weights = load_feedback_weights()
    for kw in kws:
        k = str(kw).lower().strip()
        if not k:
            continue
        entry = weights.get(k, {})
        # penalize from_cat slightly, reward to_cat (if provided)
        if from_cat:
            entry[from_cat] = float(entry.get(from_cat, 0.0)) - (delta / 2.0)
        if to_cat:
            entry[to_cat] = float(entry.get(to_cat, 0.0)) + delta
        weights[k] = entry
    save_feedback_weights(weights)
    return jsonify({"ok": True, "updated": len(kws)})

if __name__ == "__main__":
    # LOCAL MODE: port 5001 biar gampang
    app.run(host="0.0.0.0", port=5001, debug=True)
