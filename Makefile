all: download courtspot miniticker bbt

download:
	php download.php

courtspot: download
	php gen_courtspot.php

miniticker: download
	php gen_miniticker.php

bbt: download
	php gen_bbt.php

clean:
	rm -rf output

cleanall: clean
	rm -rf cache

.PHONY: all clean cleanall courtspot download miniticker bbt
