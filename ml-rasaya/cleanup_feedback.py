#!/usr/bin/env python3
"""
Feedback Weights Cleanup Utility
- Splits long-phrase keys into individual words
- Clips extreme weight values to [-1.0, +1.2]
- Applies optional decay (default 5%) to prevent stale weights
- Removes noise keywords (very generic, used by >6 categories)
- Generates a clean backup before modifying
"""
import json
import os
import sys
from datetime import datetime
from collections import defaultdict

FEEDBACK_FILE = os.path.join(os.path.dirname(__file__), "feedback_weights.json")
BACKUP_DIR = os.path.join(os.path.dirname(__file__), "backups")
DECAY_FACTOR = 0.95  # 5% decay per run
MIN_WEIGHT = -1.0
MAX_WEIGHT = 1.2
NOISE_THRESHOLD = 6  # if keyword appears in >6 categories, flag as noise

# Generic stopwords (too common to be signals)
NOISE_KEYWORDS = {
    "banget", "aja", "saja", "dulu", "tapi", "kalo", "kalau", "yang", "sama", "juga",
    "dan", "atau", "untuk", "dari", "dengan", "ke", "di", "pada", "ini", "itu",
    "nya", "kan", "sih", "dong", "ya", "yah", "lah", "deh", "kek", "kayak",
    "masuk", "minggu", "hari", "kelas", "siswa", "guru", "sekolah"
}


def load_weights():
    try:
        with open(FEEDBACK_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error loading {FEEDBACK_FILE}: {e}")
        sys.exit(1)


def save_weights(weights, filepath):
    with open(filepath, 'w', encoding='utf-8') as f:
        json.dump(weights, f, ensure_ascii=False, indent=2)


def backup_weights():
    os.makedirs(BACKUP_DIR, exist_ok=True)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    backup_path = os.path.join(BACKUP_DIR, f"feedback_weights_{timestamp}.json")
    weights = load_weights()
    save_weights(weights, backup_path)
    print(f"✅ Backup created: {backup_path}")
    return backup_path


def split_phrase_keys(weights):
    """Split long-phrase keys (>3 words) into individual words, merge weights."""
    new_weights = defaultdict(dict)
    splits = 0
    
    for key, cats in weights.items():
        words = [w.strip() for w in key.split() if w.strip() and len(w.strip()) >= 3]
        
        if len(words) <= 3:
            # Keep original key if <=3 words (good for bigrams/trigrams)
            for cat, val in cats.items():
                if key not in new_weights:
                    new_weights[key] = {}
                new_weights[key][cat] = val
        else:
            # Split into individual words
            splits += 1
            for word in words:
                if word.lower() in NOISE_KEYWORDS:
                    continue
                for cat, val in cats.items():
                    if word not in new_weights:
                        new_weights[word] = {}
                    # Average if word already has weight for this category
                    if cat in new_weights[word]:
                        new_weights[word][cat] = (new_weights[word][cat] + val) / 2.0
                    else:
                        new_weights[word][cat] = val
    
    print(f"📝 Split {splits} long phrases into individual words")
    return dict(new_weights)


def clip_weights(weights):
    """Clip all weights to [MIN_WEIGHT, MAX_WEIGHT]."""
    clipped = 0
    for key, cats in weights.items():
        for cat in cats:
            old_val = cats[cat]
            new_val = max(MIN_WEIGHT, min(MAX_WEIGHT, old_val))
            if old_val != new_val:
                clipped += 1
            cats[cat] = round(new_val, 4)
    print(f"✂️  Clipped {clipped} extreme weights to [{MIN_WEIGHT}, {MAX_WEIGHT}]")
    return weights


def apply_decay(weights, decay=DECAY_FACTOR):
    """Apply decay to all weights to prevent old feedback from dominating."""
    for key, cats in weights.items():
        for cat in cats:
            cats[cat] = round(cats[cat] * decay, 4)
    print(f"📉 Applied {int((1-decay)*100)}% decay to all weights")
    return weights


def remove_noise(weights):
    """Remove keywords that appear in too many categories (likely generic)."""
    cat_counts = defaultdict(int)
    for key, cats in weights.items():
        cat_counts[key] = len(cats)
    
    noise_keys = [k for k, count in cat_counts.items() if count > NOISE_THRESHOLD or k.lower() in NOISE_KEYWORDS]
    for key in noise_keys:
        del weights[key]
    
    print(f"🗑️  Removed {len(noise_keys)} noisy keywords (>{NOISE_THRESHOLD} categories or in stoplist)")
    return weights


def consolidate_duplicates(weights):
    """Merge case-insensitive duplicates."""
    lower_map = {}
    for key in list(weights.keys()):
        lower_key = key.lower()
        if lower_key in lower_map:
            # Merge with existing
            orig_key = lower_map[lower_key]
            for cat, val in weights[key].items():
                if cat in weights[orig_key]:
                    weights[orig_key][cat] = (weights[orig_key][cat] + val) / 2.0
                else:
                    weights[orig_key][cat] = val
            del weights[key]
        else:
            lower_map[lower_key] = key
    
    print(f"🔗 Consolidated case-insensitive duplicates")
    return weights


def main():
    print("=" * 60)
    print("Feedback Weights Cleanup Utility")
    print("=" * 60)
    
    # Step 1: Backup
    backup_path = backup_weights()
    
    # Step 2: Load
    weights = load_weights()
    original_count = len(weights)
    print(f"📂 Loaded {original_count} keywords")
    
    # Step 3: Process
    weights = split_phrase_keys(weights)
    weights = consolidate_duplicates(weights)
    weights = clip_weights(weights)
    weights = apply_decay(weights)
    weights = remove_noise(weights)
    
    final_count = len(weights)
    print(f"📊 Final: {final_count} keywords (Δ {final_count - original_count:+d})")
    
    # Step 4: Save
    save_weights(weights, FEEDBACK_FILE)
    print(f"💾 Saved to {FEEDBACK_FILE}")
    print("=" * 60)
    print("✅ Cleanup complete!")
    print(f"   Backup: {backup_path}")
    print("=" * 60)


if __name__ == "__main__":
    main()
