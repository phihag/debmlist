all: download courtspot

download:
	php download.php

courtspot: download
	php gen_courtspot.php

clean:
	rm -rf output

cleanall: clean
	rm -rf cache

.PHONY: all clean cleanall courtspot download
