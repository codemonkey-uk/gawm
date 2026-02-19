# ---------- tools ----------
INKSCAPE = inkscape
PYTHON   = python3
XELATEX  = xelatex
GS       = gs

# ---------- files ----------
SVGS   := $(wildcard *.svg)
# final raster assets for print (TIFF) and for XeLaTeX inclusion (PDF)
RASTERS := $(SVGS:.svg=.tif)
RASTERS_PDF := $(SVGS:.svg=.pdf)

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

# ---------- png -> cmyk tiff (final raster for print) ----------
# Requires ImageMagick `convert` on PATH. Produces CMYK TIFFs at 300dpi.
%.tif: %.png
	magick $< -density 300 -colorspace CMYK -strip -quality 100 $@
 

# ---------- png/jpg -> pdf (for XeLaTeX inclusion) ----------
# Embed the raster into a single-page PDF at 300dpi; keep CMYK colorspace.
%.pdf: %.png
	magick $< -density 300 -colorspace CMYK -strip -quality 100 $@

%.pdf: %.jpg
	magick $< -density 300 -colorspace CMYK -strip -quality 100 $@

# ---------- special handling for background_titlecard (lighten grayscale to meet TAC limit) ----------
# background_titlecard is B&W/sepia; convert to grayscale and lighten to reduce black density
# This reduces overall ink coverage below 240% TAC
background_titlecard.pdf: background_titlecard.jpg
	magick $< -colorspace Gray -level 0%,100%,1.3 \
	  -density 300 -colorspace CMYK -strip -quality 100 $@

background_titlecard.tif: background_titlecard.jpg
	magick $< -colorspace Gray -level 0%,100%,1.3 \
	  -density 300 -colorspace CMYK -strip -quality 100 $@

# ---------- json → tex ----------
$(CARDS): $(JSON) $(GEN)
	$(PYTHON) $(GEN)

# ---------- latex → pdf ----------
$(PDF): $(TEX) $(CARDS) $(RASTERS_PDF) background_titlecard.pdf innocence_white.pdf
	$(XELATEX) $(TEX)
	$(XELATEX) $(TEX)

# ---------- pdf → PDF/X-1a CMYK ----------
$(FINAL): $(PDF)
	$(GS) -dSAFER -dBATCH -dNOPAUSE \
		-sDEVICE=pdfwrite \
		-dPDFX \
		-dCompatibilityLevel=1.3 \
		-sDefaultCMYKProfile=CGATS21_CRPC1.icc \
		-sColorConversionStrategy=CMYK \
		-dProcessColorModel=/DeviceCMYK \
		-dAutoFilterColorImages=false \
		-dColorImageFilter=/FlateEncode \
		-dDownsampleColorImages=false \
		-sOutputFile=$@ \
		$<

# ---------- check coverage ----------
.PHONY: verify
verify: $(FINAL)
	@echo "Verifying CMYK TAC ≤ 240%..."
	@gs -q -o - -sDEVICE=inkcov $(FINAL) | \
	awk '\
	/^Page / {page=$$2} \
	/CMYK/ { \
		total = $$1 + $$2 + $$3 + $$4; \
		if (total > 2.40) { \
			printf("FAIL page %s: %.1f%% (C %.1f M %.1f Y %.1f K %.1f)\n", \
				page, total*100, $$1*100, $$2*100, $$3*100, $$4*100); \
			fail=1 \
		} \
	} \
	END { \
		if (fail) exit 1; \
		print "OK: all pages ≤ 240% TAC"; \
	}'


# ---------- font embedding check ----------
.PHONY: fonts
fonts: $(PDF)
	@echo "Checking embedded fonts in $(PDF)..."
	@pdffonts $(PDF) || true

# ---------- cleanup ----------
clean:
	rm -f *.aux *.log *.out *.toc
	rm -f $(CARDS) $(PDF) $(FINAL)

.PHONY: all clean
