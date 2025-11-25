import sys
import os
import re
import json
import math
import logging
from collections import Counter, defaultdict
from datetime import datetime
from typing import List, Dict, Tuple, Optional

import nltk
import numpy as np
import pandas as pd
from flask import Flask, request, jsonify
try:
    from langdetect import detect
except Exception:
    # Fallback sederhana jika langdetect tidak tersedia
    def detect(_text: str) -> str:
        return "id"

# --- LIBRARY BARU (Deep Learning & Emoji) ---
import emoji
import torch
from transformers import AutoTokenizer, AutoModel
from sklearn.cluster import KMeans
from sklearn.feature_extraction.text import TfidfVectorizer # Tetap butuh untuk fallback

# NLTK & RAKE
from nltk.corpus import stopwords
from nltk.sentiment import SentimentIntensityAnalyzer
from rake_nltk import Rake

# Setup Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Download NLTK resources quietly
try:
    nltk.data.find('tokenizers/punkt')
    nltk.data.find('sentiment/vader_lexicon')
    nltk.data.find('corpora/stopwords')
    nltk.data.find('tokenizers/punkt_tab')
except LookupError:
    print("Downloading NLTK resources...")
    nltk.download('punkt', quiet=True)
    nltk.download('vader_lexicon', quiet=True)
    nltk.download('stopwords', quiet=True)
    nltk.download('punkt_tab', quiet=True)

app = Flask(__name__)

# Configuration
API_KEY = os.getenv("FLASK_API_KEY", "rahasia-negara-123") # Gunakan env var
SERVICE_VERSION = "1.2.0-bert-sarcasm" # Version bump

# --- GLOBAL VARIABLES ---
sia = SentimentIntensityAnalyzer()
STOPWORDS_ID_CHAT = set(stopwords.words('indonesian')) | set(stopwords.words('english'))
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
    "apalah", "apa", "aduh", "wah", "nah", "kek", "kayak", "macam"
}
STOPWORDS_ID_CHAT.update(_CHAT_FILLERS)

# ==== Integrasi TALA Stopwords tambahan ====
try:
    _TALA_PATH = os.path.join(os.path.dirname(__file__), 'tala-stopwords-indonesia.txt')
    if os.path.exists(_TALA_PATH):
        with open(_TALA_PATH, 'r', encoding='utf-8') as _tf:
            tala_words = {w.strip().lower() for w in _tf if w.strip() and not w.startswith('#')}
            # Hindari kata yang terlalu pendek (1 huruf) agar tidak over-filter
            tala_words = {w for w in tala_words if len(w) > 1}
            STOPWORDS_ID_CHAT.update(tala_words)
            logger.info(f"Loaded TALA stopwords: +{len(tala_words)} terms (total={len(STOPWORDS_ID_CHAT)})")
    else:
        logger.warning('TALA stopwords file not found, skipping integration.')
except Exception as e:
    logger.warning(f'Failed loading TALA stopwords: {e}')

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


sia = SentimentIntensityAnalyzer()

# Lexicon sederhana untuk Indonesia/Kupang dalam range standar [-1, +1]
ID_EXTRA = {
    # Emosi negatif umum
    "capek": -0.7, "capai": -0.5, "pusing": -0.7, "marah": -0.8, "sedih": -0.7,
    "murung": -0.7, "galau": -0.6, "bingung": -0.5, "takut": -0.7, "cemas": -0.7,
    "kecewa": -0.7, "kesal": -0.6, "jengkel": -0.6, "frustasi": -0.8, "depresi": -0.9,
    "stres": -0.8, "tegang": -0.6, "resah": -0.7, "gelisah": -0.7,
    # Emosi positif umum
    "senang": 0.7, "bahagia": 0.8, "semangat": 0.7, "hepi": 0.7, "gembira": 0.8,
    "excited": 0.7, "antusias": 0.7, "optimis": 0.6, "tenang": 0.5, "damai": 0.6,
    "puas": 0.6, "lega": 0.6, "syukur": 0.7, "bangga": 0.7,
    # Masalah sekolah
    "telat": -0.6, "bolos": -0.8, "berantem": -0.9, "ribut": -0.7, "gaduh": -0.6,
    "berkelahi": -0.9, "bertengkar": -0.8, "keributan": -0.7, "masalah": -0.5,
    "PR": -0.3, "tugas": -0.2, "banyak": -0.2, "malas": -0.5, "rajin": 0.5,
    "skip": -0.6, "cabut": -0.6, "pontang": -0.7, "mangkir": -0.7,
    # Keluarga & rumah
    "berantem": -0.9, "cekcok": -0.8, "bertengkar": -0.8, "marahan": -0.7,
    "berisik": -0.5, "berantakan": -0.4, "kacau": -0.7, "chaos": -0.7,
    "pisah": -0.7, "bercerai": -0.8, "kabur": -0.7, "minggat": -0.8, "pergi": -0.3,
    # Kupang/Manado dialect dengan sentiment
    "sonde": -0.3, "tara": -0.2, "teda": -0.2, "pigi": -0.1,  # Kupang negation/pergi
    "kaco": -0.5, "cungkel": -0.5, "bongkar": -0.2, "kobo": -0.4, "susa": -0.6,
    "dolo": -0.4, "molo": -0.4, "so": -0.3, "nda": -0.3,  # Manado negation
    "bodo": -0.6, "bodoh": -0.7, "tolol": -0.8, "goblok": -0.8,  # Insults
    # Neutral pronouns (score 0 won't affect sentiment)
    "beta": 0.0, "ko": 0.0, "torang": 0.0, "katong": 0.0, "deng": 0.0,
    "dong": 0.0, "de": 0.0, "so": 0.0, "pe": 0.0, "pung": 0.0,
    "tanta": 0.0, "oma": 0.0, "opa": 0.0, "mama": 0.0, "papa": 0.0,
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
# Regex patterns
_RE_URL = re.compile(r"https?://\S+|www\.\S+")
_RE_MENTION = re.compile(r"[@#]\w+")
_RE_REPEAT = re.compile(r"(.)\1{2,}") # 3 kali atau lebih
_RE_MULTISPACE = re.compile(r"\s+")

def clean_text(t: str) -> str:
    """
    Cleaning text tapi mempertahankan emoji dan tanda baca penting untuk sentimen.
    """
    if not t: return ""
    
    # 1. Demojize: Ubah emoji jadi teks bahasa Indonesia (manual mapping dikit)
    t = emoji.demojize(t, delimiters=(" ", " ")) 
    t = t.replace("loudly_crying_face", "menangis") \
         .replace("crying_face", "sedih") \
         .replace("pensive_face", "murung") \
         .replace("angry_face", "marah") \
         .replace("rolling_on_the_floor_laughing", "tertawa") \
         .replace("face_with_rolling_eyes", "bosan") \
         .replace("broken_heart", "patah hati")

    t = t.lower().strip()

    # 2. Remove URL & Mention
    t = _RE_URL.sub(" ", t)
    t = _RE_MENTION.sub(" ", t)

    # 3. Keep punctuation important for emotion (?!.,)
    # Hapus karakter aneh selain alphanumeric dan tanda baca penting
    t = re.sub(r"[^a-z0-9\?\!\.\,\s]", " ", t)
    
    # Pisahkan tanda baca biar jadi token terpisah
    t = re.sub(r"([\?\!\.\,])", r" \1 ", t)

    # 4. Normalize Repeat (bangeeet -> banget)
    t = _RE_REPEAT.sub(r"\1", t)

    # 5. Slang & Dialect Normalization (Indonesian + Kupang + Manado + Ambon)
    dialect = {
        # Standard Indonesian slang
        "gw": "saya", "gue": "saya", "lu": "kamu", "lo": "kamu", "elu": "kamu",
        "ak": "aku", "aq": "aku", "sy": "saya", "w": "saya", "ane": "saya",
        "gak": "tidak", "ga": "tidak", "nggak": "tidak", "kaga": "tidak", "ndak": "tidak",
        "enggak": "tidak", "engga": "tidak", "ngga": "tidak", "kagak": "tidak",
        "krn": "karena", "karna": "karena", "bgt": "banget", "bgtt": "banget",
        "tdk": "tidak", "jgn": "jangan", "udh": "sudah", "sdh": "sudah",
        "blm": "belum", "trus": "terus", "jd": "jadi", "dgn": "dengan",
        "sm": "sama", "yg": "yang", "kalo": "kalau", "kl": "kalau",
        "mager": "malas gerak", "baper": "bawa perasaan", "gabut": "bosan",
        "anjir": "kaget", "njir": "kaget", "anjay": "hebat", 
        "mantul": "mantap", "santuy": "santai", "sans": "santai",
        "gajelas": "tidak jelas", "gaje": "tidak jelas",
        # Kupang/NTT dialect
        # --- KATA GANTI ORANG (PRONOUNS) ---
        "beta": "saya", "b": "saya", "bt": "saya", # Kupang/Ambon
        "kita": "saya", # Manado (konteks santai)
        "ana": "saya", "awak": "saya", "sa": "saya", "sy": "saya",
        "ak": "aku", "aq": "aku", "gw": "saya", "gue": "saya",
        
        "lu": "kamu", "lo": "kamu", "elu": "kamu", 
        "ose": "kamu", "os": "kamu", "ale": "kamu", # Ambon
        "ngana": "kamu", "nga": "kamu", # Manado
        "ko": "kamu", "kau": "kamu", "ju": "kamu", # Kupang/Papua
        "bo": "kamu", # Bima/Dompu kadang masuk
        
        "dia": "dia", "de": "dia", "i": "dia", # Papua/Kupang (De pung rumah)
        "antua": "beliau", # Ambon (respektif)
        
        "katong": "kita", "ketong": "kita", "ktg": "kita", # Kupang/Ambon
        "torang": "kita", "tong": "kita", # Manado/Papua
        
        "dorang": "mereka", "dong": "mereka", "drg": "mereka", # Manado/Kupang/Ambon
        "besong": "kalian", "basong": "kalian", "kamorang": "kalian", # Kupang/Papua
        "ngoni": "kalian", # Manado

        # --- NEGASI (TIDAK/BUKAN) ---
        "sonde": "tidak", "son": "tidak", "snd": "tidak", "sond": "tidak", # Kupang
        "seng": "tidak", "sing": "tidak", "tra": "tidak", "trada": "tidak", # Ambon/Papua
        "tara": "tidak", "tar": "tidak", 
        "nyanda": "tidak", "nda": "tidak", "ndak": "tidak", # Manado/Jawa
        "gak": "tidak", "ga": "tidak", "nggak": "tidak", "kaga": "tidak", 
        "bukang": "bukan",

        # --- KATA KERJA & KETERANGAN (VERBS & ADVERBS) ---
        "pi": "pergi", "p": "pergi", "pig": "pergi", # Kupang/Ambon (saya kabur 'pi'...)
        "su": "sudah", "so": "sudah", # Kupang/Manado/Ambon
        "sdh": "sudah", "udh": "sudah", "udah": "sudah",
        "blm": "belum", "balom": "belum", 
        
        "mo": "mau", "mau": "mau", 
        "kasi": "beri", "kase": "beri", "kas": "beri", # Kase tinggal -> Beri tinggal
        "omong": "bicara", "baomong": "bicara", "bakata": "berkata",
        "dapa": "dapat", "dap": "dapat",
        "baku": "saling", # Baku pukul -> Saling pukul
        "bae": "baik", "baek": "baik",
        "ancor": "hancur",
        "ambe": "ambil", "pigi": "pergi",
        
        # --- KEPEMILIKAN & PENGHUBUNG ---
        "pung": "punya", "puny": "punya", "pu": "punya", "pe": "punya", # Beta pung -> Saya punya
        "deng": "dengan", "dg": "dengan", "dng": "dengan", 
        "par": "untuk", "for": "untuk", # Ambon/Manado (For ngana)
        "vor": "untuk",
        "kek": "seperti", "mcam": "macam", "kek": "kayak",

        # --- KATA SIFAT & LAINNYA ---
        "talalu": "terlalu", "tlalu": "terlalu",
        "sadiki": "sedikit", "sadikit": "sedikit",
        "banya": "banyak", 
        "skali": "sekali",
        "samua": "semua",
        "karna": "karena", "krn": "karena", "gara": "karena",
        
        # --- GENERAL SLANG INDONESIA ---
        "bgt": "banget", "bgtt": "banget",
        "trus": "terus", "trs": "terus",
        "jd": "jadi", "jdi": "jadi", 
        "yg": "yang", "kalo": "kalau", "kl": "kalau",
        "mager": "malas gerak", "baper": "bawa perasaan", "gabut": "bosan",
        "anjir": "kaget", "njir": "kaget", "anjay": "hebat", 
        "mantul": "mantap", "santuy": "santai", "sans": "santai",
        "gajelas": "tidak jelas", "gaje": "tidak jelas",
        "ortu": "orang tua", "mksd": "maksud",
        "knp": "kenapa", "np": "kenapa", "napa": "kenapa",
        "utk": "untuk"
    }
    
    toks = []
    for tk in t.split():
        toks.append(dialect.get(tk, tk))
    
    t = " ".join(toks)
    t = _RE_MULTISPACE.sub(" ", t).strip()
    return t

def detect_sarcasm_heuristic(text_clean, raw_text, current_sentiment):
    """
    Mendeteksi potensi sarkasme berdasarkan kontras sentimen, emoji, dan tanda baca.
    Returns: (is_sarcasm: bool, confidence: float)
    """
    is_sarcasm = False
    confidence = 0.0
    text_clean = text_clean.lower()
    
    # Kamus Heuristik
    intensifiers = ["banget", "bgt", "kali", "sumpah", "bener", "bet", "parah", "amat"]
    positives = ["hebat", "bagus", "pinter", "jenius", "mantap", "enak", "keren", "rajin", "suci"]
    negatives = ["pusing", "capek", "stres", "gila", "mati", "rusak", "hancur", "sebel", "benci", "malas", "bodoh", "tolol"]
    
    # Fitur
    has_pos = any(p in text_clean for p in positives)
    has_neg = any(n in text_clean for n in negatives)
    has_intensifier = any(i in text_clean for i in intensifiers)
    has_exclamation = "!" in raw_text or "?" in raw_text
    
    # LOGIC 1: Kalimat mengandung Positif DAN Negatif ("Hebat banget lo bikin gue stres")
    if has_pos and has_neg:
        return True, 0.75

    # LOGIC 2: Kalimat Positif + Tanda baca agresif + Konteks ambigu ("Pinter ya lo??")
    # Biasanya kalau muji beneran jarang pake '??'
    if has_pos and ("??" in raw_text or "!!" in raw_text):
        return True, 0.6

    # LOGIC 3: Positif + Emoji Negatif (Manual check raw text for common sarcastic emojis)
    # Emoji: Rolling eyes, Unamused face, Upside-down face
    sarcastic_emojis = ["🙄", "😒", "🙃", "😤", "🤡"]
    if has_pos and any(e in raw_text for e in sarcastic_emojis):
        return True, 0.9

    return False, 0.0

def load_inset_lexicon(base_dir: str) -> dict[str, float]:
    """Load InSet format: lexicons/inset/{positive.tsv,negative.tsv}."""
    out: dict[str, float] = {}
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


def load_barasa_csv(path: str) -> dict[str, float]:
    """Load Barasa CSV with headers; expects at least a 'lemma' column and
    either a 'score' column (float, negative to positive) or separate
    'pos'/'neg' columns that can be combined (score = pos - neg).
    Values are clamped to [-1, 1].
    """
    lex: dict[str, float] = {}
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


def load_barasa_optional(base_dir: str) -> dict[str, float]:
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
        out: dict[str, float] = {}
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


def build_lexicon() -> dict[str, float]:
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
    
    # Context-aware scoring: handle negation & intensifiers
    negation_words = {"tidak", "bukan", "belum", "jangan", "tanpa", "sonde", "tara", "teda", "nda", "tra"}
    intensifiers = {"banget", "sangat", "amat", "sekali", "parah", "bener", "pisan"}
    
    s = 0.0
    negated = False
    intensify = 1.0
    
    for i, tok in enumerate(toks):
        # Check negation (flip sign for next 3 words)
        if tok in negation_words:
            negated = True
            continue
        
        # Check intensifier (boost next word by 1.5x)
        if tok in intensifiers:
            intensify = 1.5
            continue
        
        # Get sentiment score
        score = lex.get(tok, 0.0)
        
        # Apply negation (flip sign)
        if negated and score != 0.0:
            score = -score * 0.8  # Slightly dampen negated sentiment
            negated = False  # Reset after applying
        
        # Apply intensifier
        if intensify > 1.0 and score != 0.0:
            score = score * intensify
            intensify = 1.0  # Reset
        
        s += score
        
        # Reset negation after 3 words
        if negated and i > 0 and (i % 3 == 0):
            negated = False
    
    # Dampen by sqrt length to avoid bias for long texts
    normalized = s / max(1.0, math.sqrt(len(toks)))
    return max(-1.0, min(1.0, normalized))

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

# --- GLOBAL BERT VARIABLES ---
_bert_tokenizer = None
_bert_model = None
_bert_device = None

def get_bert():
    global _bert_tokenizer, _bert_model, _bert_device
    if _bert_tokenizer is None:
        print("⏳ Loading IndoBERT model... (First run might take a while)")
        try:
            model_name = "indobenchmark/indobert-base-p1"
            _bert_tokenizer = AutoTokenizer.from_pretrained(model_name)
            _bert_model = AutoModel.from_pretrained(model_name)
            _bert_device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
            _bert_model.to(_bert_device)
            _bert_model.eval()
            print(f"✅ IndoBERT loaded on {_bert_device}")
        except Exception as e:
            print(f"❌ Failed to load IndoBERT: {e}")
            return None, None, None
    return _bert_tokenizer, _bert_model, _bert_device

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
@app.post("/analyze")
# (Load helpers lain seperti check_key, load_feedback, taxonomy, dll biarkan seperti file lama Anda)
# ... (Pastikan functions: check_key, load_feedback_weights, build_topic_index..., load_inset_lexicon ada) ...

@app.post("/analyze")
def analyze():
    if not check_key():
        return jsonify({"error": "unauthorized"}), 401

    data = request.get_json(force=True) or {}
    items = data.get("items")
    
    if items is None:
        items = [{
            "id": data.get("id") or "item-1",
            "text": data.get("text") or "",
            "lang_hint": (data.get("context") or {}).get("lang_hint") if isinstance(data.get("context"), dict) else None
        }]
    
    if not isinstance(items, list) or not items:
        return jsonify({"error": "items required"}), 422

    # Setup Taxonomy & Feedback
    categories_override = data.get("categories")
    TOPIC_INDEX, TAXONOMY_CATEGORIES = build_topic_index_and_categories_map()
    
    categories_map = {}
    if isinstance(categories_override, dict) and categories_override:
        for k, v in categories_override.items():
            if isinstance(v, list):
                categories_map[str(k).upper()] = [str(x) for x in v if isinstance(x, (str, int))]
    
    if not categories_map:
        categories_map = TAXONOMY_CATEGORIES

    feedback = load_feedback_weights()

    # Setup Variables
    results = []
    per_legacy = []
    all_texts = []
    negatives = []
    per_entry_cats = {}

    # Load IndoBERT Model
    tok, mdl, dev = get_bert()

    # --- PROCESS PER ITEM ---
    for it in items:
        item_id = it.get("id")
        raw_txt = (it.get("text") or "").strip()
        lang_hint = it.get("lang_hint")

        # 1. Text Cleaning (New Logic)
        clean = clean_text(raw_txt)
        if not clean:
            continue

        # 2. Sentiment Scoring (Hybrid)
        s_lex = score_with_lexicon(clean, LEXICON_ID)
        s_vad = sia.polarity_scores(raw_txt).get("compound", 0.0)
        aggregate = float(0.7 * s_lex + 0.3 * s_vad)
        
        # Fallback: keyword-based detection if aggregate is neutral (0)
        if abs(aggregate) < 0.05:
            negative_keywords = ["berkelahi", "bertengkar", "murung", "sedih", "marah", "kabur", "masalah", "ribut", "berantem", "stress", "pusing", "takut", "cemas", "galau", "kecewa"]
            positive_keywords = ["senang", "bahagia", "gembira", "semangat", "excited", "bagus", "oke", "mantap", "suka", "hebat"]
            
            neg_count = sum(1 for kw in negative_keywords if kw in clean)
            pos_count = sum(1 for kw in positive_keywords if kw in clean)
            
            if neg_count > pos_count and neg_count > 0:
                aggregate = -0.35  # Set mild negative
            elif pos_count > neg_count and pos_count > 0:
                aggregate = 0.3   # Set mild positive

        # 3. Sarcasm Detection (New Logic)
        is_sarcasm, sarc_conf = detect_sarcasm_heuristic(clean, raw_txt, aggregate)
        
        if is_sarcasm:
            # Flip score: Positive -> Negative
            if aggregate > 0:
                aggregate = -0.5 * aggregate - 0.3
            elif aggregate == 0:
                aggregate = -0.4
            lbl = "negatif"
        else:
            lbl = label_from_score(aggregate)

        # 4. Negative Gate & Severity
        # Check severity based on flipped score
        neg_flag, severity = negative_gate(aggregate, raw_txt)
        if is_sarcasm: 
            neg_flag = True
            severity = max(severity, 0.6) # Sarkasme biasanya sakit

        # 5. Category Scoring
        cat_scores, reasons = score_categories_for_text(clean, categories_map, feedback)
        best_cat = max(cat_scores, key=cat_scores.get) if cat_scores else None

        # 6. Cluster Labeling
        cluster = None
        if best_cat:
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
                cluster = {"id": best_cat, "label": best_cat, "bucket": str(best_cat).upper(), "confidence": 0.4}

        # 7. Keywords Extraction
        try:
            rk = Rake(stopwords=STOPWORDS_ID_CHAT, min_length=1, max_length=3)
            rk.extract_keywords_from_text(clean) # Use clean text
            raw_phrases = [p.lower() for p in rk.get_ranked_phrases()[:8]]
        except Exception:
            raw_phrases = []
        
        # Filter phrases
        phrases = sorted(list(set(raw_phrases)), key=len)[:5]

        # 8. Summary Text
        if is_sarcasm:
            summary_text = f"Terdeteksi sarkasme/sindiran. Inti keluhan: {', '.join(phrases[:3])}."
        elif cluster:
            summary_text = f"Masalah utama: {cluster['label']}. Gejala: {', '.join(phrases[:3])}."
        else:
            summary_text = f"Inti keluhan: {', '.join(phrases[:3])}."

        results.append({
            "id": item_id,
            "clean_text": clean,
            "sentiment": {
                "barasa": s_lex, "english": s_vad, "aggregate": aggregate, "label": lbl
            },
            "negative_flag": neg_flag,
            "is_sarcasm": is_sarcasm, # Field Baru
            "severity": severity,
            "cluster": cluster,
            "summary": summary_text,
            "key_phrases": phrases,
            "recommendations": [],
            "cat_scores": cat_scores,
            "cat_reasons": reasons,
        })

        per_legacy.append({
            "id": item_id, "text": raw_txt, "sentiment": aggregate, 
            "label": lbl, "keywords": phrases
        })
        
        all_texts.append(clean)
        
        # Collect negatives for clustering
        if neg_flag:
            negatives.append(clean)
            ranked = sorted([(c, s) for c, s in cat_scores.items() if s > 0], key=lambda x: x[1], reverse=True)
            per_entry_cats[item_id] = {
                "ranked": ranked[:3],
                "reasons": {c: reasons.get(c, []) for c, _ in ranked[:3]}
            }

    # --- AGGREGATION & CLUSTERING ---

    # Global Keywords
    keyphrases = extract_keyphrases(all_texts) if all_texts else []

    # Clustering with IndoBERT
    clusters = []
    if len(negatives) >= 2:
        used_engine = "tfidf"
        X = None
        
        # Try BERT
        if tok and mdl:
            try:
                with torch.no_grad():
                    enc = tok(negatives, padding=True, truncation=True, max_length=128, return_tensors="pt").to(dev)
                    out = mdl(**enc)
                    cls = out.last_hidden_state[:, 0, :]
                    X = cls.detach().cpu().numpy()
                    used_engine = "bert"
            except Exception as e:
                print(f"⚠️ BERT error, falling back: {e}")
                X = None
        
        # Fallback TF-IDF
        if X is None:
            vec = _build_cluster_vectorizer() # Pastikan fungsi ini ada (helper lama)
            X = vec.fit_transform(negatives)
            
        k = 2 if len(negatives) == 2 else min(4, max(2, len(negatives)//2))
        km = KMeans(n_clusters=k, n_init='auto', random_state=42)
        y = km.fit_predict(X)
        
        for ci in range(k):
            ex = [negatives[i] for i in range(len(negatives)) if y[i] == ci][:5]
            clusters.append({
                "cluster": int(ci),
                "engine": used_engine,
                "examples": ex
            })

    # Overview Weighted by Severity & Sarcasm
    cat_counter = Counter()
    for r in results:
        sev = r.get("severity", 0.0)
        weight = 0.5
        if r.get("negative_flag"): 
            weight = 1.0 + sev
        
        for cat, sc in (r.get("cat_scores") or {}).items():
            if sc > 0: cat_counter[cat] += sc * weight

    categories_overview = [
        {"category": cat, "score": round(val, 4)} for cat, val in cat_counter.most_common()
    ]

    # Summary Stats
    avg = sum([x["sentiment"] for x in per_legacy]) / len(per_legacy) if per_legacy else 0.0
    summary = {
        "avg_sentiment": round(avg, 3),
        "negative_ratio": round(sum(1 for x in per_legacy if x["label"]=="negatif")/len(per_legacy), 3) if per_legacy else 0.0
    }

    # Recommendations Generation
    def recommend_rules(bucket: str, severity_val: float, negative: bool, sarcasm: bool):
        recs = []
        if not bucket: return recs
        
        # Prioritize Sarcasm/High Severity
        if (negative or sarcasm) and severity_val >= 0.6:
            recs.append({"tag": "KONSELING_INDIVIDU", "title": "Sesi konseling individu", "priority": 1, "rationale": "Indikasi masalah mendalam/sarkasme"})
        
        if bucket in ("SOSIAL", "DISIPLIN") and negative:
            recs.append({"tag": "MEDIASI_WALI", "title": "Mediasi dengan wali kelas", "priority": 2})
        if bucket == "AKADEMIK":
            recs.append({"tag": "RAPAT_JADWAL", "title": "Evaluasi jadwal belajar", "priority": 3})
        if bucket == "EMOSI":
            recs.append({"tag": "REGULASI_EMOSI", "title": "Latihan regulasi emosi", "priority": 3})
            
        return recs

    # Assign Recs per item
    for r in results:
        bucket = (r.get("cluster") or {}).get("bucket")
        r["recommendations"] = recommend_rules(
            bucket, r.get("severity", 0), r.get("negative_flag", False), r.get("is_sarcasm", False)
        )

    # Global Recs
    abs_sent = abs(avg)
    global_recommendations = []
    valid_cats = [c for c in categories_overview if c["score"] >= 0.05]
    is_neg_avg = avg < -0.05
    
    for cat in valid_cats:
        cname = cat["category"]
        meta = TOPIC_INDEX.get(cname.upper()) or {}
        bucket = meta.get("bucket", "")
        recs = recommend_rules(bucket, max(0.3, abs_sent), is_neg_avg, False)
        if recs:
            global_recommendations.append({
                "category": cname,
                "score": cat["score"],
                "recommendations": recs
            })

    return jsonify({
        "version": SERVICE_VERSION,
        "items": results,
        "summary": summary,
        "keyphrases": keyphrases,
        "clusters": clusters,
        "categories_overview": categories_overview,
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
