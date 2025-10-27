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
    if not isinstance(items, list) or not items:
        return jsonify({"error": "items required"}), 422

    per = []
    all_texts = []
    negatives = []

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
        "clusters": clusters
    })

if __name__ == "__main__":
    # LOCAL MODE: port 5001 biar gampang
    app.run(host="0.0.0.0", port=5001, debug=True)
