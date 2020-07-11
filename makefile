
.PHONY : gawm
gawm: 
	src/cp_api.sh
	src/setup_db.sh
	php test.php
	
.PHONY : clean
clean:
	rm api/*
