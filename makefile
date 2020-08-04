
# all JS source files are in the src/js folder
SRC_JS := $(wildcard ./src/js/*.js)
SRC_CSS := $(wildcard ./src/css/*.css)
SRC_PHP := $(wildcard ./src/api/*.php)

# Create a list of all the output files you want to generate
JS_OUT := $(patsubst ./src/js/%.js,./build/js/%.js,$(SRC_JS))
CSS_OUT := $(patsubst ./src/css/%.css,./build/css/%.css,$(SRC_CSS))
API_OUT := $(patsubst ./src/api/%.php,./build/api/%.php,$(SRC_PHP))

# The default is to build all the OUTPUTS files
all: run_tests $(JS_OUT) $(API_OUT) $(CSS_OUT) copy_files

# Tell make how to build a minified js file
./build/js/%.js  : ./src/js/%.js
	@mkdir -p $(@D)
	terser --compress -- $< > $@

# css, for now just copy it
./build/css/%.css  : ./src/css/%.css
	@mkdir -p $(@D)
	cp -f $< $@
	
# Tell make how to secret inject the php
./build/api/%.php  : ./src/api/%.php
	@mkdir -p $(@D)
	sed -e "s/GAWM_DB_USER/\"${GAWM_DB_USER}\"/g" -e "s/GAWM_DB_HOST/\"${GAWM_DB_HOST}\"/g" -e "s/GAWM_DB_PWD/\"${GAWM_DB_PWD}\"/g" $< > $@

.PHONY : run_tests
run_tests: 
	(cd src/tests && exec php test*.php)

.PHONY : copy_files
copy_files: 
	cp src/*.html build
	cp -r libs/hashids-4.0.0/src build/api/Hashids
	cp -r assets build/assets

.PHONY : setup_db
setup_db:
	src/setup_db.sh

.PHONY : clean
clean:
	rm -rf api build/*