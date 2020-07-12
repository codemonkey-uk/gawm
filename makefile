
.PHONY : gawm
gawm: 
	src/cp_api.sh
	php test.php

.PHONY : db
db: 
	src/setup_db.sh
	
.PHONY : clean
clean:
	rm api/*
