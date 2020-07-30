
.PHONY : gawm
gawm: 
	(cd src/tests && exec php test*.php)
	src/cp_api.sh


.PHONY : db
db: 
	src/setup_db.sh
	
.PHONY : clean
clean:
	rm -rf api build/*