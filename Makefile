all:
	php download.php

clean:
	rm -rf output

cleanall: clean
	rm -rf cache

.PHONY: all clean cleanall
