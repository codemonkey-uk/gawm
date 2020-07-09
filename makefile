
.PHONY : gawm
gawm: 
	src/cp_api.sh
	src/setup_db.sh
	
.PHONY : clean
clean:
	rm api/*
