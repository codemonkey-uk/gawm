# ---------- tools ----------
INKSCAPE = inkscape
PYTHON   = python3
XELATEX  = xelatex
GS       = gs

# ---------- files ----------
SVGS   := $(wildcard *.svg)
PNGS   := $(SVGS:.svg=.png)

JSON   := cards.json
GEN    := generate_cards_tex.py
CARDS  := cards_generated.tex

TEX    := cards.tex
PDF    := cards.pdf
FINAL  := deck-print-PDFX1a-cmyk.pdf

# ---------- default ----------
all: $(FINAL)

# ---------- svg → png ----------
%.png: %.svg
	$(INKSCAPE) $< \
	  --export-type=png \
	  --export-dpi=300 \
	  --export-filename=$@

# ---------- json → tex ----------
$(CARDS): $(JSON) $(GEN)
	$(PYTHON) $(GEN)

# ---------- latex → pdf ----------
$(PDF): $(TEX) $(CARDS) $(PNGS)
	$(XELATEX) $(TEX)
	$(XELATEX) $(TEX)

# ---------- pdf → PDF/X-1a CMYK ----------
$(FINAL): $(PDF)
	$(GS) -dSAFER -dBATCH -dNOPAUSE \
	  -sDEVICE=pdfwrite \
	  -dPDFX \
	  -dCompatibilityLevel=1.3 \
	  -sColorConversionStrategy=CMYK \
	  -dProcessColorModel=/DeviceCMYK \
	  -sOutputFile=$@ \
	  $<

# ---------- cleanup ----------
clean:
	rm -f *.aux *.log *.out *.toc
	rm -f $(CARDS) $(PDF) $(FINAL)

.PHONY: all clean
