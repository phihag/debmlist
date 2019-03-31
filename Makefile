all: download courtspot miniticker bbt locations

bup:
	@test -e ../bup || test -e bup || git clone https://github.com/phihag/bup.git

download: bup
	php download.php

courtspot: download
	php gen_courtspot.php

miniticker: download
	php gen_miniticker.php

bbt: download
	php gen_bbt.php

locations: download
	php gen_locations.php

clean:
	rm -rf output

cleanall: clean
	rm -rf cache

.PHONY: all clean cleanall courtspot download miniticker bbt bup locations
