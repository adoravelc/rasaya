from flask import Flask, request, jsonify
from nltk.sentiment import SentimentIntensityAnalyzer
from nltk.corpus import stopwords
from nltk import download
from rake_nltk import Rake
from langdetect import detect
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans
import os
import nltk
import json
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
        txt = (it.get("text") or "").strip()
        lang_hint = it.get("lang_hint")
        if not txt:
            continue

        # lang detect simple
        lang = detect_lang(txt, hint=lang_hint)

        # sentiment via VADER + extended lexicon
        s = sia.polarity_scores(txt)
        compound = float(s.get("compound", 0.0))
        lbl = label_from_score(compound)

        # keywords per item (pakai RAKE cepat)
        rk = Rake()
        rk.extract_keywords_from_text(txt)
        kps = rk.get_ranked_phrases()[:5]

        rec = {
            "id": _id,
            "text": txt,
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
        vec = TfidfVectorizer(max_features=500, ngram_range=(1, 2))
        X = vec.fit_transform(negatives)
        k = 2 if len(negatives) == 2 else min(4, max(2, len(negatives)//2))
        km = KMeans(n_clusters=k, n_init='auto', random_state=42)
        y = km.fit_predict(X)
        # kumpulkan contoh teks per cluster + top terms
        terms = vec.get_feature_names_out()
        centroids = km.cluster_centers_
        for ci in range(k):
            top_idx = centroids[ci].argsort()[-8:][::-1]
            top_terms = [terms[j] for j in top_idx]
            ex = [negatives[i] for i in range(len(negatives)) if y[i] == ci][:5]
            clusters.append({
                "cluster": int(ci),
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
