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
from typing import List, Dict
from collections import defaultdict, Counter

def ensure_nltk():
    # pastikan paket-paket penting ada
    needed = ["punkt", "punkt_tab", "stopwords"]
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
FEEDBACK_FILE = os.environ.get("ML_FEEDBACK_FILE", "feedback_weights.json")
LEXICON_DIR = os.environ.get("ML_LEXICON_DIR", os.path.join(os.path.dirname(__file__), "lexicons"))
ENABLE_BERT = os.environ.get("ML_ENABLE_BERT", "false").lower() in ("1","true","yes")
BERT_MODEL_NAME = os.environ.get("ML_BERT_MODEL", "indobenchmark/indobert-base-p1")
ENABLE_BERT_WARMUP = os.environ.get("ML_BERT_WARMUP", "false").lower() in ("1","true","yes")

def check_key():
    if API_KEY:
        key = request.headers.get("X-API-Key")
        if key != API_KEY:
            return False
    return True

def detect_lang(txt, hint=None):
    if hint: 
        return hint
    try:
        return detect(txt)
    except Exception:
        return "unknown"

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

# =====================
# Cleaning & Lexicon Loader (InSet + optional Barasa)
# =====================

_RE_MULTISPACE = re.compile(r"\s+")
_RE_REPEAT = re.compile(r"(.)\1{2,}")  # aaa -> aa

def clean_text(t: str) -> str:
    t = (t or "").strip().lower()
    t = _RE_REPEAT.sub(r"\1\1", t)
    t = _RE_MULTISPACE.sub(" ", t)
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
    s = 0.0
    for t in toks:
        s += lex.get(t, 0.0)
    # dampen by sqrt length
    return max(-1.0, min(1.0, s / max(1.0, len(toks) ** 0.5)))

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
    return jsonify({"status": "ok"})

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
    items = data.get("items", [])
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

    per = []
    all_texts = []
    negatives = []
    per_entry_cats = {}

    for it in items:
        _id = it.get("id") or ""
        raw_txt = (it.get("text") or "").strip()
        txt = clean_text(raw_txt)
        lang_hint = it.get("lang_hint")
        if not txt:
            continue

        # lang detect simple
        lang = detect_lang(txt, hint=lang_hint)

        # sentiment hybrid: Indonesian lexicon + VADER (raw for emoticons)
        s_lex = score_with_lexicon(txt, LEXICON_ID)
        s_vad = sia.polarity_scores(raw_txt).get("compound", 0.0)
        compound = float(0.7 * s_lex + 0.3 * s_vad)
        lbl = label_from_score(compound)

        # keywords per item (pakai RAKE cepat)
        rk = Rake()
        rk.extract_keywords_from_text(txt)
        kps = rk.get_ranked_phrases()[:5]

        rec = {
            "id": _id,
            "text": raw_txt,
            "lang": lang,
            "sentiment": compound,
            "label": lbl,
            "keywords": kps
        }
        per.append(rec)
        all_texts.append(txt)
        if compound <= -0.05:
            negatives.append(txt)
            # category scoring only for negatives
            cat_scores, reasons = score_categories_for_text(txt, categories_map, feedback)
            # keep top categories with non-zero scores
            ranked = sorted([(c, s) for c, s in cat_scores.items() if s > 0], key=lambda x: x[1], reverse=True)
            per_entry_cats[_id] = {
                "ranked": ranked[:3],
                "reasons": {c: reasons.get(c, []) for c, _ in ranked[:3]}
            }

    # global keyphrases
    keyphrases = extract_keyphrases(all_texts) if all_texts else []

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

    avg = sum([x["sentiment"] for x in per]) / len(per) if per else 0.0
    summary = {
        "avg_sentiment": round(avg, 3),
        "negative_ratio": round(sum(1 for x in per if x["label"]=="negatif")/len(per), 3) if per else 0.0,
        "notes": "Rangkuman kasar berdasar skor rata-rata & proporsi negatif."
    }

    return jsonify({
        "per_entry": per,
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
