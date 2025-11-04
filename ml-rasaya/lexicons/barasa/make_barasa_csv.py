import csv, os, re, sys
from pathlib import Path

# Resolve paths relative to this script so it works no matter where it's run from
SCRIPT_DIR = Path(__file__).resolve().parent
BASE = SCRIPT_DIR / "barasa"
wn_file = BASE / "wn-msa-all.tab"
swn_file = BASE / "SentiWordNet_3.0.0_20130122.txt"
out_file = BASE / "barasa_lexicon.csv"

# Load SentiWordNet: synset_key -> (pos, neg)
swn = {}
if not swn_file.exists():
    print(f"ERROR: Missing file: {swn_file}")
    print("Hint: Place SentiWordNet_3.0.0_20130122.txt in ml-rasaya/lexicons/barasa/")
    sys.exit(1)
with swn_file.open(encoding="utf-8") as f:
    for line in f:
        if not line.strip() or line.startswith("#"):
            continue
        pos_tag, snum, pscore, nscore, *_ = line.strip().split("\t")
        syn_key = f"{snum}-{pos_tag}"  # contoh: 00001740-a
        swn[syn_key] = (float(pscore), float(nscore))

# Gabungkan dengan WN-MSA (synset, lang, goodness, lemma)
rows = {}
if not wn_file.exists():
    print(f"ERROR: Missing file: {wn_file}")
    print("Hint: Place wn-msa-all.tab in ml-rasaya/lexicons/barasa/")
    sys.exit(1)
with wn_file.open(encoding="utf-8") as f:
    for line in f:
        parts = line.strip().split("\t")
        if len(parts) != 4: 
            continue
        synset, lang, good, lemma = parts
        if lang not in ("I","B"):  # Indonesia atau Indo+Malay
            continue
        if good not in ("Y","O"):  # yang kualitas bagus
            continue
        if synset in swn:
            p, n = swn[synset]
            # komposit skor: pos - neg, clamp ke [-1,1]
            score = max(-1.0, min(1.0, (p - n)))
            lemma_l = lemma.lower()
            # keep max |score| kalau lemma duplikat
            if lemma_l not in rows or abs(score) > abs(rows[lemma_l][2]):
                rows[lemma_l] = (p, n, score)

out_file.parent.mkdir(parents=True, exist_ok=True)
with out_file.open("w", newline="", encoding="utf-8") as f:
    w = csv.writer(f)
    w.writerow(["lemma","pos","neg","score"])
    for lemma, (p,n,sc) in sorted(rows.items()):
        w.writerow([lemma, p, n, sc])

print("Wrote:", str(out_file), "total lemmas:", len(rows))
