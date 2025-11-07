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

# Tambahkan lexicon sederhana untuk Indonesia/Kupang (bisa kamu kembangkan)
ID_EXTRA = {
    "capek": -2.0, "capai": -1.5, "pusing": -2.0, "marah": -2.4, "sedih": -2.2,
    "senang": 2.2, "bahagia": 2.4, "semangat": 2.0, "hepi": 2.0,
    "telat": -1.8, "bolos": -2.3, "berantem": -2.5, "ribut": -2.0, "gaduh": -1.8,
    "PR": -0.4, "tugas": -0.3, "banyak": -0.2, "malas": -1.5, "rajin": 1.6,
    # Kupang-style (contoh)
    "sonde": -0.3, "beta": 0.0, "ko": 0.0, "pigi": -0.1, "teda": -0.2,
    "tara": -0.2, "kaco": -1.0, "cungkel": -1.0, "bongkar": -0.2, "kobo": -0.8
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

# Default category keyword map (can be overridden by request payload)
DEFAULT_CATEGORIES = {
    "AKADEMIK": ["tugas","ujian","nilai","pr","belajar","deadline","remedial","bimbel","kelas","mapel","catatan","jadwal"],
    "EMOSI": ["sedih","cemas","stres","marah","bahagia","semangat","mindfulness","psikolog","tidur","lelah","gelisah","murung"],
    "SOSIAL": ["teman","berantem","cekcok","konflik","bully","perundungan","orang tua","keluarga","komunikasi","mediasi","inklusif"],
    "DISIPLIN": ["telat","bolos","alpha","aturan","hadir","absen","sanksi","gadget","ketertiban","izin","terlambat"],
}

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
    # Very lightweight heuristic scoring using keyword hits + feedback weights
    text_low = txt.lower()
    scores = {cat: 0.0 for cat in categories_map.keys()}
    reasons = defaultdict(list)

    # keyword matching
    for cat, kws in categories_map.items():
        for kw in kws:
            if not kw:
                continue
            kw_low = kw.lower()
            if kw_low in text_low:
                base = 1.0
                # apply feedback weight if exists
                base += float(feedback.get(kw_low, {}).get(cat, 0.0))
                scores[cat] += base
                reasons[cat].append(kw_low)

    # Normalize
    total = sum(scores.values())
    if total > 0:
        for k in list(scores.keys()):
            scores[k] = round(scores[k] / total, 4)
    return scores, {k: sorted(set(v))[:5] for k, v in reasons.items()}

"""
Cleaning & Lexicon Loader (InSet + optional Barasa)
"""

_RE_URL = re.compile(r"https?://\S+|www\.\S+", re.I)
_RE_MENTION = re.compile(r"[@#]\w+")
_RE_NON_ALNUM = re.compile(r"[^0-9a-zA-Z\u00C0-\u024F\u1E00-\u1EFF\u0600-\u06FF\u0900-\u097F\u0980-\u09FF\u0A00-\u0AFF\u0B00-\u0B7F\u0C00-\u0C7F\u0D00-\u0D7F\u0E00-\u0E7F ]+")
_RE_MULTISPACE = re.compile(r"\s+")
_RE_REPEAT = re.compile(r"(.)\1{2,}")  # aaa -> aa

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
    t = _RE_REPEAT.sub(r"\1\1", t)
    t = _norm_negasi(t)
    t = _RE_MULTISPACE.sub(" ", t).strip()
    # Dialect normalization (Kupang/common variants)
    dialect = {
        "pung": "punya",
        "puny": "punya",
        "beta": "saya",
        "b": "saya",
        "sy": "saya",
        "aku": "saya",  # unify first person
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
    # Add custom Kupang/ID extra (normalize roughly to [-1,1])
    for k, v in ID_EXTRA.items():
        lex[k.lower()] = max(-1.0, min(1.0, float(v) / 3.0))
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
    categories_map = {}
    if isinstance(categories_override, dict) and categories_override:
        # sanitize override
        for k, v in categories_override.items():
            if isinstance(v, list):
                categories_map[str(k).upper()] = [str(x) for x in v if isinstance(x, (str, int))]
    if not categories_map:
        categories_map = DEFAULT_CATEGORIES

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
            # Try map best_cat to taxonomy subtopic if exists, else use category as bucket
            st_meta = SUBTOPICS.get(best_cat)
            if st_meta:
                cluster = {
                    "id": best_cat,              # taxonomy subtopic id
                    "subtopic_code": st_meta.get("code"),  # matches kategori_masalahs.kode
                    "label": st_meta["name"],
                    "bucket": st_meta["bucket"],
                    "topic_id": st_meta.get("topic_id"),
                    "topic_name": st_meta.get("topic_name"),
                    "confidence": 0.5
                }
            else:
                cluster = {"id": best_cat, "label": best_cat, "bucket": best_cat, "confidence": 0.4}

        # Keyphrases (RAKE) per-entry + normalization & dedup
        try:
            rk = Rake()
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
            "recommendations": []  # filled later
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
                # fallback TF-IDF
                vec = TfidfVectorizer(max_features=500, ngram_range=(1, 2))
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
            vec = TfidfVectorizer(max_features=500, ngram_range=(1, 2))
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
    cat_counter = Counter()
    for _id, info in per_entry_cats.items():
        if info.get("ranked"):
            # weight by score of top-1
            top_cat, top_score = info["ranked"][0]
            cat_counter[top_cat] += top_score
    categories_overview = [
        {"category": cat, "score": round(score, 4)} for cat, score in cat_counter.most_common()
    ]

    avg = sum([x["sentiment"] for x in per_legacy]) / len(per_legacy) if per_legacy else 0.0
    summary = {
        "avg_sentiment": round(avg, 3),
        "negative_ratio": round(sum(1 for x in per_legacy if x["label"]=="negatif")/len(per_legacy), 3) if per_legacy else 0.0,
        "notes": "Rangkuman kasar berdasar skor rata-rata & proporsi negatif."
    }

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
        return recs

    for r in results:
        bucket = (r.get("cluster") or {}).get("bucket")
        r["recommendations"] = recommend_rules(bucket, r.get("severity") or 0.0, r.get("negative_flag") or False)

    return jsonify({
        "version": SERVICE_VERSION,
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "items": results,
        # legacy outputs for compatibility with earlier UI/bridge
        "per_entry": per_legacy,
        "summary": summary,
        "keyphrases": keyphrases,
        "clusters": clusters,
        "per_entry_categories": per_entry_cats,
        "categories_overview": categories_overview,
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
