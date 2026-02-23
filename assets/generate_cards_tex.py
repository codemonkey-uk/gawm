#!/usr/bin/env python3
"""
Generate LaTeX card definitions from a JSON file where
each top-level key is a category containing a list of cards.

Output format:
\\card{category}{name}{subtype}{description}
"""

import json
from pathlib import Path
import re

# ---------- CONFIGURATION ----------

INPUT_JSON = Path("cards.json")
OUTPUT_TEX = Path("cards_generated.tex")

# Warn (but do not fail) if descriptions get very long
DESC_LENGTH_WARNING = 450

# ---------- CATEGORY BACK LOOKUP ----------
CATEGORY_BACK_LOOKUP = {
    "aliases": "alias_back_no_border",
    "objects": "object_back_no_border",
    "relationships": "rel_back_no_border",
    "wildcards": "wild_back_no_border",
    "motives": "motive_back_no_border",
    "murder_discovery": "md_back_no_border",
    "murder_cause": "mc_back_no_border",
}

# ---------- CATEGORY BORDER LOOKUP ----------
CATEGORY_BORDER_LOOKUP = {
    "aliases": "RichBlue",
    "objects": "RichGreen",
    "relationships": "RichRed",
    "wildcards": "RichPurple",
    "motives": "RichBrown",
    "murder_discovery": "RichBlack",
    "murder_cause": "RichBlack",
}

# ---------- CATEGORY SINGULAR NAME LOOKUP ----------
CATEGORY_SINGULAR_LOOKUP = {
    "aliases": "Alias",
    "objects": "Object",
    "relationships": "Relationship",
    "wildcards": "Wildcard",
    "motives": "Motive",
    "murder_discovery": "Murder Discovery",
    "murder_cause": "Murder Cause",
}

def tex_escape(s: str) -> str:
    """
    Escape characters with special meaning in LaTeX.
    Explicit and conservative by design.
    """
    replacements = {
        "\\": r"\textbackslash{}",
        "&": r"\&",
        "%": r"\%",
        "$": r"\$",
        "#": r"\#",
        "_": r"\_",
        "{": r"\{",
        "}": r"\}",
        "~": r"\textasciitilde{}",
        "^": r"\textasciicircum{}",
        "\n": r"\\",  # Convert newlines to LaTeX line breaks
    }

    for char, escaped in replacements.items():
        s = s.replace(char, escaped)

    return s

def mbox_words(text):
    """
    Wrap each word in \mbox{} to prevent LaTeX hyphenation.
    Keeps punctuation attached to the word.
    """
    def wrap_word(match):
        return r'\mbox{' + match.group(0) + '}'

    # matches sequences of non-whitespace characters
    return re.sub(r'\S+', wrap_word, text)

def category_id_to_label(cat_id: str) -> str:
    """
    Convert a category identifier like 'murder_cause'
    into a human-readable label like 'Murder Cause'.
    """
    words = cat_id.split("_")
    return " ".join(word.capitalize() for word in words)

def desc_font_variant(desc: str) -> str:
    """
    Decide which description font size to use.
    Returns 'large' or 'small'.
    """
    length = len(desc)

    # You have dedicated your life to looking after others. You are down to earth and good with people. You are likely young to middle aged and could be attractive. You may be working in the public domain, or your bedside manner means you’ve been offered work looking after wealthy individuals.
    # tune this threshold by eyeballing 2–3 decks
    if length <= 220:
        return "large"
    else:
        return "small"
    
# ---------- CARD EMISSION ----------

def emit_front(category: str, card: dict) -> str:
    """
    Convert a single card into a LaTeX \\card command.

    Required card keys:
    - name
    - subtype
    - desc
    """
    name = mbox_words(tex_escape(card["name"]))
    subtype = tex_escape(card["subtype"])

    desc = tex_escape(card["desc"])
    variant = desc_font_variant(desc)

    if variant == "large":
        desc_tex = r"\descLarge{" + desc + "}"
    else:
        desc_tex = r"\descSmall{" + desc + "}"

    category_label = tex_escape(category_id_to_label(category))
    category_tex = mbox_words(tex_escape(category_label))

    border_colour = CATEGORY_BORDER_LOOKUP.get(category, category)

    return f"\\cardfront[{border_colour}]{{{category_tex}}}{{{name}}}{{{subtype}}}{{{desc_tex}}}"

def emit_back(category: str, card: dict) -> str:
    """
    Convert a single card into a LaTeX \\cardback command.

    Required card keys:
    - name
    - subtype
    - desc
    """
    category_back = CATEGORY_BACK_LOOKUP.get(category, category)
    category_label = CATEGORY_SINGULAR_LOOKUP.get(category, category_id_to_label(category))
    
    return f"\\cardback{{{category_back}}}{{{category_label}}}"

# ---------- MAIN ----------

def main() -> None:
    if not INPUT_JSON.exists():
        raise FileNotFoundError(f"Missing input file: {INPUT_JSON}")

    data = json.loads(INPUT_JSON.read_text(encoding="utf-8"))

    if not isinstance(data, dict):
        raise TypeError("Top-level JSON must be an object")

    output_lines = []
    warnings = []
    total_cards = 0

    for category, cards in data.items():
        if not isinstance(cards, list):
            raise TypeError(
                f"Category '{category}' must contain a list of cards"
            )

        for idx, card in enumerate(cards, start=1):
            for key in ("name", "subtype", "desc"):
                if key not in card:
                    raise KeyError(
                        f"Card {idx} in category '{category}' "
                        f"missing required key: {key}"
                    )

            if len(card["desc"]) > DESC_LENGTH_WARNING:
                warnings.append(
                    f"[{category}] long desc ({len(card['desc'])} chars): {card['name']}"
                )

            output_lines.append(emit_back(category, card))
            output_lines.append(emit_front(category, card))
            
            total_cards += 1

    OUTPUT_TEX.write_text(
        "% AUTO-GENERATED FILE — DO NOT EDIT\n"
        "% Generated by generate_cards_tex.py\n\n"
        + "\n".join(output_lines)
        + "\n",
        encoding="utf-8",
    )

    if warnings:
        print("Warnings:")
        for w in warnings:
            print(f"  - {w}")

    print(f"Wrote {total_cards} cards to {OUTPUT_TEX}")


if __name__ == "__main__":
    main()
