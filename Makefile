all: download courtspot miniticker

download:
	php download.php

courtspot: download
	php gen_courtspot.php

miniticker: download
	php gen_miniticker.php

clean:
	rm -rf output

cleanall: clean
	rm -rf cache

.PHONY: all clean cleanall courtspot download miniticker
